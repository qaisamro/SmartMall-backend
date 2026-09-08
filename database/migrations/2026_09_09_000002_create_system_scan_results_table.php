<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_scan_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('system_scans')->cascadeOnDelete();
            $table->string('check_key', 80);
            $table->enum('category', ['technical', 'application', 'ux', 'performance']);
            $table->enum('severity', ['info', 'warning', 'error', 'critical']);
            $table->enum('status', ['pass', 'warning', 'failed', 'not_checked', 'review_required']);
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->json('details')->nullable();
            $table->enum('user_impact', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->timestamps();
            $table->index('scan_id');
            $table->index('check_key');
            $table->index('status');
            $table->index('severity');
            $table->unique(['scan_id', 'check_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_scan_results');
    }
};
