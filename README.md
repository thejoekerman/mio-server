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
AI_PROVIDER=
OPENAI_API_KEY=
GEMINI_API_KEY=
LMSTUDIO_HOST_URL=http://host.docker.internal:1234
```

When `AI_PROVIDER` is empty, development defaults to `lmstudio` and non-development
environments default to `gemini`. All AI helpers use the same provider choice.
AI endpoints accept an optional JSON body such as `{"language":"de"}` or
`{"language":"en"}` so generated text can follow the PWA language.

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

## Production Deployment

The published production images are intended to run as a small stack:

- `mio-server-backend`: PHP-FPM Symfony app
- `mio-server-web`: nginx serving `public/` and forwarding PHP requests to `backend:9000`
- `mysql:8.0` or another MySQL-compatible database
- an outer TLS reverse proxy such as Caddy, nginx, Traefik, or a platform load balancer

For example, a Docker Compose deployment can put Caddy in front of the web image:

```text
internet -> Caddy/TLS -> mio-server-web -> mio-server-backend -> MySQL
```

The web image expects the PHP-FPM service to be reachable as `backend:9000`.
If your service is named differently, adjust the nginx production config or provide
an equivalent web-server configuration.

Required production environment includes:

```dotenv
APP_ENV=prod
APP_SECRET=
DATABASE_URL=mysql://user:password@db:3306/miolog?serverVersion=8.0.32&charset=utf8mb4
CORS_ALLOW_ORIGIN=^https://your-pwa-domain.example$
```

Run migrations after starting a new deployment:

```bash
docker compose exec -T backend php bin/console doctrine:migrations:migrate --no-interaction
```

IGDB enrichment, if enabled, should be scheduled separately, for example with cron:

```bash
docker compose exec -T backend php bin/console app:igdb:enrich --limit=50
```

## License

MIT.
