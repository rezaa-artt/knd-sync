# Changelog

## 1.0.0 — 2026-10-01

### Added

- `knd-sync-receiver` REST API (`/knd-sync/v1/status`, `/post`, `/preview`)
- Header API key auth + optional HMAC + rate limit
- Idempotent post upsert by source site + source post ID
- Media import with mapping table and MIME/size checks
- Category/tag mapping by slug (optional create)
- Rank Math SEO adapter (canonical optional)
- Elementor overwrite guard (opt-in adapter)
- `knd-sync-sender` publish/update hooks with queue + retry/backoff
- Action Scheduler support when available; WP-Cron fallback
- Safe internal link rewriter (host-parsed, preserves path/query/fragment)
- Admin settings, logs, connection test, metabox (preview / sync now / exclude)
- Structured logging with retention prune
- Documentation: README, INSTALL, ARCHITECTURE, SECURITY, API, testing notes
