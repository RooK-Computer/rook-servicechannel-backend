# Repository Instructions

## Start here first

When starting work in this repository, read these files first:

1. `plans/00-backend-plan-overview.md`
2. `spec/implementation/04-rook-backend-status.md`
3. `plans/02-backend-domain-model-and-drupal-architecture.md`
4. `plans/04-web-api-and-access-control.md`

These files are the primary handoff points for current backend scope, architecture, and next steps.

## Project purpose

This repository contains the Drupal-based backend for the RooK Service Channel.

The backend is the system control plane for:

* support sessions
* short-lived PINs
* team access
* terminal grants
* audit data

The canonical product and API specification lives in the `spec/` submodule.

## Repository conventions

* `composer.json` lives in the repository root.
* The Drupal webroot is `docroot/`.
* The Drupal config sync directory is `configurations/`.
* Local development uses `docker-compose.yml`.
* PHP and the database run in Docker.
* Database data is persisted via the mounted host directory under `.docker/db-data/`.

## Version control policy

This repository should always contain an executable state.

That means:

* required Drupal runtime artifacts are committed
* generated artifacts required for execution are committed
* future frontend artifacts are expected to be committed both as source and compiled output when they are required to run the system

Do not assume a fresh clone should require rebuilding everything before the project can run.

## Current architectural decisions

### Drupal modeling

Do not model the core backend domain with node bundles.

Current agreed approach:

* `support_session` is a custom content entity
* `terminal_grant` is a custom content entity
* `pin` is a field on `support_session`, not a standalone entity
* `rook_support_session_participant` is a dedicated table for session/user coupling
* `rook_support_audit_log` is a dedicated append-only audit table

The initial implementation lives in:

* `docroot/modules/custom/rook_servicechannel_core/`

### Access control

The backend must explicitly create and use a Drupal role named `Service` for service staff.

This is planned as part of the web API and access control work. Do not treat that role as an implicit manual prerequisite.

## Current state

Already completed:

* Drupal bootstrap and local Docker environment
* installed Drupal codebase committed into the repository
* local Drupal installation validated
* initial domain module `rook_servicechannel_core`
* domain entities, tables, and core services validated locally

The next planned implementation areas are the API layers, beginning with agent and web access flows.

## Practical working rules

* Prefer extending the agreed domain model instead of introducing parallel structures.
* Update the relevant files in `plans/` when architectural decisions change.
* Keep `spec/implementation/04-rook-backend-status.md` aligned with real progress.
* If you change install/update behavior, verify module enablement and schema creation against the running Drupal instance.
* If you touch generated or executable artifacts, keep the committed runnable state intact.
