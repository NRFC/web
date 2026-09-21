<?php

declare(strict_types=1);

namespace NRFC\AutoLinks\Tests;

use NRFC_Auto_Links_Rule_Engine;
use PHPUnit\Framework\TestCase;

final class RuleEngineTest extends TestCase
{
    public function test_returns_unchanged_content_when_no_rules(): void
    {
        $content = 'Visit the clubhouse this weekend.';

        $result = NRFC_Auto_Links_Rule_Engine::apply_rules(
            $content,
            [],
            123,
            static fn ($page_id): string => 'https://example.com/page/' . $page_id,
            static fn (string $url): string => $url,
            static fn (string $text): string => $text
        );

        self::assertSame($content, $result);
    }

    public function test_replaces_matching_text_with_link(): void
    {
        $content = 'Read our Fixtures for this season.';
        $rules = [
            [
                'text' => 'Fixtures',
                'link' => 42,
                'target_pages' => [],
            ],
        ];

        $result = NRFC_Auto_Links_Rule_Engine::apply_rules(
            $content,
            $rules,
            101,
            static fn ($page_id): string => 'https://example.com/page/' . $page_id,
            static fn (string $url): string => $url,
            static fn (string $text): string => $text
        );

        self::assertSame(
            'Read our <a href="https://example.com/page/42">Fixtures</a> for this season.',
            $result
        );
    }

    public function test_does_not_replace_partial_word_matches(): void
    {
        $content = 'The fixturelist is now published.';
        $rules = [
            [
                'text' => 'fixture',
                'link' => 11,
                'target_pages' => [],
            ],
        ];

        $result = NRFC_Auto_Links_Rule_Engine::apply_rules(
            $content,
            $rules,
            202,
            static fn ($page_id): string => 'https://example.com/page/' . $page_id,
            static fn (string $url): string => $url,
            static fn (string $text): string => $text
        );

        self::assertSame($content, $result);
    }

    public function test_applies_target_page_filtering(): void
    {
        $content = 'Contact details are available here.';
        $rules = [
            [
                'text' => 'Contact',
                'link' => 13,
                'target_pages' => [5, 6],
            ],
        ];

        $result = NRFC_Auto_Links_Rule_Engine::apply_rules(
            $content,
            $rules,
            99,
            static fn ($page_id): string => 'https://example.com/page/' . $page_id,
            static fn (string $url): string => $url,
            static fn (string $text): string => $text
        );

        self::assertSame($content, $result);
    }

    public function test_skips_invalid_rules(): void
    {
        $content = 'Our sponsors support the club.';
        $rules = [
            [
                'text' => '',
                'link' => 2,
            ],
            [
                'text' => 'sponsors',
                'link' => '',
            ],
        ];

        $result = NRFC_Auto_Links_Rule_Engine::apply_rules(
            $content,
            $rules,
            300,
            static fn ($page_id): string => 'https://example.com/page/' . $page_id,
            static fn (string $url): string => $url,
            static fn (string $text): string => $text
        );

        self::assertSame($content, $result);
    }

    public function test_rules_from_settings_returns_normalized_rules(): void
    {
        $settings = [
            'rules' => [
                ['text' => 'News', 'link' => 7],
            ],
        ];

        $result = NRFC_Auto_Links_Rule_Engine::rules_from_settings($settings);

        self::assertSame($settings['rules'], $result);
    }
}

