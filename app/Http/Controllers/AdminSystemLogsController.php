<?php

namespace App\Http\Controllers;

use App\Services\SystemLogReader;
use Illuminate\Http\Request;

class AdminSystemLogsController extends Controller
{
    public function index(Request $request, SystemLogReader $reader)
    {
        return response()->view('admin.system-logs', [
            'logs' => $reader->paginateLogs($request, 'admin.system.logs'),
        ])->withHeaders(['Cache-Control' => 'no-store']);
    }

    public function stream(Request $request, SystemLogReader $reader)
    {
        $logs = $reader->paginateLogs($request, 'admin.system.logs');

        return response()->json([
            'logs' => $logs->items(),
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
            'html' => view('superadmin.partials.log-results', compact('logs'))->render(),
        ])->header('Cache-Control', 'no-store');
    }
}
