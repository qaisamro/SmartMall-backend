<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_errors', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('type', 60);
            $table->enum('severity', ['info', 'warning', 'error', 'critical']);
            $table->enum('source', ['backend', 'frontend', 'database', 'network', 'queue', 'storage']);
            $table->text('message');
            $table->string('file', 255)->nullable();
            $table->integer('line')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('route', 255)->nullable();
            $table->string('method', 10)->nullable();
            $table->smallInteger('status_code')->nullable();
            $table->string('request_id', 40)->nullable();
            $table->text('stack_trace')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_role', 40)->nullable();
            $table->integer('occurrences')->default(1);
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('severity');
            $table->index('source');
            $table->index('resolved');
            $table->index('last_seen_at');
            $table->index(['type', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_errors');
    }
};
