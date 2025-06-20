<?php

use App\Http\Controllers\GetActiveServicesController;
use App\Http\Controllers\GetPlaylistsController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\TriggerPlaylistTransferController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

Route::get('/', function () {
    return response()->json(['message' => 'hello world']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::middleware(['web'])->group(function () {
        Route::get('redirect/{provider}', function (string $provider, Request $request) {
            $state = Crypt::encryptString(json_encode([
                'user_id' => $request->user()?->getKey(),
            ]));

            return Socialite::driver($provider)
                ->with(['state' => $state])
                ->redirect();
        });

        Route::get('callback/{provider}', function (string $provider, Request $request) {
            $state = $request->input('state');
            $userId = json_decode(Crypt::decryptString($state))->user_id;

            $oauthUser = Socialite::driver($provider)
                ->stateless()
                ->user();

            $user = User::query()->firstOrCreate(['id' => $userId]);
            $user->oauthCredentials()->updateOrCreate([
                'provider' => $provider,
                'email'    => $oauthUser->getEmail(),
            ], [
                'provider'      => $provider,
                'provider_id'   => $oauthUser->getId(),
                'email'         => $oauthUser->getEmail(),
                'token'         => $oauthUser->token,
                'refresh_token' => $oauthUser->refreshToken,
            ]);

            Auth::login($user);
            $user->tokens()->delete();
            return redirect('http://127.0.0.1:5173/dashboard?token=' . $user->createToken('auth_token')->plainTextToken);
        });
    });

    Route::post('login', LoginController::class)->name('login');
    Route::post('register', RegisterController::class)->name('register');

});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('playlist-transfers')->group(function () {
        Route::post('/trigger', TriggerPlaylistTransferController::class)->name('trigger');
    });

    Route::get('/services', GetACtiveServicesController::class)->name('services');

    Route::get('/playlists', GetPLaylistsController::class)->name('playlists');
});

