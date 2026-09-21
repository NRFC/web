#  Production

## Building

### GitHub Actions

The `Build production images` workflow builds and pushes both production images to GitHub Container Registry on manual dispatch and on pushes to `main`.

Images are tagged with the commit SHA. Builds from `main` are also tagged as `latest`, which is what `docker/production/compose.production.yaml` uses by default.

### Assumptions

 * Have docker cli or docker desktop installed and running.
 * You are part of the nrfc organization on GitHub and have access to the GitHub Container Registry.
 * You have logged into the GitHub Container Registry with `docker login ghcr.io`.
   * You have a token, https://github.com/settings/tokens?utm_source=gemini
   * You have logged in: `echo "YOUR_GITHUB_PAT" | docker login ghcr.io -u YOUR_GITHUB_USERNAME --password-stdin`

Run these from the project root directory.

```bash
docker build -t ghcr.io/nrfc/wp-prod-php -f docker/production/Dockerfile.production .
docker push ghcr.io/nrfc/wp-prod-php

docker build -t ghcr.io/nrfc/wp-prod-nginx -f docker/production/Dockerfile.production.nginx .
docker push ghcr.io/nrfc/wp-prod-nginx
```

These will build and push the production images to GitHub Container Registry. The images are tagged with the current git commit hash.

## Staging

You can stage the site anywhere. Grab a DB dump from production and copy of the media files.

Assuming you are on the live server

```bash
mkdir -p initdb.d
/opt/docker/www-wp/backup.sh - | gzip -d > initdb.d/dump.sql
cp -r /opt/docker/www-wp/uploads .
wget -O compose.yaml https://raw.githubusercontent.com/NRFC/web/refs/heads/main/docker/production/compose.production.yaml
wget -O .env https://raw.githubusercontent.com/NRFC/web/refs/heads/main/.env.example
# edit the env file as needed, for staging you'll need to change the WORDPRESS_PORT
docker compose up
```
