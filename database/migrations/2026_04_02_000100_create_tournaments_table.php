<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('stage_name')->nullable();
            $table->string('creator_name')->nullable();
            $table->unsignedInteger('rounds_count')->default(3);
            $table->unsignedInteger('advancing_count')->default(3);
            $table->unsignedInteger('game_time_limit_minutes')->default(20);
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('source_tournament_id')->nullable()->constrained('tournaments')->nullOnDelete();
            $table->string('status')->default('registration');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
