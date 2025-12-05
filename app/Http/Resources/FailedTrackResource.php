<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FailedTrackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'artists'     => $this->artists,
            'remote_id'   => $this->remote_id,
            'source'      => $this->source,
            'destination' => $this->destination,
        ];
    }
}
