# NRFC WordPress Project - Developer Agents Guide

## Project Overview

This is a **Docker-based WordPress development environment** for the Norwich Rugby Football Club (NRFC) website. The project uses Docker Compose to orchestrate a PHP 8.4 Apache web server and MariaDB database, with automated syncing from a live development server.

### Key Technologies
- **PHP**: 8.4 with Apache
- **Database**: MariaDB (latest)
- **Container Orchestration**: Docker Compose
- **WordPress CLI**: For command-line management
- **Xdebug**: For debugging in development environment
- **OPCache**: For production optimization

---

## Architecture Overview

### Docker Services

#### 1. **nrfc-wp-dev-web** (Web Server)
- **Image**: Custom built from `Dockerfile` (PHP 8.4 + Apache)
- **Port**: 8015
- **Extensions Installed**: GD, MySQLi, PDO MySQL, ZIP, EXIF, Intl, BCMath, OPCache, SOAP
- **Build Target**: `dev` stage with Xdebug enabled
- **User Context**: Runs as non-root user with UID/GID matching host system
- **Mounts**:
  - `./wordpress:/var/www/html` - WordPress installation
  - `./wp:/usr/local/bin/wp` - WordPress CLI tool
  - `./docker/development/php.ini:/usr/local/etc/php/conf.d/custom.ini` - Custom PHP config
- **Environment Variables**: WordPress database credentials, debug logging
- **Debugging**: Xdebug configured on port 9003

#### 2. **nrfc-wp-dev-db** (Database)
- **Image**: MariaDB (latest)
- **Port**: 3336
- **Container Name**: nrfc-wp-dev-db
- **Initialization**: SQL dump loaded from `./initdb.d/dump.sql`
- **Restart Policy**: Always
- **Volumes**: Database data persisted in Docker volume

---

## Project Structure

```
web/
├── compose.yaml                  # Docker Compose configuration
├── Dockerfile                    # Multi-stage Docker build (dev/prod)
├── README.md                     # Quick start guide
├── AGENTS.md                     # This file
├── LICENSE                       # Project license
├── bin/
│   ├── stack.sh                 # Main orchestration script
│   ├── check-mysql.sh           # MySQL dependency checker
│   ├── generate-fixtures.sh     # Sample fixture data generator
│   └── transformFixtures.php    # PHP utility for fixture transformation
├── docker/
│   ├── development/
│   │   ├── php.ini              # Development PHP configuration
│   │   └── xdebug.ini           # Xdebug configuration
│   └── production/
│       ├── opcache.ini          # OPCache optimization
│       └── apache-security.conf # Production security headers
├── initdb.d/
│   └── dump.sql                 # Database initialization script
├── wordpress/                    # WordPress installation root
│   ├── wp-admin/                # WordPress admin interface
│   ├── wp-content/
│   │   ├── plugins/             # WordPress plugins (see below)
│   │   ├── themes/              # WordPress themes
│   │   ├── mu-plugins/          # Must-use plugins
│   │   └── uploads/             # User-uploaded files
│   ├── wp-includes/             # WordPress core files
│   └── [wp-*.php]              # WordPress core files
└── wp                           # WordPress CLI tool (downloaded at runtime)
```

---

## Custom Code & Plugins

### Custom Themes
- **nrfc/** - Custom NRFC theme
- **polestar/** - Third-party theme
- **vantage/** - Third-party theme

### Custom Plugins

#### 1. **nrfc-fixtures**
Purpose: Manages rugby fixture scheduling and display
- Defines custom post types for fixtures
- Integrates with the fixture database

#### 2. **nrfc-match-reports**
Purpose: Manages and displays match report content
- Custom post type for match reports
- Integration with fixture data

#### 3. **nrfc-auto-links**
Purpose: Automatic link generation and management
- Manages internal linking

#### 4. **nrfc-person-directory**
Purpose: Directory of club members/staff
- Custom post types for person entries
- Directory display functionality

#### 5. **nrfc-sponsor-management**
Purpose: Sponsor management system
- Sponsor database
- Sponsor display integrations

#### 6. **siteorigin-panels** & **so-css**, **so-widgets-bundle**
Purpose: Page builder framework
- Visual page building interface

#### 7. **akismet**
Purpose: Anti-spam protection
- Comment spam filtering

### Must-Use Plugins (mu-plugins/)
- **preserve-port.php** - Preserves non-standard ports (e.g., `:8015`) in WordPress URLs and redirects
  - Respects common proxy headers
  - Critical for development environment on non-standard port

---

## Development Workflow

### Quick Start

```bash
# Start the development environment (downloads WordPress, syncs DB/uploads)
./bin/stack.sh start

# Access WordPress at http://localhost:8015
# Credentials: admin user or test user (created by stack.sh)

# Stop the development environment
./bin/stack.sh stop

# Restart the environment
./bin/stack.sh restart

# Reset test user password
./bin/stack.sh reset-password [optional-password]
```

### The `stack.sh` Start Process

1. **Docker Check**: Verifies Docker is running
2. **WP CLI Tool**: Downloads WordPress CLI if not present
3. **WordPress Core**: Downloads WordPress if not already installed
4. **Environment Setup**: Creates `.env` with UID/GID for file permissions
5. **MySQL Check**: Ensures MySQL/MariaDB client is installed (interactive installer if needed)
6. **Database Sync**: 
   - SSH to `dev.norwichrugby.com`
   - Runs `/opt/docker/www-wp/backup.sh` and pipes output to `initdb.d/dump.sql`
7. **Media Sync**:
   - Uses rsync to pull `/opt/docker/www-wp/uploads` from live server
   - Overwrites `wordpress/wp-content/uploads`
8. **Container Startup**: Starts Docker Compose services
9. **Database Wait**: Waits 15 seconds for database initialization
10. **Domain Fix**: Updates WordPress options table to point to `http://localhost:8015`
11. **Test User**: Creates test user with admin privileges

### Syncing to Live Environment

The project includes sample rsync commands for pushing changes back to production:

```bash
# Sync plugins
rsync -a --progress --exclude=akismet --exclude=siteorigin-panels --exclude=so-* \
  wordpress/wp-content/plugins nrfc:/opt/docker/www-wp

# Sync custom theme
rsync -a --progress wordpress/wp-content/themes/nrfc nrfc:/opt/docker/www-wp/themes
```

---

## Debugging

### Xdebug Setup (IDE Integration)

The development container includes Xdebug configured on port 9003.

**In JetBrains IDE (PhpStorm, WebStorm, etc.):**

1. Go to Settings → Languages & Frameworks → PHP → Servers
2. Create a new server:
   - **Name**: localhost
   - **Host**: localhost
   - **Port**: 8015
   - **Debugger**: Xdebug
   - **Use path mappings**: Enable
     - Map `./wordpress` to `/var/www/html`
3. Set debug breakpoints in code
4. Enable debug listening in IDE
5. Access WordPress in browser or use WP CLI commands in container
6. IDE will break at breakpoints

### Viewing Logs

```bash
# PHP error logs
docker compose logs nrfc-wp-dev-web

# Database logs
docker compose logs nrfc-wp-dev-db

# Follow logs in real-time
docker compose logs -f nrfc-wp-dev-web
```

---

## Database Management

### Initial Database

The database is initialized from `initdb.d/dump.sql`, which is synced from the live server during `stack.sh start`.

### Managing Fixtures

Clear all fixture post types from the database:

**Development:**
```bash
docker compose exec nrfc-wp-dev-web bash -c 'wp post delete $(wp post list --post_type=fixture --field=ID) --force'
```

**Production:**
```bash
docker compose exec nrfc-wordpress bash -c 'wp post delete $(wp post list --post_type=fixture --field=ID) --force'
```

### Accessing Database Directly

```bash
# Via MySQL client
mysql -h 127.0.0.1 -P 3336 -u wordpress -pwordpress wordpress

# Via Docker container
docker compose exec nrfc-wp-dev-db mysql -u wordpress -pwordpress wordpress
```

### Database Credentials (Development)

- **User**: wordpress (default, configurable via `WORDPRESS_DB_USER`)
- **Password**: wordpress (default, configurable via `WORDPRESS_DB_PASSWORD`)
- **Database**: wordpress (default, configurable via `WORDPRESS_DB_NAME`)
- **Root Password**: changemenow (default, configurable via `MYSQL_ROOT_PASSWORD`)
- **Port**: 3336 (host) → 3306 (container)

---

## Generate Sample Fixture Data

The project includes a fixture generator script for testing:

```bash
./bin/generate-fixtures.sh
```

This generates `sample_fixtures.csv` with:
- Multiple rugby teams (1st XV, Lions, academies, youth, women, mini rugby)
- Match scheduling (Saturdays for senior teams, Sundays for others)
- Realistic opposing clubs
- Competition types (League, Cup, Friendly, Festival)
- Time slots and venue information

---

## Environment Configuration

### Build Arguments
- **PHP_VERSION**: Specified in `compose.yaml` as `8.4` (modifiable)

### Environment Variables (Configurable)

Create a `.env` file in the project root:

```bash
# WordPress Database
WORDPRESS_DB_USER=wordpress
WORDPRESS_DB_PASSWORD=wordpress
WORDPRESS_DB_NAME=wordpress

# MySQL Root
MYSQL_ROOT_PASSWORD=changemenow

# Docker User Settings (auto-set by stack.sh)
UID=1000
GID=1000
```

### PHP Configuration

**Development** (`docker/development/php.ini`):
- Error reporting: E_ALL
- Display errors: On
- Debug logging enabled

**Production** (`docker/production/opcache.ini`):
- Error reporting: E_ALL & ~E_DEPRECATED & ~E_STRICT
- Display errors: Off
- Log errors: On
- OPCache optimization enabled

---

## Port Mappings

| Service  | Container Port | Host Port | Purpose           |
|----------|----------------|-----------|-------------------|
| Apache   | 80             | 8015      | WordPress website |
| MariaDB  | 3306           | 3336      | Database access   |
| Xdebug   | 9003           | 9003      | IDE debugging     |

---

## File Permissions & Security

### Development
- Runs as non-root user with host UID/GID (preserves file ownership)
- Allows `.htaccess` overrides for WordPress permalinks
- `preserve-port.php` MU-plugin ensures port is preserved in URLs

### Production
- Runs as dedicated `www` user (UID 1001)
- `.htaccess` support for WordPress rewrite rules
- Security headers configured via `apache-security.conf`
- Health check enabled (checks HTTP endpoint every 30 seconds)

---

## Common Tasks

### SSH Access to Container

```bash
docker compose exec nrfc-wp-dev-web bash
```

### Running WP CLI Commands

```bash
# From host (WP CLI mounted at ./wp)
docker compose exec nrfc-wp-dev-web wp <command>

# Examples
docker compose exec nrfc-wp-dev-web wp plugin list
docker compose exec nrfc-wp-dev-web wp theme activate nrfc
docker compose exec nrfc-wp-dev-web wp post list --post_type=fixture
```

### Updating WordPress

```bash
docker compose exec nrfc-wp-dev-web wp core update
docker compose exec nrfc-wp-dev-web wp plugin update --all
```

### Clearing Object Cache (if configured)

```bash
docker compose exec nrfc-wp-dev-web wp cache flush
```

---

## Requirements

### System Requirements

**Linux/macOS:**
- Docker & Docker Compose
- PHP 8.3+ (for local WP CLI, only if not using Docker)
- MySQL/MariaDB client
- rsync (for syncing back to live server)
- SSH key access to `dev.norwichrugby.com`
- git

**Windows:**
- WSL (Windows Subsystem for Linux) recommended
- All the above tools within WSL
- Docker Desktop for Windows with WSL 2 backend

### External Dependencies

- **Live Server Access**: `dev.norwichrugby.com` must have `/opt/docker/www-wp/backup.sh` for database dumps
- **Live Server Access**: For rsync-based media synchronization

---

## Troubleshooting

### Docker Not Running
```bash
# Error: "Docker is not running"
# Start Docker daemon (varies by OS)
sudo systemctl start docker  # Linux
# or open Docker Desktop      # macOS/Windows
```

### MySQL Client Not Found
```bash
# The stack.sh script will offer to install MySQL client
# Manual installation:
sudo apt install mysql-client      # Ubuntu/Debian
brew install mysql-client          # macOS
```

### Cannot Connect to Live Server
```bash
# Error during database/media sync
# Check SSH key: keys must be in ~/.ssh/id_rsa or add via ssh-add
ssh dev.norwichrugby.com "ls /opt/docker/www-wp"
```

### Port Conflicts
```bash
# If ports 8015 or 3336 are in use:
# Edit compose.yaml to use different host ports
# Change: "8015:80" to "8016:80", etc.
```

### Database Not Initializing
```bash
# Force database recreation
docker compose down -v        # Remove volumes
docker compose up -d          # Restart with fresh volume
./bin/stack.sh start          # Re-run sync and setup
```

### File Permission Issues
```bash
# If you get permission errors on WordPress files:
# Restart stack to reset UID/GID
./bin/stack.sh restart
```

---

## Multi-Stage Build

The `Dockerfile` uses multi-stage builds:

- **base**: Common PHP 8.4 + Apache setup with all extensions
- **dev**: Development stage with Xdebug and verbose error reporting
- **production**: Production stage with OPCache and security hardening

Build for specific target:
```bash
docker build --target dev .
docker build --target production .
```

---

## Next Steps for Developers

1. **Clone/Navigate** to project directory
2. **Run** `./bin/stack.sh start` to initialize environment
3. **Access** WordPress at `http://localhost:8015`
4. **Configure** IDE debugger for Xdebug integration (port 9003)
5. **Review** custom plugins/themes in `wordpress/wp-content/`
6. **Set up** SSH access for live server sync
7. **Create** and test features using WP CLI and IDE
8. **Commit** changes to version control (WordPress core excluded)

---

## Additional Resources

- [WordPress CLI Documentation](https://make.wordpress.org/cli/handbook/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [PHP 8.4 Changes](https://www.php.net/manual/en/migration84.php)
- [Xdebug Configuration](https://xdebug.org/docs/step_debugger)
- [MariaDB Documentation](https://mariadb.com/documentation/)


