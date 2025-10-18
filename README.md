# web

## Requirements

 * Wordpress CLI tool. https://make.wordpress.org/cli/handbook/guides/installing/

## Dev quick start

 * Required once - Get wordpress: `cd wordpress && wp core download && cd -`
 * Grab SQL from live: `ssh dev.norwichrugby.com /opt/docker/www-wp/backup.sh - | gzip -d > initdb.d/dump.sql`
 * Grab the media from live: `rsync -a --progress dev.norwichrugby.com:/opt/docker/www-wp/uploads wordpress/wp-content`
 * Set UID and GID: `echo -e "UID=$(id -u)\nGID=$(id -g)\n">.env`
 * Start the server: docker compose up --build
 * Fix domain names: `mysql -h 127.0.0.1 -P 3336 -u wordpress -pwordpress wordpress -e "UPDATE wp_options SET option_value = 'http://localhost:8015' WHERE option_name IN ('home', 'siteurl');"`
 
