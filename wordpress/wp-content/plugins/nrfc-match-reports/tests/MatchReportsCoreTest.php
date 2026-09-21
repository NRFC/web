<?php

declare(strict_types=1);

namespace NRFC\MatchReports\Tests;

use NRFCMatchReports\LatestMatchReportsWidget;
use NRFCMatchReports\MatchReports;
use NRFCMatchReports\TestWpState;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MatchReportsCoreTest extends TestCase
{
    private MatchReports $match_reports;
    private LatestMatchReportsWidget $widget;

    protected function setUp(): void
    {
        TestWpState::reset();
        $_POST = [];

        $match_reports_reflection = new ReflectionClass(MatchReports::class);
        $this->match_reports = $match_reports_reflection->newInstanceWithoutConstructor();

        $widget_reflection = new ReflectionClass(LatestMatchReportsWidget::class);
        $this->widget = $widget_reflection->newInstanceWithoutConstructor();
    }

    public function test_add_admin_columns_inserts_fixture_and_score_after_title(): void
    {
        $columns = [
            'cb' => '<input type="checkbox" />',
            'title' => 'Title',
            'date' => 'Date',
        ];

        $result = $this->match_reports->add_admin_columns($columns);

        self::assertSame(['cb', 'title', 'fixture', 'score', 'date'], array_keys($result));
        self::assertSame('Fixture', $result['fixture']);
        self::assertSame('Score', $result['score']);
    }

    public function test_widget_update_sanitizes_title_and_count(): void
    {
        $new_instance = [
            'title' => '  <b>Latest Reports</b>  ',
            'count' => '-12',
        ];

        $result = $this->widget->update($new_instance, []);

        self::assertSame('Latest Reports', $result['title']);
        self::assertSame(12, $result['count']);
    }

    public function test_save_match_report_meta_returns_early_when_nonce_invalid(): void
    {
        TestWpState::$nonce_valid = false;

        $_POST = [
            MatchReports::NONCE_METABOX => 'invalid',
            'match_report_fixture_id' => '42',
            'match_report_score_for' => '21',
            'match_report_score_against' => '10',
            'match_report_gallery' => '1,2,3',
        ];

        $this->match_reports->save_match_report_meta(99);

        self::assertSame([], TestWpState::$updated_meta);
    }

    public function test_save_match_report_meta_updates_expected_fields_when_valid(): void
    {
        $_POST = [
            MatchReports::NONCE_METABOX => 'valid',
            'match_report_fixture_id' => ' 42 ',
            'match_report_score_for' => ' 33 ',
            'match_report_score_against' => ' 17 ',
            'match_report_gallery' => ' 15,16 ',
        ];

        $this->match_reports->save_match_report_meta(101);

        self::assertCount(4, TestWpState::$updated_meta);
        self::assertSame(
            [
                MatchReports::META_FIXTURE_ID,
                MatchReports::META_SCORE_FOR,
                MatchReports::META_SCORE_AGAINST,
                MatchReports::META_GALLERY,
            ],
            array_column(TestWpState::$updated_meta, 'meta_key')
        );
        self::assertSame(
            ['42', '33', '17', '15,16'],
            array_column(TestWpState::$updated_meta, 'meta_value')
        );
    }
}

