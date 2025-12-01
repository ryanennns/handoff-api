<?php

use App\Http\Controllers\GeneralOauthController;
use App\Http\Controllers\GetActiveServicesController;
use App\Http\Controllers\GetPlaylistsController;
use App\Http\Controllers\GetPlaylistTransfersController;
use App\Http\Controllers\GoogleOauthController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\TidalOauthController;
use App\Http\Controllers\TriggerPlaylistTransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'hello world']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::middleware(['web'])->group(function () {
        Route::get('redirect/google', [GoogleOauthController::class, 'redirect']);
        Route::get('callback/google', [GoogleOauthController::class, 'callback']);

        Route::get('redirect/tidal', [TidalOauthController::class, 'redirect']);
        Route::get('callback/tidal', [TidalOauthController::class, 'callback']);

        Route::get('redirect/{provider}', [GeneralOauthController::class, 'redirect']);
        Route::get('callback/{provider}', [GeneralOauthController::class, 'callback']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/broadcasting/auth', [\Illuminate\Broadcasting\BroadcastController::class, 'authenticate'])
        ->middleware('auth:sanctum');

    Route::prefix('playlist-transfers')->name('playlist-transfers.')->group(function () {
        Route::post('/trigger', TriggerPlaylistTransferController::class)->name('trigger');
        Route::get('/', GetPlaylistTransfersController::class)->name('index');
    });

    Route::get('/services', GetActiveServicesController::class)->name('services');

    Route::get('/playlists', GetPLaylistsController::class)->name('playlists');
});

Route::get('/dumping-ground', function () {
    dd(request()->all());
})->name('dumping-ground');
