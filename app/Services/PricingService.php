<?php

namespace App\Services;

use App\Models\User;

class PricingService
{

    /**
     * Calcula el pago de un procedimiento.
     *
     * @param  User  $user
     * @return array{amount: float, snapshot: array<string, mixed>}
     */
    public function calculate(
        User $instrumentist,
        bool $isVideosurgery,
        int $durationMinutes,
        string $startTimeHHMM, // HH:MM
        string $endTimeHHMM, // HH:MM
    ): array {
        //Todos 200
        $amount = (float) config('qxlog.default_rate', 200.00);

        $snapshot = [
            'version' => config('qxlog.version'),
            'rule' => 'default_rate',
            'default_rate' => $amount,
            'use_pay_scheme' => (bool) ($instrumentist->use_pay_scheme ?? false),
            'is_videosurgery' => $isVideosurgery,
            'duration_minutes' => $durationMinutes,
            'start_time' => $startTimeHHMM,
            'end_time' => $endTimeHHMM,
        ];

        return compact('amount', 'snapshot');

        //Regla 1: Video
        if ($isVideosurgery) {
            $amount = (float) config('qxlog.special.rates.video', 300.00);
            $snapshot['rule'] = 'video';
            $snapshot['video_rate'] = $amount;
        }
    }
}