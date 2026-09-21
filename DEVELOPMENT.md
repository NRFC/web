# Development

## Quick start

Mirror down the repo and start the dev docker containers. The dev container will be available at `http://localhost:8015`.

```shell
./bin/stack.sh start
```

## Dev Shutdown

```shell
./bin/stack.sh stop
```

## Syncing up (for now)

```shell
rsync -a --progress --exclude=akismet --exclude=siteorigin-panels --exclude=so-* wordpress/wp-content/plugins nrfc:/opt/docker/www-wp
rsync -a --progress wordpress/wp-content/themes/nrfc nrfc:/opt/docker/www-wp/themes
```

## Notes

- Rewrites and ports: The `.htaccess` in `wordpress/.htaccess` uses the standard WordPress rules. These rules do internal path rewrites only and don't set the host or port. If you notice redirects or generated links dropping the dev port (e.g., `:8015`), an MU plugin at `wordpress/wp-content/mu-plugins/preserve-port.php` ensures non-standard ports are preserved in WordPress URLs and redirects. It also respects common proxy headers.

## Attaching a debugger

The system is running in a container, so you need to connect to it from your IDE. The container is configured to allow Xdebug connections on port 9003.