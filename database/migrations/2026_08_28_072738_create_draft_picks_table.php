<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('pick_number');
            $table->unsignedInteger('round');
            $table->foreignId('manager_id')->constrained()->cascadeOnDelete();
            $table->foreignId('castaway_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['season_id', 'pick_number']);
            $table->unique(['season_id', 'castaway_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_picks');
    }
};
