#!/usr/bin/env bash
# Checks the WordPress auth keys/salts before handing off to the upstream entrypoint.
#
# Each key can be supplied as WORDPRESS_<NAME> or, for Docker secrets, as
# WORDPRESS_<NAME>_FILE pointing at a file containing the value.
# Missing keys are randomised per container by the upstream entrypoint, which
# logs every user out on redeploy. Set NRFC_REQUIRE_KEYS=true to refuse to start instead.
set -Eeuo pipefail

missing=()
for name in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
	var="WORDPRESS_${name}"
	file_var="${var}_FILE"
	if [ -n "${!file_var:-}" ]; then
		if [ ! -r "${!file_var}" ]; then
			echo >&2 "error: ${file_var} points at '${!file_var}', which is not readable"
			exit 1
		fi
	elif [ -z "${!var:-}" ]; then
		missing+=("$var")
	fi
done

if [ "${#missing[@]}" -gt 0 ]; then
	if [ "${NRFC_REQUIRE_KEYS:-false}" = "true" ]; then
		echo >&2 "error: missing WordPress keys/salts: ${missing[*]}"
		exit 1
	fi
	echo >&2 "warning: missing WordPress keys/salts (${missing[*]}); random values will be used and sessions will not survive a redeploy"
fi

if [ "${NEW_BASE_URL}" != 'false' ]; then
  /usr/local/bin/wp search-replace "${EXISTING_BASE_URL}" "${NEW_BASE_URL}"
fi

exec docker-entrypoint.sh "$@"
