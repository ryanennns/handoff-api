<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('failed_tracks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->json('artists');
            $table->string('remote_id');
            $table->string('source');
            $table->string('destination');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_tracks');
    }
};
