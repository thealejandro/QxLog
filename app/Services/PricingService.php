<?php

namespace App\Services;

use App\Models\PricingSetting;
use App\Models\User;
use App\Support\TimeHelper;

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

        $settings = PricingSetting::firstOrCreate([
            'id' => 1,
        ]);

        $base = (float) $settings->default_rate;

        $snapshot = [
            'version' => 2,
            'use_pay_scheme' => (bool) $instrumentist->use_pay_scheme,
            'is_videosurgery' => $isVideosurgery,
            'duration_minutes' => $durationMinutes,
            'start_time' => $startTimeHHMM,
            'end_time' => $endTimeHHMM,

            //Settings usados (auditoria)
            'rates' => [
                'default_rate' => (float) $settings->default_rate,
                'video_rate' => (float) $settings->video_rate,
                'night_rate' => (float) $settings->night_rate,
                'long_case_rate' => (float) $settings->long_case_rate,
            ],
            'thresholds' => [
                'long_case_threshold_minutes' => (int) $settings->long_case_threshold_minutes,
                'night_start' => (string) $settings->night_start,
                'night_end' => (string) $settings->night_end,
            ],
            'rule' => 'default_rate',
        ];

        // Si NO es especial: siempre base
        if (!$snapshot['use_pay_scheme']) {
            return ['amount' => $base, 'snapshot' => $snapshot];
        }

        // Regla 1: Video
        if ($isVideosurgery) {
            $amount = (float) $settings->video_rate;
            $snapshot['rule'] = 'video_rate';
            return ['amount' => $amount, 'snapshot' => $snapshot];
        }

        // Regla 2: largo
        if ($durationMinutes >= (int) $settings->long_case_threshold_minutes) {
            $amount = (float) $settings->long_case_rate;
            $snapshot['rule'] = 'long_case_rate';
            return ['amount' => $amount, 'snapshot' => $snapshot];
        }

        // Regla 3: madrugada
        $isNight = TimeHelper::isWithinTimeWindow(
            $startTimeHHMM,
            (string) $settings->night_start,
            (string) $settings->night_end
        );

        if ($isNight) {
            $amount = (float) $settings->night_rate;
            $snapshot['rule'] = 'night_rate';
            return ['amount' => $amount, 'snapshot' => $snapshot];
        }

        // Default (especial pero no cae en reglas)
        return ['amount' => $base, 'snapshot' => $snapshot];
    }


    private function calculateBeforeScheme(
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