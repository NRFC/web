# NRFC WordPress Project - Junie Developer Agent Guide

## Project Overview

This is a **Docker-based WordPress development environment** for the Norwich Rugby Football Club (NRFC) website. As Junie, you should use the provided tools to manage and interact with this environment.

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
- **User Context**: Runs as non-root user (UID/GID matching host system)
- **Mounts**:
  - `./wordpress:/var/www/html` - WordPress installation
  - `./wp:/usr/local/bin/wp` - WordPress CLI tool
- **Debugging**: Xdebug configured on port 9003

#### 2. **nrfc-wp-dev-db** (Database)
- **Image**: MariaDB (latest)
- **Port**: 3336
- **Container Name**: nrfc-wp-dev-db
- **Initialization**: SQL dump loaded from `./initdb.d/dump.sql`

---

## Project Structure

```
web/
├── compose.yaml                  # Docker Compose configuration
├── Dockerfile                    # Multi-stage Docker build (dev/prod)
├── README.md                     # Quick start guide
├── AGENTS.md                     # General developer agent guide
├── .junie/
│   └── AGENTS.md                 # Junie-specific instructions (this file)
├── bin/
│   ├── stack.sh                 # Main orchestration script
│   ├── check-mysql.sh           # MySQL dependency checker
│   ├── generate-fixtures.sh     # Sample fixture data generator
├── wordpress/                    # WordPress installation root
│   ├── wp-content/
│   │   ├── plugins/             # Custom and third-party plugins
│   │   ├── themes/              # Custom NRFC theme and others
│   │   └── mu-plugins/          # Must-use plugins (e.g., preserve-port.php)
└── wp                           # WordPress CLI tool
```

---

## Junie-Specific Workflow

### Managing the Stack

Use `bash` tool to run the orchestration script:

```bash
# Start the environment
./bin/stack.sh start

# Stop the environment
./bin/stack.sh stop

# Restart
./bin/stack.sh restart
```

### Running WP CLI Commands

Always run WP CLI commands through `docker compose exec` using the `bash` tool:

```bash
docker compose exec nrfc-wp-dev-web wp plugin list
docker compose exec nrfc-wp-dev-web wp post list --post_type=fixture
```

### Database Access

Access the database directly if needed:

```bash
docker compose exec nrfc-wp-dev-db mysql -u wordpress -pwordpress wordpress
```

### Custom Code Locations

When requested to modify custom features, look in:
- **Themes**: `wordpress/wp-content/themes/nrfc/`
- **Plugins**:
    - `wordpress/wp-content/plugins/nrfc-fixtures/`
    - `wordpress/wp-content/plugins/nrfc-match-reports/`
    - `wordpress/wp-content/plugins/nrfc-auto-links/`
    - `wordpress/wp-content/plugins/person-directory/`
    - `wordpress/wp-content/plugins/sponsor-management/`

### Debugging & Logs

View logs to troubleshoot issues:

```bash
docker compose logs nrfc-wp-dev-web
docker compose logs nrfc-wp-dev-db
```

---

## Critical Note: Port Preservation

The `wordpress/wp-content/mu-plugins/preserve-port.php` is critical for ensuring WordPress respects the `:8015` port in the development environment. Do not disable or modify this plugin unless specifically instructed.

## Database Fixtures

To generate sample data for testing:
```bash
./bin/generate-fixtures.sh
```

To clear fixtures:
```bash
docker compose exec nrfc-wp-dev-web bash -c 'wp post delete $(wp post list --post_type=fixture --field=ID) --force'
```
