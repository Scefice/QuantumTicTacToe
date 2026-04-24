<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_round_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('table_number')->default(1);
            $table->foreignId('player_one_id')->constrained('tournament_participants')->cascadeOnDelete();
            $table->foreignId('player_two_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $table->foreignId('winner_participant_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $table->string('result_type')->default('pending');
            $table->unsignedInteger('time_limit_minutes')->default(20);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_matches');
    }
};
