<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\API\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});

//Route::get('/v1/boards', [PostController::class, 'index']);
//Route::resource('/v1/boards', PostController::class);

Route::middleware('auth:api')->group(function () {
    Route::apiResource('/v1/boards', TaskController::class);
    Route::get('/v1/user_information', [AuthController::class, 'getUserInfo']);
    Route::post('/v1/user_information', [AuthController::class, 'updateUserInfo']);
    Route::get('/v1/comments/{task_id}', [CommentController::class, 'index']);
    Route::post('/v1/comments/{task_id}', [CommentController::class, 'store']);
});

Route::prefix('/v1/auth')->group(function() {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});
