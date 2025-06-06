<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $password = env('LOG_VIEWER_PASSWORD');

        if ($request->password !== $password) {
            return response('Unauthorized.', 401);
        }

        $logPath = storage_path('logs/laravel.log');
        if (!File::exists($logPath)) {
            return response('Log file not found.', 404);
        }

        $lines = explode("\n", File::get($logPath));
        $groupedLogs = [];
        $currentTimestamp = null;

        foreach ($lines as $line) {
            if (preg_match("/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+([a-zA-Z0-9_.]+):\s?(.*)/", $line, $matches)) {
                $timestamp = $matches[1];
                $level = strtoupper(explode('.', $matches[2])[1] ?? 'INFO');
                $message = $matches[3] ?? '';

                if ($currentTimestamp === $timestamp) {
                    $groupedLogs[$currentTimestamp]['lines'][] = $message;
                } else {
                    $currentTimestamp = $timestamp;
                    $groupedLogs[$currentTimestamp] = [
                        'timestamp' => $timestamp,
                        'level' => $level,
                        'lines' => [$message],
                    ];
                }
            } elseif ($currentTimestamp) {
                $groupedLogs[$currentTimestamp]['lines'][] = $line;
            }
        }

        $entries = [];
        foreach ($groupedLogs as $entry) {
            $entries[] = [
                'timestamp' => $entry['timestamp'],
                'level' => $entry['level'],
                'message' => implode("\n", $entry['lines']),
            ];
        }

        $entries = array_reverse($entries);

        $page = $request->get('page', 1);
        $perPage = 10;

        $logs = new LengthAwarePaginator(
            collect($entries)->forPage($page, $perPage),
            count($entries),
            $perPage,
            $page,
            [
                'path' => url('/logs'),
                'query' => ['password' => $request->password],
            ]
        );

        return view('logs', ['logs' => $logs]);
    }
}
