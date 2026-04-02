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

The helper script now runs in the **current execution context** and no longer wraps its own commands in `docker compose exec`.

That means both of these are valid, depending on where you want to run Drush:

```bash
bin/recreate-site-from-config
docker compose exec app bin/recreate-site-from-config
```

If the current context does not already provide the database connection through `DRUPAL_DB_*` or `DB_*`, pass it explicitly:

```bash
bin/recreate-site-from-config \
  --db-url="mysql://rook:rook@127.0.0.1:3306/rook_servicechannel"
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

### Running tests

Drupal Kernel tests in this repository must run through Drupal's own PHPUnit configuration and need the standard Simpletest environment variables.

Run the complete custom-module suite:

```bash
docker compose exec app sh -lc '
  mkdir -p /tmp/browser_output &&
  cd docroot &&
  export SIMPLETEST_DB="mysql://rook:rook@db:3306/rook_servicechannel" \
    SIMPLETEST_BASE_URL="http://localhost" \
    BROWSERTEST_OUTPUT_DIRECTORY="/tmp/browser_output" &&
  ../vendor/bin/phpunit -c core/phpunit.xml.dist modules/custom
'
```

Run a focused Kernel test file:

```bash
docker compose exec app sh -lc '
  mkdir -p /tmp/browser_output &&
  cd docroot &&
  export SIMPLETEST_DB="mysql://rook:rook@db:3306/rook_servicechannel" \
    SIMPLETEST_BASE_URL="http://localhost" \
    BROWSERTEST_OUTPUT_DIRECTORY="/tmp/browser_output" &&
  ../vendor/bin/phpunit -c core/phpunit.xml.dist \
    modules/custom/rook_servicechannel_core/tests/src/Kernel/CoreDomainServicesKernelTest.php
'
```

Current automated coverage includes:

* shared core-domain service behavior in `rook_servicechannel_core`
* console API flows and OpenAPI contract checks
* optional console IP guard checks
* client API flows, role access checks and OpenAPI contract checks
* gateway validation, runtime maintenance and OpenAPI contract checks

### Local API checks

For manual HTTP checks, keep the base URL configurable:

```bash
APP_BASE_URL="${APP_BASE_URL:-http://localhost:${APP_PORT:-8080}}"
curl -i "${APP_BASE_URL}/api/console/1/status" \
  -H 'Content-Type: application/json' \
  -d '{"pin":"0000"}'
```

### Team UI

The repository now includes a Drupal-native Service UI module at:

* `/servicechannel/team`

The UI is protected for authenticated users with the Drupal role `Service` and permission `access rook team ui`.

It integrates:

* PIN lookup via `POST /api/client/1/pinlookup`
* session status via `POST /api/client/1/sessionstatus`
* terminal grant requests via `POST /api/client/1/requestshell`
* an `xterm.js`-based browser terminal shell for the separate gateway

Gateway runtime settings can be changed in Drupal at:

* `/admin/config/services/rook-servicechannel/team-ui`

Available settings:

* gateway base URL
* gateway terminal path

The Team UI source now lives as a React + TypeScript app inside the Drupal module:

* `docroot/modules/custom/rook_servicechannel_team_ui/src/team-ui.tsx`

The committed runtime bundle remains:

* `docroot/modules/custom/rook_servicechannel_team_ui/js/team-ui.js`

To rebuild the committed frontend artifact after Team UI source changes:

```bash
cd docroot/modules/custom/rook_servicechannel_team_ui
npm install
npm run typecheck
npm run build
```

Important note:

* The browser now opens the WebSocket normally and sends the terminal grant as the first `authorize` message after the upgrade succeeds.
* The UI treats the terminal as active only after the gateway confirms the authorization path with `authorized`.
* The Service workspace is linked into the Drupal main navigation, and the settings form is linked under `Configuration/System`.

### Reset a local site from the exported configuration

For a local reset against an empty database, use:

```bash
bin/recreate-site-from-config
```

If you want a complete local reset, first ensure that the database used by Docker is empty or intentionally recreate the local database volume contents before running the script again.

### Local default credentials after the example installation

If you use the `drush site:install` command above without changes, you can work locally with the admin credentials defined there. For real team use, those credentials should of course be changed immediately.

## Project conventions

* `composer.json` lives in the repository root.
* The public Drupal webroot is `docroot/`.
* The Drupal config sync directory is `configurations/`.
* The database stores its data persistently in `.docker/db-data/`.
