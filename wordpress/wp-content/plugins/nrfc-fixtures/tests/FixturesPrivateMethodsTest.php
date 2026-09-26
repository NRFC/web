<?php

declare(strict_types=1);

namespace NRFC\Fixtures\Tests;

use DateTime;
use NRFCFixtures\Fixtures;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class FixturesPrivateMethodsTest extends TestCase
{
    private Fixtures $fixtures;

    protected function setUp(): void
    {
        $reflection = new ReflectionClass(Fixtures::class);
        $this->fixtures = $reflection->newInstanceWithoutConstructor();
    }

    public function test_normalise_team_expands_known_aliases(): void
    {
        self::assertSame('Under 12 Boys', $this->invokePrivateMethod('normaliseTeam', 'u12b'));
        self::assertSame('Boys Junior Academy', $this->invokePrivateMethod('normaliseTeam', 'jba'));
        self::assertSame('Girls Senior Academy', $this->invokePrivateMethod('normaliseTeam', 'u18g'));
    }

    public function test_normalise_team_keeps_unknown_values(): void
    {
        self::assertSame('Lions', $this->invokePrivateMethod('normaliseTeam', 'Lions'));
    }

    public function test_make_fixture_title_includes_club_and_notes(): void
    {
        $title = $this->invokePrivateMethod('makeFixtureTitle', '1st XV', 'Wymondham', 'Cup "Semi" Final');

        self::assertSame('1st XV vs Wymondham - Cup Semi Final', $title);
    }

    public function test_make_fixture_title_works_without_optional_fields(): void
    {
        $title = $this->invokePrivateMethod('makeFixtureTitle', 'Lions', '', '');

        self::assertSame('Lions', $title);
    }

    public function test_clean_up_date_supports_multiple_formats(): void
    {
        $dates = [
            ['input' => '2026-09-03', 'expected' => '2026-09-03'],
            ['input' => '03/09/2026', 'expected' => '2026-09-03'],
            ['input' => '20260903', 'expected' => '2026-09-03'],
            ['input' => '2026.09.03', 'expected' => '2026-09-03']
        ];

        foreach ($dates as $date_case) {
            $result = $this->invokePrivateMethod('clean_up_date', $date_case['input']);
            self::assertInstanceOf(DateTime::class, $result);
            self::assertSame($date_case['expected'], $result->format('Y-m-d'));
        }
    }

    public function test_clean_up_date_returns_false_for_invalid_input(): void
    {
        $result = $this->invokePrivateMethod('clean_up_date', 'not a date');

        self::assertFalse($result);
    }

    public function test_build_spond_row_home_match_with_custom_time_and_opposing_team(): void
    {
        $row = $this->fixtures->build_spond_row(
            '1st XV',
            '2026-10-12',
            '14:30',
            'Home',
            'Beccles',
            '1st XV',
            '1st XV vs Beccles'
        );

        self::assertSame([
            '12/10/2026',
            '14:30',
            '01:00',
            '12/10/2026',
            '16:30',
            'Home match',
            '1st XV',
            'Beccles 1st XV',
            '1st XV vs Beccles',
            '',
        ], $row);
    }

    public function test_build_spond_row_away_match_defaults_time_when_blank(): void
    {
        $row = $this->fixtures->build_spond_row(
            'Lions',
            '2026-12-17',
            '',
            'Away',
            'Wymondham',
            '',
            'Lions vs Wymondham'
        );

        self::assertSame([
            '17/12/2026',
            '11:00',
            '01:00',
            '17/12/2026',
            '13:00',
            'Away match',
            'Wymondham',
            'Lions',
            'Lions vs Wymondham',
            '',
        ], $row);
    }

    public function test_build_spond_row_defaults_time_when_zero(): void
    {
        $row = $this->fixtures->build_spond_row(
            'Under 15 Boys',
            '2026-11-08',
            '00:00',
            'Home',
            'Holt',
            'U15',
            'Under 15 Boys vs Holt'
        );

        self::assertSame([
            '08/11/2026',
            '11:00',
            '01:00',
            '08/11/2026',
            '13:00',
            'Home match',
            'Under 15 Boys',
            'Holt U15',
            'Under 15 Boys vs Holt',
            '',
        ], $row);
    }

    public function test_build_spond_row_returns_null_for_invalid_date(): void
    {
        $row = $this->fixtures->build_spond_row(
            '1st XV',
            'invalid-date',
            '15:00',
            'Home',
            'Beccles',
            '',
            '1st XV vs Beccles'
        );

        self::assertNull($row);
    }

    private function invokePrivateMethod(string $method_name, mixed ...$arguments): mixed
    {
        $invoker = \Closure::bind(
            static function (Fixtures $fixtures, string $method_name, array $arguments): mixed {
                return $fixtures->{$method_name}(...$arguments);
            },
            null,
            Fixtures::class
        );

        return $invoker($this->fixtures, $method_name, $arguments);
    }
}



