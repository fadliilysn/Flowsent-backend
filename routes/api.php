<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\AuthController;

// Auth
Route::post('/login', [AuthController::class, 'login']);


// Email
Route::middleware('auth.token')->group(function () {

// User
Route::get('/me', [AuthController::class, 'me']);
Route::post('/logout', [AuthController::class, 'logout']);

// Emails Routes
Route::get('/emails/all', [EmailController::class, 'all']);
Route::post('/emails/mark-as-read', [EmailController::class, 'markAsRead']);
Route::post('/emails/flag', [EmailController::class, 'markAsFlagged']);
Route::post('/emails/unflag', [EmailController::class, 'markAsUnflagged']);
Route::post('/emails/move', [EmailController::class, 'move']);
Route::post('/emails/folder', [EmailController::class, 'createFolder']);
Route::get('/emails/folders', [EmailController::class, 'folders']);

// Send Email
Route::post('/emails/send', [EmailController::class, 'send']);

// Attachment Routes
Route::prefix('emails/attachments')->group(function () {
    // Download attachment langsung
    Route::get('/{uid}/download/{filename}', [EmailController::class, 'downloadAttachment']);    
});

});
