<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

Route::get('/', function() {
    return response()->json(['message' => 'hello world']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::middleware(['web'])->group(function () {
        Route::get('/redirect', function () {
            return Socialite::driver('google')->redirect();
        });

        Route::get('/auth/callback', function () {
            $githubUser = Socialite::driver('github')->user();

            $user = User::updateOrCreate([
                'github_id' => $githubUser->id,
            ], [
                'name'                 => $githubUser->name,
                'email'                => $githubUser->email,
                'github_token'         => $githubUser->token,
                'github_refresh_token' => $githubUser->refreshToken,
            ]);

            Auth::login($user);

            return redirect('/dashboard');
        });
    });

    Route::post('login', LoginController::class)
        ->name('login');

    Route::post('register', RegisterController::class)
        ->name('register');
});
