<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();
            $table->string('exercise')->nullable();
            $table->foreignId('movement_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('reps');
            $table->float('weight')->nullable();
            $table->integer('intensity')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();

            $table->index('movement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sets');
    }
};
