<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Procedure extends Model
{
    use HasFactory;

    /**
     * Estandarizar textos a Title Case al guardar.
     */
    protected function patientName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? ucwords(strtolower($value)) : null,
        );
    }

    protected function procedureType(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? ucwords(strtolower($value)) : null,
        );
    }

    protected function instrumentistName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? ucwords(strtolower($value)) : null,
        );
    }

    protected function doctorName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? ucwords(strtolower($value)) : null,
        );
    }

    protected function circulatingName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? ucwords(strtolower($value)) : null,
        );
    }

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
