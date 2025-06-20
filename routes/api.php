<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
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
            $oauthUser = Socialite::driver($provider)->user();

            $user = User::query()->updateOrCreate([
                "{$provider}_id" => $oauthUser->getId(),
            ], collect([
                'name'                      => $oauthUser->name,
                'email'                     => $oauthUser->getEmail(),
                "{$provider}_token"         => $oauthUser->token,
                "{$provider}_refresh_token" => $oauthUser->refreshToken,
            ])->filter(fn($value) => !is_null($value))->toArray());

            Auth::login($user);
            $user->tokens()->delete();
            return redirect('http://127.0.0.1:5173/dashboard?token=' . $user->createToken('auth_token')->plainTextToken);
        });
    });

    Route::post('login', LoginController::class)->name('login');
    Route::post('register', RegisterController::class)->name('register');
});

