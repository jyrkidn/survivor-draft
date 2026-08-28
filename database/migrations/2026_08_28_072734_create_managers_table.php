<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('draft_slot');
            $table->timestamps();

            $table->unique(['season_id', 'name']);
            $table->unique(['season_id', 'draft_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managers');
    }
};
