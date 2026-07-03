<?php

namespace App\Services;

use Carbon\Carbon;

class ReservationContextService
{
    public function parseFromUserMessage(string $text): array
    {
        $text = trim($text);

        $checkIn = null;
        $checkOut = null;

        if (preg_match('/entrada\s*(?:el\s*)?(\d{1,2}\/\d{1,2}\/\d{4}).*?salida\s*(?:el\s*)?(\d{1,2}\/\d{1,2}\/\d{4})/iu', $text, $m)) {
            $checkIn = $this->parseNumericDate($m[1]);
            $checkOut = $this->parseNumericDate($m[2]);
        }

        if (! $checkIn || ! $checkOut) {
            if (preg_match_all('/\b(\d{1,2}\/\d{1,2}\/\d{4})\b/u', $text, $matches) && count($matches[1]) >= 2) {
                $first = $this->parseNumericDate($matches[1][0]);
                $second = $this->parseNumericDate($matches[1][1]);

                if ($first && $second) {
                    $checkIn = $checkIn ?: $first;
                    $checkOut = $checkOut ?: $second;
                }
            }
        }

        if (! $checkIn || ! $checkOut) {
            if (preg_match('/(?:del\s+)?(\d{1,2})\s+al\s+(\d{1,2})\s+de\s+([a-záéíóúñ]+)(?:\s+de\s+(\d{4}))?/iu', $text, $m)) {
                $month = $this->spanishMonthToNumber($m[3]);
                $year = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : (int) now()->year;

                if ($month !== null) {
                    $d1 = $this->buildDate((int) $m[1], $month, $year);
                    $d2 = $this->buildDate((int) $m[2], $month, $year);

                    if ($d1 && $d2) {
                        $checkIn = $checkIn ?: $d1;
                        $checkOut = $checkOut ?: $d2;
                    }
                }
            }
        }

        if (! $checkIn || ! $checkOut) {
            return [
                'check_in' => null,
                'check_out' => null,
                'nights' => null,
                'nights_breakdown' => [],
                'weekend_nights' => 0,
                'weekday_nights' => 0,
                'normalized_summary' => null,
            ];
        }

        if ($checkOut->lte($checkIn)) {
            return [
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'nights' => null,
                'nights_breakdown' => [],
                'weekend_nights' => 0,
                'weekday_nights' => 0,
                'normalized_summary' => null,
            ];
        }

        $nights = $checkIn->diffInDays($checkOut);
        $nightStats = $this->buildNightsBreakdown($checkIn, $checkOut);

        $summary = sprintf(
            'Estancia del %d de %s de %d al %d de %s de %d, %d noches (%d entre semana y %d fin de semana).',
            $checkIn->day,
            $this->monthNameEs($checkIn->month),
            $checkIn->year,
            $checkOut->day,
            $this->monthNameEs($checkOut->month),
            $checkOut->year,
            $nights,
            $nightStats['weekday_nights'],
            $nightStats['weekend_nights']
        );

        return [
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => $nights,
            'nights_breakdown' => $nightStats['nights_breakdown'],
            'weekend_nights' => $nightStats['weekend_nights'],
            'weekday_nights' => $nightStats['weekday_nights'],
            'normalized_summary' => $summary,
        ];
    }

    private function parseNumericDate(string $value): ?Carbon
    {
        $value = trim($value);
        [$d, $m, $y] = array_pad(explode('/', $value), 3, null);

        if (! $d || ! $m || ! $y) {
            return null;
        }

        return $this->buildDate((int) $d, (int) $m, (int) $y);
    }

    private function buildDate(int $day, int $month, int $year): ?Carbon
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    private function buildNightsBreakdown(Carbon $checkIn, Carbon $checkOut): array
    {
        $current = $checkIn->copy();
        $nightsBreakdown = [];
        $weekendNights = 0;
        $weekdayNights = 0;

        while ($current->lt($checkOut)) {
            $isWeekend = in_array($current->dayOfWeekIso, [5, 6, 7], true);
            $type = $isWeekend ? 'fin_de_semana' : 'entre_semana';

            $nightsBreakdown[] = [
                'date' => $current->toDateString(),
                'type' => $type,
            ];

            if ($isWeekend) {
                $weekendNights++;
            } else {
                $weekdayNights++;
            }

            $current->addDay();
        }

        return [
            'nights_breakdown' => $nightsBreakdown,
            'weekend_nights' => $weekendNights,
            'weekday_nights' => $weekdayNights,
        ];
    }

    private function spanishMonthToNumber(string $month): ?int
    {
        $month = mb_strtolower(trim($month));

        $map = [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];

        return $map[$month] ?? null;
    }

    private function monthNameEs(int $month): string
    {
        $names = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        return $names[$month] ?? '';
    }
}
