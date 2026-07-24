# Braven Lead Router

A working **self-select lead-routing tool** for institutional buyers — cities, counties,
chambers, and foundations — built as a **native WordPress plugin** on the exact stack it
targets: **WordPress + Elementor + custom PHP (CPTs / ACF-mappable meta) + GTM/GA4 +
CRM webhooks**. No page-builder bloat.

A prospective partner picks their organization type and training track, answers three
quick qualifiers, and a transparent scoring engine routes them to the right next step —
a booking call, a tailored proposal intake, a funding-partnership track, or a nurture
path. The qualified lead is captured as a Custom Post Type and pushed to the CRM (Smrts),
an email workflow, and GA4 (client dataLayer **and** a redundant server-side
Measurement Protocol event).

> **Live demo:** https://braven-demo.levelbrook.com · **Build docs:** https://braven-demo.levelbrook.com/docs/

This is a reference build demonstrating the routing tool, the tracking layer, and a
CPT-backed video repository as one cohesive plugin.

## Why it's built this way

The decision logic is **framework-agnostic and unit-tested without WordPress** (`php
tests/test-routing.php`, 26 assertions). A thin adapter layer binds that core to WordPress
primitives — CPTs, ACF field groups, a REST API, an Elementor widget, the admin. One
renderer powers both a shortcode and an Elementor block, so marketers drop the tool onto
any page while the deep logic stays in clean PHP.

```
wp-plugin/braven-lead-router/     the plugin (the actual IP)
  includes/                       pure core + WP adapters (see plugin header for the map)
  data/routes.php                 the declarative routing matrix (edit without touching code)
  data/videos.php                 seed catalog for the training library
  templates/ assets/ acf-json/    markup, brand-matched CSS/JS, ACF Pro field-group exports
wp-theme/braven-child/            hello-elementor child theme (Braven fonts + palette)
provision/                        WP-CLI seeding + demo mock-CRM sink
tests/                            pure-PHP tests of the routing engine + validator
docs/index.html                   extensive build documentation
Dockerfile · Caddyfile · docker-entrypoint.sh   self-contained WP+SQLite+Elementor demo
```

## Run the tests

```bash
php tests/test-routing.php
```

## Run the whole demo locally (Docker)

```bash
docker build -t braven-demo .
docker run -d -p 8080:80 -v braven_demo_data:/data \
  -e SITE_URL=http://localhost:8080 -e ADMIN_PASS='choose-a-password' braven-demo
# http://localhost:8080  ·  wp-admin user: braven
```

WordPress runs on **SQLite** (no MySQL), provisioned headlessly on first boot: it installs
core, the SQLite integration, Elementor, this plugin, and the child theme, then seeds the
pages, menu, videos, and settings.

## The three screening questions, answered in the docs

- **Build Test** — a live, operational tool with custom data structures (this).
- **Speed Test** — the video library is a CPT + taxonomies with client-side filtering over
  server-rendered markup; no directory plugin. See `docs/` for the full write-up.
- **Routing Test** — the conditional logic, the form→CRM handoff, and the GA4 data flow are
  documented end-to-end in `docs/`.

## License

GPL-2.0-or-later.
