<?php

use App\Http\Controllers\FootSizerController;
use App\Http\Controllers\ProfileController;
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
