---
description: Coding guidelines for the muslim-prayer-times WordPress plugin.
applyTo: 'wp-content/plugins/muslim-prayer-times/**'
---

# Muslim Prayer Times plugin instructions

Before making changes to this plugin, read the design document at
[docs/DESIGN.md](../docs/DESIGN.md). It is the source of truth for architecture,
load order, the data model, the REST API, blocks/shortcodes, and the calculation
library boundary.

**Always update [docs/DESIGN.md](../docs/DESIGN.md) as part of any major change.** This
includes (but is not limited to) database schema changes, new or changed REST endpoints,
new blocks/shortcodes or attributes, changes to load order/bootstrap, new settings or
conventions, and any workflow changes (build, test, import/export, migrations). The design
doc update should be part of the same change, not a follow-up.

## Required conventions

- Prefix every PHP function with `muslprti_` (global namespace — avoid collisions).
- Start every PHP file with `if (!defined('ABSPATH')) exit;`.
- Use the text domain `muslim-prayer-times` for all i18n.
- Read/write settings through the single `muslprti_settings` option array (use
  `muslprti_get_option($key, $default)`); do not add ad-hoc options.
- Reference the table via `$wpdb->prefix . MUSLPRTI_IQAMA_TABLE`; never hardcode the name.
- Keep the `SalahAPI\` calculation library (`includes/salah-api/`) self-contained. Call it
  only through the factory helpers in `includes/salah-api-helper.php`, not directly from
  UI/admin/REST code.

## Security

- Every admin AJAX handler must verify a nonce and the `manage_options` capability. Reuse the
  existing nonce pattern in `settings.php` + `settings-ajax.php`.
- Sanitize input and escape output (`esc_url`, `esc_html`, `esc_attr`, `$wpdb->prepare`).
- REST endpoints under `muslim-prayer-times/v1` are public — keep them read-only and never
  expose non-public data.

## Common change recipes

See [docs/DESIGN.md](../docs/DESIGN.md) for step-by-step recipes. In short:

- **DB schema change:** add an idempotent migration in `includes/upgrade.php`, bump
  `MUSLPRTI_DB_VERSION`, and `update_option('muslprti_db_version', ...)`.
- **New setting:** add the field in `settings.php`, persist into `muslprti_settings`, and map
  it in `salah-api-helper.php` / `salah-api-mappings.php` if it feeds the calc engine.
- **New block attribute:** update the block's `block.php` + `block.js`, then mirror defaults in
  `includes/shortcodes.php`.
- **New admin action:** add a nonce in `settings.php`, a guarded handler in `settings-ajax.php`,
  and wiring in `js/admin.js`.

## Testing

PHPUnit config is at `wp-content/plugins/muslim-prayer-times/phpunit.xml`. Use the
`docker-compose up` / `docker-compose down` tasks for manual verification in a local WordPress.

Add unit tests for any new or changed functionality, and make sure all existing tests pass. 

## Linting (WordPress Coding Standards)

This project follows the WordPress Coding Standards **with one deliberate exception: PHP is
indented with 4 spaces, not tabs.** That exception is encoded in the custom ruleset
[wp-content/plugins/muslim-prayer-times/phpcs.xml.dist](../wp-content/plugins/muslim-prayer-times/phpcs.xml.dist)
(and editors pick up `.editorconfig`). **Always lint against this ruleset — never run
`--standard=WordPress` directly, as it would rewrite spaces back to tabs.**

Validate every change against the ruleset before considering it done:

1. **PHP CodeSniffer (WPCS).** Run `phpcs` with the project ruleset against the changed
   files (or the whole plugin). The `phpcs.xml.dist` lives in the plugin folder, so run
   `phpcs` from there (it is auto-discovered) or point at it explicitly:

   ```sh
   cd wp-content/plugins/muslim-prayer-times
   phpcs --standard=phpcs.xml.dist .
   ```

   If `phpcs`/WPCS is not installed, install it first:

   ```sh
   composer global require squizlabs/php_codesniffer wp-coding-standards/wpcs
   phpcs --config-set installed_paths ~/.composer/vendor/wp-coding-standards/wpcs
   ```

2. **Auto-fix** mechanical issues (spacing, alignment, etc.) with `phpcbf`, then re-run
   `phpcs`. The ruleset keeps space indentation, so `phpcbf` will **not** convert spaces to
   tabs:

   ```sh
   phpcbf --standard=phpcs.xml.dist .
   ```

3. **Resolve all reported errors and warnings.** Do not suppress with `phpcs:ignore` unless
   genuinely necessary (e.g. the existing escaped-output exceptions in
   `includes/rest-api.php`), and add a short justifying comment when you do.

4. **Plugin Check.** For release-affecting changes, run the WordPress Plugin Check plugin
   (`plugin-check`, bundled under `wp-content/plugins/`) against `muslim-prayer-times` from
   the WP admin (Tools → Plugin Check) and address its findings.

A change is not complete until `phpcs --standard=phpcs.xml.dist` passes cleanly (or only with
justified, documented ignores) and Plugin Check reports no new issues.