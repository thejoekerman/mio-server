# Mio Server

<img src="docs/assets/miolog-chibi.png" alt="MioLog" width="128" />

`mio-server` is the self-hostable backend for [MioLog](https://miolog.net/).

It provides optional server-side features for the official [MioLog PWA](https://app.miolog.net/):

- sync for local-first data
- optional AI helpers

The official [MioLog PWA](https://app.miolog.net/) is currently maintained as the reference client. This repository contains the backend/server project only.

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

Sync works without AI credentials.

To enable AI helpers, configure the provider-specific keys you want to use:

```dotenv
AI_PROVIDER=
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
```

## Docker Images

Tagged releases publish production images to GitHub Container Registry:

```text
ghcr.io/thejoekerman/mio-server-backend:<tag>
ghcr.io/thejoekerman/mio-server-web:<tag>
```

Use `latest` for the newest published build or pin a release tag such as `v1.0.0`.

## Production Deployment

The published production images are intended to run as a small stack:

- `mio-server-backend`: PHP-FPM Symfony app
- `mio-server-web`: nginx serving `public/` and forwarding PHP requests to `backend:9000`
- `mysql:8.0` or another MySQL-compatible database
- an outer TLS reverse proxy such as Caddy, nginx, Traefik, or a platform load balancer

The example below creates a complete Docker Compose deployment in `/opt/miolog`
with Caddy handling HTTPS. Replace `miolog-api.example.com`, email addresses,
and passwords before running it.

### 1. Create The Server Directory

```bash
sudo mkdir -p /opt/miolog
sudo chown "$USER":"$USER" /opt/miolog
cd /opt/miolog
```

### 2. Create `.env`

```bash
APP_SECRET_VALUE="$(openssl rand -hex 32)"

cat > .env <<'EOF'
# Public API domain for this backend. Point DNS at this server before starting Caddy.
MIOLOG_DOMAIN=miolog-api.example.com
CADDY_ACME_EMAIL=you@example.com

# Symfony
APP_SECRET=__APP_SECRET__
APP_SHARE_DIR=var/share
DEFAULT_URI=https://miolog-api.example.com
CORS_ALLOW_ORIGIN=^https://app\.miolog\.net$
SYMFONY_TRUSTED_PROXIES=private_ranges
SYMFONY_TRUSTED_HOSTS=^miolog-api\.example\.com$

# MySQL
MYSQL_ROOT_PASSWORD=replace-with-a-root-password
MYSQL_DATABASE=miolog
MYSQL_USER=miolog
MYSQL_PASSWORD=replace-with-an-app-password

# Optional AI settings
AI_PROVIDER=
GEMINI_API_KEY=
LMSTUDIO_HOST_URL=

# mio-server images
MIOLOG_BACKEND_IMAGE=ghcr.io/thejoekerman/mio-server-backend:latest
MIOLOG_WEB_IMAGE=ghcr.io/thejoekerman/mio-server-web:latest
EOF

sed -i "s/__APP_SECRET__/${APP_SECRET_VALUE}/" .env
```

### 3. Create `compose.yml`

```bash
cat > compose.yml <<'EOF'
name: miolog

services:
  caddy:
    image: caddy:2-alpine
    restart: unless-stopped
    environment:
      MIOLOG_DOMAIN: ${MIOLOG_DOMAIN:?Set MIOLOG_DOMAIN in .env}
      CADDY_ACME_EMAIL: ${CADDY_ACME_EMAIL:?Set CADDY_ACME_EMAIL in .env}
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      web:
        condition: service_healthy
    networks:
      - public
      - app

  web:
    image: ${MIOLOG_WEB_IMAGE:?Set MIOLOG_WEB_IMAGE in .env}
    restart: unless-stopped
    depends_on:
      backend:
        condition: service_started
    healthcheck:
      test: ["CMD-SHELL", "wget -qO- http://127.0.0.1/healthz >/dev/null"]
      interval: 30s
      timeout: 5s
      retries: 3
    networks:
      - app

  backend:
    image: ${MIOLOG_BACKEND_IMAGE:?Set MIOLOG_BACKEND_IMAGE in .env}
    restart: unless-stopped
    environment:
      APP_ENV: prod
      APP_DEBUG: "0"
      APP_SECRET: ${APP_SECRET:?Set APP_SECRET in .env}
      APP_SHARE_DIR: ${APP_SHARE_DIR:-var/share}
      DEFAULT_URI: ${DEFAULT_URI:?Set DEFAULT_URI in .env}
      DATABASE_URL: mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@db:3306/${MYSQL_DATABASE}?serverVersion=8.0.32&charset=utf8mb4
      MESSENGER_TRANSPORT_DSN: ${MESSENGER_TRANSPORT_DSN:-doctrine://default?auto_setup=0}
      CORS_ALLOW_ORIGIN: ${CORS_ALLOW_ORIGIN:?Set CORS_ALLOW_ORIGIN in .env}
      SYMFONY_TRUSTED_PROXIES: ${SYMFONY_TRUSTED_PROXIES:-private_ranges}
      SYMFONY_TRUSTED_HOSTS: ${SYMFONY_TRUSTED_HOSTS:?Set SYMFONY_TRUSTED_HOSTS in .env}
      AI_PROVIDER: ${AI_PROVIDER:-}
      GEMINI_API_KEY: ${GEMINI_API_KEY:-}
      LMSTUDIO_HOST_URL: ${LMSTUDIO_HOST_URL:-}
    depends_on:
      db:
        condition: service_healthy
    networks:
      - app
      - egress

  db:
    image: mysql:8.0
    restart: unless-stopped
    ports:
      - "127.0.0.1:3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:?Set MYSQL_ROOT_PASSWORD in .env}
      MYSQL_DATABASE: ${MYSQL_DATABASE:?Set MYSQL_DATABASE in .env}
      MYSQL_USER: ${MYSQL_USER:?Set MYSQL_USER in .env}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD:?Set MYSQL_PASSWORD in .env}
    command:
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h 127.0.0.1 -u$${MYSQL_USER} -p$${MYSQL_PASSWORD} --silent"]
      interval: 10s
      timeout: 5s
      retries: 10
    networks:
      - app

volumes:
  caddy_data:
  caddy_config:
  db_data:

networks:
  public:
  egress:
  app:
    internal: true
EOF
```

### 4. Create `Caddyfile`

```bash
cat > Caddyfile <<'EOF'
{
    email {$CADDY_ACME_EMAIL}
}

{$MIOLOG_DOMAIN} {
    encode zstd gzip

    reverse_proxy web:80
}
EOF
```

### 5. Start The Stack

```bash
docker compose -f compose.yml pull
docker compose -f compose.yml up -d
docker compose -f compose.yml exec -T backend php bin/console doctrine:migrations:migrate --no-interaction
```

Create a sync token for the PWA:

```bash
docker compose -f compose.yml exec -T backend php bin/console app:sync-token:create you@example.com "My device"
```

Then open the hosted [MioLog PWA](https://app.miolog.net/) at `https://app.miolog.net`, set the sync API
base URL to your API domain, and paste the token.

## License

MIT.
