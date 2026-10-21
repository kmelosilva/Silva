<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailySuggestionController;
use App\Http\Controllers\EnlistmentController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\MissionProgressController;
use App\Http\Controllers\PriorityAreaController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/agent', [AgentController::class, 'show']);
    Route::put('/agent/priorities', [AgentController::class, 'priorities']);

    Route::get('/enlistment', [EnlistmentController::class, 'show']);
    Route::post('/enlistment/oath', [EnlistmentController::class, 'oath']);
    Route::post('/enlistment/codename', [EnlistmentController::class, 'codename']);
    Route::post('/enlistment/complete', [EnlistmentController::class, 'complete']);
    Route::get('/priority-areas', [PriorityAreaController::class, 'index']);

    Route::get('/missions', [MissionController::class, 'index']);
    Route::get('/missions/entry', [MissionController::class, 'entry']);
    Route::get('/missions/today', [DailySuggestionController::class, 'show']);
    Route::get('/missions/my', [MissionProgressController::class, 'index']);
    Route::post('/missions/{mission}/accept', [MissionProgressController::class, 'accept']);
    Route::post('/missions/{mission}/coordinates/{coordinate}/complete', [MissionProgressController::class, 'complete']);

});
