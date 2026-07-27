<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Collection;

interface AnalyzerInterface
{
    /**
     * Analyze the provided transactions and bookings to generate intelligence data.
     *
     * @param Collection $transactions
     * @param Collection $bookings
     * @return array
     */
    public function analyze(Collection $transactions, Collection $bookings): array;
}
