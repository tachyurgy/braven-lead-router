=== Braven Lead Router ===
Contributors: levelbrook
Tags: lead routing, cpt, acf, elementor, ga4, crm, conversion
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-select lead-routing engine for institutional buyers. Routes each visitor by
buyer type + training track to the right next step, captures the lead as a CPT,
and hands it to the CRM (Smrts), an email workflow, and GA4.

== Description ==

Braven Lead Router turns website traffic into qualified institutional leads. A
prospective partner (city, county, chamber, foundation, or corporate) picks their
type and training need; a transparent scoring engine ranks intent and routes them
to a booking link, a tailored proposal intake, a funding-partnership track, or a
nurture path — then captures the lead cleanly and pushes it downstream.

Built the WordPress-native way: custom PHP, Custom Post Types, ACF-mappable meta,
a REST API, an Elementor widget, GTM/GA4 (client dataLayer + server-side
Measurement Protocol), and a CRM webhook with idempotency, retries, and an audit
log. No page-builder bloat; assets load only where the tool is used.

= Features =
* Self-select routing wizard — accessible (WCAG 2.1 AA), mobile-first, ~6KB CSS / ~7KB JS.
* Transparent scoring engine driven by an editable rules matrix (data/routes.php).
* Lead capture as the `blr_lead` CPT (ACF Pro field groups included).
* CRM webhook (Smrts / Zapier / Make) with HMAC signing, idempotency keys, retries, and a delivery log.
* Email workflow: internal alert + tailored prospect auto-responder + a `blr_lead_captured` hook.
* GA4: client dataLayer events at every step + a redundant server-side `generate_lead`.
* Categorized, filterable training-video repository (`blr_video` CPT + taxonomies) — no directory plugin.
* Elementor widgets ("Braven Lead Router" / "Braven Video Library") that reuse the same renderer.
* Routing Console admin page: funnel counts + CRM delivery log.

== Installation ==
1. Upload `braven-lead-router` to `/wp-content/plugins/` and activate.
2. Partner Leads → Settings: set the CRM webhook URL, GA4 IDs, GTM container, and booking/lead-magnet URLs.
3. Add the tool to any page with `[braven_lead_router]` or the Elementor widget.
4. Add the library with `[braven_video_library]`.

== Frequently Asked Questions ==

= Does it require ACF Pro? =
No. It stores typed meta and renders a native panel without ACF. If ACF Pro is
active, the shipped field groups (acf-json/) take over the UI, bound to the same
meta keys — no migration.

= How does it stay fast? =
Assets enqueue only on pages containing the tool; the routing matrix is OPcache'd
PHP config; the video library filters in the browser over server-rendered markup.

== Changelog ==
= 1.0.0 =
* Initial release.
