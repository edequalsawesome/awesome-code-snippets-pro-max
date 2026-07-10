=== Awesome Code Snippets Pro Max ===
Contributors: eDThomas
Tags: code snippets, header footer, custom code, analytics, scripts
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2026.07.001
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Add code snippets and header/footer scripts without wondering when we'll ask you to upgrade. That's it. All features included. No upsells. No paywalls. No shenanigans.

== Description ==

**Two tools. Zero shenanigans.**

Awesome Code Snippets Pro Max does exactly two things, and it does them well:

1. **Code Snippets Manager** - Create, edit, and manage custom code snippets (PHP, JavaScript, CSS) that hook into WordPress at the exact location you specify.

2. **Header & Footer Injector** - Two text boxes. One for code after `<head>`, one for code before `</body>`. Save. Done.

= Features =

* Create unlimited code snippets (PHP, JS, CSS)
* Execute snippets in wp_head, wp_footer, or any custom WordPress hook
* Set priority for snippet execution order
* Enable/disable snippets with one click
* Syntax highlighting in the code editor (powered by WordPress's built-in CodeMirror)
* Simple header/footer script injection
* Native WordPress admin UI - no SaaS-style interfaces
* Proper security: nonces, capability checks, sanitization, escaping

= What This Plugin Does NOT Include =

* No "Pro" version upsells
* No feature limitations
* No subscription nags
* No "rate us" popups
* No analytics or tracking
* No onboarding wizards
* No premium tiers

This IS the complete version. That's it. That's all the versions.

== Installation ==

1. Upload the `awesome-code-snippets-pro-max` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Find "Code Snippets" and "Header & Footer" under the Tools menu
4. Do something rad with it

== Frequently Asked Questions ==

= Is there a Pro version? =

You're looking at it. This is the Pro Max. There is no Ultra, no Enterprise, no Team edition. All features are included. Forever.

= Where do I find the settings? =

Under Tools > Code Snippets and Tools > Header & Footer.

= Can I add Google Analytics with this? =

Yes. Go to Tools > Header & Footer, paste your GA script in the header box, click Save. You're done.

= Can I add custom PHP that runs on a specific hook? =

Yes. Go to Tools > Code Snippets, create a new snippet, set the type to PHP, choose "Custom Hook" as the location, enter the hook name (like `woocommerce_before_cart`), and save.

= How is the PHP code executed? =

Active PHP snippets are written to a cached, content-addressed file in `wp-content/cache/` (protected against direct web access) and run via `include` - not `eval()`. "wp_head" and "wp_footer" PHP runs on the frontend only; "Everywhere" and "Custom Hook" PHP runs wherever the chosen hook fires, which can include wp-admin. Be careful with what you add - the same caution applies as editing your theme's functions.php file.

= Why is it called "Pro Max"? =

Because if Apple can over-engineer their product names, so can we.

= I added a bad snippet and now my site is broken! =

Don't panic. We've got you covered with **Safe Mode**.

**Option 1: URL Parameter (recommended)**
While logged in as an admin, add `?acspm-safe-mode=1` to any URL on your site:
`https://yoursite.com/wp-admin/?acspm-safe-mode=1`

This temporarily disables all snippets and header/footer code so you can access the admin and fix or deactivate the problematic snippet.

**Option 2: wp-config.php Constant**
If you can't log in at all, add this line to your wp-config.php (before "stop editing"):
`define( 'ACSPM_SAFE_MODE', true );`

Once you've fixed the issue, remove the line from wp-config.php.

== Screenshots ==

1. Code Snippets list view
2. Adding a new code snippet
3. Header & Footer settings page

== Changelog ==

= 2026.07.001 =
* Fix: PHP snippet cache now uses a unique temp filename per writer, fixing a race where concurrent requests could tear a half-written cache file or skip execution after a save
* Fix: Snippet updates now verify the target is actually a snippet before writing, and create/update/toggle/delete report a real error instead of a false success when the operation fails
* Fix: Cache is now invalidated when snippets are changed outside the plugin's own screens (WP-CLI, imports, direct writes), so disabled code stops running promptly
* Fix: Leading `<?php` tag stripping is now case-insensitive
* Fix: Safe Mode notice now gives correct exit instructions for the wp-config constant path and links to the right Tools screen
* Fix: Fresh installs no longer run the upgrade migrations unnecessarily; the autoload migration now uses a value understood by all supported cores and clears the stale options cache
* Accessibility: Status toggle accessible name now includes its visible label, responds to the Space key, and has a larger hit target; form field descriptions are associated via `aria-describedby`
* Accessibility/UX: Editing a snippet deleted in another tab now shows a notice instead of a blank form; duplicate form submits are blocked
* Docs: Corrected the PHP-execution FAQ (cached `include`, not `eval()`; "Everywhere"/custom hooks can run in wp-admin)

= 2026.04.04 =
* Security: All code paths (snippets, header/footer) now require `unfiltered_html` capability, closing a multisite privilege escalation gap
* Security: PHP snippet cache moved from `uploads/` to `wp-content/cache/` to prevent web-accessible executable files on Nginx
* Fix: `clear_cache()` now removes stale PHP cache files when snippets are deleted, deactivated, or change type
* Performance: Consolidated snippet cache with single transient, PHP snippets use content-addressed cached files
* Accessibility: `role="alert"` on admin notices, descriptive `aria-label` on all actions, CodeMirror keyboard trap fix
* Admin JS extracted to properly enqueued `assets/admin.js`

= 2026.02.12 =
* Initial release
* Code Snippets Manager with PHP, JS, and CSS support
* Header & Footer script injection
* Safe Mode for recovery when snippets break your site
* Full feature set included because that's how software should work

== Upgrade Notice ==

= 2026.02.12 =
Initial release. There is no upgrade path because there's nothing to upgrade to. This is it. kthxbai.
