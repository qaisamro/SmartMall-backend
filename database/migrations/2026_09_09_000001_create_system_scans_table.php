<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_scans', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->enum('trigger_type', ['manual', 'scheduled'])->default('manual');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->integer('total_tests')->default(0);
            $table->integer('passed')->default(0);
            $table->integer('warnings')->default(0);
            $table->integer('failed')->default(0);
            $table->integer('critical')->default(0);
            $table->integer('not_checked')->default(0);
            $table->integer('review_required')->default(0);
            $table->enum('overall_status', ['healthy', 'warning', 'error', 'critical'])->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index('started_at');
            $table->index('triggered_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_scans');
    }
};
