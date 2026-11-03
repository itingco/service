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

        if (!$schema->hasTable('product_focus_items')) {
            $schema->create('product_focus_items', function (Blueprint $table): void {
                $table->id();
                $table->string('item_code', 100)->unique();
                $table->string('item_name', 255)->default('');
                $table->string('focus_category', 80)->index();
                $table->unsignedInteger('focus_rank')->default(999999)->index();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('similar_items')) {
            $schema->create('similar_items', function (Blueprint $table): void {
                $table->id();
                $table->string('item_code', 100)->index();
                $table->string('item_name', 255)->default('');
                $table->string('similar_item_code', 100)->index();
                $table->string('similar_item_name', 255)->default('');
                $table->timestamps();
                $table->unique(['item_code', 'similar_item_code'], 'similar_items_pair_unique');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        $schema->dropIfExists('similar_items');
        $schema->dropIfExists('product_focus_items');
    }
};
