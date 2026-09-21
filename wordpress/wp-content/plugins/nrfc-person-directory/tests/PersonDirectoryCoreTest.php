<?php

declare(strict_types=1);

namespace NRFC\PersonDirectory\Tests;

use PersonDirectory\PersonDirectory;
use PersonDirectory\PersonListWidget;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PersonDirectoryCoreTest extends TestCase
{
    private PersonDirectory $person_directory;
    private PersonListWidget $widget;

    protected function setUp(): void
    {
        $person_directory_reflection = new ReflectionClass(PersonDirectory::class);
        $this->person_directory = $person_directory_reflection->newInstanceWithoutConstructor();

        $widget_reflection = new ReflectionClass(PersonListWidget::class);
        $this->widget = $widget_reflection->newInstanceWithoutConstructor();
    }

    public function test_sanitize_phone_strips_disallowed_characters_and_tags(): void
    {
        $raw = '<script>x</script> +44-(1603) 12A3@ ';

        $result = $this->person_directory->sanitize_phone($raw);

        self::assertSame('+44-(1603) 123', $result);
    }

    public function test_add_admin_columns_inserts_phone_and_email_after_title(): void
    {
        $columns = [
            'cb' => '<input type="checkbox" />',
            'title' => 'Title',
            'date' => 'Date',
        ];

        $result = $this->person_directory->add_admin_columns($columns);

        self::assertSame(['cb', 'title', 'person_phone', 'person_email', 'date'], array_keys($result));
        self::assertSame('Phone', $result['person_phone']);
        self::assertSame('Email', $result['person_email']);
    }

    public function test_widget_update_sanitizes_title_ids_and_labels(): void
    {
        $new_instance = [
            'title' => '  <h2>Club Staff</h2>  ',
            'ids' => '1, -2, abc, 07, 0, 3',
            'labels' => ' Coach | <b>Manager</b> | ',
        ];

        $result = $this->widget->update($new_instance, []);

        self::assertSame('Club Staff', $result['title']);
        self::assertSame('1,2,7,3', $result['ids']);
        self::assertSame('Coach|Manager|', $result['labels']);
    }
}

