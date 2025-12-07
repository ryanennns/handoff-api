<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Playlist extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class);
    }
}
