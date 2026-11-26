<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AuditActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $this->validatedFilters($request);

        $logs = $this->filteredQuery($filters)
            ->with('user')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        // Every known module, plus any one-off module string that exists in
        // the data but isn't listed in AuditActions yet.
        $modules = collect(AuditActions::MODULES)
            ->union(
                AuditLog::query()->distinct()->pluck('module')
                    ->mapWithKeys(fn ($m) => [$m => AuditActions::moduleLabel($m)])
            )
            ->sort();

        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action')
            ->mapWithKeys(fn ($a) => [$a => AuditActions::label($a)]);

        // withTrashed: deactivated staff still need to be filterable — their
        // past actions are exactly what someone may be looking into.
        $users = User::withTrashed()->orderBy('name')->get(['id', 'name', 'deleted_at']);

        return view('audit-logs.index', compact('logs', 'modules', 'actions', 'users', 'filters'));
    }

    /**
     * The current filter as a CSV — for handing to an owner or auditor.
     * The export itself is audit-logged, after the fact it describes, so
     * it never appears inside its own file.
     */
    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $this->validatedFilters($request);

        // Capped at the newest entry that exists right now: the file streams
        // after the "export" entry below is written, and would otherwise
        // include it.
        $lastId = AuditLog::max('id') ?? 0;
        $query = $this->filteredQuery($filters)->where('id', '<=', $lastId)->with('user')->latest('id');
        $count = (clone $query)->count();

        $audit->log('export', 'audit', "Exported {$count} audit log entr".($count === 1 ? 'y' : 'ies').' to CSV'.$this->describeFilters($filters).'.');

        $filename = 'audit-log-'.now()->toDateString().'.csv';

        return new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date (UTC)', 'User', 'Module', 'Action', 'Description', 'IP Address']);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, array_map([$this, 'neutraliseFormula'], [
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->user?->name.($log->user?->trashed() ? ' (deactivated)' : ''),
                        AuditActions::moduleLabel($log->module),
                        AuditActions::label($log->action),
                        $log->description,
                        $log->ip_address ?? '',
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
     * Descriptions include text staff typed (member names, plan names, a
     * login email), so a value starting with = + - @ would run as a formula
     * when the CSV is opened in Excel or Sheets. A leading apostrophe makes
     * it display as plain text instead.
     */
    private function neutraliseFormula(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * @return array{search: ?string, module: ?string, action: ?string, user: ?int, from: ?Carbon, to: ?Carbon}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            'search' => $validated['search'] ?? null,
            'module' => $validated['module'] ?? null,
            'action' => $validated['action'] ?? null,
            'user' => isset($validated['user']) ? (int) $validated['user'] : null,
            'from' => $request->date('from'),
            'to' => $request->date('to'),
        ];
    }

    private function filteredQuery(array $filters): Builder
    {
        return AuditLog::query()
            ->search($filters['search'])
            ->module($filters['module'])
            ->action($filters['action'])
            ->byUser($filters['user'])
            ->betweenDates($filters['from'], $filters['to']);
    }

    private function describeFilters(array $filters): string
    {
        $parts = array_filter([
            $filters['module'] ? 'module '.$filters['module'] : null,
            $filters['action'] ? 'action '.$filters['action'] : null,
            $filters['user'] ? 'user '.(User::withTrashed()->find($filters['user'])?->name ?? "#{$filters['user']}") : null,
            $filters['from'] ? 'from '.$filters['from']->toDateString() : null,
            $filters['to'] ? 'to '.$filters['to']->toDateString() : null,
            $filters['search'] ? "search \"{$filters['search']}\"" : null,
        ]);

        return $parts ? ' (filter: '.implode(', ', $parts).')' : '';
    }
}
