<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\AuthController;

// Auth
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.token');

// Email
Route::middleware('auth.token')->group(function () {
Route::get('/me', [AuthController::class, 'me']);
Route::get('/emails/all', [EmailController::class, 'all']);
Route::get('/emails/inbox', [EmailController::class, 'inbox']);
Route::get('/emails/folders', [EmailController::class, 'folders']);
Route::get('/emails/sent', [EmailController::class, 'sent']);
Route::get('/emails/draft', [EmailController::class, 'draft']);
Route::get('/emails/delete', [EmailController::class, 'delete']);
Route::get('/emails/junk', [EmailController::class, 'junk']);
Route::get('/emails/{folder}/{uid}', [EmailController::class, 'show']);
Route::post('/emails/send', [EmailController::class, 'send']);
});
