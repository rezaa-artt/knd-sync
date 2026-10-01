# KND Sync REST API (v1)

Base: `https://knd-home.com/wp-json/knd-sync/v1/`

## Authentication

All routes require:

```http
X-KND-SYNC-KEY: <api-key>
X-KND-SYNC-REQUEST-ID: <uuid>
```

Optional HMAC (when enabled on both sides):

```http
X-KND-SYNC-TIMESTAMP: <unix-seconds>
X-KND-SYNC-SIGNATURE: <hmac-sha256(timestamp + "." + body, secret)>
```

Do **not** put the API key in the query string.

## GET `/status`

Connection test.

**200**

```json
{
  "success": true,
  "request_id": "…",
  "status": "connected",
  "plugin": "knd-sync-receiver",
  "version": "1.0.0",
  "site": "https://knd-home.com/",
  "time": "2026-10-01T00:00:00+00:00",
  "hmac_ready": true
}
```

## POST `/post`

Create or update a synchronized post (idempotent by `source.site` + `source.post_id`).

**201 Created** / **200 OK**

```json
{
  "success": true,
  "request_id": "abc123",
  "source_post_id": 1520,
  "target_post_id": 843,
  "status": "synced",
  "created": true
}
```

**Error**

```json
{
  "code": "AUTH_FAILED",
  "message": "Authentication failed.",
  "data": {
    "status": 401,
    "request_id": "abc123",
    "success": false,
    "error_code": "AUTH_FAILED"
  }
}
```

### Payload (`schema_version: "1.0"`)

```json
{
  "schema_version": "1.0",
  "source": {
    "site": "https://knddecor.com/",
    "post_id": 1520,
    "url": "https://knddecor.com/example/"
  },
  "post": {
    "title": "Example Title",
    "slug": "example",
    "content": "<p>…</p>",
    "excerpt": "…",
    "status": "draft",
    "date": "2026-10-01 09:00:00",
    "date_gmt": "2026-10-01 05:30:00",
    "modified": "…",
    "modified_gmt": "…"
  },
  "author": { "id": 5, "name": "…", "login": "…" },
  "categories": [{ "slug": "modern-chandeliers", "name": "…" }],
  "tags": [{ "slug": "lighting", "name": "…" }],
  "featured_image": {
    "id": 10,
    "url": "https://knddecor.com/wp-content/uploads/…/image.webp",
    "alt": "…",
    "title": "…",
    "caption": "…",
    "description": "…",
    "filename": "image.webp"
  },
  "images": [],
  "meta": {
    "seo": {
      "rank_math_title": "…",
      "rank_math_description": "…"
    }
  }
}
```

## POST `/preview`

Dry-run validation; no writes.

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK / updated |
| 201 | Created |
| 400 | Bad request / insecure auth usage |
| 401 | Auth / HMAC failure |
| 403 | Disabled / source not allowed |
| 404 | Not found |
| 409 | Slug / Elementor conflict |
| 422 | Unprocessable payload/media |
| 429 | Rate limited |
| 500 | Server error |
| 502 | Upstream media download failure |
