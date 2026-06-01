# Muslim Prayer Times — Design Document

This document orients agents making changes to the `muslim-prayer-times` WordPress plugin.
It describes the architecture, conventions, key files, and data model. Keep it updated when
structure changes.

> Plugin root: `wp-content/plugins/muslim-prayer-times/`
> All paths below are relative to that root unless noted.

## 1. Purpose

A WordPress plugin to calculate, store, and display Islamic prayer (salah) times and iqama
times. It supports:
- Astronomical prayer-time calculation (via the bundled `SalahAPI` PHP library).
- Storing per-day athan/iqama times in a custom DB table.
- Displaying times through Gutenberg blocks and equivalent shortcodes.
- Importing/exporting data (CSV and SalahAPI JSON) and a public REST API.

## 2. Conventions (follow these when adding code)

- **Function prefix:** all PHP functions are prefixed `muslprti_`. Keep using it to avoid
  collisions (WordPress global namespace).
- **Text domain:** `muslim-prayer-times` for all i18n (`__()`, `_e()`).
- **Guard clause:** every PHP file starts with `if (!defined('ABSPATH')) exit;`.
- **Settings storage:** a single option array `muslprti_settings` (read via
  `muslprti_get_option($key, $default)` or `get_option('muslprti_settings', [])`).
- **DB version:** stored in option `muslprti_db_version`; current schema version constant is
  `MUSLPRTI_DB_VERSION` in [muslim-prayer-times.php](../wp-content/plugins/muslim-prayer-times/muslim-prayer-times.php).
- **Table constant:** `MUSLPRTI_IQAMA_TABLE` (= `muslprti_iqama_times`); always prefix with
  `$wpdb->prefix`.
- **Namespaced library:** the calculation library lives under the `SalahAPI\` namespace in
  `includes/salah-api/`. The rest of the plugin is non-namespaced procedural code.
- **AJAX security:** every admin AJAX handler verifies a nonce and `manage_options`
  capability. Reuse the existing nonce pattern when adding handlers.

## 3. Bootstrap / Load Order

Entry point [muslim-prayer-times.php](../wp-content/plugins/muslim-prayer-times/muslim-prayer-times.php)
wires everything together in this order:

1. `includes/salah-api-helper.php` — loads the `SalahAPI\` library classes and exposes
   factory helpers that build library objects from `muslprti_settings`.
2. `includes/rest-api.php` — registers public REST endpoints.
3. Defines constants `MUSLPRTI_IQAMA_TABLE`, `MUSLPRTI_DB_VERSION`.
4. `includes/upgrade.php` — schema migrations.
5. Activation hook `muslprti_plugin_activate()` creates the table via `dbDelta()`.
6. `plugins_loaded` → `muslprti_check_for_upgrades()` runs migrations when the stored DB
   version is older than `MUSLPRTI_DB_VERSION`.
7. Registers block category `muslim-prayer-times`.
8. `settings.php` — admin settings page.
9. Each block's `index.php` + `block.php` (daily, monthly, live).
10. `includes/shortcodes.php` — shortcode wrappers that delegate to block render functions.

## 4. Component Map

| Area | Files | Responsibility |
|------|-------|----------------|
| Bootstrap | `muslim-prayer-times.php` | Constants, activation, upgrade trigger, includes |
| Settings UI | `settings.php`, `js/admin.js`, `assets/css/admin-styles.css` | Options page, enqueue, nonces |
| Admin AJAX | `settings-ajax.php` | Geocode, generate, export DB, import preview/import, hijri preview, SalahAPI import |
| Calc library | `includes/salah-api/` (`SalahAPI\` namespace) | Prayer/iqama calculation engine |
| Library glue | `includes/salah-api-helper.php` | Build `Location`/`CalculationMethod`/etc. from settings |
| Import/export | `includes/salah-api-importer.php`, `includes/salah-api-mappings.php` | SalahAPI JSON ↔ settings mapping |
| REST API | `includes/rest-api.php` | Public endpoints (see §6) |
| Helpers | `includes/helpers.php` | Time/DST utilities |
| Hijri | `includes/hijri-date-converter.php` | Gregorian↔Hijri conversion |
| Migrations | `includes/upgrade.php` | Schema upgrades |
| Blocks | `blocks/{daily,monthly,live}-prayer-times/` | Gutenberg blocks + frontend assets |
| Shortcodes | `includes/shortcodes.php` | Shortcode → block render delegation |
| Tests | `tests/`, `phpunit.xml` | PHPUnit tests (plugin-local) |

## 5. Data Model

Table `{$wpdb->prefix}muslprti_iqama_times`, primary key `day` (date):

```
day            date    (PK)
fajr_athan     time
fajr_iqama     time
sunrise        time     (added in DB v1.1 migration)
dhuhr_athan    time
dhuhr_iqama    time
asr_athan      time
asr_iqama      time
maghrib_athan  time
maghrib_iqama  time
isha_athan     time
isha_iqama     time
created_at     datetime
updated_at     datetime (ON UPDATE CURRENT_TIMESTAMP)
```

- One row per calendar day. Times stored as local wall-clock `time` values.
- DST handling is explicit in `includes/helpers.php` (`muslprti_time_to_minutes`,
  `muslprti_normalize_time_for_dst`, etc.) — be careful when changing stored vs. displayed time.

### Schema changes
Add a migration in `includes/upgrade.php` (idempotent — check `INFORMATION_SCHEMA.COLUMNS`
before `ALTER TABLE`), bump `MUSLPRTI_DB_VERSION`, and call `update_option('muslprti_db_version', ...)`.
The upgrade runs automatically on `plugins_loaded`.

## 6. REST API

Namespace `muslim-prayer-times/v1`, all public (`permission_callback => '__return_true'`):

| Route | Method | Purpose |
|-------|--------|---------|
| `/salah-api` | GET | Full prayer-time config in SalahAPI JSON format |
| `/last-updated` | GET | Timestamp of latest data update |
| `/prayer-times-csv` | GET | CSV of stored times; optional `fromDate`/`toDate` (YYYY-MM-DD) |

Registered in [rest-api.php](../wp-content/plugins/muslim-prayer-times/includes/rest-api.php)
via `rest_api_init`. These endpoints are public — keep them read-only and avoid leaking
non-public data.

## 7. Blocks & Shortcodes

Three blocks, each in `blocks/<name>/` with the same shape:
- `index.php` — registers/enqueues editor + frontend assets (`enqueue_block_editor_assets`,
  `wp_enqueue_scripts`).
- `block.php` — `register_block_type('prayer-times/<name>', ...)` with `api_version => 3` and
  a PHP `render_callback`.
- `block.js` — editor UI.
- `style.css` — frontend/editor styles. Some blocks also inject dynamic inline CSS via a
  registered handle with no file (e.g. `*-dynamic-style`).
- Extra frontend JS: daily has `carousel.js`; live has `frontend.js`.

Blocks:
- **daily-prayer-times** — today's times, optional carousel.
- **monthly-prayer-times** — month table view.
- **live-prayer-times** — auto-updating display (`frontend.js`).

Block names use the `prayer-times/` namespace; the editor category is `muslim-prayer-times`.

Shortcodes in `includes/shortcodes.php` reuse block render functions. The helper
`muslprti_convert_shortcode_atts_to_block_atts()` coerces string attrs to bool/int/float so a
shortcode and its block share defaults. When adding a block attribute, update both the block
registration and the shortcode defaults.

## 8. Calculation Library (`includes/salah-api/`)

Self-contained `SalahAPI\` namespace. Key classes:
- `Location`, `CalculationMethod`, `IqamaCalculationRules`, `PrayerCalculationRule`,
  `PrayerCalculationOverrideRule`, `JumuahRule`, `JumuahLocation`.
- `Calculations/` — `Builder`, `PrayerTimes`, `Method`, `IqamaCalculator`, `TimeHelpers`,
  `HijriDateConverter`.
- `SalahAPI.php` — top-level facade; `DailyPrayerTimes.php`, `Info.php`, `Contact.php`,
  `CsvUrlParameters.php` are DTOs for the SalahAPI document format.

Do not call these classes directly from UI/admin code. Go through the factory helpers in
`includes/salah-api-helper.php` (e.g. `muslprti_create_location`,
`muslprti_create_calculation_method`) which translate `muslprti_settings` into library objects
and handle value mapping (see `salah-api-mappings.php`).

## 9. Common Change Recipes

- **Add a setting:** add field to `settings.php`, persist into the `muslprti_settings` array,
  read with `muslprti_get_option()`, and if it feeds the calculation engine, map it in
  `salah-api-helper.php` / `salah-api-mappings.php`.
- **Add an admin action:** add a nonce in `settings.php`'s `wp_localize_script`, add a handler
  in `settings-ajax.php` (verify nonce + `manage_options`), wire it in `js/admin.js`.
- **Add a block attribute:** edit the block's `block.php` registration and `block.js` editor
  UI, then mirror defaults in `includes/shortcodes.php`.
- **Add a REST field/endpoint:** edit `includes/rest-api.php`; keep endpoints public and
  read-only.
- **Change the DB schema:** see §5 (migration + version bump).

## 10. Testing

PHPUnit config: `wp-content/plugins/muslim-prayer-times/phpunit.xml`. Workspace-level tests
live in `tests/` with `vendor/bin/phpunit`. Run the local Docker WordPress via the
`docker-compose up` / `docker-compose down` tasks for manual verification.
