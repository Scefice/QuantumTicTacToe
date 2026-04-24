<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['tournament_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_rounds');
    }
};
