<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\GenerateController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\WizardController;
use App\Http\Middleware\RequireBuilderPassword;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(RequireBuilderPassword::class)->group(function () {
    Route::redirect('/', '/apps');
    Route::get('/apps', [AppController::class, 'index'])->name('apps.index');
    Route::get('/apps/new', [AppController::class, 'create'])->name('apps.create');
    Route::post('/apps', [AppController::class, 'store'])->name('apps.store');
    Route::delete('/apps/{app}', [AppController::class, 'destroy'])->name('apps.destroy');

    Route::get('/apps/{app}/{step}', [WizardController::class, 'show'])->name('wizard.show');
    Route::put('/apps/{app}/{step}', [WizardController::class, 'update'])->name('wizard.update');
    Route::post('/apps/{app}/generate', GenerateController::class)->name('apps.generate');

    Route::get('/library', [LibraryController::class, 'index'])->name('library');
});
