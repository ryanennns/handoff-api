<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StreamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetStreamingServicePlaylists extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['service' => 'required|string']);

        $service = $request->input('service');
        /** @var User $user */
        $user = auth()->user();
        if (
            !$user->oauthCredentials()
                ->where('provider', $service)
                ->exists()
        ) {
            return response()->json(['message' => 'No OAuth credentials found'], 404);
        }

        $api = StreamingService::getServiceForProvider(
            $service,
            $user->oauthCredentials()
                ->where('provider', $service)
                ->firstOrFail()
        );

        return response()->json([
            'playlists' => $api->getPlaylists(),
        ]);
    }
}
