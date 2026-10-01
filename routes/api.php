<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMuteController;
use App\Http\Controllers\Api\ConversationReadController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureNotBanned;
use App\Http\Middleware\UpdateLastSeen;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::middleware(['auth:sanctum', EnsureNotBanned::class, UpdateLastSeen::class])->group(function () {
        Route::get('me', [MeController::class, 'show']);
        Route::patch('me', [MeController::class, 'update']);
        Route::post('me/avatar', [MeController::class, 'avatar']);
        Route::post('me/devices', [DeviceController::class, 'store']);
        Route::delete('me/devices/{token}', [DeviceController::class, 'destroy']);

        Route::get('users', [UserController::class, 'index']);

        Route::apiResource('conversations', ConversationController::class)->except('update');
        Route::match(['put', 'patch'], 'conversations/{conversation}', [ConversationController::class, 'update']);
        Route::post('conversations/{conversation}/participants', [ParticipantController::class, 'store']);
        Route::delete('conversations/{conversation}/participants/{user}', [ParticipantController::class, 'destroy']);
        Route::post('conversations/{conversation}/mute', ConversationMuteController::class);
        Route::post('conversations/{conversation}/read', ConversationReadController::class);

        Route::get('conversations/{conversation}/messages', [MessageController::class, 'index']);
        Route::post('conversations/{conversation}/messages', [MessageController::class, 'store']);
        Route::patch('messages/{message}', [MessageController::class, 'update']);
        Route::delete('messages/{message}', [MessageController::class, 'destroy']);

        Route::post('attachments', [AttachmentController::class, 'store']);
    });
});
