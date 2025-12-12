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
            $table->integer('playlists_processed')->default(0);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])
                ->default('pending');

            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_transfers');
    }
};
