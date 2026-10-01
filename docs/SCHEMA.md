# Database & Meta Schema

## Options

| Option | Plugin |
|--------|--------|
| `knd_sync_receiver_settings` | Receiver |
| `knd_sync_receiver_db_version` | Receiver |
| `knd_sync_sender_settings` | Sender |
| `knd_sync_sender_db_version` | Sender |

## Tables (`{$wpdb->prefix}`)

### `knd_sync_logs` (both)

Columns: `id`, `created_at`, `level`, `event`, `request_id`, `source_post_id`, `target_post_id`, `response_code`, `message`, `error_detail`, `context`

### `knd_sync_media` (receiver)

Columns: `id`, `source_site`, `source_media_id`, `source_url`, `source_url_hash`, `target_media_id`, `target_url`, `created_at`, `updated_at`

Unique: `(source_site, source_url_hash)`

### `knd_sync_jobs` (sender)

Columns: `id`, `post_id`, `action`, `status`, `attempts`, `max_attempts`, `request_id`, `payload`, `last_error`, `available_at`, `created_at`, `updated_at`

## Post meta

### Source (Decor)

- `_knd_sync_status`
- `_knd_sync_target_post_id`
- `_knd_sync_last_sync`
- `_knd_sync_last_error`
- `_knd_sync_disabled`
- `_knd_sync_request_id`

### Target (Home)

- `_knd_sync_source_site`
- `_knd_sync_source_post_id`
- `_knd_sync_last_sync`
- `_knd_sync_origin`
- `_knd_sync_schema_version`
- `_knd_sync_request_id`
