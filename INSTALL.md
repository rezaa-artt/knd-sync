# Installation & Configuration Guide

## 1. Package plugins

From the repository root, create two ZIPs:

- `knd-sync-receiver.zip` containing `knd-sync-receiver/`
- `knd-sync-sender.zip` containing `knd-sync-sender/`

The ZIP root must be the plugin directory (so WordPress sees `knd-sync-receiver/knd-sync-receiver.php`).

## 2. Install Receiver on KND Home

1. WP Admin → Plugins → Add New → Upload Plugin.
2. Activate **KND Sync Receiver**.
3. Go to **Settings → KND Sync**.
4. Confirm:
   - Enable Receiver = ON
   - Force Draft = ON (recommended for first tests)
   - Allowed Source Hosts includes `knddecor.com` and `www.knddecor.com`
5. Copy **API Key** (and HMAC secret if you will enable HMAC).
6. Note endpoint: `https://knd-home.com/wp-json/knd-sync/v1/status`

## 3. Install Sender on KND Decor

1. Upload & activate **KND Sync Sender**.
2. Go to **Settings → KND Sync**.
3. Set:
   - Target URL: `https://knd-home.com`
   - API Key: (from receiver)
   - Source Hosts: `knddecor.com` / `www.knddecor.com`
   - Target Domain: `knd-home.com`
   - TEST MODE: ON
   - Sync as Draft: ON
   - Enable Sync: OFF until connection test passes
4. Click **Test Connection** → expect **Connected ✓**.
5. Enable Sync when ready.

## 4. First safe test

1. Create a non-critical draft/test article on Decor.
2. Open the post editor → **KND Sync** metabox → **Preview Changes**.
3. Click **Sync Now**.
4. On Home, confirm a **Draft** post with:
   - title/content/slug
   - rewritten internal links
   - images
   - categories/tags
   - Rank Math fields (if enabled)
5. Only then turn off Force Draft / Test Mode for production publish sync.

## 5. HMAC (optional)

1. Receiver: enable **Require HMAC signature**, copy HMAC secret.
2. Sender: enable HMAC, paste the same secret.
3. Re-run Test Connection and a manual sync.

## 6. Rollback

- Disable Sender (**Enable Sync** OFF) to stop outbound jobs.
- Disable Receiver to reject inbound API calls.
- Uninstall without enabling “Delete Sync Data on Uninstall” keeps tables/meta for recovery.
- Synced posts on Home are normal posts; remove individually if needed — the plugin never bulk-deletes content.
