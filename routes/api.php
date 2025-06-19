<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        Route::get('redirect', function () {
            return Socialite::driver('google')
                ->scopes(['openid', 'profile', 'email'])
                ->redirect();
        });

        Route::get('callback', function () {
            $googleUser = Socialite::driver('google')->user();

            $token = $googleUser->token;
            $id = $googleUser->getId();
            Log::info('Google User Info', [
                'id'    => $id,
                'name'  => $googleUser->name,
                'email' => $googleUser->getEmail(),
                'token' => $token,
            ]);

            $user = User::query()->updateOrCreate([
                'google_id' => $googleUser->getId(),
            ], [
                'name'                 => $googleUser->name,
                'email'                => $googleUser->getEmail(),
                'google_token'         => $token,
                'google_refresh_token' => $googleUser->refreshToken,
                'google_id'            => $id,
            ]);

            Auth::login($user);

            $user->tokens()->delete();
            return redirect('http://127.0.0.1:5173/dashboard?token=' . $user->createToken('auth_token')->plainTextToken);
        });
    });

    Route::post('login', LoginController::class)
        ->name('login');

    Route::post('register', RegisterController::class)
        ->name('register');
});
