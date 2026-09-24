# web

## Requirements

 * Wordpress CLI tool. https://make.wordpress.org/cli/handbook/guides/installing/
 * php 8.3 or above `apt install php8.3` or `brew install php@8.3`
 * docker
 * mysql client: `apt install mysql-client` or `brew install mysql-client`
 * If you're on Windoze I seriously suggest you use WSL
 * You have ssh key access to the NRFC server
 * You are part of the NRFC git team

## Cleaning out fixtures

```bash
docker compose exec nrfc-wp-dev-web bash -c 'wp post delete $(wp post list --post_type=fixture --field=ID) --force'
```