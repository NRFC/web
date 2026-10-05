#!/usr/bin/env bash
# Prints freshly generated WordPress keys/salts as .env lines.
# Usage: ./bin/generate-wp-keys.sh >> .env
set -eu

for name in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
	# Alphanumeric only so values are safe in .env files and compose interpolation.
	value="$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 64)"
	echo "WORDPRESS_${name}=${value}"
done
