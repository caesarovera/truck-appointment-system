<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('transport_companies')->cascadeOnDelete();
            $table->foreignId('truck_id')->constrained('trucks');
            $table->foreignId('driver_id')->constrained('users');
            $table->foreignId('slot_window_id')->constrained('slot_windows');
            $table->string('move_type'); // DELIVERY | RECEIVAL
            $table->string('status')->default('BOOKED'); // see BUSINESS-FLOW §2 state machine
            $table->unsignedInteger('version')->default(1); // optimistic lock for reschedule
            $table->string('booking_code')->unique();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['company_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index(['slot_window_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
