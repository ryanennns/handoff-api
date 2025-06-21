<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('oauth_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider');
            $table->string('provider_id');
            $table->string('email')->nullable();
            $table->text('token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->uuid('user_id')->index();
            $table->foreign('user_id')
                ->references('id')
                ->on('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_credentials');
    }
};
