# MioServer

Optional self-hostable sync backend for MioLog. The App works fine without it.

## What it is
- Sync server for MioLog's Games, Journeys, Logs, and Trophies
- **Does NOT edit game metadata** — enrichment was retired in v3
- First sync: full reconciliation. Subsequent: incremental with cursors

## Tech Stack
- PHP 8.2 + Slim 4
- Doctrine ORM
- SQLite or MySQL
- Docker-first

## Development
```bash
make up       # Start containers
make test     # PHPStan, PHPUnit, coding standards
make migrate  # Run database migrations
```

## Sync Contract (API v2)
- Endpoint: `/api/me` advertises `version: 2`
- Entities: Games, Journeys, Journey-owned Logs, Trophies
- Order: Games → Journeys → Logs in one transaction
- Conflicts: Last-write-wins by `updatedAt`
- Tombstones synced for deletions
- Server identity: normalized API URL + authenticated user ID

## Memory
Extended context in `memory/`:
- `01-ecosystem.md` — Project shape and release state
- `02-sync.md` — Sync API v2 implementation and contract
- `03-verification.md` — Dev commands and verification

