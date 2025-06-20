<?php

namespace App\Helpers;

class Track
{
    private string $source;
    private string $remote_id;
    private string $name;
    private array $artists;
    private bool $explicit;
    private array $album;


    public function __construct(array $contents)
    {
        $this->source = $contents['source'];
        $this->remote_id = $contents['remote_id'];
        $this->name = $contents['name'];
        $this->artists = $contents['artists'];
        $this->explicit = $contents['explicit'] ?? false;
        $this->album = $contents['album'];
    }
}
