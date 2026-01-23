<?php

namespace App\Support;

use Carbon\Carbon;

class TimeHelper
{
    /**
     * Recibe date ("YYYY-MM-DD"), start ("HH:MM"), end ("HH:MM")
     * y devuelve duración en minutos. Soporta cruce de medianoche.
     */
    public static function durationMinutes(string $date, string $startTime, string $endTime): int
    {
        $start = Carbon::parse("$date $startTime");
        $end = Carbon::parse("$date $endTime");

        // Si la cirugía cruza medianoche (ej: 22:00 → 02:00)
        if ($end->lt($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }
    public static function isNight(string $timeHHMM): bool
    {
        $time = Carbon::parse($timeHHMM);
        $nightStart = Carbon::parse('21:00');
        $nightEnd = Carbon::parse('05:00');

        return $time->between($nightStart, $nightEnd, true);
    }
}
