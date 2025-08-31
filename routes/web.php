<?php

use App\Http\Controllers\RgpdController;
use Illuminate\Support\Facades\Route;

/**************************************************** Authentification ****************************************************/
// Route::get('/login', [\App\Http\Controllers\AuthController::class, 'login'])->name('login');
// Route::get('/callback', [\App\Http\Controllers\AuthController::class, 'callback'])->name('callback');
// Route::get('/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->name('logout');
/**************************************************************************************************************************/

/**************************************************** RGPD ****************************************************/
Route::get('/rgpd-notice', [RgpdController::class, 'show'])->name('rgpd.notice');
Route::post('/rgpd-notice', [RgpdController::class, 'accept'])->name('rgpd.accept');
/**************************************************************************************************************************/

Route::get('/registration', [App\Http\Controllers\AuthController::class, 'register'])->name('registration');