<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlite_targets';

    public function up(): void
    {
        Schema::connection($this->connection)->create('dashboard_login_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('dashboard_users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('dashboard_devices')->nullOnDelete();
            $table->string('username', 100)->nullable();
            $table->string('event', 40)->index();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::connection($this->connection)->create('dashboard_activity_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('dashboard_users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('dashboard_devices')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('route_name', 120)->nullable();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('dashboard_activity_audits');
        Schema::connection($this->connection)->dropIfExists('dashboard_login_audits');
    }
};
