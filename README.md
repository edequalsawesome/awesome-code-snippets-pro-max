# Awesome Code Snippets Pro Max

Add code snippets and header/footer scripts without wondering when we'll ask you to upgrade. That's it. All features included. No upsells. No paywalls. No shenanigans.

![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/License-GPLv3-green.svg)

---

## Two tools. Zero shenanigans.

Awesome Code Snippets Pro Max does exactly two things, and it does them well:

1. **Code Snippets Manager** - Create, edit, and manage custom code snippets (PHP, JavaScript, CSS) that hook into WordPress at the exact location you specify.

2. **Header & Footer Injector** - Two text boxes. One for code after `<head>`, one for code before `</body>`. Save. Done.

---

## Features

- Create unlimited code snippets (PHP, JS, CSS)
- Execute snippets in wp_head, wp_footer, or any custom WordPress hook
- Set priority for snippet execution order
- Enable/disable snippets with one click
- Syntax highlighting in the code editor (powered by WordPress's built-in CodeMirror)
- Simple header/footer script injection
- Native WordPress admin UI - no SaaS-style interfaces
- Proper security: nonces, capability checks, sanitization, escaping
- **Safe Mode** for recovering from bad snippets

## What This Plugin Does NOT Include

- No "Pro" version upsells
- No feature limitations
- No subscription nags
- No "rate us" popups
- No analytics or tracking
- No onboarding wizards
- No premium tiers

**This IS the complete version. That's it. That's all the versions.**

---

## Installation

1. Download the latest release from the [Releases page](https://github.com/edequalsawesome/awesome-code-snippets-pro-max/releases)
2. Upload the `awesome-code-snippets-pro-max` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Find "Code Snippets" and "Header & Footer" under the Tools menu
5. Do something rad with it

---

## Safe Mode

Added a bad snippet and now your site is broken? Don't panic.

### Option 1: URL Parameter (recommended)

While logged in as an admin, add `?acspm-safe-mode=1` to any URL on your site:

```
https://yoursite.com/wp-admin/?acspm-safe-mode=1
```

This temporarily disables all snippets and header/footer code so you can access the admin and fix or deactivate the problematic snippet.

### Option 2: wp-config.php Constant

If you can't log in at all, add this line to your wp-config.php (before "stop editing"):

```php
define( 'ACSPM_SAFE_MODE', true );
```

Once you've fixed the issue, remove the line from wp-config.php.

---

## FAQ

### Is there a Pro version?

You're looking at it. This is the Pro Max. There is no Ultra, no Enterprise, no Team edition. All features are included. Forever.

### Where do I find the settings?

Under Tools > Code Snippets and Tools > Header & Footer.

### Can I add Google Analytics with this?

Yes. Go to Tools > Header & Footer, paste your GA script in the header box, click Save. You're done.

### Can I add custom PHP that runs on a specific hook?

Yes. Go to Tools > Code Snippets, create a new snippet, set the type to PHP, choose "Custom Hook" as the location, enter the hook name (like `woocommerce_before_cart`), and save.

### Why is it called "Pro Max"?

Because if Apple can over-engineer their product names, so can we.

---

## Changelog

### 2026.02.12
- Initial release
- Code Snippets Manager with PHP, JS, and CSS support
- Header & Footer script injection
- Safe Mode for recovery when snippets break your site
- Full feature set included because that's how software should work

---

## License

GPLv3 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html) for details.
