# Contributing

Thank you for helping maintain Vantigo Share Shop Cart for WooCommerce.

## Report a bug

Open a repository issue with a clear description, steps to reproduce, expected/actual behavior, and your WordPress, WooCommerce, PHP, theme, and optional Elementor versions. Include screenshots where useful. Remove customer details, credentials, private cart links, and other personal data from reports and logs.

Report security vulnerabilities privately; see [SECURITY.md](SECURITY.md).

## Make a change

1. Fork the repository and create a branch for one focused change.
2. Install the plugin in a separate WordPress test site with WooCommerce and `WP_DEBUG` enabled. Never experiment on a production shop.
3. Keep the public plugin name, slug, and text domain `vantigo-share-shop-cart-for-woocommerce`. Use the `vantshsh_` / `VANTSHSH_` prefix for plugin identifiers.
4. Preserve existing settings, shared links, shortcode attributes, and Elementor placements. Include a migration and tests if persistent identifiers change.
5. Validate inputs, escape outputs, check nonces on state-changing requests, and check capabilities for restricted actions. Use WordPress enqueue APIs and bundled assets.
6. Keep the debug and `.min.js` copies synchronized. This release ships matching readable JavaScript in both filenames and needs no Node build to install.
7. Update documentation and tests for behavior changes. Explain what changed, why, and how you verified it in your pull request.

## Automated checks

From the repository root, with PHP available:

```sh
php tests/review-contract.php
```

The isolated security harness uses test doubles plus WordPress core's real HTML filtering implementation. Set `WP_TEST_CORE_DIR` to a WordPress **core directory** containing `wp-includes/kses.php`. It does not load that site's configuration or database.

PowerShell:

```powershell
$env:WP_TEST_CORE_DIR = 'C:\path\to\wordpress'
php tests/security-regression.php
```

Linux/macOS:

```sh
WP_TEST_CORE_DIR=/path/to/wordpress php tests/security-regression.php
```

Also lint changed PHP/JavaScript and run the official WordPress **Plugin Check** plugin on the installed build. Tests using doubles do not replace real WordPress/WooCommerce tests.

## Functional verification

- Activation/deactivation and settings saving.
- Guest and logged-in cart sharing with simple and variable products.
- Classic cart, block cart, inline layout, floating button, and optional Elementor widget.
- Link copying, local QR generation, and configured sharing destinations without publishing unsolicited messages.
- Recipient confirmation, replace/append behavior, coupons, redirects, invalid nonces, and expired links.
- Existing settings, earlier shared links, and Elementor data across an upgrade.
- No new warnings or fatal errors with `WP_DEBUG` enabled.

## Releases

Only maintainers publish releases. Coordinate version/header/stable-tag/changelog changes. WordPress.org SVN is the release destination; development and review can take place in GitHub. Directory images live in the SVN repository's top-level `assets` directory, separately from the plugin's runtime `assets` directory.
