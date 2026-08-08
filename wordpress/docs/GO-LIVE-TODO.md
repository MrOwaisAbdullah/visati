# Visati WordPress — Go-Live Checklist

Consolidated pre-launch checklist for the Hostinger deploy (T070–T072). Source: `specs/002-wp-migration/tasks.md`, `specs/002-wp-migration/plan.md` Phase 6, and the security review + fixes logged in `wordpress/docs/migration-log.md` (session: "Pre-deploy security review + rate limiting", 2026-07-23).

## 1. Content — pages still to build

Re-audited directly against the live DB (`_elementor_data` + `post_content` per page) on 2026-07-24 — this list was stale, several items were already done and not reflected here.

- [x] T045 — Homepage: real Elementor page (`_elementor_data` present, ~28.8KB), confirmed live
- [x] T053 — Contact page: `[visati_contact_page]` shortcode (not Elementor, but fully functional — settings-driven contact details + map, verified live)
- [x] About page: real Elementor page, confirmed live
- [x] Careers page: hero + `[visati_jobs]` shortcode (not Elementor), built + verified 2026-07-24
- [x] Blog page: hero + `[visati_blog]` shortcode (not Elementor), built + verified 2026-07-24
- [x] Apply page: `[visati_apply_form]` shortcode, fully functional (extensively tested this session)
- [x] Track Application page: dedicated `page-track.php` template (blank `post_content` is expected — the template renders independently), confirmed live and working
- [x] FAQ page: small content length but functional (`[visati_faq]` shortcode)
- [x] T041 — Visa Types: **correction** — the static Page (ID 63) is an unused duplicate; `/visa-types/` actually resolves to the `visati_visa_type` CPT archive (`archive-visa_type.php`, since the CPT's rewrite slug is also `visa-types`), which genuinely renders the visa cards. Confirmed live (48.5KB body, archive markup present). My earlier "empty" finding was checking the wrong thing (the orphaned static page, not the archive template actually serving that URL).
- [x] Services page: built 2026-07-24 — hero (`[visati_services_hero]`, reusing the About page's navy hero visual treatment) + 6-item feature grid (`[visati_services]`, ported from the original Next.js `services/page.tsx`: Tourist Visas, Express Processing, Document Assistance, Visa Extensions, Business Visas, 24/7 Support) + CTA (`[visati_services_cta]`, Apply Now + WhatsApp Us, WhatsApp number sourced dynamically from Settings). Verified live at `/services/`.
- [x] T046 — Global-reach: `[visati_global_reach]` shortcode, placed on the homepage via an Elementor Shortcode widget (user-placed 2026-07-24). Fully settings-driven (heading/description/CTA/4 stat number+label pairs editable under Visati Settings), map+arcs+pins render as one inlined SVG so they scale together at every viewport, stat icons match the original design (Users/CheckCircle/Star/Globe). Confirmed live.

**All content is now built.** Section 1 is fully complete as of 2026-07-24 — remaining work is deploy prep (sections 2–5 below).

## 2. Security — pre-live fixes

- [x] Rate limiting on all public write endpoints (`applications`, `documents`, `inquiries`, `checkout-session`) — `visati_rate_limit()` in `includes/helpers.php`
- [x] Inquiries controller sanitize review — confirmed already correct (`sanitize_callback` per field), false positive, no change needed
- [x] Skip-link + mobile sticky-header CSS fixes
- [ ] Add to Hostinger's `wp-config.php` at deploy time:
  ```php
  define( 'DISALLOW_FILE_EDIT', true );       // no plugin/theme editor in wp-admin
  define( 'DISALLOW_UNFILTERED_HTML', true ); // stricter content escaping
  define( 'FORCE_SSL_ADMIN', true );          // HTTPS for /wp-admin
  ```
- [ ] Confirm `WP_DEBUG` / `WP_DEBUG_LOG` are `false` on the live `wp-config.php` (they were correctly `false` locally — just don't flip them on for prod debugging and forget to flip back)
- [ ] Add the `VISATI_*` secret constants directly to the live `wp-config.php` (Stripe live keys, R2 credentials, Resend API key) — there's no Next.js repo on Hostinger for `VISATI_ENV_FILE` to read, so the dotenv fallback path doesn't apply there

### 2a. Harden `wp-config.php` itself (do this once, applies regardless of what's in it)

- [ ] Restrict file permissions — only the owning user needs to read it:
  ```bash
  chmod 440 ~/domains/visati.visati.ae/public_html/wp-config.php
  ```
- [ ] Block direct HTTP access via `.htaccess` — add near the top of `public_html/.htaccess`, above the WordPress block:
  ```apache
  <Files wp-config.php>
  Require all denied
  </Files>
  ```
- [ ] **Strongest option** — move `wp-config.php` one directory above `public_html`. WordPress's `wp-load.php` automatically checks the parent directory, so this takes the file fully outside anything a URL could ever reach:
  ```bash
  mv ~/domains/visati.visati.ae/public_html/wp-config.php ~/domains/visati.visati.ae/wp-config.php
  ```
- [ ] Turn on Wordfence's **file integrity monitoring** (in addition to its brute-force/WAF features already planned below) — alerts if `wp-config.php` or any core file changes unexpectedly, a reliable "you've been compromised" early signal
- [ ] Confirm UpdraftPlus (or whatever runs backups) never drops a `.sql`/`.zip` archive inside a publicly-servable path with a guessable name — a stray backup file in `public_html` leaks every secret in it regardless of how well `wp-config.php` itself is locked down

### 2b. Switching Stripe from sandbox to live (config-only — no code changes needed)

The code has zero test/live branching — `class-stripe-payments.php` just uses whatever `STRIPE_SECRET_KEY`/`VISATI_STRIPE_SECRET_KEY` resolves to, and builds Checkout Sessions with inline `price_data` rather than referencing persistent Stripe Products, so a new visa type's price works at checkout automatically with no Stripe Dashboard product setup.

- [ ] Switch the Stripe Dashboard to **Live mode**, copy the live secret key (`sk_live_...`)
- [ ] **Developers → Webhooks → Add endpoint** (while in Live mode — test and live webhooks are separate, this can't reuse a test-mode one):
  - URL: `https://visati.visati.ae/wp-json/visati/v1/webhooks/stripe` (update to the apex domain once the DNS cutover happens)
  - Event destination scope: **"Your account"** (not Connected accounts — this isn't a Stripe Connect platform)
  - API version: leave the Dashboard's current default — the webhook handler does a raw `json_decode()` and only reads `event.type`/`event.data.object`, so it isn't version-sensitive
  - Events: search for and select only `checkout.session.completed` and `payment_intent.payment_failed` — those are the only two `class-webhooks-controller.php` handles; don't use "Select all"
  - Copy the live signing secret (`whsec_...`) shown after creating it
- [ ] Update `wp-config.php` with both live values:
  ```php
  define( 'VISATI_STRIPE_SECRET_KEY',     'sk_live_...' );
  define( 'VISATI_STRIPE_WEBHOOK_SECRET', 'whsec_...' );
  ```
- [ ] Test with **one real transaction** (live mode doesn't accept Stripe's test card numbers) and refund it afterward. Confirm: the webhook's "Recent deliveries" shows `200`, the application flips to `submitted`, a row lands in `wp_visati_payments`, and the confirmation email sends
- [ ] Confirm AED is enabled as a settlement currency on the account (Settings → Payments/Payouts) — the code always sends `currency: 'aed'`
- [ ] If your client's Stripe account was shared via a team invite rather than account ownership, confirm the invited role (Settings → Team) is **Developer** or **Admin** — restricted roles (Analyst/Support) can't reveal secret keys or manage webhooks
- [ ] Never pass the live secret key or webhook signing secret through plain chat/email — use a password manager, or have the account owner add it to `wp-config.php` directly over SSH

## 3. Deploy (T070) — Local → Hostinger migration, step by step

**Safety principles for this whole section — read before running anything:**
1. Every command in 3.1–3.5 is a **read/export from Local, or a write to the *new*, empty Hostinger site** — nothing here can modify or delete the working local dev site.
2. Take the local DB export (3.1) **first**, before anything else, and keep it (plus the theme/plugin zips) until section 4's smoke test has fully passed live on Hostinger. Don't delete or overwrite the local site as "cleanup" before then.
3. `wp search-replace` only ever runs against the **Hostinger** database, on a copy of the data, after import (3.7) — never against the local site. There's no reason to run it there, but this is the single most common way people accidentally wipe out a working local site's URLs, so it's called out explicitly.
4. Verify everything on Hostinger's raw subdomain/temporary URL for as long as possible before any DNS/domain cutover — no live-traffic pressure while things are still being checked.

### 3.1 — Export the local database (read-only, safe to re-run anytime)

Run this from Local's own **"Open Site Shell"** (right-click the `visati` site in the Local app → **Open Site Shell**), not a bare terminal — Local's shell pre-configures `wp`/`php`/`mysqldump` on `PATH`; invoking WP-CLI any other way on this machine fails with `'mysqldump' is not recognized...` because the bundled MySQL bin directory (`mysql-8.4.0`) isn't on that process's `PATH` (confirmed while writing this doc).

From the Site Shell, inside `C:\Users\K TECH\Local Sites\visati\app\public`:

```powershell
wp db export "C:\Users\K TECH\Desktop\visati-backup-2026-07-24.sql"
```

- Export it **outside** the site folder (Desktop or a dated backups folder), so it never ends up inside a `wp-content` zip by accident.
- Current reference: 27 tables (WP core + all `wp_visati_*` custom tables). Sanity-check the export completed by confirming the `.sql` file size is nonzero and its last lines say `UNLOCK TABLES;`.
- This is read-only against the local DB — safe to re-run as many times as needed.

### 3.2 — Package the theme + plugin only

Only `wp-content/themes/visati` and `wp-content/plugins/visati-core` travel to Hostinger. Hostinger's one-click WordPress install already provides WordPress core; `uploads` is transferred separately (3.5) since it's large and shouldn't be bundled with code.

In File Explorer:
- Right-click `wp-content\themes\visati` → **Send to → Compressed (zipped) folder** → `visati-theme.zip`
- Right-click `wp-content\plugins\visati-core` → **Send to → Compressed (zipped) folder** → `visati-core.zip`

Do **not** zip the whole `wp-content` folder — that would also sweep in `uploads` and any other plugin installed locally for testing that shouldn't ship to production.

### 3.3 — Provision Hostinger

- [ ] hPanel → **Websites → Add Website** → create the subdomain (or final domain) via the one-click WordPress installer, PHP 8.2+, force HTTPS on
- [ ] Note the temporary/subdomain URL Hostinger assigns — this is the target for the search-replace in 3.7
- [ ] hPanel → **Advanced → SSH Access** — enable it and note host/port/username. If your plan doesn't include SSH, every step below has a File Manager/phpMyAdmin fallback

### 3.4 — Upload + activate theme/plugin

- Upload `visati-theme.zip`/`visati-core.zip` via SFTP or hPanel File Manager into `public_html/wp-content/themes/` and `public_html/wp-content/plugins/`, then extract in place (File Manager has a built-in "Extract" action; via SSH: `unzip visati-theme.zip -d ../`)
- wp-admin → **Appearance → Themes** → activate **Visati**
- **Plugins** → activate **Visati Core**; install companions: Elementor, EMCP, WP Mail SMTP, Rank Math, Wordfence, LiteSpeed Cache, UpdraftPlus

### 3.5 — Copy `wp-content/uploads`

Carries all media **and** Elementor's cached CSS — skipping it silently breaks every image and Elementor's page styling.

- SFTP: upload the local `uploads` folder wholesale into `public_html/wp-content/uploads/`
- Faster if SSH is available on both ends: zip locally, upload the single archive, `unzip` server-side instead of thousands of individual SFTP transfers

### 3.6 — Import the database (the step to be most careful with)

1. **First**, on Hostinger, export the just-installed *empty* database (hPanel → Databases → phpMyAdmin → **Export**) as a pre-import snapshot — even though it's only the installer's default tables, this gives you something to restore if the import below goes wrong.
2. Upload your local export (3.1) to the server, then import it — this **replaces** the fresh install's tables with the real content:
   - SSH + WP-CLI: `wp db import visati-backup-2026-07-24.sql` (run from the Hostinger WordPress root)
   - phpMyAdmin: Databases → your DB → **Import** tab → select the `.sql` → Go. If it exceeds the upload size limit, `gzip` it first — phpMyAdmin can import `.sql.gz` directly
3. **Delete the uploaded `.sql`/`.sql.gz` from the server immediately after a successful import** — until then it's the entire database, secrets included, sitting in a web-accessible directory

### 3.7 — Point URLs at the new domain

Run only against the Hostinger database, after the import above:

```bash
wp search-replace 'http://visati.local' 'https://<your-hostinger-domain>' --all-tables --precise
```

(Confirmed current local `siteurl`/`home` are both exactly `http://visati.local`.)

Without SSH/WP-CLI, a raw SQL find-and-replace is **not** safe here — URLs are serialized inside PHP arrays in some rows (e.g. Elementor's `_elementor_data` postmeta), and changing string lengths without updating the serialized length prefix corrupts those rows. Use the [Better Search Replace](https://wordpress.org/plugins/better-search-replace/) plugin instead (handles serialization correctly; remove it again after use).

### 3.8 — Post-import housekeeping

- [ ] Elementor → **Tools → Regenerate CSS & Data** once (cached file hashes/paths from local go stale after a host move)
- [ ] Re-apply the Hello Elementor `reset.css` pink-button fix by hand — it was a direct local file edit, never tracked in git (see `migration-log.md` "P9"). On the new install:
  ```bash
  sed -i 's/#c36/#C9A84C/g' wp-content/themes/hello-elementor/assets/css/reset.css
  ```
  then reapply the button pill-shape/hover/form-field refinements described in that log entry (the `sed` alone only fixes the color, not the shape/hover polish).
- [ ] Re-point EMCP to the Node.js proxy transport (HTTPS + Application Password) — no WP-CLI stdio on shared hosting
- [ ] Add security constants to `wp-config.php` (see section 2 above)
- [ ] Add the `VISATI_*` secret constants to `wp-config.php` — there's no `.env.local`/Next.js repo on Hostinger, so `class-settings.php`'s dotenv fallback never fires there; these must be defined directly (get actual values from `.env.local` locally or each service's dashboard — never paste them into this checklist file):
  ```php
  define( 'VISATI_STRIPE_SECRET_KEY',     '...' ); // Stripe live secret key
  define( 'VISATI_STRIPE_WEBHOOK_SECRET', '...' ); // from the webhook registered below
  define( 'VISATI_R2_ACCOUNT_ID',         '...' );
  define( 'VISATI_R2_ACCESS_KEY',         '...' );
  define( 'VISATI_R2_SECRET_KEY',         '...' );
  define( 'VISATI_R2_BUCKET',             '...' );
  define( 'VISATI_RESEND_API_KEY',        '...' ); // omit if using WP Mail SMTP's own Resend integration instead
  ```
- [ ] Switch Stripe to live keys; register the production webhook endpoint (`/wp-json/visati/v1/webhooks/stripe`) in the Stripe dashboard
- [ ] Enable Wordfence + schedule UpdraftPlus backups

### 3.9 — Verify before considering the migration done

- [ ] Spot-check row counts on a couple of custom tables to catch a partial import, e.g. `wp db query "SELECT COUNT(*) FROM wp_visati_applications"` on both local and Hostinger — should match
- [ ] Load homepage, About, Services, Careers, Blog, Apply, Contact, FAQ, Visa Types on the new domain and compare against local
- [ ] Only after section 4's full smoke test passes live: archive (don't delete) the `.sql` export and zips somewhere safe, and keep treating the local Local-by-Flywheel site as the ongoing dev/staging environment — not something to tear down

### 3.10 — Ongoing code-only redeploys (after the initial migration above)

Once the site is live, most future changes are theme/plugin code only (no DB/content changes) — a much lighter operation than section 3's full migration. This is what actually gets used session to session; full details + lessons learned live in `migration-log.md`'s "Quick reference: code-only redeploy in 3 commands" entry (2026-08-08). `wordpress/deploy-local.ps1` automates this (`-ThemeOnly`/`-PluginOnly` flags); run these 3 by hand when only running from WSL (no SSH key-based auth to this host from there):

1. **Build the zip** (PowerShell) — swap `plugin`/`visati-core` for `theme`/`visati` to deploy the theme instead:
   ```powershell
   Compress-Archive -Path "C:\Users\K TECH\Local Sites\visati\app\public\wp-content\plugins\visati-core\*" -DestinationPath "D:\GIAIC\Visati\wordpress\docs\deploy-plugin.zip" -Force
   ```
   Re-verify the zip's contents (entry count, version string, a symbol unique to the change) right before uploading — a zip can silently go stale between build and upload.

2. **Upload**:
   ```powershell
   scp -P 65002 "D:\GIAIC\Visati\wordpress\docs\deploy-plugin.zip" u438130270@72.62.214.133:~/domains/visati.visati.ae/public_html/
   ```

3. **Extract + permissions + cleanup + cache flush, chained in one SSH call**:
   ```powershell
   ssh -p 65002 u438130270@72.62.214.133 "cd ~/domains/visati.visati.ae/public_html && unzip -o deploy-plugin.zip -d wp-content/plugins/visati-core/ && find wp-content/plugins/visati-core -type d -exec chmod 755 {} \; && find wp-content/plugins/visati-core -type f -exec chmod 644 {} \; && rm -f deploy-plugin.zip && /usr/local/bin/wp cache flush && echo DONE"
   ```
   Confirm the trailing `echo DONE` appears in the output — its absence means the chain stopped partway (has happened: a zip left behind on the server because output was truncated before `rm` ran). Always finish with a live `curl` check against `/wp-json/visati/v1/status` (version bump) plus a content marker specific to the change — "no error shown" alone isn't confirmation.

## 4. Smoke test (T071)

- [ ] Full apply flow end-to-end on the live subdomain: form → R2 document upload → Stripe Checkout (live or test mode first) → webhook → status flips to `submitted` → confirmation email sent → `VIS-YYYY-XXXXXX` ID generated
- [ ] Tracking page: look up the application by ID, confirm timeline renders
- [ ] Admin: status update flow, inquiry view, document view
- [ ] Contact form submission → admin notification email arrives
- [ ] Rate limiting doesn't false-positive under normal single-user testing (429 only after exceeding the bucket's limit)
- [ ] Footer renders correctly from Settings: social icon links (Instagram/Facebook/LinkedIn/Twitter — only Facebook has a real value saved locally as of writing, the rest are still empty), trade licence number, and copyright line (remember: "Custom copyright text" fully overrides the auto-generated line including the licence number — leave it empty to get the auto-generated version with the real trade licence interpolated in)
- [ ] After deploying code updates on Hostinger, confirm changes actually appear on the live page — if not, use hPanel's "Restart PHP" (or equivalent) to clear opcache rather than disabling it; opcache should stay **enabled** in production for performance, unlike the local dev environment where it was disabled this session to avoid stale-cache confusion during active development (see migration-log.md, "Root cause found — stale PHP opcache" session, 2026-07-24)

## 5. Handoff (T072)

- [ ] Client handoff docs in `wordpress/docs/`
- [ ] Short Loom tutorials: applying for a visa, admin dashboard walkthrough, editing content via EMCP/Elementor

## 6. Nice-to-have / post-launch (not blocking)

- [ ] Rank Math: verify sitemap.xml generated, submit to Google Search Console
- [ ] Confirm SPF/DKIM records for the sending domain (Resend via WP Mail SMTP) so confirmation/notification emails don't land in spam
- [ ] Uptime monitor on the live subdomain
- [ ] LiteSpeed Cache: confirm page cache + image optimization enabled post-launch, purge cache after first content edits
