=== Zeko Business Directory ===
Contributors: zeko
Tags: directory, business, listings, local, reviews
Requires at least: 5.8
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced business profiles and directory for the Zeko ecosystem.

== Description ==

Zeko Business Directory adds a business directory to your WordPress site.
Owners can create rich business profiles, list paid and free
services, collect reviews and ratings, accept followers, and get verified.
Independent services are powered by the Companion plugins shipped in the Zeko
ecosystem (Zeko Shop for products, Zeko QA for questions, Zeko Jobs for
openings, Zeko Pay for payments).

= Key features =

* Business profiles with hours, location, social links, gallery and staff
* Service and product listings (via Zeko Shop)
* Reviews & star ratings with moderation
* Followers, claims, ownership verification
* Plans (free / basic / pro / enterprise), featured & sponsored spots
* Directory with search, city filter, rating filter and sorting
* Owner portal and submission wizard
* Demo data generator for testing
* WordPress privacy tools (personal-data export & erase), rate limiting & spam guards
* Fully translatable (`.pot`), PHP 7.4 compatible

== Installation ==

1. Upload the `zeko-business` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. The plugin creates its pages and schema on activation. Visit
   `Settings > Permalinks` and click *Save Changes* to refresh rewrite rules.
4. Optionally activate the Companion plugins (Zeko Shop, Zeko QA, Zeko Jobs)
   to unlock products, Q&A and jobs features.

== Frequently Asked Questions ==

= Where does my business listing appear? =

Each active business gets a public profile page at
`/business/{slug}/`. The directory grid is available via the `[zbp_directory]`
shortcode or the generated Directory page.

= Why are my businesses pending? =

New submissions start in *pending* unless the submitting user has the
`publish_businesses` capability. Approve them under `Businesses > Approval`.

= How do I load test data? =

Use `Businesses > Demo Data` in the admin to generate businesses, services,
reviews, followers and media with one click, and remove them when done.

= Is personal data handled? =

Yes. Privacy exporters and erasers are registered under
`Tools > Erase Personal Data`, covering owned businesses, reviews, follows,
claims and verification requests.

== Dependencies ==

Zeko Business runs standalone. Optional "Companion" plugins unlock extra
features: Zeko Shop (products), Zeko QA (questions), Zeko Jobs (openings), and
Zeko Pay (payments).

== Privacy ==

Data stored includes business profiles, services, reviews, followers, claims
and verification requests, plus the IP address used for rate limiting. The demo
data generator creates and removes test businesses on command. Personal-data
export and erasure are provided through the registered privacy exporters and
erasers.

== External Services ==

Two optional outbound services exist; neither is contacted by default:

* **Nominatim (geocoding)** — When geocoding is used, an address string is sent
  to `https://nominatim.openstreetmap.org/search` and the returned coordinates
  are cached locally (30-day transient).
* **Akismet (spam checks)** — When the Akismet plugin is active and configured,
  review/submission content and an IP address are sent to Akismet's endpoint.
  Otherwise Akismet is never called.

Endpoint/field detail with file:line citations is in `REMOTE-SERVICES.txt` in
this plugin folder.

== Third-party resources ==

Zeko Business Directory bundles no third-party fonts, scripts, icon libraries,
or images (icons are inline SVG; the only image is the screenshot). All bundled
code is original Zeko work under GPL-2.0-or-later.

== Uninstall ==

Deleting the plugin removes its options. Business tables and content are only
dropped when `ZBE_UNINSTALL_DROP_DATA` is defined as `true` in `wp-config.php`
before deletion; this keeps accidental data loss from happening.

== Troubleshooting ==

* Listings not appearing? They stay *pending* until approved under
  `Businesses > Approval`.
* Rewrite rules 404? Visit `Settings > Permalinks` and click *Save Changes*.
* Demo data stuck? Use `Businesses > Demo Data` to clear it.

== Screenshots ==

1. Business directory dashboard with verified listings


== Changelog ==

= 1.0.0 =
* Initial release.
* Business profiles, services, reviews, followers, claims & verification.
* Plans, featuring, sponsorship, analytics, import/export.
* Demo data generator, privacy tools, rate limiting & spam guards.
* i18n (`.pot`), manual QA checklist, PHPCS configuration.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
