<?php

namespace App\Helpers;

use Illuminate\Support\Arr;

class StreamingServicePlaylistDto
{
    public $id;
    public $name;
    public $tracks;
    public $owner;
    public $number_of_tracks;
    public $image_uri;

    public function __construct(array $params)
    {
        $this->id = Arr::get($params, 'id');
        $this->name = Arr::get($params, 'name');
        $this->tracks = Arr::get($params, 'tracks');
        $this->owner = Arr::get($params, 'owner');
        $this->number_of_tracks = Arr::get($params, 'number_of_tracks');
        $this->image_uri = Arr::get($params, 'image_uri');
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'tracks'           => $this->tracks,
            'owner'            => $this->owner,
            'number_of_tracks' => $this->number_of_tracks,
            'image_uri'        => $this->image_uri,
        ];
    }
}
