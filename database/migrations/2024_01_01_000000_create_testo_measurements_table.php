<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testo_measurements', function (Blueprint $table) {
            $table->id();
            $table->string('logger_uuid')->nullable()->index();
            $table->timestamp('measured_at')->index();
            $table->decimal('temperature', 8, 4)->nullable();
            $table->decimal('humidity', 8, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testo_measurements');
    }
};
