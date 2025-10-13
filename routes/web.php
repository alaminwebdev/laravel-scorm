<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScormController;
use App\Http\Controllers\ScormTrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';

Route::middleware(['auth'])->group(function () {
    Route::get('/scorm', [ScormController::class, 'index'])->name('scorm.index');
    Route::post('/scorm', [ScormController::class, 'store'])->name('scorm.store');
    Route::get('/scorm/{id}/launch', [ScormController::class, 'launch'])->name('scorm.launch');
    Route::get('/scorm/{id}/outline', [ScormController::class, 'outline'])->name('scorm.outline');
    Route::post('/scorm/api/commit', [ScormTrackingController::class, 'commit'])->name('scorm.api.commit');
});

