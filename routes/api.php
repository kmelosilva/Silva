<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\MissionProgressController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/agent', [AgentController::class, 'show']);

    Route::get('/missions', [MissionController::class, 'index']);
    Route::get('/missions/my', [MissionProgressController::class, 'index']);
    Route::post('/missions/{mission}/accept', [MissionProgressController::class, 'accept']);
    Route::post('/missions/{mission}/coordinates/{coordinate}/complete', [MissionProgressController::class, 'complete']);

});
