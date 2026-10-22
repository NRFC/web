#!/bin/bash

# Default values
TARGET="prod"
OUT="$(dirname $0)/backups/dump-$(date +"%y-%m-%d").sql"

# Parse command line options
while getopts ":t:o:" opt; do
  case ${opt} in
    t)
      TARGET="$OPTARG"
      ;;
    o)
      OUT="$OPTARG"
      ;;
    \?)
      echo "Invalid option: -$OPTARG" >&2
      exit 1
      ;;
    :)
      echo "Option -$OPTARG requires an argument." >&2
      exit 1
      ;;
  esac
done
shift $((OPTIND -1))

# Setup working directory and environment
WORKING_DIR=$(realpath "$(dirname "$0")/${TARGET}")
if [ -f "$WORKING_DIR/.env" ]; then
  source "$WORKING_DIR/.env"
else
  echo "Error: .env file not found in $WORKING_DIR" >&2
  exit 1
fi

# DB container name
CONTAINER_NAME=$(grep -B 1 mariadb $WORKING_DIR/compose.yaml | head -n 1 | sed 's/^[[:space:]]*//; s/:[[:space:]]*$//')

# Build command
CMD="docker compose -f $WORKING_DIR/compose.yaml exec ${CONTAINER_NAME} mariadb-dump -u${WORDPRESS_DB_USER} -p${WORDPRESS_DB_PASSWORD} ${WORDPRESS_DB_NAME}"

# Execute and output
if [ "$OUT" == "-" ]; then
  $CMD | gzip -
else
  mkdir -p "$(dirname "$OUT")"
  $CMD > "$OUT"
  echo "Backup successfully saved to $OUT"
fi

