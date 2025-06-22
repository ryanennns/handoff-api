<?php

namespace App\Events;

use App\Models\OauthCredential;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewOauthCredential implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private OauthCredential $oauthCredential
    )
    {
        //
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->oauthCredential->user->getKey()),
        ];
    }

    public function broadcastWith(): array
    {
        return ['service' => $this->oauthCredential->provider];
    }
}
