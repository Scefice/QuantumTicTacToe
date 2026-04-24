<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('join_code', 6)->nullable()->after('slug');
        });

        DB::table('tournaments')
            ->select(['id'])
            ->orderBy('id')
            ->get()
            ->each(function (object $tournament): void {
                do {
                    $code = Str::upper(Str::random(6));
                } while (DB::table('tournaments')->where('join_code', $code)->exists());

                DB::table('tournaments')
                    ->where('id', $tournament->id)
                    ->update(['join_code' => $code]);
            });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('join_code', 6)->nullable(false)->change();
            $table->unique('join_code');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropUnique(['join_code']);
            $table->dropColumn('join_code');
        });
    }
};
