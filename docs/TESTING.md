# Testing Report

Date: 2026-10-01

## Environment available to agent

- Repository: greenfield (no WP runtime in this workspace)
- Live site probes: public HTML/REST of knddecor.com & knd-home.com (read-only)
- PHP CLI: **not installed / not on PATH** on the development machine, so `php -l` and `tests/bootstrap-link-rewriter.php` could not be executed here
- Offline algorithm check: `tests/link-rewriter.ps1` (mirrors host-parse rewrite rules)

**No production posts were modified. No live sync was executed against the real sites.**

## Automated tests performed

| Test | Result |
|------|--------|
| Link rewrite rule matrix via PowerShell mirror (`tests/link-rewriter.ps1`) | **PASSED** (12/12 cases) |
| PHP unit harness (`tests/bootstrap-link-rewriter.php`) | **Not run** — PHP binary unavailable |
| `php -l` on plugin PHP files | **Not run** — PHP binary unavailable |

## Manual / integration tests (required on staging before production)

These are **not** claimed as passed by the agent:

1. New Post → Target Create  
2. Post Update → Target Update  
3. Same post twice → no duplicate  
4. Featured image upload  
5. Multiple content images  
6. Already-synced image reuse  
7–11. Link rewrite matrix (algorithm covered by PowerShell suite; WP HTML integration still needed)  
12. Auth failure  
13. Target unavailable  
14. Retry/backoff  
15. Invalid payload  
16. Unauthorized REST  
17. Exclude from Sync  
18. Manual Sync  
19. Draft Sync  
20. Loop prevention  

## Recommended staging checklist

1. Install receiver on a staging clone of Home with Force Draft ON.  
2. Install sender on a staging clone of Decor with Test Mode ON.  
3. Sync one disposable post; verify content, media, taxonomies, Rank Math, links.  
4. Re-sync same post; confirm same `target_post_id`.  
5. Only then point at production Home with draft mode still ON for the first live article.
