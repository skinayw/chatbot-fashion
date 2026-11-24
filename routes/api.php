<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/chat', [ChatController::class, 'send'])->middleware('throttle:15,1');
Route::get('/chat/{uuid}', [ChatController::class, 'show'])->middleware('throttle:30,1');