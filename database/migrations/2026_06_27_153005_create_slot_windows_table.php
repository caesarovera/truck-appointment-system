<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_windows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gate_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('booked_count')->default(0);
            $table->string('status')->default('OPEN'); // OPEN | CLOSED
            $table->timestamps();

            // One window per gate/date/start. Also the lookup index for availability queries.
            $table->unique(['gate_id', 'date', 'start_time']);
            $table->index(['gate_id', 'date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_windows');
    }
};
