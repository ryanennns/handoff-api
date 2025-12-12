<?php

namespace App\Models;

use App\Services\StreamingService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlaylistTransfer extends Model
{
    use HasUuids;
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['playlists' => 'json'];

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_IN_PROGRESS = 'in_progress';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_FAILED = 'failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceApi(): StreamingService
    {
        return StreamingService::getServiceForProvider(
            $this->source,
            $this->user
                ->oauthCredentials()
                ->where('provider', $this->source)
                ->firstOrFail()
        );
    }

    public function destinationApi(): StreamingService
    {
        return StreamingService::getServiceForProvider(
            $this->destination,
            $this->user
                ->oauthCredentials()
                ->where('provider', $this->destination)
                ->firstOrFail()
        );
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(
            Playlist::class,
            'playlist_transfer_playlists',
            'playlist_id',
            'playlist_transfer_id'
        );
    }
}
