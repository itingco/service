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

        if (!$schema->hasTable('business_analytics_daily_sales')) {
            $schema->create('business_analytics_daily_sales', function (Blueprint $table): void {
                $table->id();
                $table->date('sales_date')->index();
                $table->string('source', 10)->index();
                $table->string('sales_line', 160)->default('LAINNYA')->index();
                $table->string('customer_code', 80)->default('');
                $table->string('customer_name', 240)->default('');
                $table->string('salesperson_name', 180)->default('');
                $table->string('item_code', 100)->default('')->index();
                $table->string('item_name', 255)->default('');
                $table->decimal('sales_amount', 22, 2)->default(0);
                $table->decimal('quantity', 22, 4)->default(0);
                $table->unsignedInteger('invoice_count')->default(0);
                $table->timestamps();
                $table->index(['sales_date', 'source', 'item_code'], 'ba_daily_date_source_item_idx');
                $table->index(['sales_date', 'customer_code'], 'ba_daily_customer_idx');
                $table->index(['sales_date', 'sales_line'], 'ba_daily_line_idx');
            });
        }

        if (!$schema->hasTable('business_analytics_product_profiles')) {
            $schema->create('business_analytics_product_profiles', function (Blueprint $table): void {
                $table->id();
                $table->string('source', 10)->default('ALL')->index();
                $table->string('item_code', 100)->index();
                $table->string('item_name', 255)->default('');
                $table->text('specifications')->default('');
                $table->text('use_cases')->nullable();
                $table->text('advantages')->nullable();
                $table->decimal('reference_price', 22, 2)->nullable();
                $table->decimal('current_stock', 22, 4)->nullable();
                $table->decimal('target_margin_percent', 8, 3)->nullable();
                $table->text('competitor_notes')->nullable();
                $table->text('constraints')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps();
                $table->unique(['source', 'item_code'], 'ba_product_profile_unique');
            });
        }

        if (!$schema->hasTable('business_analytics_product_reviews')) {
            $schema->create('business_analytics_product_reviews', function (Blueprint $table): void {
                $table->id();
                $table->string('source', 10)->default('ALL')->index();
                $table->string('item_code', 100)->index();
                $table->string('review_period', 7)->index();
                $table->text('internal_facts_json');
                $table->text('profile_snapshot_json');
                $table->text('diagnosis_json');
                $table->text('external_research_json')->nullable();
                $table->text('strategy_json')->nullable();
                $table->string('research_status', 30)->default('NOT_REQUESTED')->index();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('business_analytics_advisor_messages')) {
            $schema->create('business_analytics_advisor_messages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('review_id')->index();
                $table->string('role', 20)->index();
                $table->text('message_text');
                $table->text('response_json')->nullable();
                $table->string('provider', 60)->nullable();
                $table->string('model', 120)->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('business_analytics_experiments')) {
            $schema->create('business_analytics_experiments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('review_id')->nullable()->index();
                $table->string('item_code', 100)->index();
                $table->string('source', 10)->default('ALL')->index();
                $table->string('channel', 20)->default('OMNICHANNEL')->index();
                $table->string('title', 255);
                $table->text('hypothesis');
                $table->text('target_audience');
                $table->text('offer_description');
                $table->date('start_date');
                $table->date('end_date');
                $table->date('baseline_start_date');
                $table->date('baseline_end_date');
                $table->text('kpi_json');
                $table->text('success_threshold_json');
                $table->text('stop_rule');
                $table->text('control_definition_json')->nullable();
                $table->string('status', 30)->default('DRAFT')->index();
                $table->text('result_json')->nullable();
                $table->string('decision', 20)->nullable()->index();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('business_analytics_customer_segments')) {
            $schema->create('business_analytics_customer_segments', function (Blueprint $table): void {
                $table->id();
                $table->string('customer_code', 80)->unique();
                $table->string('segment', 120)->index();
                $table->string('subsegment', 160)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        $schema->dropIfExists('business_analytics_customer_segments');
        $schema->dropIfExists('business_analytics_experiments');
        $schema->dropIfExists('business_analytics_advisor_messages');
        $schema->dropIfExists('business_analytics_product_reviews');
        $schema->dropIfExists('business_analytics_product_profiles');
        $schema->dropIfExists('business_analytics_daily_sales');
    }
};
