<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FailedTrack extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['artists' => 'array'];

    public function playlistTransfer(): BelongsToMany
    {
        return $this->belongsToMany(PlaylistTransfer::class);
    }
}
