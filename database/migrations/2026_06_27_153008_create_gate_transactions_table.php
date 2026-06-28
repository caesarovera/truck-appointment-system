<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // IN | OUT
            $table->foreignId('processed_by')->constrained('users');
            $table->timestamp('processed_at');
            $table->timestamps();

            // One IN and one OUT per appointment — last line of defence against double gate events.
            $table->unique(['appointment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_transactions');
    }
};
