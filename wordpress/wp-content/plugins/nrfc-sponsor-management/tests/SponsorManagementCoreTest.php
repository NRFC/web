<?php

declare(strict_types=1);

namespace NRFC\SponsorManagement\Tests;

use PHPUnit\Framework\TestCase;
use Closure;
use ReflectionClass;
use SponsorManagement\SponsorManagement;
use SponsorManagement\SponsorTypeWidget;
use SponsorManagement\TestWpState;

final class SponsorManagementCoreTest extends TestCase
{
    private SponsorManagement $sponsor_management;
    private SponsorTypeWidget $widget;

    protected function setUp(): void
    {
        TestWpState::reset();
        $_POST = [];

        $sponsor_management_reflection = new ReflectionClass(SponsorManagement::class);
        $this->sponsor_management = $sponsor_management_reflection->newInstanceWithoutConstructor();

        $widget_reflection = new ReflectionClass(SponsorTypeWidget::class);
        $this->widget = $widget_reflection->newInstanceWithoutConstructor();
    }

    public function test_add_admin_columns_inserts_type_and_url_after_title(): void
    {
        $columns = [
            'cb' => '<input type="checkbox" />',
            'title' => 'Title',
            'date' => 'Date',
        ];

        $result = $this->sponsor_management->addAdminColumns($columns);

        self::assertSame(['cb', 'title', 'sponsor_type', 'sponsor_url', 'date'], array_keys($result));
        self::assertSame('Type', $result['sponsor_type']);
        self::assertSame('URL', $result['sponsor_url']);
    }

    public function test_widget_update_sanitizes_and_validates_settings(): void
    {
        $new_instance = [
            'title' => '  <b>Main Sponsors</b>  ',
            'type' => 'invalid-type',
            'show_titles' => '1',
            'columns' => '9',
        ];

        $result = $this->widget->update($new_instance, []);

        self::assertSame('Main Sponsors', $result['title']);
        self::assertSame('', $result['type']);
        self::assertSame(1, $result['show_titles']);
        self::assertSame(6, $result['columns']);
    }

    public function test_detect_delimiter_selects_expected_separator(): void
    {
        $detect_delimiter = Closure::bind(
            fn (string $line): string => $this->detectDelimiter($line),
            $this->sponsor_management,
            SponsorManagement::class
        );

        self::assertIsCallable($detect_delimiter);
        self::assertSame(',', $detect_delimiter("name,url,type\n"));
        self::assertSame(';', $detect_delimiter("name;url;type\n"));
        self::assertSame("\t", $detect_delimiter("name\turl\ttype\n"));
    }

    public function test_save_sponsor_meta_returns_early_when_nonce_invalid(): void
    {
        TestWpState::$nonce_valid = false;

        $_POST = [
            'sponsor_details_nonce' => 'invalid',
            'sponsor_url' => 'https://example.com',
            'sponsor_type' => 'gold',
        ];

        $this->sponsor_management->saveSponsorMeta(42);

        self::assertSame([], TestWpState::$updated_meta);
    }

    public function test_save_sponsor_meta_updates_url_and_type_when_valid(): void
    {
        $_POST = [
            'sponsor_details_nonce' => 'valid',
            'sponsor_url' => ' https://example.com/sponsor ',
            'sponsor_type' => ' silver ',
        ];

        $this->sponsor_management->saveSponsorMeta(77);

        self::assertCount(2, TestWpState::$updated_meta);
        self::assertSame(['_sponsor_url', '_sponsor_type'], array_column(TestWpState::$updated_meta, 'meta_key'));
        self::assertSame(['https://example.com/sponsor', 'silver'], array_column(TestWpState::$updated_meta, 'meta_value'));
    }
}


