<?php

namespace App\Helpers;

use Illuminate\Support\Arr;

class StreamingServicePlaylistDto
{
    public string $id;
    public string $name;
    public string $tracks;
    public array $owner;
    public int $number_of_tracks;
    public string $image_uri;

    public function __construct(array $params)
    {
        $this->id = Arr::get($params, 'id');
        $this->name = Arr::get($params, 'name');
        $this->tracks = Arr::get($params, 'tracks');
        $this->owner = Arr::get($params, 'owner');
        $this->number_of_tracks = Arr::get($params, 'number_of_tracks');
        $this->image_uri = Arr::get($params, 'image_uri');
    }
}
