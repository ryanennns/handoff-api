<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaylistTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'source'              => $this->source,
            'destination'         => $this->destination,
            'playlists'           => $this->whenPivotLoaded('playlists', fn() => $this->pivot->playlists),
            'playlists_processed' => $this->playlists_processed,
            'status'              => $this->status,
            'created_at'          => $this->created_at->toIso8601String(),
        ];
    }
}
