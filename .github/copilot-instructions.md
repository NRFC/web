# GitHub Copilot Instructions - NRFC WordPress Project

## Project Context

This is a Docker-based WordPress development environment for Norwich Rugby Football Club (NRFC). The project uses:
- **PHP 8.4** with Apache in Docker
- **MariaDB** for database
- **WordPress CLI** for management
- **Xdebug** for development debugging
- Custom plugins for fixtures, match reports, person directory, and sponsor management

**Key Infrastructure:**
- Development runs on `http://localhost:8015`
- Database accessible on `localhost:3336`
- `./bin/stack.sh start` orchestrates the full setup
- Remote sync from `dev.norwichrugby.com` via SSH

## Code Style & Conventions

### PHP
- **Version**: PHP 8.4 (use modern syntax features)
- **PSR Standards**: Follow PSR-12 for code formatting
- **WordPress Coding Standards**: Follow WP standards for hooks, actions, and filters
- **Naming**:
  - Classes: `PascalCase` (e.g., `FixtureManager`)
  - Functions: `snake_case` (e.g., `get_fixture_details()`)
  - Classes in plugins: Namespace with plugin name (e.g., `NRFC\Fixtures\FixtureManager`)
  - Variables: `$snake_case`
  - Constants: `CONSTANT_CASE`
- **No trailing commas** in function parameters (PHP 8.4 supports them, but keep consistent with codebase)
- **Type hints**: Always use strict types for new code - `declare(strict_types=1);`
- **Return types**: Always specify return types for functions

### WordPress-Specific
- Use `wp_*` prefixes for custom capabilities, post types, taxonomies
- Hooks: Use `do_action()` and `apply_filters()` with descriptive names
- Sanitization: Always sanitize user input with `sanitize_*()` and `esc_*()` for output
- Database queries: Use `$wpdb->prepare()` for parameterized queries
- Nonces: Include nonces for any form submissions or AJAX requests

### JavaScript (if applicable)
- Use modern ES6+ syntax
- Prefer vanilla JS over jQuery in new code
- Follow WordPress JS standards

## File Organization

### Plugin Structure
```
plugins/nrfc-[feature]/
├── nrfc-[feature].php       # Main plugin file with header comment
├── includes/
│   ├── class-[feature].php
│   └── functions.php
├── admin/
│   ├── class-admin.php
│   └── settings-page.php
└── README.md
```

### Theme Structure
```
themes/nrfc/
├── style.css               # Theme header
├── functions.php
├── templates/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
└── README.md
```

## When Generating Code

### Always Include

1. **Plugin/Theme Header Comment**
   - Plugin Name
   - Description
   - Version
   - Author
   - License (typically GPL v2 or later for WordPress)
   - Text Domain for translations

2. **Security**
   - Nonce verification for forms/AJAX
   - User capability checks with `current_user_can()`
   - Input sanitization with `sanitize_*()` functions
   - Output escaping with `esc_html()`, `esc_attr()`, etc.

3. **Documentation**
   - PHPDoc blocks for classes and functions
   - Explain complex logic with comments
   - Include usage examples in README files

4. **Error Handling**
   - Check `wp_*()` return values
   - Use `is_wp_error()` where applicable
   - Provide user-friendly error messages

### Avoid

- Hardcoded URLs (use `admin_url()`, `home_url()`, etc.)
- Direct database queries without `$wpdb->prepare()`
- Direct `$_GET`, `$_POST` access (use `sanitize_*()`)
- Inline styles or scripts (use proper enqueue)
- Global variables (use classes and dependency injection)
- Pre-existing plugins in exclusion list: akismet, siteorigin-panels, so-css, so-widgets-bundle

## Custom Plugins (Maintain Existing)

### nrfc-fixtures
- Custom post type for rugby fixtures
- Handles match scheduling and display
- Integration with team data

### nrfc-match-reports
- Custom post type for match reports
- Connects to fixture data

### nrfc-auto-links
- Automatic internal link management

### person-directory
- Personnel/staff directory
- Custom post types for people

### sponsor-management
- Sponsor database and display

## Must-Use Plugin

### preserve-port.php
**Critical for development** - Ensures non-standard ports (`:8015`) are preserved in WordPress URLs
- Do NOT modify without testing on development environment
- Respects proxy headers for live environments

## Development Tasks

### Database Operations
```bash
# Access database
mysql -h 127.0.0.1 -P 3336 -u wordpress -pwordpress wordpress

# View fixtures
wp post list --post_type=fixture

# Clear fixtures
wp post delete $(wp post list --post_type=fixture --field=ID) --force
```

### WordPress CLI Usage
- Use for testing custom post types
- Test plugin functionality
- Verify hooks and filters are firing

### Debugging with Xdebug
- Port: 9003
- Configure IDE path mapping: `./wordpress` → `/var/www/html`
- Set breakpoints for interactive debugging

## Common Patterns

### Custom Post Type Registration
```php
register_post_type( 'fixture', [
    'labels'       => [ 'name' => 'Fixtures' ],
    'public'       => true,
    'hierarchical' => false,
    'supports'     => [ 'title', 'editor', 'custom-fields' ],
    'show_ui'      => true,
    'has_archive'  => true,
    'rewrite'      => [ 'slug' => 'fixtures' ],
] );
```

### Custom Taxonomy Registration
```php
register_taxonomy( 'fixture_category', 'fixture', [
    'labels'       => [ 'name' => 'Categories' ],
    'hierarchical' => true,
    'show_ui'      => true,
] );
```

### Action/Filter Hooks
```php
// Action hook with proper priority
add_action( 'init', 'nrfc_register_fixtures', 10, 0 );

// Filter hook with data modification
add_filter( 'the_content', 'nrfc_enhance_fixture_display', 10, 1 );
```

### Custom Settings Page
```php
add_action( 'admin_menu', function() {
    add_menu_page(
        'NRFC Settings',
        'NRFC Settings',
        'manage_options',
        'nrfc-settings',
        'nrfc_settings_page'
    );
} );
```

## Testing Considerations

- Test with `wp-debug.log` enabled in development
- Verify custom post types appear in admin
- Test WP CLI commands for CRUD operations
- Check `.htaccess` rewrites work correctly
- Verify port preservation with `preserve-port.php`

## Environment Variables

Available in Docker container (set in `compose.yaml`):
```
WORDPRESS_DB_HOST=nrfc-wp-dev-db
WORDPRESS_DB_USER=<from .env>
WORDPRESS_DB_PASSWORD=<from .env>
WORDPRESS_DB_NAME=wordpress
WORDPRESS_DEBUG=true
WP_DEBUG_LOG=/dev/stderr
```

## Performance Considerations

- Use OPCache in production (enabled in `docker/production/opcache.ini`)
- Minimize database queries in loops
- Use WordPress transients for caching
- Lazy load heavy assets
- Test with debug logging enabled

## Security Checklist

- [ ] All user input sanitized
- [ ] All output escaped
- [ ] Nonces verified for forms/AJAX
- [ ] User capabilities checked with `current_user_can()`
- [ ] No direct `$_GET/$_POST` access
- [ ] No hardcoded sensitive data
- [ ] SQL queries parameterized with `$wpdb->prepare()`

## Resources

- [AGENTS.md](../../AGENTS.md) - Full project documentation
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Theme Handbook](https://developer.wordpress.org/themes/)
- [PHP 8.4 Features](https://www.php.net/manual/en/migration84.php)

