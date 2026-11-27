<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AuditActions;
use App\Support\CsvCell;
use App\Support\DeviceName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /** Quick ranges offered next to From/To: key => days back (0 = today). */
    public const RANGES = ['today' => 0, '7d' => 6, '30d' => 29];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $tz = $this->timezone();
        $filters = $this->validatedFilters($request, $tz);

        $logs = $this->filteredQuery($filters)
            ->with(['user', 'subject'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        // The dropdowns list what exists in the table, which only changes
        // when a new kind of entry is first written — no need to scan the
        // whole table on every page view.
        [$modules, $actions] = Cache::remember('audit-log.filter-options', now()->addMinutes(10), fn () => [
            AuditLog::query()->distinct()->pluck('module')->all(),
            AuditLog::query()->distinct()->pluck('action')->all(),
        ]);

        // Every known module, plus any one-off module string that exists in
        // the data but isn't listed in AuditActions yet.
        $modules = collect(AuditActions::MODULES)
            ->union(collect($modules)->mapWithKeys(fn ($m) => [$m => AuditActions::moduleLabel($m)]))
            ->sort();

        $actions = collect($actions)->sort()->values()
            ->mapWithKeys(fn ($a) => [$a => AuditActions::label($a)]);

        // withTrashed: deactivated staff still need to be filterable — their
        // past actions are exactly what someone may be looking into.
        $users = User::withTrashed()->orderBy('name')->get(['id', 'name', 'deleted_at']);

        $summary = $this->summary($tz);

        return view('audit-logs.index', compact('logs', 'modules', 'actions', 'users', 'filters', 'summary', 'tz'));
    }

    /**
     * The current filter as a CSV — for handing to an owner or auditor.
     * The export itself is audit-logged, after the fact it describes, so
     * it never appears inside its own file.
     */
    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $tz = $this->timezone();
        $filters = $this->validatedFilters($request, $tz);

        // Capped at the newest entry that exists right now: the file streams
        // after the "export" entry below is written, and would otherwise
        // include it.
        $lastId = AuditLog::max('id') ?? 0;
        $query = $this->filteredQuery($filters)->where('id', '<=', $lastId)->with('user')->latest('id');
        $count = (clone $query)->count();

        $audit->log('export', 'audit', "Exported {$count} audit log entr".($count === 1 ? 'y' : 'ies').' to CSV'.$this->describeFilters($filters).'.');

        $filename = 'audit-log-'.now($tz)->toDateString().'.csv';

        return new StreamedResponse(function () use ($query, $tz) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ["Date ({$tz})", 'User', 'Module', 'Action', 'Description', 'IP Address', 'Device']);

            $query->chunk(500, function ($logs) use ($handle, $tz) {
                foreach ($logs as $log) {
                    // Descriptions include text staff typed — see CsvCell.
                    fputcsv($handle, array_map(CsvCell::text(...), [
                        $log->created_at->copy()->setTimezone($tz)->format('Y-m-d H:i:s'),
                        $log->actorName().($log->user?->trashed() ? ' (deactivated)' : ''),
                        AuditActions::moduleLabel($log->module),
                        AuditActions::label($log->action),
                        $log->description,
                        $log->ip_address ?? '',
                        $log->user_agent ? DeviceName::from($log->user_agent) : '',
                    ]));
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * The gym's own timezone (Settings → General). Entries are stored in
     * UTC; this is only how they're shown and how "From"/"To" days are read.
     */
    private function timezone(): string
    {
        $tz = ApplicationSetting::current()->timezone;

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : config('app.timezone');
    }

    /**
     * @return array{search: ?string, module: ?string, action: ?string, user: int|string|null, from: ?Carbon, to: ?Carbon, range: ?string, signins: bool}
     */
    private function validatedFilters(Request $request, string $tz): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            // A user id, or "system" for entries the app wrote by itself.
            'user' => ['nullable', 'regex:/^(system|\d{1,18})$/'],
            'range' => ['nullable', 'in:'.implode(',', array_keys(self::RANGES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'signins' => ['nullable', 'boolean'],
        ]);

        $range = $validated['range'] ?? null;

        // A quick range wins over typed dates: it's the button just pressed.
        [$from, $to] = $range !== null
            ? [now($tz)->subDays(self::RANGES[$range]), now($tz)]
            : [$request->date('from', null, $tz), $request->date('to', null, $tz)];

        $user = $validated['user'] ?? null;

        return [
            'search' => $validated['search'] ?? null,
            'module' => $validated['module'] ?? null,
            'action' => $validated['action'] ?? null,
            'user' => $user === null || $user === 'system' ? $user : (int) $user,
            'from' => $from,
            'to' => $to,
            'range' => $range,
            'signins' => (bool) ($validated['signins'] ?? false),
        ];
    }

    private function filteredQuery(array $filters): Builder
    {
        return AuditLog::query()
            // Asking for the Authentication module is asking for sign-ins.
            ->withoutSignIns(! $filters['signins'] && $filters['module'] !== 'auth')
            ->search($filters['search'])
            ->module($filters['module'])
            ->action($filters['action'])
            ->when($filters['user'] === 'system', fn (Builder $q) => $q->whereNull('user_id'))
            ->when(is_int($filters['user']), fn (Builder $q) => $q->byUser($filters['user']))
            ->betweenDates($filters['from'], $filters['to']);
    }

    /**
     * The strip above the filters. Deliberately independent of the filter:
     * it answers "what's been happening?" before anyone starts digging.
     *
     * @return array{today: int, changes: int, failed: int, top: ?array{name: string, count: int}}
     */
    private function summary(string $tz): array
    {
        $startOfToday = now($tz)->startOfDay()->utc();
        $weekAgo = now()->subDays(7);

        $top = AuditLog::query()
            ->withoutSignIns()
            ->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->first();

        return [
            'today' => AuditLog::where('created_at', '>=', $startOfToday)->count(),
            'changes' => AuditLog::query()->withoutSignIns()
                ->whereNotIn('action', ['export', 'permission-denied'])
                ->where('created_at', '>=', $weekAgo)->count(),
            'failed' => AuditLog::where('action', 'failed-login')->where('created_at', '>=', $weekAgo)->count(),
            'top' => $top ? [
                'name' => User::withTrashed()->find($top->user_id)?->name ?? __('Unknown'),
                'count' => (int) $top->total,
            ] : null,
        ];
    }

    private function describeFilters(array $filters): string
    {
        $user = match (true) {
            $filters['user'] === 'system' => 'user System',
            is_int($filters['user']) => 'user '.(User::withTrashed()->find($filters['user'])?->name ?? "#{$filters['user']}"),
            default => null,
        };

        $parts = array_filter([
            $filters['module'] ? 'module '.$filters['module'] : null,
            $filters['action'] ? 'action '.$filters['action'] : null,
            $user,
            $filters['from'] ? 'from '.$filters['from']->toDateString() : null,
            $filters['to'] ? 'to '.$filters['to']->toDateString() : null,
            $filters['search'] ? "search \"{$filters['search']}\"" : null,
            $filters['signins'] ? 'including sign-ins' : null,
        ]);

        return $parts ? ' (filter: '.implode(', ', $parts).')' : '';
    }
}
