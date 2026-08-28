<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('episode_number');
            $table->foreignId('castaway_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->integer('points')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['season_id', 'episode_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
