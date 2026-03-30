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

After that, the application is available at `http://localhost:8080` or on the `APP_PORT` defined in `.env`.

### Verification

* Check the current Drupal status:

  ```bash
  docker compose exec app vendor/bin/drush status
  ```

* Check HTTP availability:

  ```bash
  curl -I http://localhost:8080
  ```

### Local default credentials after the example installation

If you use the `drush site:install` command above without changes, you can work locally with the admin credentials defined there. For real team use, those credentials should of course be changed immediately.

## Project conventions

* `composer.json` lives in the repository root.
* The public Drupal webroot is `docroot/`.
* The Drupal config sync directory is `configurations/`.
* The database stores its data persistently in `.docker/db-data/`.
