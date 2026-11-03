<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_targets')->create('sales_kpi_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('sales_line', 150)->unique();
            $table->decimal('monthly_target', 18, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_targets')->dropIfExists('sales_kpi_targets');
    }
};
