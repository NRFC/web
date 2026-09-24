#!/bin/bash

SCRIPT_NAME=$(basename "$0")
if command -v realpath &> /dev/null; then
    PROJECT_ROOT=$(realpath "$(dirname "$0")/..")
else
    # macOS fallback
    PROJECT_ROOT=$(cd "$(dirname "$0")/.." && pwd)
fi
COMMAND=$1

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if Docker is running
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        print_error "Docker is not running. Please install & start Docker first."
        exit 1
    fi
    print_status "Docker is running"
}

# Function to start the development environment
start_environment() {
    print_status "Starting development environment..."

    # Check if WP CLI tool exists
    if [ ! -f "wp" ]; then
        print_status "Downloading WP CLI tool..."
        curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x ${PROJECT_ROOT}/wp-cli.phar || {
            print_error "Failed to download WP CLI tool"
            return 1
        }
        mv wp-cli.phar wp
    else
        print_status "WP CLI tool already exists"
    fi

    # Check if WordPress already exists
    if [ ! -d "wordpress" ] || [ ! -d "wordpress/wp-admin" ]; then
        print_status "Downloading WordPress..."
        cd "$PROJECT_ROOT/wordpress" && "$PROJECT_ROOT/wp" core download && cd - || {
            print_error "Failed to download WordPress"
            return 1
        }
    else
        print_status "WordPress already exists, skipping download"
    fi

    # Set UID and GID
    print_status "Setting UID and GID..."
    echo -e "UID=$(id -u)\nGID=$(id -g)\n" > .env

    # outsource this to it's own script
    source $(dirname $0)/check-mysql.sh

    print_status "Grabbing SQL from live environment..."
    mkdir -p "$PROJECT_ROOT/initdb.d"
    ssh dev.norwichrugby.com /opt/docker/www-wp/backup.sh - | gzip -d > "$PROJECT_ROOT/initdb.d/dump.sql" || {
        print_error "Failed to get SQL dump from live environment. Check you have ssh key access."
        return 1
    }

    print_status "Grabbing media from live environment..."
    rsync -a --progress dev.norwichrugby.com:/opt/docker/www-wp/uploads "$PROJECT_ROOT/wordpress/wp-content/" || {
        print_error "Failed to get media from live environment. Check you have ssh key access."
        return 1
    }

    # Start Docker containers
    docker compose up -d || {
        print_error "Failed to start Docker containers"
        return 1
    }

    # Wait for MySQL to be ready
    print_status "Waiting for database to be ready..."
    sleep 15

    # Fix domain names
    print_status "Fixing domain names..."
    mysql -h 127.0.0.1 -P 3336 -u wordpress -pwordpress wordpress -e "UPDATE wp_options SET option_value = 'http://localhost:8015' WHERE option_name IN ('home', 'siteurl');" || {
        print_error "Failed to update domain names in database"
        return 1
    }

    # Add test user
    print_status "Adding test user..."
    reset_test_password

    print_status "Development environment started successfully"
    print_status "WordPress should be available at: http://localhost:8015"
}

reset_test_password() {
  if [ -z "$1" ]; then
    password=$(</dev/urandom tr -dc 'A-Za-z0-9' | head -c8)
  else
    password="$1"
  fi
  docker compose exec nrfc-wp-dev-web wp user create test test@example.com --role=administrator --user_pass=${password} || {
      print_warning "Failed to create test user (might already exist), trying a password update"
      docker compose exec nrfc-wp-dev-web wp user update test test@example.com --role=administrator --user_pass=${password} || {
          print_warning "Failed to update test user!"
      }
  }
  print_status "Test user: test / ${password}"
}

# Function to stop the development environment
stop_environment() {
    print_status "Stopping development environment..."
    docker compose down || {
        print_error "Failed to stop Docker containers"
        return 1
    }
    print_status "Development environment stopped"
}

# Function to restart the development environment
restart_environment() {
    print_status "Restarting development environment..."
    stop_environment
    start_environment
}

# Function to display usage
usage() {
    echo "Usage: $SCRIPT_NAME {start|stop|restart}"
    echo ""
    echo "Commands:"
    echo "  start                     - Start the development environment"
    echo "  stop                      - Stop the development environment"
    echo "  restart                   - Restart the development environment"
    echo "  reset-password [password] - Reset password for the user test"
    echo ""
    exit 1
}

# Main script logic
case "$COMMAND" in
    start)
        check_docker
        start_environment
        ;;
    stop)
        check_docker
        stop_environment
        ;;
    restart)
        check_docker
        restart_environment
        ;;
    reset-password)
        reset_test_password "$2"
        ;;
    *)
        usage
        ;;
esac

# Exit with appropriate code
if [ $? -eq 0 ]; then
    print_status "Command '$COMMAND' completed successfully"
else
    print_error "Command '$COMMAND' failed"
    exit 1
fi