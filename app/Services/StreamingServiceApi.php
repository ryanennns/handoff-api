<?php

namespace App\Services;

abstract class StreamingServiceApi
{
    abstract public function getPlaylists(): array;
    abstract public function getPlaylistTracks(string $playlistId): array;
}
