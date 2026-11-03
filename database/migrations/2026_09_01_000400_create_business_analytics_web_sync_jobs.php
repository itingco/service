<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlite_targets';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);
        if ($schema->hasTable('business_analytics_web_sync_jobs')) return;
        $schema->create('business_analytics_web_sync_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->index();
            $table->unsignedInteger('total_steps')->default(72);
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('command', 255)->default('php artisan analytics:sync --months=36');
            $table->text('log_path')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('business_analytics_web_sync_jobs');
    }
};
