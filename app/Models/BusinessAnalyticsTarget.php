<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BusinessAnalyticsTarget extends Model
{
    protected $connection = 'sqlite_targets';
    protected $table = 'business_analytics_targets';
    protected $fillable = ['period', 'scope_level', 'source', 'dimension_key', 'official_target'];

    protected function casts(): array
    {
        return ['official_target' => 'float'];
    }
}
