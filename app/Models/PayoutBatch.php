<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutBatch extends Model
{
    protected $fillable = [
        'instrumentist_id',
        'paid_by_id',
        'paid_at',
        'total_amount',
        'status',
        'void_reason',
        'payout_snapshot',
        'payout_details',
    ];

    protected $casts = [
        'payout_snapshot' => 'array',
        'payout_details' => 'array',
        'total_amount' => 'decimal:2',
    ];

    public function instrumentist()
    {
        return $this->belongsTo(User::class, 'instrumentist_id', 'id');
    }

    public function paidByUser()
    {
        return $this->belongsTo(User::class, 'paid_by_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(PayoutItem::class, 'payout_batch_id', 'id');
    }
}
