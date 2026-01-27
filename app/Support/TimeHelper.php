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

    /**
     * Verifica si una hora dada cae dentro de una ventana de tiempo (inclusive).
     * Maneja rangos que cruzan la medianoche (ej. 22:00 a 05:00).
     */
    public static function isWithinTimeWindow(string $checkTime, string $startWindow, string $endWindow): bool
    {
        $check = Carbon::parse($checkTime);
        $start = Carbon::parse($startWindow);
        $end = Carbon::parse($endWindow);

        // Si la ventana cruza la medianoche (ej. Start: 22:00, End: 05:00)
        if ($start->gt($end)) {
            // Es válido si es >= Start (noche del día) OR <= End (madrugada del día siguiente)
            return $check->gte($start) || $check->lte($end);
        }

        // Ventana normal (ej. Start: 09:00, End: 17:00)
        return $check->between($start, $end);
    }
}
