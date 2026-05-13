# mio-server

`mio-server` is the self-hostable backend for MioLog.

It provides optional server-side features for the official MioLog PWA:

- sync for local-first data
- cached IGDB metadata enrichment
- optional AI helpers

The official MioLog PWA is currently maintained as the reference client. This repository contains the backend/server project only.

## Local Development

This project is Docker-first. You do not need host-local PHP, Composer, or database extensions installed.

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec backend composer install
docker compose exec backend php bin/console doctrine:migrations:migrate
docker compose exec backend php bin/phpunit --testdox
```

Or use the Makefile wrappers:

```bash
make build
make install
make migrate
make test
```

The API health endpoint is available at:

```text
http://localhost:8000/
```

## Optional Integrations

Sync works without IGDB or AI credentials.

To enable IGDB metadata enrichment, configure:

```dotenv
IGDB_CLIENT_ID=
IGDB_CLIENT_SECRET=
```

To enable AI helpers, configure the provider-specific keys you want to use:

```dotenv
APP_AI_PLAY_NEXT_PROVIDER=
OPENAI_API_KEY=
GEMINI_API_KEY=
LMSTUDIO_HOST_URL=http://host.docker.internal:1234
```

## Useful Commands

```bash
make shell
make console command="debug:router"
make sync-token command="you@example.com iPhone"
docker compose exec backend php bin/console app:igdb:enrich --limit=50
```

## CI

GitHub Actions runs the Docker-based test path:

- build the backend image
- start MySQL
- install Composer dependencies
- run PHPUnit
- lint the Symfony container

Tagged releases also publish Docker images to GitHub Container Registry.
Pushing a tag such as `v0.1.0` publishes:

```text
ghcr.io/<owner>/mio-server-backend:v0.1.0
ghcr.io/<owner>/mio-server-web:v0.1.0
```

## License

MIT.
