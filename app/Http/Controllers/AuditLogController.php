<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()
            ->with('user')
            ->search(Request::query('search'))
            ->module(Request::query('module'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        // Distinct module list for the filter dropdown — read from the data
        // itself rather than a hardcoded enum, since a module name is just a
        // free-form string tag on each log entry (see AuditLogger::log()),
        // not a real column-level constraint.
        $modules = AuditLog::query()->distinct()->orderBy('module')->pluck('module');

        return view('audit-logs.index', compact('logs', 'modules'));
    }
}
