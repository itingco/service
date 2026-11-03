<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BusinessAnalyticsMonthlySale extends Model
{
    protected $connection = 'sqlite_targets';
    protected $table = 'business_analytics_monthly_sales';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sales_amount' => 'float',
            'quantity' => 'float',
            'invoice_count' => 'integer',
        ];
    }
}
