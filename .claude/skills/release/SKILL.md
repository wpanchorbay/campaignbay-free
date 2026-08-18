---
name: release
description: Release checklist for CampaignBay — version bump, changelog, build & zip. Use when the user says "make a zip", "make a release zip", "update the version", or "is it ready to release".
---

# CampaignBay release process

- **Version bump:** fetch `https://api.wordpress.org/plugins/info/1.0/campaignbay.json`, take its `"version"`, bump the patch number by 1. Compare to the version already in `campaignbay.php`'s header — use whichever is higher, never decrease.
- **Update the version** in: `campaignbay.php` `Version:` header, `define( 'CAMPAIGNBAY_VERSION', ... )`, `package.json` `"version"`, `readme.txt` `Stable tag:`, `languages/campaignbay.pot` `Project-Id-Version`.
- **Changelog / Upgrade Notice:** draft new `== Changelog ==` and `== Upgrade Notice ==` entries in `readme.txt` from what was built this session, matching the existing tab-indented `*   Fix: ...` bullet style. Confirm the draft with the user before writing it.
- **Build & zip:** `./package.sh` now runs the whole pipeline — it reads the version from `campaignbay.php`'s header, calls `./build.sh <version>` itself (production + legacy JS builds, license headers), then stages the WordPress.org-submittable payload into `dist/` (no top-level slug folder — files sit at the zip root, matching the real `downloads.wordpress.org` layout) and zips to `campaignbay.zip`.
- **Ready to release** = version synced everywhere above, changelog/upgrade notice written, the zip builds cleanly. Mention the `wp-plugin-review` skill as an optional deeper security/WPCS check — don't run it automatically.
