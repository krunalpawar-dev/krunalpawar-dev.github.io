# Krunal Pawar — portfolio & project enquiries

A responsive, server-rendered PHP portfolio, extending the existing static project without adding a frontend framework. It includes 7 main pages, 12 service pages, 6 case studies, 6 starter articles, a private content manager and a SQLite-backed enquiry pipeline.

## Live GitHub Pages website

The existing public site is hosted at **https://krunalpawar-dev.github.io/**. GitHub Pages serves the committed static `index.html` and directory pages. `.nojekyll` prevents the README from becoming the homepage. The repository is `krunalpawar-dev.github.io`, so the site is published at the domain root. Assets, navigation, canonical URLs and sitemap entries use that root. Do not restore the old `/portfolio/` prefix.

Regenerate the public pages after changing templates or `app/content.json`:

```powershell
python scripts/build-pages.py
python tests/test_pages.py
```

Commit the generated HTML along with the source and push `main`; the existing branch-based GitHub Pages deployment publishes it. The build uses a temporary database seeded only from the checked-in public content, never private leads or credentials.

The public contact form uses the original website's Formspree endpoint. Email and WhatsApp links remain available. The database, sessions and `/admin` work only on PHP hosting, not GitHub Pages. CMS edits on a separate PHP installation do not automatically update the checked-in public seed or Pages export. Formspree delivery still depends on the existing Formspree account; the build never sends test messages.

## Run locally

 Requires PHP 8.2+ with PDO SQLite, mbstring, JSON and sessions. GD and fileinfo are required for screenshot uploads. No Composer or Node dependencies are required to serve the site.

```powershell
php -S 127.0.0.1:8085 router.php
```

Open `http://127.0.0.1:8085`. Herd can serve the site at `http://portfolio.test`; the PHP router above is the reference development server. Root-relative links require hosting at the domain root, not a GitHub Pages subdirectory.

## Private storage and configuration

By default, the database, administrator password hash and uploaded screenshots live in `../portfolio-storage`, **outside this repository and web root**. Set `STORAGE_PATH` to a private, persistent writable directory in production. Keep this path outside the served root. Back up that directory, including the SQLite WAL files consistently (prefer SQLite's backup API or stop writes for a file backup). Do not put private data into the assets folder.

Set these environment variables on the PHP process / PHP-FPM pool:

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Canonical origin, e.g. `https://your-domain.com`; defaults to `http://portfolio.test` |
| `STORAGE_PATH` | Private persistent directory outside the web root |
| `CONTACT_EMAIL` | Enquiry recipient; defaults to the email in the original portfolio |
| `ADMIN_PASSWORD_HASH` | Optional password_hash() result; overrides the private password file |
| `MAIL_ENABLED` | `true` to enable notifications; disabled by default |
| `MAIL_FROM` | Verified sender mailbox for your hosting mail transport |

PHP-FPM may clear environment variables; explicitly configure them in the pool or hosting control panel. `.env` files are intentionally not automatically loaded.

## Content manager

Run `php scripts/create-admin.php` in an interactive terminal and enter a password of at least 14 characters. Input is visible in a basic terminal; alternatively provide `ADMIN_PASSWORD_HASH` through your hosting secret manager. No shared or default password is included. Then open `/admin`.

Manage projects, services, articles, categories, technologies, FAQs and testimonials with labeled fields. Lists use one item per line. Blog sections use a heading on the first line, body below, and `---` between sections. Content is plain text and escaped when rendered. Publish or save as a draft; drafts are excluded from public pages and the sitemap. Existing URL slugs are immutable to preserve links. Detail-page SEO overrides are optional. Articles include author details, a table of contents, related posts, internal links and Article structured data.

Project screenshots can be uploaded from the editor or selected from existing `/assets/` files. Supply meaningful captions. Do not upload client-sensitive or unapproved screenshots. The provided CRM assets are retained from the original portfolio. Other projects use clearly identified system diagrams until approved screenshots are supplied. Testimonial publishing requires confirming authenticity and permission.

Lead statuses are `new`, `contacted`, `qualified` and `closed`. The lead view also supports permanent deletion for privacy requests. Sessions expire after 30 minutes of inactivity. Contact and login endpoints are rate limited, and mutations require CSRF tokens. No contact data is exposed in public APIs.

## Email notifications

Enquiries are saved before attempting a notification. Configure the host's PHP `mail()` transport (for example a local MTA connected to your SMTP provider), a verified `MAIL_FROM`, and `MAIL_ENABLED=true`. Setting the environment variables alone does **not** configure an SMTP service.

Pending notifications remain visible in the admin panel. Retry them with:

```sh
php scripts/send-notifications.php
```

Schedule that command using the host scheduler if desired. It processes up to 50 pending notifications with a single-worker lock. `accepted` means the mail transport accepted the notification; it is not proof of delivery. Monitor the mail provider for bounces. Enquiries remain stored if sending fails. As with a typical transactional outbox, a process failure after mail acceptance but before the database update can cause a duplicate notification on retry; the enquiry reference enables identification.

No external email was sent during development. Verify the transport with a real enquiry before public launch.

## Deployment

Use a PHP host with HTTPS, persistent storage and configured mail delivery. GitHub Pages and Cloudflare Sites cannot execute this PHP backend. The included Apache `.htaccess` and `deploy/nginx.conf.example` protect internal paths and provide front-controller routing. Adapt PHP-FPM, domain and TLS settings to the host. Only `index.php`, explicitly allowed public assets and the retained Google verification file should be public entry points. Never expose scripts, database files, `.git`, or private storage. Set `display_errors=Off`, log errors privately, and set reasonable PHP upload and POST limits.

Before launch:

1. Set `APP_URL` to the real HTTPS origin and verify `/sitemap.xml` and `/robots.txt`.
2. Configure the private storage path and administrator password.
3. Configure and verify email delivery.
4. Review project descriptions, contributions and existing screenshot assets with the portfolio owner. Add approved screenshots and the Treeva public URL if available. No metrics, testimonials, awards or external project URLs have been invented.
5. Review the starter articles and privacy notice for the actual publication context.
6. Run a deployed Lighthouse audit. The application uses server-rendered HTML, local assets, minimal JavaScript, reduced-motion support and lazy screenshot loading; no measured Lighthouse or Core Web Vitals score is claimed.

## Validation

Internal public links use progressive navigation: the next HTML page is fetched and its content, navigation and SEO metadata are updated without reloading the document. Back/forward navigation restores scroll and project filters. Keyboard focus and a live announcement identify the new page. Downloads, external links, modified clicks, anchor links, admin pages and form submissions retain native behavior. Failed requests, timeouts or incompatible pages fall back to normal navigation. All pages remain complete static HTML and work without JavaScript.

The navigation regression suite uses JSDOM as a development-only dependency; nothing from `node_modules` is loaded by the website:

```sh
npm ci
npm run test:navigation
```

```powershell
python tests/test_site.py
```

The integration suite creates an isolated temporary database and server. It checks public routes, internal links, metadata/schema/sitemap, filtering, 404 behavior, enquiry persistence and validation, CSRF protection, admin authentication and draft/publish/edit/delete flows. It never enables mail. PHP syntax can also be checked with `php -l`.

## Project structure

- `index.php`: routes, SEO metadata, schemas and shared layout.
- `app/views/`, `app/partials/`: server-rendered reusable templates.
- `app/content.json`: initial content; imported only for a new database.
- `app/admin.php`: authenticated content and lead management.
- `app/contact-handler.php`: validation, persistence and notifications.
- `assets/css/portfolio.css`, `assets/js/portfolio.js`: responsive design and progressive enhancements.
- `router.php`: protected local development routing.
- `scripts/`: administrator setup, notification retry and seed-source generator.

The original unused template assets remain in the repository for compatibility; the new site does not load Bootstrap, jQuery, animation packages or remote font services. Old root HTML entry points are redirected or excluded by the supplied server configuration.
