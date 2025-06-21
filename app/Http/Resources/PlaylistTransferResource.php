<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaylistTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'source'      => $this->source,
            'destination' => $this->destination,
            'playlists'   => $this->playlists,
            'status'      => $this->status,
            'created_at'  => $this->created_at->toIso8601String(),
        ];
    }
}
