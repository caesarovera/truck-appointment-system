<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('transport_companies')->cascadeOnDelete();
            $table->string('plate_no');
            $table->string('status')->default('ACTIVE'); // ACTIVE | INACTIVE
            $table->timestamps();

            $table->unique(['company_id', 'plate_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucks');
    }
};
