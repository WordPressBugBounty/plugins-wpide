=== WPIDE - File Manager & Code Editor ===
Contributors: XplodedThemes
Tags: file manager, code editor, code snippets, file editor, snippets
Requires at least: 5.9
Tested up to: 7.1
Stable tag: 3.6.4
Requires PHP: 7.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage WordPress files, edit code and add PHP, CSS, JavaScript and HTML snippets from your dashboard.

== Description ==

Manage your WordPress site from one workspace. WPIDE brings a **file manager, code editor and code snippets** together in your dashboard, so you can browse files, edit code and add functionality without switching to FTP or changing your theme. Start free, then add advanced developer tools as your needs grow.

= Video overview =

https://www.youtube.com/watch?v=wF0PUz8wfRM

= Code snippets for WordPress — free =

Add custom functionality, styles, scripts and markup without editing your theme's files. Keep your snippets in one place, activate or deactivate them, and choose where they run.

* Create PHP, JavaScript, CSS, HTML, Universal and Text snippets.
* Insert code across your site, in the header, body or footer, around posts, content, paragraphs and excerpts, or in the admin area. Use shortcodes and attributes to place snippets yourself.
* Set conditions for pages, post types, users, roles, URLs, referrers and taxonomies.
* Explore a library of 3,000+ ready-made snippets and 10 one-click packs. The library includes an offline fallback and does not require an account to browse.
* Generate code for post types, taxonomies, menus, cron jobs, schema markup and more with 32 generators.
* Import from WPCode, Code Snippets, Header Footer Code Manager, Post Snippets, Simple Custom CSS & JS and Woody, or export snippets to JSON.
* PHP syntax is checked before saving. Snippets that repeatedly fail can be deactivated automatically, and safe mode helps you recover access.
* Publish snippets to the public WPIDE library; link-only and private publishing require a paid plan.

= File manager — free =

Work with files and folders in WordPress without switching to an FTP client or hosting control panel.

* Browse a file tree, use the context menu and set a custom root path.
* Create, copy, move, duplicate, rename and delete files and folders.
* Upload files and folders with drag and drop, download in batches, create or extract ZIP archives, search folders and calculate folder sizes.
* Preview images in a gallery and play supported audio and video files.
* Use the WordPress Filesystem API for file operations.

= Code editor — free =

Edit files in your wp-content folder with a tabbed editor built on Ace.

* Open multiple files, with syntax highlighting, line numbers, matching parentheses, code folding, automatic indentation, find and replace, and keyboard shortcuts.
* Use WordPress and PHP autocomplete and function references.
* Check PHP syntax before saving; file backups and the recovery wizard can help restore a file after an error.
* Choose from editor themes and dark mode.

= WPIDE paid plans =

[Compare WPIDE plans](https://wpide.com/#pricing) for the current feature and site limits. Availability varies by plan; the features below are not all included in every paid plan.

* **Advanced snippets:** Add SCSS and Blocks snippets, insert at CSS selectors and in WooCommerce, Easy Digital Downloads or MemberPress, and target devices, schedules, cookies, store pages and more. Use smart tags, custom shortcode names, revisions, testing mode, page scripts, special files and error emails.
* **Conversion tracking:** Connect Meta, Google Analytics 4, Google Ads, TikTok, Pinterest, Snapchat and OpenAI Ads; configure click events, consent options and supported server-side and store events.
* **WPIDE Cloud and AI:** Keep a private snippet library, deploy to connected sites with rollback on eligible plans, and use the AI assistant to write, explain, improve or fix snippets. AI usage is subject to credits and plan limits; additional credits are available separately.
* **Developer tools:** Edit WordPress core files and wp-config.php in advanced mode. Recover unsaved drafts, compare changes with quick diff and use fullscreen editing. View, add, update and remove wp-config.php constants with duplicate checks.
* **Database manager:** Browse and create tables, manage rows, structure and indexes. Search and replace includes a preview and undo on eligible plans and supports serialized data.
* **Image editor and media optimizer:** Crop and resize images; add filters, frames, text, stickers, patterns and gradients. On eligible plans, optimize the media library, create WebP or AVIF copies where supported, optimize uploads, replace an image while keeping its URL, and restore backed-up originals.
* **Teams and networks:** Role-based access, geographic conditions, cloud team roles and multisite network snippets are available on eligible plans.

= External services and privacy =

The public snippet library periodically requests its catalog from WPIDE Cloud at cloud.wpide.com; a bundled catalog is available when it cannot be reached. Publishing a snippet or using optional cloud features sends the information needed for that action to WPIDE Cloud. When you use the AI assistant, your request and relevant snippet code are sent through WPIDE Cloud to an AI provider after your consent. Conversion tracking sends configured events to the providers you enable. WPIDE also uses Freemius for licensing and optional account services. See the [XplodedThemes privacy policy](https://xplodedthemes.com/privacy-policy/) and [Freemius privacy policy](https://freemius.com/privacy/).

== Installation ==

1. Install WPIDE from Plugins > Add New in your WordPress dashboard, or upload the plugin folder to `/wp-content/plugins/`.
2. Activate WPIDE from the Plugins screen.
3. Open WPIDE in the WordPress admin menu and choose File Manager, File Editor or Snippets.

== Frequently Asked Questions ==

= Is WPIDE free? =

Yes. File management, the code editor, code snippets, the public snippet library and code generators are included in the free plugin. Advanced features and tools are available on paid plans; see the [plan comparison](https://wpide.com/#pricing).

= Do I need FTP to use WPIDE? =

You can manage files from the WordPress dashboard without an FTP client when your hosting permissions allow it. Keep an independent backup and a way to access your files outside WordPress in case your site becomes unavailable.

= Can I add code snippets without editing my theme? =

Yes. Create a snippet in WPIDE, choose where it should run, and activate it. You can also use a shortcode to place supported snippets in content.

= How does WPIDE help me recover from a code error? =

WPIDE checks PHP syntax before saving files and snippets. It keeps file backups and provides a recovery wizard for supported fatal errors; snippets that repeatedly fail can be deactivated automatically. These protections cannot catch every problem, so keep independent backups and test changes before using them on a live site.

If WordPress is unavailable, you may need to restore a backup through your host's file manager, FTP or SSH. WPIDE stores file backups in the uploads directory under `wpide/backups`.

= Do I need an account for the snippet library? =

No account is needed to browse the public library. WPIDE periodically retrieves the catalog from WPIDE Cloud and falls back to a bundled copy when the service is unavailable. Publishing and optional private cloud features use a connected Freemius installation.

= Can I control permissions for newly created files and folders? =

WordPress supports `FS_CHMOD_DIR` and `FS_CHMOD_FILE` constants in wp-config.php. Consult your host before changing file permissions.

= Why do I see an unknown error when accessing files? =

Some hosts block direct access to PHP files. On Cloudways, users have resolved this by enabling Direct PHP File Access in application settings. Check with your host if the problem continues.

== Screenshots ==

1. Browse and manage files and folders in the WordPress dashboard.
2. Edit code with tabs, syntax highlighting and function references.
3. Preview images in the gallery.
4. Preview video and audio files.
5. Search for files and folders.
6. Restore a file with the recovery wizard.
7. Manage and filter code snippets from one screen.
8. Create a snippet and choose where it runs (some options shown require a paid plan).
9. Browse the public snippet library.
10. Generate WordPress code with guided forms.
11. Optimize your media library (available on eligible paid plans).

== Changelog ==

#### V.3.6.4 - 2026-11-08
- **security**: Enforced request verification across all WPIDE admin pages to prevent cross-site request forgery. Reported by Greg Otto.
- **security**: Hardened output escaping, request input sanitization, SQL placeholders and direct file access protection following a WordPress Plugin Check review.
- **update**: WPIDE now requires WordPress 5.9 or newer, matching the WordPress functions it uses.

#### V.3.6.2 - 2026-10-31
- **security**: On multisite, WPIDE and its actions are now limited to super administrators. A subsite administrator could previously get file permissions through the authenticated API and edit network-shared plugin files. Reported by Veer Gosai.
- **fix**: The Snippet Library's category sidebar now stays on screen while the results scroll, with a slim overlay-style scrollbar for long category lists.

#### V.3.6.1 - 2026-10-30
- **fix**: Open the file tree and database tables from the mobile editor, with clearer empty states and smoother sidebar transitions.
- **fix**: Improved mobile scrolling across WPIDE, including the AI assistant dialog.
- **fix**: Improved the media optimizer layout on mobile.
- **fix**: Kept the full release history available on the in-plugin changelog page.

#### V.3.6.0 - 2026-10-27
- Added free code snippets, a public snippet library, generators, import and export, and snippet safety tools.
- Added paid advanced snippets, conversion tracking, WPIDE Cloud, AI, database search and replace, and media optimization. Availability varies by plan.
- Improved recovery and compatibility with PHP 8.4 and 8.5.

#### V.3.5.11 - 2026-10-21
- **fix**: Plans and Pricing now shows "/ month" for monthly plans instead of "/ year", and no period for lifetime plans.
- **fix**: Corrected the annual "Normally" price, which showed the monthly price instead of 12 months of monthly billing.
- **update**: Annual and lifetime savings badges are now shown inside the billing cycle switcher, which stays on one row at any screen width.

See the [full changelog](https://plugins.svn.wordpress.org/wpide/trunk/changelog.txt) for detailed release notes and earlier releases.
