# MioLog Phase 2 Sync Plan

## Goal

Add an optional backend for:

- authenticated sync between devices
- future metadata enrichment
- future AI features

The backend is a thin service layer. The PWA remains local-first and is still the primary place where the user creates and edits data.

## Core Principles

- The PWA remains usable without the backend.
- Local data stays the source of truth for the user experience.
- The backend mirrors the PWA data model closely.
- `Game` and `LogEntry` keep their PWA-generated UUIDs as real IDs.
- The backend owns `User` and `SyncToken`.
- External metadata IDs such as `igdbId` can be added later as separate fields.

## Entities v1

### User

Purpose:

- internal ownership of synced data
- future support for a few friends

Suggested fields:

- `id` integer or UUID, backend-owned
- `email` nullable for now
- `displayName` nullable
- `createdAt`
- `updatedAt`

Notes:

- The frontend does not need to know much about the user yet.
- A user can own many sync tokens.
- A user can own many games.

### SyncToken

Purpose:

- simple external authentication model for the PWA
- self-host-friendly setup via Settings

Suggested fields:

- `id` backend-owned
- `user` many-to-one
- `name` string, for example `iPhone`, `iPad`, `Desktop`
- `tokenHash` string
- `lastUsedAt` nullable
- `createdAt`
- `revokedAt` nullable

Notes:

- Only the plain token is shown once when created.
- The database stores only a hash.
- The PWA uses the plain token as `Sync token` in Settings.

### Game

Purpose:

- remote copy of the PWA game record for sync

Suggested fields:

- `id` string UUID from the PWA
- `user` many-to-one
- `title` string
- `status` string or enum
- `rating` nullable int
- `playTimeHours` nullable decimal
- `review` text
- `platform` string
- `tags` JSON
- `finishedAt` nullable date
- `createdAt` datetime
- `updatedAt` datetime
- `deletedAt` nullable datetime

Notes:

- `tags` should stay simple as JSON for v1.
- `deletedAt` is part of v1 and is how deletions sync safely between devices.

### LogEntry

Purpose:

- remote copy of the PWA play logs

Suggested fields:

- `id` string UUID from the PWA
- `game` many-to-one
- `content` text
- `createdAt` datetime
- `updatedAt` datetime
- `deletedAt` nullable datetime

Notes:

- `updatedAt` is included from the start so future log editing does not require another sync model change.

## Relationships

- `User` 1 -> many `SyncToken`
- `User` 1 -> many `Game`
- `Game` 1 -> many `LogEntry`

## Auth Model v1

External UX:

- backend URL in Settings
- sync token in Settings
- optional `Test connection` button later

Internal behavior:

- request sends token in `Authorization: Bearer <token>`
- backend resolves token to `SyncToken`
- token resolves to `User`
- all sync reads and writes are scoped to that user

## Sync Shape v1

We start with manual sync only.

Frontend flow:

1. user presses `Sync now`
2. PWA sends full local dataset
3. backend merges dataset for the authenticated user
4. backend returns canonical full remote dataset
5. PWA replaces or merges local state with the response

This is intentionally simple and easy to reason about.

## First Sync Endpoint

Suggested endpoint:

- `POST /api/sync`

Suggested request shape:

```json
{
  "games": [
    {
      "id": "uuid",
      "title": "Final Fantasy VII Remake",
      "status": "playing",
      "rating": null,
      "playTimeHours": 12.5,
      "review": "",
      "platform": "PS5",
      "tags": ["JRPG", "Action"],
      "finishedAt": null,
      "createdAt": "2026-04-20T12:00:00+00:00",
      "updatedAt": "2026-04-22T18:00:00+00:00",
      "deletedAt": null
    }
  ],
  "logs": [
    {
      "id": "uuid",
      "gameId": "uuid",
      "content": "Boss fight was dope!",
      "createdAt": "2026-04-22T18:05:00+00:00",
      "updatedAt": "2026-04-22T18:05:00+00:00",
      "deletedAt": null
    }
  ]
}
```

Suggested response shape:

```json
{
  "games": [],
  "logs": [],
  "syncedAt": "2026-04-22T18:10:00+00:00"
}
```

## Conflict Rule v1

Start with:

- `updatedAt` wins for both `Game` and `LogEntry`
- `deletedAt` is treated as a state-changing update

Rules:

- if local record is newer than remote, remote is updated
- if remote record is newer than local, remote version is returned
- if a tombstone is newer than a non-deleted record, the tombstone wins

This is not the final perfect sync engine, but it is a practical first version for a personal tool.

## Deletion Strategy

Decision:

- use `deletedAt` tombstones from day one

Why:

- safer multi-device sync
- avoids resurrecting deleted records accidentally
- gives the sync engine a clear record that something was intentionally removed

## API Surface v1

Keep the first surface very small:

- `POST /api/sync`
- `GET /api/me` optional for connection testing later

Possible future endpoints:

- `POST /api/tokens`
- `DELETE /api/tokens/{id}`
- `GET /api/igdb/search`
- `POST /api/ai/play-next`
- `POST /api/ai/review-draft`

## Not In Scope Yet

- background auto-sync
- push notifications
- social features
- shared libraries
- IGDB enrichment
- AI recommendations
- AI review drafting

These all become better once sync is stable and trusted.

## Recommended Build Order

1. entities and migrations
2. sync-token authentication
3. `POST /api/sync`
4. manual sync in the PWA Settings
5. sync UX refinement and connection testing
6. only then enrichment and AI
