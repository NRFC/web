<?php

declare(strict_types=1);

/**
 * Core rule engine for the NRFC Auto Links plugin.
 */
final class NRFC_Auto_Links_Rule_Engine
{
    /**
     * Extract normalized rules from saved plugin settings.
     *
     * @param array<string, mixed> $settings Plugin settings array.
     * @return array<int, array<string, mixed>>
     */
    public static function rules_from_settings(array $settings): array
    {
        $rules = $settings['rules'] ?? [];

        return is_array($rules) ? $rules : [];
    }

    /**
     * Apply configured auto-link rules to a content string.
     *
     * @param string $content Raw post content.
     * @param array<int, array<string, mixed>> $rules Auto-link rules.
     * @param int $current_page_id Current page/post ID.
     * @param callable $permalink_resolver Receives rule link ID and returns URL.
     * @param callable $escape_url URL escaping callback.
     * @param callable $escape_text Text escaping callback.
     */
    public static function apply_rules(
        string $content,
        array $rules,
        int $current_page_id,
        callable $permalink_resolver,
        callable $escape_url,
        callable $escape_text
    ): string {
        if ($content == '' || $rules === []) {
            return $content;
        }

        foreach ($rules as $rule) {
            if (
                ! is_array($rule)
                || empty($rule['text'])
                || empty($rule['link'])
            ) {
                continue;
            }

            $target_pages = isset($rule['target_pages']) ? (array) $rule['target_pages'] : [];
            if ($target_pages !== [] && ! in_array($current_page_id, $target_pages)) {
                continue;
            }

            $pattern = '/\b' . preg_quote((string) $rule['text'], '/') . '\b/u';
            $link_url = (string) $permalink_resolver($rule['link']);
            $replacement = sprintf(
                '<a href="%s">%s</a>',
                (string) $escape_url($link_url),
                (string) $escape_text((string) $rule['text'])
            );

            $content = (string) preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }
}

