<?php

namespace App\Http\Controllers;

use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends Controller
{
    /**
     * Register
     *
     * Registers a new user.
     *
     * @bodyParam name string required The name of the user.
     * @bodyParam email string required The email of the user.
     * @bodyParam password string required The password of the user.
     * @bodyParam password_confirmation string required The password confirmation of the user.
     * @bodyParam device_name string required The name of the device.
     * @bodyParam latitude float required The latitude of the user.
     * @bodyParam longitude float required The longitude of the user.
     * @bodyParam fcm_token string optional The Firebase Cloud Messaging token for the user's device. Example: fMEQMOF0xEELbP7icvPD:APA91bHQOcmVEbg...
     *
     * @response 201 { token: "token", user_id: "08e1608c-eb31-4623-bde6-b63646daecf9" }
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'string|max:255',
            'email'       => 'required|string|email|max:255|unique:users',
            'password'    => 'required|string|min:8|confirmed',
        ]);

        $user = User::query()->create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'token'   => $user->createToken($request->device_name)->plainTextToken,
            'user_id' => $user->getKey(),
        ], Response::HTTP_CREATED);
    }
}
