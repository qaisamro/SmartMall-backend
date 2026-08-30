<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\v1\SocialAuthController;
use App\Http\Controllers\GoogleDriveAuthController;

Route::get('/', function () {
    return view('welcome');
});

// Google OAuth routes (browser redirect flow, not API)
Route::get('/auth/google/redirect', [SocialAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);

// Google Drive OAuth for personal account (smartmallps2026@gmail.com - My Drive)
Route::get('/google-drive/connect', [GoogleDriveAuthController::class, 'connect'])->name('google-drive.connect');
Route::get('/google-drive/callback', [GoogleDriveAuthController::class, 'callback'])->name('google-drive.callback');
Route::get('/google-drive/status', [GoogleDriveAuthController::class, 'status'])->name('google-drive.status');
