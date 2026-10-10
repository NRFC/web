#!/bin/bash -e

SRC_DB_CONTAINER=nrfc-wp-db
UPLOADS_SRC_PATH=/opt/docker/www-wp/uploads
ENV_FILE=/opt/docker/www-wp/.env
TARGET_PATH=/opt/docker/staging
TARGET_STAGE=staging

function usage {
  printf 'Usage: %s [-e ENV_FILE] [-d SRC_DB_CONTAINER] [-u UPLOADS_SRC_PATH] [-t TARGET_PATH] [-s TARGET_STAGE] [-h]\n' "$0"
}

while getopts ":e:d:u:t:s:h" opt; do
  case "$opt" in
    e) ENV_FILE="$OPTARG" ;;
    d) SRC_DB_CONTAINER="$OPTARG" ;;
    u) UPLOADS_SRC_PATH="$OPTARG" ;;
    t) TARGET_PATH="$OPTARG" ;;
    s) TARGET_STAGE="$OPTARG" ;;
    h) usage; exit 0 ;;
    :)
      printf 'Option -%s requires an argument.\n' "$OPTARG" >&2
      usage >&2
      exit 1
      ;;
    \?)
      printf 'Unknown option: -%s\n' "$OPTARG" >&2
      usage >&2
      exit 1
      ;;
  esac
done
shift "$((OPTIND - 1))"

if (( $# > 0 )); then
  printf 'Unexpected argument: %s\n' "$1" >&2
  usage >&2
  exit 1
fi

if [ ! -d "$UPLOADS_SRC_PATH" ]; then
  printf "Uploads path (%s) does not exist or is not a directory.\n" "$UPLOADS_SRC_PATH" >&2
  exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
  printf "Env file (%s) does not exist or is not a directory.\n" "$ENV_FILE" >&2
  exit 1
fi

if [ "$(docker inspect -f '{{.State.Running}}' "$SRC_DB_CONTAINER" 2>/dev/null)" != "true" ]; then
  printf "DB Source container (%s) is not running.\n" "$SRC_DB_CONTAINER" >&2
  exit 1
fi

if [ ! -d "$TARGET_PATH" ]; then
  printf "Target path (%s) does not exist or is not a directory. We'll try and create it.\n" "$TARGET_PATH"
fi

printf "\nDeploying stage:  %s\n" "$TARGET_STAGE"
printf "========================================================================\n"
printf "Uploads path:     %s\n" "$UPLOADS_SRC_PATH"
printf "DB from:          %s\n" "$SRC_DB_CONTAINER"
printf "Environment from: %s\n" "$ENV_FILE"
printf "Deploying to:     %s\n" "$TARGET_PATH"
echo "------------------------------------------------------------------------"
printf "This script will ask for sudo escalation to chown the uploads folder to \nwww-data, if you refuse that the site will be read only and you will need to \nrun the following command to fix it:\n    chown -R 33:33 %s/uploads\n" "$TARGET_PATH"
echo "------------------------------------------------------------------------"
printf "\nProceed with deployment? (y/n)\n"
read -r response
if [[ ! $response =~ ^[Yy]$ ]]; then
  echo "Deployment aborted."
  exit 0
fi

source "$ENV_FILE"
mkdir -p "${TARGET_PATH}/initdb.d"
docker exec -t "${SRC_DB_CONTAINER}" mariadb-dump -u${WORDPRESS_DB_USER} -p${WORDPRESS_DB_PASSWORD} ${WORDPRESS_DB_NAME} > "${TARGET_PATH}/initdb.d/dump.sql"
sudo rsync -a "${UPLOADS_SRC_PATH}" "${TARGET_PATH}/uploads"

wget --quiet -O "${TARGET_PATH}/compose.yaml" "https://raw.githubusercontent.com/NRFC/web/refs/heads/main/docker/production/compose.${TARGET_STAGE}.yaml"
wget --quite -O "${TARGET_PATH}/.env"  "https://raw.githubusercontent.com/NRFC/web/refs/heads/main/.env.sample"
echo -e "\n" >> "${TARGET_PATH}/.env"
wget --quiet -O - https://raw.githubusercontent.com/NRFC/web/refs/heads/main/bin/generate-wp-keys.sh | bash >> "${TARGET_PATH}/.env"
# uploads must be writable by www-data inside the container
sudo chown -R 33:33 "${TARGET_PATH}/uploads"

#docker compose up
