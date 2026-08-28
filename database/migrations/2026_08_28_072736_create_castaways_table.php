<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('castaways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('nickname')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->string('occupation')->nullable();
            $table->string('hometown')->nullable();
            $table->string('tribe')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('photo_source_url')->nullable();
            $table->timestamp('photo_fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('castaways');
    }
};
