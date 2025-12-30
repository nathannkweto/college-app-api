<?php

namespace App\Services;

class GradingService
{
    /**
     * Convert a total score (0-100) to a Letter Grade.
     */
    public function getGradeFromScore($score)
    {
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }

    /**
     * Calculate GPA points from a Letter Grade.
     */
    public function getPointsFromGrade($grade)
    {
        return match($grade) {
            'A' => 4.0,
            'B' => 3.0,
            'C' => 2.0,
            'D' => 1.0,
            default => 0.0,
        };
    }

    /**
     * Determine Academic Standing based on GPA.
     */
    public function getAcademicStanding($gpa)
    {
        if ($gpa >= 2.0) return 'Good Standing';
        if ($gpa >= 1.5) return 'Academic Warning';
        return 'Probation';
    }

    /**
     * Determine Semester Decision (Promoted, Repeat, etc)
     */
    public function getSemesterDecision($gpa, $failedCoursesCount)
    {
        if ($failedCoursesCount > 2) return 'REPEAT';
        if ($gpa < 1.0) return 'DISMISSED';
        if ($failedCoursesCount > 0) return 'PROBATION';
        return 'PROMOTED';
    }
}
