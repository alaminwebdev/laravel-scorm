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
    // Route::get('/scorm/sco/{sco}', [ScormController::class, 'launch'])->name('scorm.launch');


    Route::get('/scorm/{package}/outline', [ScormController::class, 'outline'])->name('scorm.outline');
    // Serve internal SCORM files same-origin
    Route::get('/scorm/content/{package}/{path}', [ScormController::class, 'serveContent'])
        ->where('path', '.*')
        ->name('scorm.content');

    // SCORM Tracking API Routes
    Route::prefix('scorm/tracking')->group(function () {
        Route::post('/{package}/{sco}/initialize', [ScormTrackingController::class, 'initialize'])->name('scorm.tracking.initialize');
        Route::get('/{package}/{sco}/getvalue', [ScormTrackingController::class, 'getValue'])->name('scorm.tracking.getvalue');
        Route::post('/{package}/{sco}/setvalue', [ScormTrackingController::class, 'setValue'])->name('scorm.tracking.setvalue');
        Route::post('/{package}/{sco}/commit', [ScormTrackingController::class, 'commit'])->name('scorm.tracking.commit');
        Route::post('/{package}/{sco}/terminate', [ScormTrackingController::class, 'terminate'])->name('scorm.tracking.terminate');
    });
});

