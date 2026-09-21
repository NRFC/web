# NRFC Fixtures Plugin

This plugin adds a "Fixture" custom post type to manage rugby matches for the Norwich Rugby Football Club.

## Features

- **Fixture Custom Post Type**: Manage all match details in one place.
- **Custom Taxonomies**:
  - **Teams**: NRFC teams (e.g., 1st XV, Lions, etc.)
  - **Opposing Clubs**: The club being played against.
  - **Opposing Teams**: The specific team from the opposing club.
  - **Competition Types**: League, Cup, Friendly, etc.
- **Custom Metadata**:
  - Date
  - Kick Off Time
  - Venue (Home/Away/Specific Ground)

## Installation

1. Upload the `nrfc-fixtures` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

Once activated, a "Fixtures" menu item will appear in the WordPress admin sidebar. You can add new fixtures, categorize them by team and competition, and specify match details.

## Unit Testing

This plugin now includes a PHPUnit setup for unit tests.

### Included files

- `composer.json`
- `phpunit.xml.dist`
- `tests/bootstrap.php`
- `tests/FixturesPrivateMethodsTest.php`

### Run tests

From the `nrfc-fixtures` directory:

```bash
composer install
composer test
```

If Composer is not available on your host, you can run tests using Docker from the repository root:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/app" -w /app/wordpress/wp-content/plugins/nrfc-fixtures composer:2 install --no-interaction --no-progress

docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/app" -w /app/wordpress/wp-content/plugins/nrfc-fixtures composer:2 composer test
```

