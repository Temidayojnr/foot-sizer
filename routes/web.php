<?php

use App\Http\Controllers\FootSizerController;
use App\Http\Controllers\ProfileController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/logs', function () {
    $password = env('LOG_VIEWER_PASSWORD');
    if (request('password') !== $password) {
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

    $page = request()->get('page', 1);
    $perPage = 10;

    $logs = new LengthAwarePaginator(
        collect($entries)->forPage($page, $perPage),
        count($entries),
        $perPage,
        $page,
        [
            'path' => url('/logs'),
            'query' => ['password' => request('password')],
        ]
    );

    return view('logs', ['logs' => $logs]);
});

Route::post('/foot-sizer', [FootSizerController::class, 'process'])->name('foot-sizer.process');

Route::get('sac/download-csv/{date}', function ($date) {
    // Validate date format: YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return response()->json(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
    }

    $filePath = storage_path('app/foot_records/' . $date . '.csv');

    if (!file_exists($filePath)) {
        return response()->json(['error' => "No record found for {$date}."], 404);
    }

    return response()->download($filePath);
});


Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
