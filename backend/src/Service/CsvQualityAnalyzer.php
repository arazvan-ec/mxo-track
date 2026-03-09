<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CsvQualityReport;
use App\Dto\CsvQualityWarning;

final class CsvQualityAnalyzer
{
    /** Minimum rows needed for statistical outlier detection. */
    private const MIN_ROWS_FOR_OUTLIERS = 10;

    /** Standard deviation multiplier for outlier detection. */
    private const OUTLIER_THRESHOLD = 3.0;

    /** Spain bounding box (approximate). */
    private const LAT_MIN = 35.0;
    private const LAT_MAX = 44.0;
    private const LNG_MIN = -10.0;
    private const LNG_MAX = 5.0;

    /** Minimum digits for a valid phone number. */
    private const MIN_PHONE_DIGITS = 9;

    /** Minimum address length to consider it plausible. */
    private const MIN_ADDRESS_LENGTH = 10;

    /** Score deductions per severity. */
    private const DEDUCTION_ERROR = 5.0;
    private const DEDUCTION_WARNING = 2.0;
    private const DEDUCTION_INFO = 0.5;

    /**
     * Analyze an array of CSV rows (each row is an associative or indexed array).
     *
     * Rows should NOT include the header row. Each row is expected to have columns
     * in the same order as ShipmentCsvImporter::EXPECTED_COLUMNS:
     * [reference, recipient_name, address, latitude, longitude, phone, notes]
     *
     * @param list<array<int, string>> $rows
     */
    public function analyze(array $rows): CsvQualityReport
    {
        $warnings = [];
        $references = [];

        // First pass: collect numeric values for statistical analysis
        $weights = []; // Not present in current CSV but kept for extensibility
        $volumes = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because index is 0-based and row 1 is the header
            $row = array_pad($row, 7, '');

            $reference = trim((string) ($row[0] ?? ''));
            $recipientName = trim((string) ($row[1] ?? ''));
            $address = trim((string) ($row[2] ?? ''));
            $latRaw = trim((string) ($row[3] ?? ''));
            $lonRaw = trim((string) ($row[4] ?? ''));
            $phone = trim((string) ($row[5] ?? ''));

            // Required fields
            if ($reference === '') {
                $warnings[] = new CsvQualityWarning(
                    $rowNumber,
                    'reference',
                    'Reference is empty',
                    CsvQualityWarning::SEVERITY_ERROR,
                );
            }

            if ($recipientName === '') {
                $warnings[] = new CsvQualityWarning(
                    $rowNumber,
                    'recipient_name',
                    'Recipient name is empty',
                    CsvQualityWarning::SEVERITY_ERROR,
                );
            }

            if ($address === '') {
                $warnings[] = new CsvQualityWarning(
                    $rowNumber,
                    'address',
                    'Address is empty',
                    CsvQualityWarning::SEVERITY_ERROR,
                );
            }

            // Duplicate references
            if ($reference !== '') {
                if (isset($references[$reference])) {
                    $warnings[] = new CsvQualityWarning(
                        $rowNumber,
                        'reference',
                        sprintf('Duplicate reference "%s" (first seen at row %d)', $reference, $references[$reference]),
                        CsvQualityWarning::SEVERITY_ERROR,
                    );
                } else {
                    $references[$reference] = $rowNumber;
                }
            }

            // Coordinate validation
            if ($latRaw !== '') {
                $lat = filter_var($latRaw, FILTER_VALIDATE_FLOAT);
                if ($lat === false) {
                    $warnings[] = new CsvQualityWarning(
                        $rowNumber,
                        'latitude',
                        'Latitude is not a valid number',
                        CsvQualityWarning::SEVERITY_ERROR,
                    );
                } elseif ($lat < self::LAT_MIN || $lat > self::LAT_MAX) {
                    $warnings[] = new CsvQualityWarning(
                        $rowNumber,
                        'latitude',
                        sprintf('Latitude %.6f is outside expected range for Spain (%.0f to %.0f)', $lat, self::LAT_MIN, self::LAT_MAX),
                        CsvQualityWarning::SEVERITY_WARNING,
                    );
                }
            }

            if ($lonRaw !== '') {
                $lon = filter_var($lonRaw, FILTER_VALIDATE_FLOAT);
                if ($lon === false) {
                    $warnings[] = new CsvQualityWarning(
                        $rowNumber,
                        'longitude',
                        'Longitude is not a valid number',
                        CsvQualityWarning::SEVERITY_ERROR,
                    );
                } elseif ($lon < self::LNG_MIN || $lon > self::LNG_MAX) {
                    $warnings[] = new CsvQualityWarning(
                        $rowNumber,
                        'longitude',
                        sprintf('Longitude %.6f is outside expected range for Spain (%.0f to %.0f)', $lon, self::LNG_MIN, self::LNG_MAX),
                        CsvQualityWarning::SEVERITY_WARNING,
                    );
                }
            }

            // Phone validation
            if ($phone !== '') {
                $digits = preg_replace('/\D/', '', $phone);
                if ($digits !== null && strlen($digits) < self::MIN_PHONE_DIGITS) {
                    $warnings[] = new CsvQualityWarning(
                        $rowNumber,
                        'phone',
                        sprintf('Phone number has only %d digits (minimum %d expected)', strlen($digits), self::MIN_PHONE_DIGITS),
                        CsvQualityWarning::SEVERITY_WARNING,
                    );
                }
            }

            // Address quality
            if ($address !== '' && strlen($address) < self::MIN_ADDRESS_LENGTH) {
                $warnings[] = new CsvQualityWarning(
                    $rowNumber,
                    'address',
                    'Address appears too short to be complete',
                    CsvQualityWarning::SEVERITY_WARNING,
                );
            }

            if ($address !== '' && !preg_match('/\d/', $address)) {
                $warnings[] = new CsvQualityWarning(
                    $rowNumber,
                    'address',
                    'Address does not contain a street number',
                    CsvQualityWarning::SEVERITY_INFO,
                );
            }
        }

        $score = $this->calculateScore($warnings);

        return new CsvQualityReport($score, $warnings);
    }

    /** @param list<CsvQualityWarning> $warnings */
    private function calculateScore(array $warnings): int
    {
        $score = 100.0;

        foreach ($warnings as $warning) {
            $score -= match ($warning->severity) {
                CsvQualityWarning::SEVERITY_ERROR => self::DEDUCTION_ERROR,
                CsvQualityWarning::SEVERITY_WARNING => self::DEDUCTION_WARNING,
                CsvQualityWarning::SEVERITY_INFO => self::DEDUCTION_INFO,
                default => 0.0,
            };
        }

        return (int) max(0, round($score));
    }
}
