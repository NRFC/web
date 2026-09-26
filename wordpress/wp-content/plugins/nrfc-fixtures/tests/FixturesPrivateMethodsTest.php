<?php

declare(strict_types=1);

namespace NRFC\Fixtures\Tests;

use DateTime;
use FixturesTestState;
use NRFCFixtures\Fixtures;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Error;
use WP_REST_Request;
use WP_Term;

final class FixturesPrivateMethodsTest extends TestCase
{
    private Fixtures $fixtures;

    protected function setUp(): void
    {
        FixturesTestState::reset();
        $_GET = [];
        $_POST = [];

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

    public function test_resolve_team_finds_by_name_slug_alias_and_id(): void
    {
        $team1 = new WP_Term(10, '1st XV', '1st-xv', Fixtures::TAX_TEAM);
        $team2 = new WP_Term(20, 'Under 15 Boys', 'under-15-boys', Fixtures::TAX_TEAM);
        $team3 = new WP_Term(30, 'Lions', 'lions', Fixtures::TAX_TEAM);

        FixturesTestState::$terms = [$team1, $team2, $team3];

        self::assertSame($team1, $this->fixtures->resolve_team('1st XV'));
        self::assertSame($team1, $this->fixtures->resolve_team('1st-xv'));
        self::assertSame($team1, $this->fixtures->resolve_team(10));
        self::assertSame($team1, $this->fixtures->resolve_team('10'));

        self::assertSame($team2, $this->fixtures->resolve_team('Under 15 Boys'));
        self::assertSame($team2, $this->fixtures->resolve_team('u15b'));
        self::assertSame($team2, $this->fixtures->resolve_team('under-15-boys'));

        self::assertSame($team3, $this->fixtures->resolve_team('lions'));
        self::assertSame($team3, $this->fixtures->resolve_team('LIONS'));

        self::assertNull($this->fixtures->resolve_team('Unknown Team'));
        self::assertNull($this->fixtures->resolve_team(''));
    }

    public function test_generate_spond_csv_returns_formatted_csv(): void
    {
        $team = new WP_Term(10, '1st XV', '1st-xv', Fixtures::TAX_TEAM);

        $fixture_post = (object)[
            'ID' => 101,
            'post_title' => '1st XV vs Beccles',
        ];

        FixturesTestState::$query_posts = [$fixture_post];
        FixturesTestState::$post_meta[101] = [
            Fixtures::META_DATE => '2026-10-12',
            Fixtures::META_KICK_OFF_TIME => '14:30',
            Fixtures::META_VENUE => 'Home',
        ];
        FixturesTestState::$object_terms[101] = [
            Fixtures::TAX_OPPOSING_CLUB => [new WP_Term(1, 'Beccles', 'beccles', Fixtures::TAX_OPPOSING_CLUB)],
            Fixtures::TAX_OPPOSING_TEAM => [new WP_Term(2, '1st XV', '1st-xv', Fixtures::TAX_OPPOSING_TEAM)],
        ];

        $csv = $this->fixtures->generate_spond_csv($team);

        self::assertStringContainsString('Start date', $csv);
        self::assertStringContainsString('Start time', $csv);
        self::assertStringContainsString('Meet up', $csv);
        self::assertStringContainsString('End date', $csv);
        self::assertStringContainsString('End time', $csv);
        self::assertStringContainsString('Match type', $csv);
        self::assertStringContainsString('Home team', $csv);
        self::assertStringContainsString('Away team', $csv);
        self::assertStringContainsString('Description', $csv);
        self::assertStringContainsString('Place', $csv);

        self::assertStringContainsString('12/10/2026', $csv);
        self::assertStringContainsString('14:30', $csv);
        self::assertStringContainsString('01:00', $csv);
        self::assertStringContainsString('16:30', $csv);
        self::assertStringContainsString('Home match', $csv);
        self::assertStringContainsString('1st XV', $csv);
        self::assertStringContainsString('Beccles 1st XV', $csv);
        self::assertStringContainsString('1st XV vs Beccles', $csv);
    }

    public function test_rest_export_fixtures_validation_errors(): void
    {
        $team = new WP_Term(10, '1st XV', '1st-xv', Fixtures::TAX_TEAM);
        FixturesTestState::$terms = [$team];

        // Missing team parameter
        $request_empty = new WP_REST_Request([]);
        $error = $this->fixtures->rest_export_fixtures($request_empty);
        self::assertInstanceOf(WP_Error::class, $error);
        self::assertSame('missing_team', $error->get_error_code());

        // Unknown team
        $request_unknown = new WP_REST_Request(['team' => 'Unknown']);
        $error_unknown = $this->fixtures->rest_export_fixtures($request_unknown);
        self::assertInstanceOf(WP_Error::class, $error_unknown);
        self::assertSame('team_not_found', $error_unknown->get_error_code());
    }

    public function test_register_landing_page_rewrite_and_query_vars(): void
    {
        $this->fixtures->register_landing_page_rewrite();
        self::assertCount(2, FixturesTestState::$rewrite_rules);
        self::assertSame('^fixtures/export/?$', FixturesTestState::$rewrite_rules[1]['regex']);

        $vars = $this->fixtures->register_query_vars([]);
        self::assertContains('nrfc_fixtures_export', $vars);
        self::assertContains('team', $vars);
    }

    public function test_register_rest_routes(): void
    {
        $this->fixtures->register_rest_routes();
        self::assertCount(1, FixturesTestState::$registered_rest_routes);
        self::assertSame('nrfc-fixtures/v1', FixturesTestState::$registered_rest_routes[0]['namespace']);
        self::assertSame('/export', FixturesTestState::$registered_rest_routes[0]['route']);
    }

    public function test_render_spond_export_page_outputs_form_and_direct_get_links(): void
    {
        $team1 = new WP_Term(10, '1st XV', '1st-xv', Fixtures::TAX_TEAM);
        $team2 = new WP_Term(20, 'Under 15 Boys', 'under-15-boys', Fixtures::TAX_TEAM);
        FixturesTestState::$terms = [$team1, $team2];

        ob_start();
        $this->fixtures->render_spond_export_page();
        $output = ob_get_clean();

        self::assertStringContainsString('Spond Export', $output);
        self::assertStringContainsString('Export to CSV', $output);
        self::assertStringContainsString('Public GET Export Links', $output);
        self::assertStringContainsString('http://example.org/fixtures/export?team=1st-xv', $output);
        self::assertStringContainsString('http://example.org/wp-json/nrfc-fixtures/v1/export?team=1st-xv', $output);
        self::assertStringContainsString('http://example.org/fixtures/export?team=under-15-boys', $output);
        self::assertStringContainsString('http://example.org/wp-json/nrfc-fixtures/v1/export?team=under-15-boys', $output);
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



