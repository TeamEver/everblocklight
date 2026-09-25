<?php

declare(strict_types=1);

namespace Everblocklight\Tests\Unit;

use Everblocklight\Tools\Service\EverblocklightTools;
use PHPUnit\Framework\Attributes\DataProvider;

final class StoreHoursTest extends TestCase
{
    public static function timeProvider(): array
    {
        return [
            ['9h30', '09:30'], ['10h', '10:00'], ['20h00', '20:00'],
            ['9:30', '09:30'], ['9', '09:00'], [' 14h ', '14:00'],
            ['fermé', null], ['', null],
        ];
    }

    #[DataProvider('timeProvider')]
    public function testNormalizeTime(string $input, ?string $expected): void
    {
        self::assertSame($expected, self::callStatic(EverblocklightTools::class, 'normalizeTime', $input));
    }

    public function testNormalizeDashes(): void
    {
        self::assertSame('10h - 12h', self::callStatic(EverblocklightTools::class, 'normalizeDashes', '10h – 12h'));
        self::assertSame('10h - 12h', self::callStatic(EverblocklightTools::class, 'normalizeDashes', '10h — 12h'));
        self::assertNull(self::callStatic(EverblocklightTools::class, 'normalizeDashes', null));
    }

    public static function easterProvider(): array
    {
        return [[2024, '2024-03-31'], [2025, '2025-04-20'], [2026, '2026-04-05'], [2027, '2027-03-28']];
    }

    #[DataProvider('easterProvider')]
    public function testEasterDate(int $year, string $expected): void
    {
        self::assertSame($expected, self::callStatic(EverblocklightTools::class, 'getEasterDate', $year));
    }

    public function testFrenchHolidays2026(): void
    {
        $holidays = EverblocklightTools::getFrenchHolidays(2026);

        self::assertCount(12, $holidays);
        foreach (['2026-01-01', '2026-05-01', '2026-05-08', '2026-07-14', '2026-08-15', '2026-11-01', '2026-11-11', '2026-12-25'] as $fixed) {
            self::assertContains($fixed, $holidays);
        }
        self::assertContains('2026-04-05', $holidays, 'Pâques');
        self::assertContains('2026-05-14', $holidays, 'Ascension');
        self::assertContains('2026-05-24', $holidays, 'Pentecôte');
        self::assertContains('2026-05-25', $holidays, 'Lundi de Pentecôte');
    }
}
