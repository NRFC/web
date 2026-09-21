# NRFC Match Reports Plugin

This plugin adds a "Match Report" custom post type linked to fixtures.

## Unit Testing

A plugin-local PHPUnit setup is included for fast unit tests.

### Included files

- `composer.json`
- `phpunit.xml.dist`
- `tests/bootstrap.php`
- `tests/MatchReportsCoreTest.php`

### What is currently covered

- `MatchReports::add_admin_columns()`
- `LatestMatchReportsWidget::update()`
- `MatchReports::save_match_report_meta()`

### Run tests

From the plugin directory:

```bash
composer install
composer test
```

If Composer is not installed locally, run from repository root with Docker:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/app" -w /app/wordpress/wp-content/plugins/nrfc-match-reports composer:2 install --no-interaction --no-progress

docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/app" -w /app/wordpress/wp-content/plugins/nrfc-match-reports composer:2 composer test
```

