<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            // Denormalised from the appointment's window so the DB can enforce the
            // "(slot_window_id, container_no) unique per ACTIVE appointment" rule
            // (CLAUDE.md hardening). Cancel/no-show NULLs this to release the slot;
            // multiple NULLs are allowed by both SQLite and MySQL, so freed containers
            // can be re-booked without colliding.
            $table->unsignedBigInteger('slot_window_id')->nullable();
            $table->string('container_no');
            $table->string('iso_type')->nullable();
            $table->unsignedSmallInteger('size')->nullable(); // 20 | 40 (feet)
            $table->timestamps();

            $table->unique(['slot_window_id', 'container_no']);
            $table->index('container_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
