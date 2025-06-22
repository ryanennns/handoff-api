<?php

namespace App\Helpers;

use Illuminate\Support\Arr;

class Playlist
{
    public ?string $id;
    public ?string $name;
    public ?string $description;
    public ?array $owner;
    public ?int $numberOfTracks;
    public ?string $imageUri;

    public function __construct(array $data)
    {
        $this->id = Arr::get($data, 'id');
        $this->name = Arr::get($data, 'name');
        $this->description = Arr::get($data, 'description');
        $this->owner = Arr::get($data, 'owner');
        $this->numberOfTracks = Arr::get($data, 'numberOfTracks');
        $this->imageUri = Arr::get($data, 'imageUri');
    }
}
