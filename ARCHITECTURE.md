# KND Sync — Architecture

## Overview

One-way content synchronization:

```text
KND Decor (Sender) → Secure REST API → KND Home (Receiver)
```

Two independent WordPress plugins (separate ZIP installs):

| Plugin | Install on | Role |
|--------|------------|------|
| `knd-sync-sender` | knddecor.com | Detect publish/update, queue jobs, build payload, send |
| `knd-sync-receiver` | knd-home.com | Authenticate, upsert posts/media/taxonomies, log |

Direction for v1 is **Decor → Home only**. Code is structured so a reverse Sender/Receiver pair can be added later without rewriting core identity logic.

## Design Principles

1. **Separation of concerns** — Auth, REST, Post, Media, Taxonomy, Meta/SEO, Queue, Logger, Settings are distinct classes.
2. **Idempotency** — Identity key is `(source_site, source_post_id)`. Never blind INSERT.
3. **Fail-safe publish** — Sync failures never block WordPress publish on source.
4. **Background first** — Prefer Action Scheduler (bundled with WooCommerce); fallback to custom cron + jobs table.
5. **Secure by default** — Header API key; HMAC headers prepared; HTTPS warnings; no secrets in logs.
6. **Extensible adapters** — SEO (Rank Math), Elementor stub, future content types.

## Site Audit Findings (2026-10-01)

| Item | Finding |
|------|---------|
| Repository | Greenfield (empty git, no prior sync code) |
| WP version | 7.1.2 (both sites) |
| PHP target | 8.0+ (plugin requires 8.0) |
| Theme | Woodmart (both) |
| Page builder | Elementor 4.2.0 (site chrome); sampled **posts** use classic HTML in `post_content` (no Elementor/Gutenberg markers in rendered content) |
| SEO | **Rank Math** (confirmed via HTML + `/wp-json` namespaces). Not Yoast/AIOSEO/SEOPress |
| Commerce | WooCommerce present → Action Scheduler available |
| Cache | LiteSpeed Cache |
| Existing API | `knd-panel/v1` already exists — we use `knd-sync/v1` to avoid conflict |
| Timezone | Asia/Tehran (GMT+3.5) |
| Media | WebP common; Persian filenames in uploads |

### Unknowns (not assumed)

- Exact author ID mapping between sites (configurable fallback author).
- Whether KND Home should keep independent SEO canonicals or mirror (default: **do not rewrite Rank Math canonical** unless enabled).
- Whether some historical posts use Elementor content (adapter detects `_elementor_edit_mode` and skips unsafe overwrite).
- Production API key / secrets (generated on install; never committed).

## Components

### Sender

```text
Hooks (transition_post_status / save_post)
    → Guard (post type, disabled meta, loop flag, autosave)
    → Queue job
    → Background worker
        → Build payload (post, tax, media refs, SEO meta)
        → Rewrite internal links (source host → target host)
        → HTTP client (auth headers + request id)
        → Update local sync status meta
        → Log
```

### Receiver

```text
REST /knd-sync/v1/*
    → Auth (API key + optional HMAC)
    → Rate limit / schema validation
    → Upsert by source identity
        → Taxonomies (slug map / optional create)
        → Media (map table / download / sideload)
        → Post insert/update (slug conflict check)
        → Meta + Rank Math adapter
        → Featured image
    → Standard JSON response
    → Log
```

## REST API (v1)

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/wp-json/knd-sync/v1/status` | Connection test |
| POST | `/wp-json/knd-sync/v1/post` | Create/update synced post |
| POST | `/wp-json/knd-sync/v1/preview` | Dry-run validation (optional) |

Auth headers:

```http
X-KND-SYNC-KEY: <api-key>
X-KND-SYNC-REQUEST-ID: <uuid>
X-KND-SYNC-TIMESTAMP: <unix>          # when HMAC enabled
X-KND-SYNC-SIGNATURE: <hmac-sha256>   # when HMAC enabled
```

## Identity & Meta

### Source post meta

- `_knd_sync_status` — `not_synced|pending|syncing|synced|failed|disabled`
- `_knd_sync_target_post_id`
- `_knd_sync_last_sync`
- `_knd_sync_last_error`
- `_knd_sync_disabled` — `1` excludes post
- `_knd_sync_request_id`

### Target post meta

- `_knd_sync_source_site`
- `_knd_sync_source_post_id`
- `_knd_sync_last_sync`
- `_knd_sync_origin` — loop prevention (`knddecor`)
- `_knd_sync_schema_version`

### Custom tables (`$wpdb->prefix`)

- `{prefix}knd_sync_jobs` — queue (sender; also usable on receiver for deferred media)
- `{prefix}knd_sync_logs` — structured logs
- `{prefix}knd_sync_media` — source↔target media map

No `DROP`/`TRUNCATE` in runtime code. Uninstall deletes data only if setting enabled (default OFF).

## Payload Schema (`schema_version: 1.0`)

See `docs/API.md`. Versioned JSON; unknown fields ignored for forward compatibility.

## Link Rewriter Rules

1. Parse URLs with `wp_parse_url` / ` DomDocument` for `href`/`src`.
2. Rewrite only when host ∈ configured source hosts (`knddecor.com`, `www.knddecor.com`).
3. Preserve path, query, fragment.
4. Leave relative URLs unchanged.
5. Leave external hosts unchanged.
6. Reject lookalike hosts (`knddecor.com.evil.com`, substring traps).

## Media Pipeline

1. Collect featured + content image URLs/IDs from payload.
2. Lookup `{prefix}knd_sync_media` by `source_url` / `source_media_id`.
3. If mapped → reuse target attachment.
4. Else download with WP HTTP API → validate MIME/size/extension → `media_handle_sideload` → store mapping → rewrite content URLs.

Allowed MIME (v1): `image/jpeg`, `image/png`, `image/webp`, `image/gif`.

## SEO Adapter

`KND_Sync_SEO_Adapter_RankMath` copies an allowlisted set of `rank_math_*` keys when:

- Rank Math is active on both sides (receiver checks), and
- Setting `sync_seo_meta` is ON.

Canonical sync is a **separate** toggle (`sync_seo_canonical`, default OFF).

## Elementor Adapter

v1: If target/source indicates Elementor-built post (`_elementor_edit_mode = builder`), do **not** overwrite Elementor meta unless `sync_elementor` is explicitly enabled (default OFF). Classic/`post_content` HTML posts sync normally.

## Queue & Retry

- Attempts: configurable (default 3)
- Backoff: 60s × attempt² (60, 240, 540)
- Terminal state: `FAILED` + admin log
- Loop prevention: constant `KND_SYNC_RECEIVING` during receiver writes; sender skips when set / when `_knd_sync_origin` present

## Security Model

- Capability: `manage_options` for settings; `edit_post` for manual sync metabox
- Nonces on all admin AJAX
- Prepared SQL only
- Auth required on mutating REST routes
- Secrets never logged
- HTTPS URL validation + admin warning for HTTP targets

## Admin UX

**Sender:** Settings → KND Sync (connection, toggles, domains, retry), Logs, post metabox (status / Sync Now / Preview / Exclude).

**Receiver:** Settings → KND Sync (API key, generate secret, toggles), Logs.

## Future Extension Points

- `POST /v1/post/unpublish` and `/v1/post/delete`
- CPT adapters implementing `KND_Sync_Content_Type_Interface`
- Two-way sync via symmetric plugins + conflict policy
- HMAC-required mode as default in v1.1
