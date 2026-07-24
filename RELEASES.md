# Releases — Braven Lead Router

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
