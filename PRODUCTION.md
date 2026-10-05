#  Production

## Building

### GitHub Actions

The `Build production images` workflow builds and pushes the production image (`ghcr.io/nrfc/wp-prod`) to GitHub Container Registry on manual dispatch and on pushes to `main`.

Images are tagged with the commit SHA. Builds from `main` are also tagged as `latest`, which is what `docker/production/compose.production.yaml` uses by default.

### Assumptions

 * Have docker cli or docker desktop installed and running.
 * You are part of the nrfc organization on GitHub and have access to the GitHub Container Registry.
 * You have logged into the GitHub Container Registry with `docker login ghcr.io`.
   * You have a token, https://github.com/settings/tokens?utm_source=gemini
   * You have logged in: `echo "YOUR_GITHUB_PAT" | docker login ghcr.io -u YOUR_GITHUB_USERNAME --password-stdin`

Run these from the project root directory.

```bash
docker build -t ghcr.io/nrfc/wp-prod -f docker/production/Dockerfile .
docker push ghcr.io/nrfc/wp-prod
```

This builds and pushes the production image to GitHub Container Registry.

## Staging

You can stage the site anywhere. Grab a DB dump from production and copy of the media files.

You will need to grab the live files. Either scp them down top your machine or if you are on the live server:

```bash
mkdir -p initdb.d
/opt/docker/www-wp/backup.sh - | gzip -d > initdb.d/dump.sql
cp -r /opt/docker/www-wp/uploads .
```
Grab the docker files:

```bash
wget -O compose.yaml https://raw.githubusercontent.com/NRFC/web/refs/heads/main/docker/production/compose.production.yaml
wget -O .env https://raw.githubusercontent.com/NRFC/web/refs/heads/main/.env.example
# edit the env file as needed, for staging you'll need to change the WORDPRESS_PORT
# generate the WordPress keys/salts (keep them stable between deploys)
wget -O - https://raw.githubusercontent.com/NRFC/web/refs/heads/main/bin/generate-wp-keys.sh | bash >> .env
# uploads must be writable by www-data inside the container
sudo chown -R 33:33 uploads
docker compose up
```

## Single Apache image

`docker/production/Dockerfile` builds a self-contained image based on `wordpress:7.1.2-php8.4-apache`
containing WordPress core, the themes, plugins (with production Composer autoloaders), mu-plugins and
production PHP/Apache config. Code is served from `/var/www/nrfc` and is read-only to Apache.

```bash
docker build -f docker/production/Dockerfile -t ghcr.io/nrfc/wp-prod .

docker run -d -p 8015:80 \
  -e WORDPRESS_DB_HOST=db.example:3306 \
  -e WORDPRESS_DB_USER=wordpress \
  -e WORDPRESS_DB_PASSWORD=secret \
  -e WORDPRESS_DB_NAME=wordpress \
  -v /opt/docker/www-wp/uploads:/var/www/nrfc/wp-content/uploads \
  ghcr.io/nrfc/wp-prod
```

* The uploads directory must be writable by `www-data` (UID 33).
* Set `WORDPRESS_AUTH_KEY`, `WORDPRESS_SECURE_AUTH_KEY`, `WORDPRESS_LOGGED_IN_KEY`, `WORDPRESS_NONCE_KEY`
  and the matching `*_SALT` variables (generate with `./bin/generate-wp-keys.sh`). Each can instead be
  supplied as a Docker secret via `WORDPRESS_<NAME>_FILE=/run/secrets/...`. If any are missing the
  container logs a warning and random keys are used, logging users out on every redeploy; set
  `NRFC_REQUIRE_KEYS=true` (as `compose.production.yaml` does) to refuse to start instead.
* Plugin/theme installs and core auto-updates are disabled (`DISALLOW_FILE_MODS`); ship changes by
  rebuilding the image. Extra config can be passed with `WORDPRESS_CONFIG_EXTRA`.
