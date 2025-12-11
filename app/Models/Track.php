<?php

namespace App\Models;

use App\Helpers\TrackDto;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;

class Track extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'artists'    => 'json',
        'remote_ids' => 'json',
        'isrc_ids'   => 'json',
    ];

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class);
    }

    public function toDto(string $source): TrackDto
    {
        return new TrackDto([
            'source'    => $source,
            'remote_id' => Arr::get($this->remote_ids, $source),
            'isrc_ids'  => $this->isrc_ids,
            'name'      => $this->name,
            'artists'   => $this->artists,
            'album'     => ['name' => $this->album],
            'explicit'  => $this->explicit,
        ]);
    }
}
