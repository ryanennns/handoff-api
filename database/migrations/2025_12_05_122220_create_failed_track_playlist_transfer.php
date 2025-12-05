<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('failed_track_playlist_transfer', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('failed_track_id')
                ->references('id')
                ->on('failed_tracks')
                ->onDelete('cascade');
            $table->foreignUuid('playlist_transfer_id')
                ->references('id')
                ->on('playlist_transfers')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_track_playlist_transfer');
    }
};
