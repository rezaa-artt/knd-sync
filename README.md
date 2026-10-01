# KND Sync

Production-oriented WordPress plugins that synchronize **posts** from [KND Decor](https://knddecor.com) to [KND Home](https://knd-home.com).

```text
KND Decor → knd-sync-sender → Secure REST API → knd-sync-receiver → KND Home
```

## Packages

| Folder | Install on | Role |
|--------|------------|------|
| `knd-sync-sender/` | knddecor.com | Detect publish/update, queue, rewrite links, send payload |
| `knd-sync-receiver/` | knd-home.com | Authenticate, upsert posts/media/taxonomies/SEO |

## Quick start

1. Zip each plugin folder separately (root of ZIP = plugin folder).
2. Install **Receiver** on KND Home → copy the generated API key.
3. Install **Sender** on KND Decor → paste Target URL + API key.
4. Keep **TEST MODE** + **Sync as Draft** ON.
5. Use **Test Connection**, then sync a non-critical test post via **Sync Now**.
6. Verify draft on Home, then disable draft/test mode when ready.

Full steps: [INSTALL.md](INSTALL.md)  
Architecture: [ARCHITECTURE.md](ARCHITECTURE.md)  
Security: [SECURITY.md](SECURITY.md)  
API: [docs/API.md](docs/API.md)

## v1 scope

**Synced:** posts, content, slug, excerpt, dates, categories, tags, featured/content images, Rank Math SEO meta (canonical optional), internal link rewrite.

**Not synced:** products, orders, users, pages, menus, widgets, theme options, Elementor pages (unless explicitly enabled), unknown CPTs.

## Defaults (safe)

- Sender automatic sync: **OFF** until configured
- Test mode: **ON** (forces draft on target)
- Sync as draft: **ON**
- SEO canonical sync: **OFF**
- Elementor overwrite: **OFF**
- Delete data on uninstall: **OFF**

## Requirements

- WordPress 6.0+ (sites audited on 7.1.2)
- PHP 8.0+
- HTTPS between sites
- Rank Math (optional, for SEO meta sync)
- Action Scheduler recommended (comes with WooCommerce)

## License

GPL-2.0-or-later
