<?php

namespace App\Services;

class GradingService
{
    public static function calculateGrade(float $mark): string
    {
        return match (true) {
            $mark >= 86 => 'A+',
            $mark >= 76 => 'A',
            $mark >= 70 => 'B+',
            $mark >= 60 => 'B',
            $mark >= 55 => 'C+',
            $mark >= 50 => 'C',
            $mark >= 45 => 'D+',
            $mark >= 40 => 'D',
            default => 'F',
        };
    }
}
