<?php

namespace App\Http\Controllers;

use App\Services\StreamingService;
use Illuminate\Http\Request;

class DeleteOauthCredentialController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'service' => 'required|in:' . implode(',', StreamingService::PROVIDERS)
        ]);

        $service = $request->input('service');

        auth()->user()
            ->oauthCredentials()
            ->where('provider', $service)
            ->delete();

        return response()->noContent();
    }
}
