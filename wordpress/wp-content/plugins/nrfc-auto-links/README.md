# NRFC Auto Links - Unit Tests

This plugin now includes a PHPUnit unit test setup focused on the auto-link rule engine.

## What's Included

- `composer.json` with PHPUnit as a dev dependency
- `phpunit.xml.dist` test configuration
- `tests/bootstrap.php` bootstrap file
- `tests/RuleEngineTest.php` initial test coverage for rule application behavior
- `src/class-nrfc-auto-links.php` extracted, testable rule engine

## Run Tests

From the plugin directory:

```bash
composer install
composer test
```

If Composer is not available on your host, you can run it in Docker:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/app" -w /app/wordpress/wp-content/plugins/nrfc-auto-links composer:2 install

docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/app" -w /app/wordpress/wp-content/plugins/nrfc-auto-links composer:2 composer test
```

