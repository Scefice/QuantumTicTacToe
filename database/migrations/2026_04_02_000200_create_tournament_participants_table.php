<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_participant_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $table->string('display_name');
            $table->unsignedInteger('seed')->default(0);
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->unsignedInteger('bye_count')->default(0);
            $table->string('status')->default('active');
            $table->unsignedInteger('final_rank')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'display_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_participants');
    }
};
