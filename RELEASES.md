# Releases — Braven Lead Router

## 2026-07-24 — 1.0.1 container healthcheck fix
- **What deployed:** https://braven-demo.levelbrook.com (Hetzner Box B) — same application, rebuilt image with a working container healthcheck. No functional/product changes.
- **Changed:**
  - The image defined no `HEALTHCHECK`, so it inherited `dunglas/frankenphp`'s, which curls Caddy's admin API at `http://localhost:2019/metrics`. Our `Caddyfile` sets `admin off`, so that endpoint never listens — the probe failed every 30s from first boot (**2,546 consecutive failures**, container labelled `unhealthy` for 21h) while the app served traffic normally with **0 restarts**.
  - Added `provision/healthz.php` — a liveness endpoint independent of WordPress and the SQLite database — and an explicit `HEALTHCHECK` against it. Probing PHP rather than the static `/up` route means a wedged FrankenPHP worker is actually caught; `/up` is a Caddy `respond` and would return 200 even with PHP dead.
  - `/up` deliberately left unchanged: kamal-proxy probes it with `Host: <container-id>`, so it must stay dependency-free.
  - `--start-period=180s` to cover first-boot WP-CLI provisioning.
- **How:** `git pull` on Box B → `docker build -t braven-demo .` (all heavy layers cached) → `docker rm -f braven-demo` + `docker run -d --name braven-demo --network kamal --restart unless-stopped -v braven_demo_data:/data -e SITE_URL=… -e ADMIN_USER=… -e ADMIN_PASS=… -e ADMIN_EMAIL=… braven-demo` → `docker exec kamal-proxy kamal-proxy deploy braven-demo --target braven-demo:80 --host braven-demo.levelbrook.com --tls`.
- **Verified:** `Up 3 minutes (healthy)`, `FailingStreak=0`, 5 consecutive passing probes. HTTP 200 on `/`, `/training-library/`, `/docs/`, `/docs/how-it-works.html`, `/wp-admin/`, `/healthz.php` (body `ok`). Front page still renders the router. **Recreate was non-destructive** — DB is on the `braven_demo_data` volume, entrypoint took its `[provision] existing install` branch; 17 `blr_video` + 1 `blr_lead` intact, all plugins + `braven-child` theme active. Not re-run: the Playwright E2E wizard flow (unchanged code path).

## 2026-07-23 — 1.0.0 initial build + deploy
- **What deployed:** https://braven-demo.levelbrook.com (Hetzner Box B, FrankenPHP + WordPress on SQLite behind kamal-proxy). Live self-select lead-routing tool, training video library, wp-admin Routing Console, and `/docs/` build documentation.
- **Changed:**
  - Native WordPress plugin `braven-lead-router`: routing engine, lead-capture as `blr_lead` CPT, `blr_video` CPT + `blr_track`/`blr_proficiency` taxonomies, custom `blr_deliveries` table, REST API (`braven/v1`), `[braven_lead_router]` + `[braven_video_library]` shortcodes, two Elementor widgets, admin dashboard + settings, ACF Pro field-group exports.
  - CRM webhook dispatcher (Smrts-shaped, idempotent, retried, HMAC-signed) + delivery log; email workflow; GA4 client dataLayer + server-side Measurement Protocol.
  - `braven-child` hello-elementor child theme (Nunito Sans + Playfair Display, gold #c7945b palette) matching bravenagency.com.
  - Self-contained Docker image: WordPress + SQLite + Elementor provisioned headlessly via WP-CLI.
  - 26-assertion pure-PHP test suite for the routing engine + validator.
  - Added `/docs/how-it-works.html` — big "How It Works" deep-dive explainer (full architecture, request lifecycle, routing engine with worked examples, ERD data model, capture pipeline, CRM payload, GA4/tracking, performance, a11y, deploy stack) with diagrams; cross-linked from `/docs/`.
- **How:** `git clone` on Box B → `docker build -t braven-demo .` → `docker run -d --network kamal -v braven_demo_data:/data …` → `kamal-proxy deploy braven-demo --target braven-demo:80 --host braven-demo.levelbrook.com --tls`. DNS A `braven-demo`→5.78.227.227 (DNS-only).
- **Verified:** see the session agent report (HTTPS 200, wizard end-to-end, CRM delivery logged, video filter).
