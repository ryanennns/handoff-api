<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlaylistTransferResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetPlaylistTransfersController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return PlaylistTransferResource::collection(
            auth()->user()->playlistTransfers()->latest()->paginate(25)
        );
    }
}
