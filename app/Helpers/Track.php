<?php

namespace App\Helpers;

use Illuminate\Support\Arr;

class Track
{
    public ?string $source;
    public ?string $remote_id;
    public ?string $name;
    public ?array $artists;
    public ?bool $explicit;
    public ?array $album;

    public function __construct(array $contents)
    {
        $this->source = Arr::get($contents, 'source');
        $this->remote_id = Arr::get($contents, 'remote_id');
        $this->name = Arr::get($contents, 'name');
        $this->artists = Arr::get($contents, 'artists');
        $this->explicit = Arr::get($contents, 'explicit') ?? false;
        $this->album = Arr::get($contents, 'album');
    }

    public function toSearchString(): string
    {
        return $this->artists[0] . ' ' . trim(explode('(feat.', $this->name)[0]);
    }
}
