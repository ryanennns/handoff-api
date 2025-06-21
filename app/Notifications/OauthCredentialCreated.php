<?php

namespace App\Notifications;

use App\Models\OauthCredential;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class OauthCredentialCreated extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(private OauthCredential $credential)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.' . $this->credential->user_id),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'service' => $this->credential->provider,
        ];
    }
}
