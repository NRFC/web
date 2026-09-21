#!/bin/bash

# MySQL installation script for Linux/macOS
# Prints info message for Windows

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_message() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Detect operating system
detect_os() {
    case "$(uname -s)" in
        Darwin*)
            echo "macos"
            ;;
        Linux*)
            echo "linux"
            ;;
        CYGWIN*|MINGW*|MSYS*)
            echo "windows"
            ;;
        *)
            echo "unknown"
            ;;
    esac
}

# Check if MySQL is installed
check_mysql_installed() {
    print_message "$BLUE" "Checking for MySQL installation..."

    # Try multiple commands to check for MySQL
    if command -v mysql &> /dev/null; then
        MYSQL_VERSION=$(mysql --version 2>/dev/null | head -n1)
        print_message "$GREEN" "✓ MySQL is already installed: $MYSQL_VERSION"
        return 0
    elif command -v mysqld &> /dev/null; then
        MYSQL_VERSION=$(mysqld --version 2>/dev/null | head -n1)
        print_message "$GREEN" "✓ MySQL server is already installed: $MYSQL_VERSION"
        return 0
    elif [ -x "$(command -v mysql)" ]; then
        print_message "$GREEN" "✓ MySQL is already installed"
        return 0
    else
        print_message "$YELLOW" "✗ MySQL is not installed"
        return 1
    fi
}

# Install MySQL on macOS
install_macos() {
    print_message "$BLUE" "Installing MySQL on macOS..."

    # Check for Homebrew
    if ! command -v brew &> /dev/null; then
        print_message "$RED" "Homebrew is required but not installed."
        read -p "Install Homebrew? (y/n): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
        else
            print_message "$RED" "Cannot install MySQL without Homebrew."
            return 1
        fi
    fi

    # Install MySQL using Homebrew
    print_message "$BLUE" "Installing MySQL via Homebrew..."
    brew update
    brew install mysql

    # Start MySQL service
    print_message "$BLUE" "Starting MySQL service..."
    brew services start mysql

    # Secure installation (optional)
    print_message "$YELLOW" "MySQL has been installed. Consider running: mysql_secure_installation"

    return 0
}

# Install MySQL on Linux
install_linux() {
    print_message "$BLUE" "Installing MySQL on Linux..."

    # Detect Linux distribution
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        DISTRO=$ID
    else
        DISTRO=$(uname -s)
    fi

    case $DISTRO in
        ubuntu|debian)
            print_message "$BLUE" "Detected Ubuntu/Debian based system..."

            # Update package list
            sudo apt-get update

            # Install MySQL server
            sudo apt-get install -y mysql-server

            # Check if service needs to be started
            if command -v systemctl &> /dev/null; then
                sudo systemctl start mysql
                sudo systemctl enable mysql
            elif command -v service &> /dev/null; then
                sudo service mysql start
            fi
            ;;

        fedora|rhel|centos)
            print_message "$BLUE" "Detected RHEL/Fedora/CentOS based system..."

            # For RHEL/CentOS 7 and older
            if command -v yum &> /dev/null; then
                sudo yum install -y mysql-server
                sudo systemctl start mysqld
                sudo systemctl enable mysqld
            # For RHEL/CentOS 8+ and Fedora
            elif command -v dnf &> /dev/null; then
                sudo dnf install -y mysql-server
                sudo systemctl start mysqld
                sudo systemctl enable mysqld
            fi
            ;;

        arch|manjaro)
            print_message "$BLUE" "Detected Arch Linux based system..."
            sudo pacman -Syu --noconfirm
            sudo pacman -S --noconfirm mysql
            sudo systemctl start mysqld
            sudo systemctl enable mysqld
            ;;

        *)
            print_message "$RED" "Unsupported Linux distribution: $DISTRO"
            print_message "$YELLOW" "Please install MySQL manually for your distribution."
            return 1
            ;;
    esac

    # Get initial temporary password (for newer MySQL versions)
    if [ -f /var/log/mysqld.log ]; then
        TEMP_PASS=$(sudo grep 'temporary password' /var/log/mysqld.log | tail -1 | awk '{print $NF}')
        if [ ! -z "$TEMP_PASS" ]; then
            print_message "$YELLOW" "Temporary root password: $TEMP_PASS"
            print_message "$YELLOW" "Run 'sudo mysql_secure_installation' to secure your installation."
        fi
    fi

    return 0
}

# Handle Windows detection
handle_windows() {
    print_message "$YELLOW" "================================================================================"
    print_message "$YELLOW" "This script is designed for Linux and macOS systems."
    print_message "$YELLOW" ""
    print_message "$YELLOW" "For Windows, please install MySQL using one of these methods:"
    print_message "$YELLOW" ""
    print_message "$YELLOW" "1. Download MySQL Installer from: https://dev.mysql.com/downloads/installer/"
    print_message "$YELLOW" "2. Use Chocolatey package manager: choco install mysql"
    print_message "$YELLOW" "3. Use WSL (Windows Subsystem for Linux) and run this script inside WSL"
    print_message "$YELLOW" ""
    print_message "$YELLOW" "Alternatively, you can use MariaDB which is compatible with MySQL:"
    print_message "$YELLOW" "Download from: https://mariadb.org/download/"
    print_message "$YELLOW" "================================================================================"
    exit 0
}

# Main script execution
main() {
    OS=$(detect_os)

    case $OS in
        linux)
            if check_mysql_installed; then
                print_message "$GREEN" "MySQL is already installed."
            else
                read -p "Install MySQL? (y/n): " -n 1 -r
                echo
                if [[ $REPLY =~ ^[Yy]$ ]]; then
                    install_linux
                    if [ $? -eq 0 ]; then
                        print_message "$GREEN" "✓ MySQL installation completed successfully!"
                    else
                        print_message "$RED" "✗ MySQL installation failed."
                    fi
                else
                    print_message "$YELLOW" "MySQL installation skipped."
                fi
            fi
            ;;

        macos)
            if check_mysql_installed; then
                print_message "$GREEN" "MySQL is already installed."
            else
                read -p "Install MySQL? (y/n): " -n 1 -r
                echo
                if [[ $REPLY =~ ^[Yy]$ ]]; then
                    install_macos
                    if [ $? -eq 0 ]; then
                        print_message "$GREEN" "✓ MySQL installation completed successfully!"
                    else
                        print_message "$RED" "✗ MySQL installation failed."
                    fi
                else
                    print_message "$YELLOW" "MySQL installation skipped."
                fi
            fi
            ;;

        windows)
            handle_windows
            ;;

        unknown)
            print_message "$RED" "Unsupported operating system."
            print_message "$YELLOW" "This script supports Linux, macOS, and Windows (info only)."
            exit 1
            ;;
    esac

    # Final verification
    if command -v mysql &> /dev/null; then
        print_message "$GREEN" "✓ Verification: MySQL is now available in your PATH"
    elif [ "$OS" != "windows" ]; then
        print_message "$YELLOW" "Note: You may need to restart your terminal or log out and back in."
    fi
}

# Run main function
main