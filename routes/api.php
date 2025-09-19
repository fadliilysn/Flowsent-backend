<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);

// ==========================
// Protected Routes
// ==========================
Route::middleware('auth.token')->group(function () {

    // User
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ------------------------
    // Emails
    // ------------------------
    Route::prefix('emails')->group(function () {
        // Get emails
        Route::get('/all', [EmailController::class, 'all']);
        Route::get('/folder/{folder}', [EmailController::class, 'folder']);

        // Email operations
        Route::post('/mark-as-read', [EmailController::class, 'markAsRead']);
        Route::post('/flag', [EmailController::class, 'markAsFlagged']);
        Route::post('/unflag', [EmailController::class, 'markAsUnflagged']);
        Route::post('/move', [EmailController::class, 'move']);

        // Delete operations
        Route::delete('/delete-permanent-all', [EmailController::class, 'deletePermanentAll']);

        // Draft and send
        Route::post('/draft', [EmailController::class, 'saveDraft']);
        Route::post('/send', [EmailController::class, 'send']);

        // Attachments
        Route::get('/attachments/{uid}/download/{filename}', [EmailController::class, 'downloadAttachment']);
        // Preview attachment
        Route::get('/attachments/{uid}/preview/{filename}', [EmailController::class, 'previewAttachment']);
        // Upload attachment untuk draft
        Route::post('/attachments/upload', [EmailController::class, 'uploadAttachment']);

    });
});
