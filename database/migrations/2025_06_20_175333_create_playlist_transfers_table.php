<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('playlist_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source');
            $table->string('destination');
            $table->json('playlists');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])
                ->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_transfers');
    }
};
