# Verification and Development

## Preferred Commands

- `make up`
- `make test`
- `make migrate`

## Verification

- MioServer 3 verification passed coding standards, PHPStan, PHPUnit, the full migration chain, and Doctrine schema validation.
- A real local first sync from an existing browser library into an empty MioServer succeeded before production deployment.
- Do not install or repair host-local tooling as a workaround.

## Container Baselines

- PHP 8.4, Composer 2, Nginx 1.30 stable, and MySQL 8.4 LTS.
- MySQL upgrades require a SQL backup and a copy of the stopped old database volume.
- Doctrine URLs and PHPUnit advertise MySQL `serverVersion=8.4`.
- Dependabot monitors Dockerfiles and Docker Compose images as well as Composer and Actions.
