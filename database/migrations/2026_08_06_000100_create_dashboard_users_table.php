<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlite_targets';

    public function up(): void
    {
        Schema::connection($this->connection)->create('dashboard_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('username', 100)->unique();
            $table->string('password');
            $table->string('role', 30)->default('viewer')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('dashboard_users');
    }
};
