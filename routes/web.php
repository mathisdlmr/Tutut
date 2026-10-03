<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RgpdController;

/**************************************************** Healthz ****************************************************/
Route::get('/healthz', function () {
    return 'ok';
});
/**************************************************************************************************************************/

/**************************************************** Authentification ****************************************************/
Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::get('/callback', [AuthController::class, 'callback'])->name('callback')->middleware('throttle:60,1'); # Petit rate-limit : 60 req/IP/minutes
/**************************************************************************************************************************/

/**************************************************** RGPD ****************************************************/
Route::middleware('auth')->group(function () {
    Route::get('/rgpd-notice', [RgpdController::class, 'show'])->name('rgpd.notice');
    Route::post('/rgpd-notice', [RgpdController::class, 'accept'])->name('rgpd.accept');
});
/**************************************************************************************************************************/
