# General Developer Instructions - NRFC WordPress Project

## Overview

This document provides general guidelines for developers working on the NRFC WordPress project. For coding-specific instructions for GitHub Copilot, see [copilot-instructions.md](./copilot-instructions.md).

## Quick Reference

### Getting Started
```bash
# Start development environment
./bin/stack.sh start

# Access WordPress
# URL: http://localhost:8015
# Default user: test (password generated during startup)

# Stop development environment
./bin/stack.sh stop

# Restart environment
./bin/stack.sh restart
```

### Database Access
```bash
# MySQL Client
mysql -h 127.0.0.1 -P 3336 -u wordpress -pwordpress wordpress

# Docker Container
docker compose exec nrfc-wp-dev-db mysql -u wordpress -pwordpress wordpress

# WordPress CLI (in container)
docker compose exec nrfc-wp-dev-web wp [command]
```

## Development Environment

### Services
- **WordPress**: http://localhost:8015
- **Database**: localhost:3336 (MySQL/MariaDB)
- **Debugging**: Port 9003 (Xdebug)

### Key Features
- **Automatic Sync**: Database and media files synced from live environment
- **Hot Reload**: Code changes immediately reflected in browser
- **Debugging**: Xdebug integration for step-through debugging
- **WP CLI**: Full WordPress command-line interface available
- **Port Preservation**: `:8015` preserved in all WordPress URLs via `preserve-port.php`

## Project Structure

```
web/
├── AGENTS.md                     # Comprehensive project documentation
├── .github/
│   ├── copilot-instructions.md  # GitHub Copilot code generation guidelines
│   └── instructions.md           # This file
├── bin/
│   ├── stack.sh                 # Main orchestration script
│   ├── check-mysql.sh           # MySQL dependency installer
│   └── generate-fixtures.sh     # Fixture data generator
├── docker/
│   ├── development/             # Dev-specific configs (PHP, Xdebug)
│   └── production/              # Prod-specific configs (OPCache, security)
├── wordpress/
│   ├── wp-content/
│   │   ├── plugins/             # WordPress plugins
│   │   ├── themes/              # WordPress themes
│   │   ├── mu-plugins/          # Must-use plugins (always loaded)
│   │   └── uploads/             # Media files
│   └── [WordPress core files]
└── compose.yaml                 # Docker Compose configuration
```

## Development Workflow

### 1. Initial Setup
```bash
# Clone repository (if not already done)
git clone [repo-url]
cd web

# Start environment (downloads WP, syncs DB/media from live)
./bin/stack.sh start
```

### 2. Working on Code

#### For Plugins
```bash
wordpress/wp-content/plugins/nrfc-[feature]/
├── nrfc-[feature].php       # Main file with header
├── includes/                # Classes and helper functions
├── admin/                   # Admin interface code
└── README.md                # Documentation
```

#### For Theme
```bash
wordpress/wp-content/themes/nrfc/
├── functions.php            # Hook registration
├── templates/               # Template files
├── assets/                  # CSS, JS, images
└── README.md               # Documentation
```

### 3. Testing Changes

```bash
# SSH into container
docker compose exec nrfc-wp-dev-web bash

# Test with WP CLI
wp plugin list
wp theme list
wp post list --post_type=fixture

# View logs
docker compose logs -f nrfc-wp-dev-web
```

### 4. Syncing to Production

```bash
# Sync plugins (excluding third-party)
rsync -a --progress --exclude=akismet --exclude=siteorigin-panels --exclude=so-* \
  wordpress/wp-content/plugins nrfc:/opt/docker/www-wp

# Sync custom theme
rsync -a --progress wordpress/wp-content/themes/nrfc \
  nrfc:/opt/docker/www-wp/themes
```

## Important Notes

### preserve-port.php
The critical MU-plugin that preserves the development port (`:8015`) in all WordPress URLs.
- **Location**: `wordpress/wp-content/mu-plugins/preserve-port.php`
- **DO NOT modify** without extensive testing
- **Impact**: Without this, WordPress will redirect to http://localhost without the port

### Third-Party Plugins (Do NOT Sync)
These should NEVER be synced to production:
- `akismet` - Separately licensed
- `siteorigin-panels` - Page builder (use locally only)
- `so-css` - SiteOrigin CSS support
- `so-widgets-bundle` - SiteOrigin widgets

### Custom Plugins (ALWAYS Sync)
These are developed in-house and should be kept in sync:
- `nrfc-fixtures` - Fixture management
- `nrfc-match-reports` - Match report management
- `nrfc-auto-links` - Auto-linking
- `person-directory` - Staff/member directory
- `sponsor-management` - Sponsor database

## Database Management

### Initial Data
Database is automatically synced from `dev.norwichrugby.com` during `./bin/stack.sh start`

### Reset Database
```bash
# Force full reset
docker compose down -v                    # Remove volumes
docker compose up -d                      # Restart fresh
./bin/stack.sh start                      # Run full setup again
```

### Fixture Management
```bash
# View all fixtures
wp post list --post_type=fixture

# Clear all fixtures
wp post delete $(wp post list --post_type=fixture --field=ID) --force

# Generate sample fixtures
./bin/generate-fixtures.sh
```

## Debugging

### IDE Setup (JetBrains PhpStorm/WebStorm)

1. **Configure Server**
   - Go to: Settings → Languages & Frameworks → PHP → Servers
   - Create server "localhost"
   - Host: `localhost`
   - Port: `8015`
   - Debugger: `Xdebug`
   - Enable path mappings
   - Map: `./wordpress` → `/var/www/html`

2. **Set Breakpoints**
   - Click on line number to set breakpoint
   - Choose "Run → Debug" or press Ctrl+D

3. **Trigger Debugging**
   - Open WordPress in browser
   - IDE will pause at breakpoints
   - Use debugger console to inspect variables

### Viewing Logs

```bash
# Real-time PHP logs
docker compose logs -f nrfc-wp-dev-web

# Database logs
docker compose logs -f nrfc-wp-dev-db

# Historical logs (last 100 lines)
docker compose logs --tail=100 nrfc-wp-dev-web
```

## File Permissions

### Development
- Files run as current user (UID/GID set in `.env` during stack.sh start)
- Preserves your file ownership
- Allows `.htaccess` overrides for WordPress permalinks

### Production
- Files run as `www` user (UID 1001)
- Read-only for world (more secure)
- OPCache enabled for performance

## Common Issues & Solutions

### Port Already in Use
```bash
# Edit compose.yaml
# Change "8015:80" to "8016:80" (or use different port)
# Restart: docker compose up -d
```

### MySQL Connection Refused
```bash
# Ensure MySQL client is installed
./bin/check-mysql.sh

# Or manually:
sudo apt install mysql-client      # Ubuntu/Debian
brew install mysql-client          # macOS
```

### SSH Key Issues
```bash
# Generate if missing
ssh-keygen -t ed25519 -f ~/.ssh/id_rsa

# Add to agent
ssh-add ~/.ssh/id_rsa

# Test connection
ssh dev.norwichrugby.com "ls /opt/docker/www-wp"
```

### Can't Connect to Live Server
- Verify SSH key in `~/.ssh/id_rsa` or run `ssh-add`
- Check SSH config for correct host alias
- Verify network connectivity to `dev.norwichrugby.com`

### WordPress Redirecting to Wrong Port
- Check `preserve-port.php` is present
- Verify database `wp_options` has correct `home` and `siteurl`
- Run: `mysql -h 127.0.0.1 -P 3336 -u wordpress -pwordpress wordpress -e "SELECT * FROM wp_options WHERE option_name IN ('home', 'siteurl');"`

## Docker Commands

### Container Management
```bash
# View container status
docker compose ps

# Restart services
docker compose restart

# Stop services
docker compose down

# Remove volumes (full reset)
docker compose down -v

# Rebuild images
docker compose build --no-cache
```

### Development Container Access
```bash
# Bash shell in web container
docker compose exec nrfc-wp-dev-web bash

# Run command in web container
docker compose exec nrfc-wp-dev-web wp [command]

# Access database container
docker compose exec nrfc-wp-dev-db mysql -u wordpress -pwordpress wordpress
```

## Best Practices

### Code Changes
- Always create feature branches
- Test changes thoroughly in development
- Use WP CLI to verify post types, hooks, etc.
- Check error logs before committing

### Plugin Development
- Use namespacing with plugin name
- Implement proper sanitization/escaping
- Use nonces for forms
- Check user capabilities
- Document with PHPDoc blocks

### Testing
- Enable `WP_DEBUG` (already enabled in dev)
- Check `wp-debug.log` for errors
- Test WP CLI commands
- Verify admin interface changes
- Check frontend display

### Commits
- Exclude WordPress core files
- Include only custom plugins/themes
- Use `.gitignore` appropriately
- Write clear commit messages

## Helpful Commands

```bash
# Reset test user password
./bin/stack.sh reset-password [optional-password]

# Generate sample fixture data
./bin/generate-fixtures.sh

# WP core update
docker compose exec nrfc-wp-dev-web wp core update

# Update all plugins
docker compose exec nrfc-wp-dev-web wp plugin update --all

# Search WordPress documentation
docker compose exec nrfc-wp-dev-web wp help [command]

# List installed plugins with status
docker compose exec nrfc-wp-dev-web wp plugin list
```

## Resources

- **[AGENTS.md](../../AGENTS.md)** - Complete project documentation
- **[copilot-instructions.md](./copilot-instructions.md)** - Copilot coding guidelines
- **[WordPress CLI Handbook](https://make.wordpress.org/cli/handbook/)**
- **[WordPress Plugin Development](https://developer.wordpress.org/plugins/)**
- **[WordPress Theme Development](https://developer.wordpress.org/themes/)**
- **[Docker Compose Documentation](https://docs.docker.com/compose/)**
- **[Xdebug Documentation](https://xdebug.org/docs/)**

## Support & Questions

For issues or questions:
1. Check [AGENTS.md](../../AGENTS.md) troubleshooting section
2. Review error logs: `docker compose logs -f`
3. Test database connectivity directly
4. Verify SSH key setup for live server access
5. Contact project maintainers


