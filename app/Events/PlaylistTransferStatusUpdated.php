<?php

namespace App\Events;

use App\Models\PlaylistTransfer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlaylistTransferStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(private readonly PlaylistTransfer $playlistTransfer)
    {

    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->playlistTransfer->user->getKey()),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'playlist_transfer_id' => $this->playlistTransfer->getKey(),
            'status'               => $this->playlistTransfer->status
        ];
    }
}
