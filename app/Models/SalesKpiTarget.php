<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SalesKpiTarget extends Model
{
    protected $connection = 'sqlite_targets';

    protected $fillable = ['sales_line', 'monthly_target'];

    protected function casts(): array
    {
        return ['monthly_target' => 'float'];
    }
}
