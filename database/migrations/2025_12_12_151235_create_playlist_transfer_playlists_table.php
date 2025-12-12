<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('playlist_transfer_playlists', function (Blueprint $table) {
            $table->id();
            $table->uuid('playlist_id');
            $table->uuid('playlist_transfer_id');
            $table->unique(['playlist_id', 'playlist_transfer_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_transfer_playlists');
    }
};
