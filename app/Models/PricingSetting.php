<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingSetting extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'default_rate',
        'video_rate',
        'night_rate',
        'long_case_rate',
        'long_case_threshold_minutes',
        'night_start',
        'night_end',
    ];

    protected $casts = [
        'default_rate' => 'decimal:2',
        'video_rate' => 'decimal:2',
        'night_rate' => 'decimal:2',
        'long_case_rate' => 'decimal:2',
        'long_case_threshold_minutes' => 'integer',
        'night_start' => 'string',
        'night_end' => 'string',
    ];
}
