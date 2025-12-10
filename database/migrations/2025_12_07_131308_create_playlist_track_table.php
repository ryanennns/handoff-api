<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('playlist_track', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('playlist_id')
                ->constrained();
            $table->foreignUuid('track_id')
                ->constrained();
            $table->unique(['playlist_id', 'track_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_track');
    }
};
