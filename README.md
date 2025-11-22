# web

## Requirements

 * Wordpress CLI tool. https://make.wordpress.org/cli/handbook/guides/installing/

## Dev quick start

 * Required once 
   - Get wordpress: `cd wordpress && wp core download && cd -`
   - Get the wp cli tool: `curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar`
 * Grab SQL from live: `ssh dev.norwichrugby.com /opt/docker/www-wp/backup.sh - | gzip -d > initdb.d/dump.sql`
 * Grab the media from live: `rsync -a --progress dev.norwichrugby.com:/opt/docker/www-wp/uploads wordpress/wp-content`
 * Set UID and GID: `echo -e "UID=$(id -u)\nGID=$(id -g)\n">.env`
 * Start the server: `docker compose up -d`
 * Fix domain names: `mysql -h 127.0.0.1 -P 3336 -u wordpress -pwordpress wordpress -e "UPDATE wp_options SET option_value = 'http://localhost:8015' WHERE option_name IN ('home', 'siteurl');"`
 * Add test user: `docker compose exec nrfc-wp-dev-web wp user create test test@example.com --role=administrator --user_pass=password`
 
## Dev Shutdown

 * `docker compose down`

## Syncing up (for now)

```
rsync -a --progress plugins nrfc:/opt/docker/www-wp
rsync -a --progress themes nrfc:/opt/docker/www-wp
```

## Notes

- Rewrites and ports: The `.htaccess` in `wordpress/.htaccess` uses the standard WordPress rules. These rules do internal path rewrites only and don't set the host or port. If you notice redirects or generated links dropping the dev port (e.g., `:8015`), an MU plugin at `wordpress/wp-content/mu-plugins/preserve-port.php` ensures non-standard ports are preserved in WordPress URLs and redirects. It also respects common proxy headers.
 
