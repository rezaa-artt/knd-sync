# Rollback Procedure

1. **Stop outbound sync** — On Decor: Settings → KND Sync → uncheck Enable Sync → Save.
2. **Stop inbound API** — On Home: Settings → KND Sync → uncheck Enable Receiver → Save.
3. **Leave data intact** — Do not enable “Delete Sync Data on Uninstall” unless you intentionally want tables removed.
4. **Deactivate plugins** if needed (Plugins → Deactivate). Synced posts remain as normal WordPress posts.
5. **Selective cleanup** — Delete or unpublish specific synced drafts/posts manually in Home if required. The plugins never bulk-delete content.
6. **Restore from backup** — If a bad sync corrupted a post, restore that post from your normal host/backup process; KND Sync does not ship automatic content rollback.
