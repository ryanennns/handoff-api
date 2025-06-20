<?php

namespace App\Models;

use App\Services\StreamingServiceApi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistTransfer extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceApi(): StreamingServiceApi
    {
        return StreamingServiceApi::getServiceForProvider(
            $this->source,
            $this->user
                ->oauthCredentials()
                ->where('provider', $this->source)
                ->firstOrFail()
        );
    }

    public function destinationApi(): StreamingServiceApi
    {
        return StreamingServiceApi::getServiceForProvider(
            $this->destination,
            $this->user
                ->oauthCredentials()
                ->where('provider', $this->destination)
                ->firstOrFail()
        );
    }
}
