# Security Notes

## Authentication

- API key is accepted **only** from `X-KND-SYNC-KEY` header.
- Query-string API keys are rejected.
- Optional HMAC-SHA256 over `timestamp + "." + body` with skew window (default 300s) mitigates replay.
- Rate limiting uses a per-key transient counter.

## Transport

- Sender warns on non-HTTPS target URLs.
- In production (`WP_DEBUG` off), non-HTTPS targets are refused.
- Media downloads require HTTPS source URLs.

## WordPress hardening

- Admin actions: `manage_options` + nonces
- Metabox / AJAX sync: `edit_post` + nonces
- REST mutating routes: custom `permission_callback` (never `__return_true`)
- Input sanitization / output escaping in admin views
- SQL via `$wpdb->prepare`
- Prefixes: `KND_SYNC_`, `knd_sync_`, `KND_Sync_`

## Media upload safety

- MIME allowlist: jpeg, png, webp, gif
- `wp_check_filetype_and_ext` validation
- Max size setting (default 8MB)
- Dangerous extensions blocked
- No arbitrary remote file execution path

## Logging privacy

- API keys, secrets, signatures, and Authorization-like keys are redacted as `[REDACTED]`.

## Loop prevention

- Constant `KND_SYNC_RECEIVING` during receiver writes
- Target posts store `_knd_sync_origin = knddecor`
- Sender skips posts that already have sync-origin meta

## Uninstall

- Data deletion is opt-in (`delete_data_on_uninstall`, default OFF).
- No runtime `DROP`/`TRUNCATE` of content tables.
