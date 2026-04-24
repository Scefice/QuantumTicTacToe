<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('player_x_name');
            $table->string('player_x_token');
            $table->string('player_o_name')->nullable();
            $table->string('player_o_token')->nullable();
            $table->unsignedInteger('match_length')->default(3);
            $table->string('status')->default('waiting');
            $table->unsignedBigInteger('version')->default(1);
            $table->json('state');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rooms');
    }
};
