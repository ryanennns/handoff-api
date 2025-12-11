<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\Request;

class GetPlaylistsController extends Controller
{
    public function __invoke(Request $request)
    {
        return Playlist::query()
            ->where('user_id', auth()->user()->getKey())
            ->paginate()
            ->toResourceCollection();
    }
}
