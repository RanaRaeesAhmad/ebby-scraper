<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
// use Hash;
use App\Http\Controllers\ScraperController;


Route::get('/', function () {
    return view('auth.login');
    // return Hash::make('12345678');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
Route::post('/process-excel', [ScraperController::class, 'processExcel'])->name('process.excel');
Route::get('/download-results/{file}', [ScraperController::class, 'downloadResults'])->name('download.results');
Route::post('/process-refined', [ScraperController::class, 'processRefined'])->name('process.refined');

require __DIR__.'/auth.php';
