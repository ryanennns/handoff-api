<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class GetActiveServicesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'services' => auth()
                    ->user()
                    ->oauthCredentials()
                    ->pluck('provider')
                    ->unique()
                    ->values()
                    ->toArray() ?? []
        ]);
    }
}
