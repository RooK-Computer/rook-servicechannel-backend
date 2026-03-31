# RooK Service Channel Backend

This repository contains the Drupal-based backend for the RooK Service Channel.

## Local development

### Requirements

* Docker with `docker compose`

### Initial setup

1. Create a local environment file:

   ```bash
   cp .env.example .env
   ```

2. Build the PHP/Apache image:

   ```bash
   docker compose build app
   ```

3. Install Drupal dependencies inside the container:

   ```bash
   docker compose run --rm app composer install
   ```

4. Start the containers:

   ```bash
   docker compose up -d
   ```

5. Run the initial Drupal installation once the dependencies are available:

   ```bash
   docker compose exec app vendor/bin/drush site:install standard \
     --db-url="mysql://rook:rook@db:3306/rook_servicechannel" \
     --site-name="RooK Service Channel Backend"
   ```

If you use different database values in `.env`, adjust the `--db-url` accordingly.

After that, the application is available on the local app port defined by `APP_PORT` in `.env` (for example `http://localhost:8080` if you keep the default).

### Recreate a site from the exported configuration

If you start with an empty database but already have the full config export in `configurations/`, the obvious one-step command

```bash
docker compose exec app vendor/bin/drush site:install --existing-config
```

does not work for this repository.

Reason: `configurations/core.extension.yml` currently contains `profile: standard`, and Drupal core blocks configuration installs for profiles that implement `hook_install()`. The core `standard` profile does exactly that.

Use this two-step process instead:

```bash
docker compose exec app vendor/bin/drush site:install standard \
  --db-url="mysql://rook:rook@db:3306/rook_servicechannel" \
  --site-name="RooK Service Channel Backend"

docker compose exec app sh -lc 'vendor/bin/drush config:set -y system.site uuid "$(sed -n "s/^uuid: //p" configurations/system.site.yml)"'

docker compose exec app vendor/bin/drush config:import -y
```

Or use the helper script in this repository:

```bash
bin/recreate-site-from-config
```

The helper script also removes the shortcut entities created by `standard_install()`, because they otherwise block the subsequent configuration import.

Important notes:

* A fresh Drupal install gets a new site UUID. The UUID must match `configurations/system.site.yml` before `config:import` will succeed.
* All modules referenced by the exported config must be installed in `docroot/` before running the command.
* If the database is not empty, drop it first or start with a new schema.
* If you want a future one-step install from config, the practical options are a custom profile without `hook_install()` or a recipe-based installation flow.

### Verification

* Check the current Drupal status:

  ```bash
  docker compose exec app vendor/bin/drush status
  ```

* Check HTTP availability:

  ```bash
  curl -I "http://localhost:${APP_PORT:-8080}"
  ```

### Local default credentials after the example installation

If you use the `drush site:install` command above without changes, you can work locally with the admin credentials defined there. For real team use, those credentials should of course be changed immediately.

## Project conventions

* `composer.json` lives in the repository root.
* The public Drupal webroot is `docroot/`.
* The Drupal config sync directory is `configurations/`.
* The database stores its data persistently in `.docker/db-data/`.
