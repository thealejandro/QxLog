<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Procedure extends Model
{
    use HasFactory;
    protected $fillable = [
        'procedure_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'patient_name',
        'procedure_type',
        'is_videosurgery',

        'instrumentist_id',
        'instrumentist_name',

        'doctor_id',
        'doctor_name',

        'circulating_id',
        'circulating_name',

        'calculated_amount',
        'pricing_snapshot',
        'status',
    ];

    protected $casts = [
        'procedure_date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
        'is_videosurgery' => 'boolean',
        'pricing_snapshot' => 'array',      // JSON ↔ array
        'calculated_amount' => 'decimal:2', // siempre 2 decimales
    ];

    public function instrumentist()
    {
        return $this->belongsTo(User::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class);
    }

    public function circulating()
    {
        return $this->belongsTo(User::class);
    }

}
