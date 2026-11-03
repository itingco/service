<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlite_targets';

    public function up(): void
    {
        Schema::connection($this->connection)->create('dashboard_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('dashboard_users')->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('token_hash', 64);
            $table->string('status', 20)->default('pending')->index();
            $table->string('browser', 80)->nullable();
            $table->string('platform', 80)->nullable();
            $table->string('first_ip', 64)->nullable();
            $table->string('last_ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('dashboard_users')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'token_hash']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('dashboard_devices');
    }
};
