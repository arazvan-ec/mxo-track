<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dto\CsvQualityWarning;
use App\Service\CsvQualityAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvQualityAnalyzer::class)]
final class CsvQualityAnalyzerTest extends TestCase
{
    private CsvQualityAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new CsvQualityAnalyzer();
    }

    #[Test]
    public function validCsvReturnsHighScore(): void
    {
        $rows = [
            ['REF001', 'Juan Perez', 'Calle Mayor 10, Madrid', '40.4168', '-3.7038', '612345678', 'Notas'],
            ['REF002', 'Maria Garcia', 'Gran Via 25, Madrid', '40.4200', '-3.7100', '698765432', ''],
        ];

        $report = $this->analyzer->analyze($rows);

        self::assertGreaterThanOrEqual(90, $report->score);
    }

    #[Test]
    public function emptyReferenceGeneratesError(): void
    {
        $rows = [
            ['', 'Juan Perez', 'Calle Mayor 10, Madrid', '40.4168', '-3.7038', '612345678', ''],
        ];

        $report = $this->analyzer->analyze($rows);

        $errors = array_filter($report->warnings, fn (CsvQualityWarning $w) => $w->field === 'reference' && $w->severity === CsvQualityWarning::SEVERITY_ERROR);

        self::assertNotEmpty($errors);
    }

    #[Test]
    public function duplicateReferenceGeneratesError(): void
    {
        $rows = [
            ['REF001', 'Juan', 'Calle Mayor 10, Madrid', '40.4168', '-3.7038', '612345678', ''],
            ['REF001', 'Maria', 'Gran Via 25, Madrid', '40.4200', '-3.7100', '698765432', ''],
        ];

        $report = $this->analyzer->analyze($rows);

        $duplicates = array_filter($report->warnings, fn (CsvQualityWarning $w) => $w->field === 'reference' && str_contains($w->message, 'Duplicate'));

        self::assertNotEmpty($duplicates);
    }

    #[Test]
    public function invalidLatitudeGeneratesError(): void
    {
        $rows = [
            ['REF001', 'Juan', 'Calle Mayor 10, Madrid', 'not-a-number', '-3.7038', '612345678', ''],
        ];

        $report = $this->analyzer->analyze($rows);

        $errors = array_filter($report->warnings, fn (CsvQualityWarning $w) => $w->field === 'latitude' && $w->severity === CsvQualityWarning::SEVERITY_ERROR);

        self::assertNotEmpty($errors);
    }

    #[Test]
    public function coordinatesOutsideSpainGenerateWarning(): void
    {
        $rows = [
            ['REF001', 'Juan', 'Somewhere in London 123', '51.5074', '-0.1278', '612345678', ''],
        ];

        $report = $this->analyzer->analyze($rows);

        $warnings = array_filter($report->warnings, fn (CsvQualityWarning $w) => $w->field === 'latitude' && $w->severity === CsvQualityWarning::SEVERITY_WARNING);

        self::assertNotEmpty($warnings);
    }

    #[Test]
    public function shortPhoneNumberGeneratesWarning(): void
    {
        $rows = [
            ['REF001', 'Juan', 'Calle Mayor 10, Madrid', '40.4168', '-3.7038', '12345', ''],
        ];

        $report = $this->analyzer->analyze($rows);

        $phoneWarnings = array_filter($report->warnings, fn (CsvQualityWarning $w) => $w->field === 'phone');

        self::assertNotEmpty($phoneWarnings);
    }

    #[Test]
    public function shortAddressGeneratesWarning(): void
    {
        $rows = [
            ['REF001', 'Juan', 'Calle 1', '40.4168', '-3.7038', '612345678', ''],
        ];

        $report = $this->analyzer->analyze($rows);

        $addrWarnings = array_filter($report->warnings, fn (CsvQualityWarning $w) => $w->field === 'address' && $w->severity === CsvQualityWarning::SEVERITY_WARNING);

        self::assertNotEmpty($addrWarnings);
    }

    #[Test]
    public function emptyRowsReturnPerfectScore(): void
    {
        $report = $this->analyzer->analyze([]);

        self::assertSame(100, $report->score);
        self::assertEmpty($report->warnings);
    }

    #[Test]
    public function emptyRecipientNameGeneratesError(): void
    {
        $rows = [
            ['REF001', '', 'Calle Mayor 10, Madrid', '40.4168', '-3.7038', '612345678', ''],
        ];

        $report = $this->analyzer->analyze($rows);

        $errors = array_filter($report->warnings, fn (CsvQualityWarning $w) => $w->field === 'recipient_name');

        self::assertNotEmpty($errors);
    }

    #[Test]
    public function addressWithoutNumberGeneratesInfo(): void
    {
        $rows = [
            ['REF001', 'Juan', 'Calle Mayor, Madrid, España', '40.4168', '-3.7038', '612345678', ''],
        ];

        $report = $this->analyzer->analyze($rows);

        $infos = array_filter($report->warnings, fn (CsvQualityWarning $w) => $w->field === 'address' && $w->severity === CsvQualityWarning::SEVERITY_INFO);

        self::assertNotEmpty($infos);
    }
}
