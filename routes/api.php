<?php 

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\UserMissionController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/missions', [MissionController::class, 'index']);
    Route::get('/missions/today', [UserMissionController::class, 'todayMissions']);
    Route::get('/missions/my', [UserMissionController::class, 'allMissions']);

});
