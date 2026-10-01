# Known Limitations (v1)

1. **One-way only** — Decor → Home. No reverse sync.
2. **Posts only** — Pages, products, CPTs, menus, widgets, theme options are out of scope.
3. **Elementor articles** — If a target post is Elementor-built, sync refuses overwrite unless `sync_elementor` is enabled.
4. **Sampled Decor posts** use classic HTML in `post_content`; Elementor appears used for site chrome. Historical Elementor posts may need manual review.
5. **Author mapping** requires a filter (`knd_sync_receiver_author_map`) or matching login; otherwise fallback author is used.
6. **Canonical SEO** is not synced by default (independent SEO assumed until configured).
7. **No WP-CLI / PHPUnit suite** in this repo yet; offline rewriter checks are provided.
8. **Live integration tests** must be run on staging by operators (see `docs/TESTING.md`).
9. **LiteSpeed / object cache** may delay visibility of synced drafts until cache purge.
10. **Slug conflicts** abort sync rather than overwriting an unrelated post.
