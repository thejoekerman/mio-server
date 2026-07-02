# MioServer Ecosystem

Last refreshed: 2026-06-26

## Project Shape

- MioServer is the optional self-hostable sync backend for MioLog.
- The App (`mio-pwa/`) remains complete and useful without it.
- Everything is Docker-first. Keep Node, npm, PHP, Composer off the host.
- Use the repository's Makefile and Docker containers.

## Current Release State

- MioServer 3 with sync API v2 was deployed on 2026-06-14.
- Production sync has worked correctly since deployment.
- The MioServer worktree was clean after the release commit.
