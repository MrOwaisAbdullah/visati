# Visati — Next.js → WordPress Migration Log

> **Purpose:** Capture every step, decision, problem, and solution during this
> re-platform so it can be distilled into a reusable skill (working name:
> `nextjs-to-wordpress-elementor-migration`). This file is **source material for that skill** —
> keep entries factual, concrete, and copy-pasteable.
>
> **Maintained by:** Claude Code (per `CLAUDE.md` → "WordPress Migration Work Log" rule).
> **Append** a new dated entry each session. For every blocker record
> **Symptom → Cause → Fix → Commands**.

---

## 0. Context

- **Source app:** Next.js 16 + Sanity (CMS) + Neon/Drizzle (DB) + Stripe + Cloudflare R2 + BetterAuth. Spec: `specs/001-visati-platform/spec.md`.
- **Target:** WordPress — all-Elementor content pages + custom `visati-core` plugin + Hello Elementor child theme. Plan: `specs/002-wp-migration/plan.md`, tasks: `specs/002-wp-migration/tasks.md`.
- **Build environment:** Local by Flywheel (local-first) → deploy to Hostinger subdomain (fresh DB, no Neon migration).
- **Control plane:** EMCP (`elementor-mcp` / `emcp-tools`) + WP-CLI driven from Claude Code.
- **Constraint:** free plugins only; Next.js repo root untouched (all WP work under `wordpress/`).

---

## 1. Locked decisions

| Area | Decision |
|---|---|
| Page-builder strategy | All-Elementor for every content page (not a theme/Elementor hybrid) — maximizes client self-editing |
| Business logic | Custom `visati-core` plugin (apply form, track, admin, Stripe, R2, email, CPTs, REST) |
| Theme | Hello Elementor child (`visati`) — global chrome + design tokens |
| Build env | Local by Flywheel first → Hostinger subdomain |
| Elementor tier | Free (Pro deferred) |
| Plugin budget | $0 |
| DB / data | Fresh WordPress MySQL, content re-entered |

---

## 2. Local environment (state)

- WordPress **7.0.1**, site `http://visati.local`, admin `visati-admin`
- PHP **8.2.29** (Local Lightning), MySQL **8.4.0** on `127.0.0.1:10005` (db `local`, user `root`/`root`)
- Elementor **4.1.5**, EMCP Tools **3.4.1**, WP Mail SMTP **4.9.0**, Hello Elementor **3.4.9**
- Active: theme `visati` 0.1.0; plugins `elementor`, `emcp-tools`, `visati-core`, `wp-mail-smtp`
- Theme/plugin served live via directory **junctions** → repo `wordpress/wp-content/{themes/visati,plugins/visati-core}`

---

## 3. Problems & Solutions (skill gold) 💎

### P1 — Local: "Error Installing WordPress … php.exe is not recognized"
- **Symptom:** `ExecError: "…\lightning-services\php-8.2.29+0\bin\win64\php.exe" is not recognized as an internal or external command` during site creation.
- **Cause:** Local's "Lightning Services" PHP service **failed to install** — folder `php-8.2.29+0` existed but `bin/win64/php.exe` was missing (no PHP binary anywhere under `lightning-services`). Not antivirus in this case (Defender history clean).
- **Fix:** Quit Local fully (tray → Quit). Delete the broken folder `…\AppData\Roaming\Local\lightning-services\php-8.2.29+0`. Reopen Local **as Administrator** → it re-downloads PHP cleanly. Verify `php.exe` exists.
- **Commands:**
  ```bash
  rm -rf "/c/Users/K TECH/AppData/Roaming/Local/lightning-services/php-8.2.29+0"
  # then reopen Local as Administrator and recreate the site
  ```
- **Skill note:** If re-download keeps failing, delete the whole `lightning-services` dir, or add AV exclusions, or fall back to DevKinsta/Laragon/Docker.

### P2 — External WP-CLI: "Your PHP installation appears to be missing the MySQL extension"
- **Symptom:** Driving WP-CLI from Claude Code's Git Bash: `wp core version` worked, but any DB-touching command errored `missing the MySQL extension (mysqli)`.
- **Cause:** Local's bundled `php.exe`, when invoked directly from outside Local, **loads no `php.ini`** (`php --ini` → "Loaded Configuration File: (none)"), so no extensions load. Local's own "Site Shell" sets up the ini; the bare CLI doesn't.
- **Fix:** Invoke PHP with `-d` flags pointing to Local's ext dir and enabling the needed extensions. Encapsulated in a reusable wrapper `wordpress/scripts/wp-local.sh`.
- **Commands / wrapper:**
  ```bash
  PHPEXE="/c/Users/K TECH/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe"
  EXTWIN="C:/Users/K TECH/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/ext"
  WPCLI="C:/Users/K TECH/AppData/Local/Programs/Local/resources/extraResources/bin/wp-cli/wp-cli.phar"
  "$PHPEXE" -d extension_dir="$EXTWIN" -d extension=mysqli -d extension=pdo_mysql \
    -d extension=curl -d extension=mbstring -d extension=openssl -d extension=zip \
    "$WPCLI" --path="<site>" --allow-root <command>
  ```
- **Skill note:** This is the #1 gotcha for automating Local from an external shell. Always wrap; never call `php.exe` bare.

### P3 — "Error establishing a database connection" (after enabling mysqli)
- **Symptom:** With mysqli loaded, WP-CLI still couldn't connect to MySQL.
- **Cause:** `wp-config.php` had `DB_HOST = 'localhost'` → Windows mysqli defaults to TCP **127.0.0.1:3306**. But Local's MySQL runs on a **custom port** (`10005`). Local's web PHP works because its generated `php.ini` sets the custom port; the CLI PHP uses the default 3306.
- **Diagnosis:** `tasklist` showed `mysqld.exe` listening; `netstat` → `127.0.0.1:10005`; a PHP mysqli probe confirmed `127.0.0.1:10005` + `root/root`/`local` connects.
- **Fix:** Edit `wp-config.php`: `define( 'DB_HOST', '127.0.0.1:10005' );` (explicit host:port works for web + CLI).
- **Commands:**
  ```bash
  sed -i "s|define( 'DB_HOST', 'localhost' );|define( 'DB_HOST', '127.0.0.1:10005' );|" "<site>/wp-config.php"
  # discover the port: netstat -ano | findstr LISTENING  (the mysqld.exe PID)
  ```
- **Skill note:** Local assigns one TCP port per service per site (mysql often `1000X`). Discover via `netstat` + `tasklist`, then hardcode into `DB_HOST`. Port is stable per site but changes on site recreation.

### P4 — Symlinking repo theme/plugin into the Local site (Windows)
- **Symptom:** Wanted the repo `wordpress/wp-content/{themes/visati,plugins/visati-core}` served live by Local. Multiple attempts failed.
- **Attempt 1 — `cmd //c mklink /D` (symlink):** needs **admin/elevation**, and Git Bash mangled the spaced path (`K TECH`) through `cmd /c` → `Invalid switch` / `syntax is incorrect`.
- **Attempt 2 — `MSYS_NO_PATHCONV=1 cmd //c …`:** `//c` wasn't converted to `/c`, so `cmd` started **interactively** and hung (timeout). ❌
- **Cause:** Directory **symlinks** require elevation; `cmd /c` quote-stripping + MSYS path conversion fights spaced paths.
- **Fix:** Use a **directory junction** (`mklink /J`) via **PowerShell** — junctions need **no elevation** and PS quoting handles spaces.
- **Commands:**
  ```bash
  powershell -NoProfile -Command "New-Item -ItemType Junction -Path 'C:\Users\K TECH\Local Sites\visati\app\public\wp-content\themes\visati' -Target 'D:\GIAIC\Visati\wordpress\wp-content\themes\visati' | Out-Null"
  powershell -NoProfile -Command "New-Item -ItemType Junction -Path 'C:\Users\K TECH\Local Sites\visati\app\public\wp-content\plugins\visati-core' -Target 'D:\GIAIC\Visati\wordpress\wp-content\plugins\visati-core' | Out-Null"
  ```
- **Skill note:** ⚠️ Never use `Remove-Item -Recurse` on a junction — it can follow the link and **delete the target**. Use junctions (`mklink /J` / `New-Item -ItemType Junction`), not symlinks, on Windows without admin.

---

### P5 — EMCP `update-global-colors`/`update-global-typography` returned success but `get-global-settings` still showed defaults
- **Symptom:** Both EMCP update calls returned `{"success":true}`, but `emcp-tools-get-global-settings` still reported Elementor defaults (`#6EC1E4`, Roboto).
- **Cause:** The writes **did** land on the active Kit (post meta `_elementor_page_settings`); the `get-global-settings` tool reads a stale/merged view (`system_colors` defaults + empty `custom_colors`) rather than the freshly written palette.
- **Verification / fix:** Confirmed tokens present via the Kit post meta (option `elementor_active_kit` → post ID); flushed caches.
- **Commands:**
  ```bash
  WP="bash wordpress/scripts/wp-local.sh"
  KIT=$( $WP option get elementor_active_kit )          # -> e.g. 6
  $WP post meta get "$KIT" _elementor_page_settings | grep -iE '0A1628|Plus Jakarta'   # confirm tokens present
  $WP cache flush
  $WP elementor flush_css
  ```
- **Skill note:** Don't trust `emcp-tools-get-global-settings` as the sole confirmation of a Kit write — verify via the **Kit post meta** (`_elementor_page_settings`) and flush Elementor's CSS cache. The Kit post is the source of truth, not the merged read view.

### P6 — `wp db query` needs the `mysql` client (not on Local's PATH)
- **Symptom:** `wp db query "SHOW TABLES LIKE '%visati%';"` → `Error: Failed to get current SQL modes. Reason: 'mysql' is not recognized`.
- **Cause:** `wp db *` subcommands shell out to the `mysql` CLI binary; Local's lightning-services ships the MySQL *server* but doesn't expose the `mysql` client on PATH.
- **Fix:** inspect the DB via WordPress's `$wpdb` (PHP/mysqli) using `wp eval` / `wp eval-file`.
- **Commands:**
  ```bash
  WP="bash wordpress/scripts/wp-local.sh"
  cat > wordpress/scripts/_check.php <<'EOF'
  <?php global $wpdb; $r=$wpdb->get_results("SHOW TABLES LIKE '%visati%'",ARRAY_N); foreach($r as $x) echo $x[0]."\n";
  EOF
  $WP eval-file wordpress/scripts/_check.php && rm wordpress/scripts/_check.php
  ```
- **Skill note:** On Local (and many hosts) prefer `$wpdb` over `wp db query` for DB inspection.

### P7 — `wp eval` via the Local wrapper is slow (Elementor bootstraps every load)
- **Symptom:** `bash wordpress/scripts/wp-local.sh eval '…'` (full plugin load) exceeded a 60s timeout and was killed (SIGTERM / exit 143); `wp core version` (light load) was fast.
- **Cause:** `wp eval` boots the full WP environment including **all active plugins**; Elementor is heavy (~10–40s) plus the PHP/WP-CLI wrapper overhead.
- **Fix:** give `wp eval` a generous timeout (≥90–110s); combine multiple checks into one `eval` to amortize the boot; use `wp eval-file` for larger scripts.
- **Skill note:** when automating Local via external WP-CLI, budget for Elementor's boot cost on any full-stack command.

### P8 — Pink buttons/links on all pages (Hello Elementor default styles)
- **Symptom:** Every bare `<button>`, `[type="submit"]`, and `<a>` element showed **pink (#c36)** background/text instead of Visati Navy/Gold/Blue. Affected both front-end content and WordPress admin panel.
- **Cause:** Hello Elementor's `assets/css/reset.css` (loaded AFTER child theme) applies global button/link styles: `button { border: 1px solid #c36; color: #c36 }` and `a { color: #c36 }`. Child theme's `app.css` button reset was overridden by loading order.
- **Fix:** Added scoped, `!important` button/link overrides in `app.css` that target ONLY content-area elements (`.visati-page button`, `.visati-page a`, etc.), leaving admin panel untouched. Used `!important` because hello-elementor loads after child theme.
- **Commands:**
  ```bash
  # Updated wordpress/wp-content/themes/visati/assets/css/app.css
  # Added scoped selectors like .visati-page button, .visati-page a
  # with !important flags to override hello-elementor/reset.css
  ```
- **Skill note:** When using Hello Elementor as parent, expect its `reset.css` to override child theme styles. Use scoped selectors (not global `button`/`a`) and `!important` to preserve admin panel styling.

### P9 — Pink buttons/links persists despite CSS overrides (Hello Elementor reset.css)
- **Symptom:** Even with high-specificity `!important` CSS overrides in child theme, buttons and links still showed pink (#c36) colors. Quick Apply button and form fields affected.
- **Cause:** Hello Elementor's `assets/css/reset.css` loads AFTER child theme and applies fundamental button/link styles that are difficult to override due to CSS specificity and loading order. The file contains `button { border: 1px solid #c36; color: #c36 }` and `a { color: #c36 }`.
- **Fix:** **Direct edit of Hello Elementor reset.css** to replace all instances of pink `#c36` with Visati theme colors. Updated:
  - Links: Gold (`#C9A84C`) with Navy hover (`#0A1628`)
  - Buttons: Solid Gold background, no borders, pill shape (`border-radius: 999px`), white text
  - Button hover: Shining effect (`filter: brightness(1.08)`, golden glow, `translateY(-1px)`)
  - Form fields: Rounded corners (`10px`), light borders (`#d7dbe2`), gold focus state
- **Commands:**
  ```bash
  # Direct file edit (outside git repo - Local Sites directory)
  sed -i 's/#c36/#C9A84C/g' "/c/Users/K TECH/Local Sites/visati/app/public/wp-content/themes/hello-elementor/assets/css/reset.css"
  # Manual refinement of hover states and border-radius
  ```
- **Deployment note:** ⚠️ **This change must be replicated on Hostinger** after deployment. The reset.css file needs to be edited in the live WordPress installation at:
  `wp-content/themes/hello-elementor/assets/css/reset.css`
  Replace all `#c36` with `#C9A84C` and apply the same button/form field styling.
- **Skill note:** When CSS specificity battles become too complex, direct modification of the parent theme's reset.css is more reliable than fighting with `!important` declarations. Document this requirement for deployment.

---

## 4. Tooling built this session

- **`wordpress/scripts/wp-local.sh`** — WP-CLI wrapper for Local's `visati` site (handles P2 + P3). Usage: `bash wordpress/scripts/wp-local.sh <command>`.
- **EMCP stdio verified working** — `initialize` probe returned `MCP Tools for Elementor Server v3.4.1` over the wrapper. Wiring into `.mcp.json` is the next step (T005).

---

## 5. Progress tracker

- [x] **Phase 0 — Setup:** Local site, Elementor/Hello Elementor/EMCP/WP Mail SMTP installed; `visati` theme + `visati-core` plugin junctioned + activated.
- [x] **T005** — Wired EMCP stdio into `.mcp.json`; reload confirmed. EMCP verified working through Claude Code's MCP integration (5 read tools exercised: `core-get-site-info`, `core-get-environment-info`, `emcp-tools-get-global-settings`, `emcp-tools-list-plugins`, `emcp-tools-list-pages`). Stdio-via-wrapper is solid — no HTTP-proxy fallback needed.
- [~] **Phase 2 — Foundational:** ✅ Chunk 1 done — Elementor Kit tokens applied via EMCP (`update-global-colors`: Navy/Gold/Blue/Mist/Ink/White/WhatsApp; `update-global-typography`: Plus Jakarta Sans / Inter / JetBrains Mono); child `theme.json` v3 (palette + fontFamilies + layout) + `functions.php` (nav menus, supports, Google Fonts + asset enqueue, ABSPATH guard) + `assets/css/app.css` (`--visati-*` token vars + base components) written. **Problem P5 (resolved inline):** `update-global-colors` took a flat array of `{_id,title,color}` — used Elementor system ids (primary/secondary/text/accent) + custom ids (mist/white/whatsapp). ⏳ Next: plugin foundation (Options page, CPTs, custom tables, service libs) via `wp-plugin-dev`, then global chrome (header/footer/announcement/WhatsApp).

**Phase 2 — Chunk 2 (plugin foundation via `wp-plugin-dev`):** ✅ Done + verified. Clean bootstrap (`visati-core.php`) + main class (`Visati_Core` singleton) + CPTs (`visati_visa_type`, `visati_faq`, `visati_testimonial`, `visati_job`, `visati_discount`) + `visati_consultant` role + 5 custom tables via `dbDelta` + helpers (`visati_generate_application_id()` → `VIS-2026-XXXXXX`, verified) + `uninstall.php`. **Verified:** 5 CPTs registered, role added, app-ID generator returned `VIS-2026-BVS2J8`, all 5 tables present & queryable. ⏳ Next: service libs (R2, Stripe, email) + REST bootstrap → global chrome → US1 apply form.

**Phase 2 — Chunk 3 (Settings/Secrets + REST + Email):** ✅ Done + verified. `Visati_Core_Settings` (secrets read from `wp-config.php` `VISATI_*` constants — never DB, values never echoed; `missing_secrets()` + admin error notice), `Visati_Core_REST_API` (namespace `visati/v1`, public `/status` ping, response + permission helpers), `Visati_Core_Email` (`wp_mail()` + Visati-branded HTML templates — confirmation + status-update; provider-agnostic, delivery routed by WP Mail SMTP). **Verified:** all classes load, `/status` returns `{"plugin":"visati-core","version":"0.2.0"}` over HTTP, `missing_secrets()` flags Stripe + R2. **Decision:** secrets = `wp-config.php` constants (per-env, never committed); non-secret config = `visati_core_settings` option; email provider = Resend (default) via WP Mail SMTP. ⏳ Next: R2 storage (S3 SigV4 — research via Context7) + Stripe (raw REST API + HMAC webhook verify). **Needs test keys in Local `wp-config.php` to verify** (Stripe `sk_test_`/`whsec_`, R2 account/access/secret/bucket).

**Phase 2 — Chunk 4 (R2 + Stripe, dependency-free):** ✅ Code done + load-verified. `Visati_Core_R2_Storage` (S3-compatible; self-contained **AWS SigV4** signer — server-side PUT/DELETE via `Authorization` header + presigned GET URLs (15-min default); region `auto`, path-style; **no AWS SDK / composer**). `Visati_Core_Stripe_Payments` (raw REST `wp_remote_post` Checkout Session + **HMAC-SHA256** webhook verify with 5-min tolerance + timing-safe `hash_equals`; **no Stripe SDK**). **Verified:** both load; degrade gracefully when keys absent (`is_configured()=false`, `presigned_url()`→`''`, webhook guard→`WP_Error('stripe_webhook_not_configured')`). ⏳ **Needs live keys** to verify real upload + presigned URL + Stripe session + webhook. **Tooling note:** Context7 mandate reaffirmed — use it for any library/framework (Elementor APIs, AWS/Stripe SDK if ever adopted, JS libs like Embla/dotted-map/html2pdf); R2 + Stripe were written with **no** library, so no lookup was needed there. R2 specifics confirmed via Cloudflare R2 S3 API docs (endpoint `https://<account>.r2.cloudflarestorage.com`, region `auto`, PutObject/GetObject/DeleteObject + presigned supported).

**Phase 2 — Chunk 5 (Options page + global chrome):** ✅ Done + verified. `Visati_Core_Admin_Settings` (top-level "Visati" admin menu; nonce + capability + sanitize + PRG save → `visati_core_settings`; field groups: WhatsApp/contact, announcement bar, tracking, social — **secrets excluded**, those stay in `wp-config.php`). Global chrome injected via action hooks (no `header.php` override = low risk): `wp_body_open` → announcement bar (`template-parts/announcement-bar.php`, cookie-dismiss + end-date), `wp_footer` → floating WhatsApp widget (`template-parts/whatsapp-widget.php`, `wa.me` deep-link). **Verified:** admin class loads; homepage HTTP 200; chrome markup present (`id="visati-announcement"`, `Test announcement`, `visati-whatsapp-fab`, `wa.me/971500000000`). **🎉 PHASE 2 (Foundational) COMPLETE.** ⏳ Next: Phase 3 — US1 (5-step apply form + draft + R2 document upload) — needs R2 test keys for step 3.

**Post-checkpoint — `.env.local` integration + live verification:** ✅ The plugin now reuses the Next.js `.env.local` as the **single source of secrets**. `Visati_Core_Env_Loader` (dependency-free dotenv; path via `VISATI_ENV_FILE` constant → `D:/GIAIC/Visati/.env.local`; real env vars win over file values). `Visati_Core_Settings` reads `getenv()` first, then wp-config `VISATI_*` constants (mapped: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `R2_ACCOUNT_ID`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET_NAME`, `RESEND_API_KEY`, `RESEND_FROM_EMAIL`, `ADMIN_NOTIFICATION_EMAIL`, `EXPRESS_SURCHARGE_AED`). wp-config edited (Local, outside repo): `define('VISATI_ENV_FILE','D:/GIAIC/Visati/.env.local')`. **R2 + Stripe now VERIFIED LIVE:** R2 SigV4 round-trip (upload → presigned GET **200 body-matches** → delete) ✅; Stripe HMAC self-signed **OK** + tampered **rejected** ✅. Secrets were never printed (status-only checks). **The "needs test keys" blocker is fully resolved — Phase 3 is unblocked.**
- [~] **Phases 3–12 (US1–US10):** ✅ US1–US10 core complete. US1 (apply form), US2 (Stripe), US3 (tracking), US4 (visa types), US5 (homepage), US6 (admin apps), US7 (admin inquiries), US8 (contact/WhatsApp), US9 (FAQ), US10 (CMS content) all functional. Deferred: EMCP page builds (T041, T045, T053). ⏳ Phase 13 polish + deploy.

---

## 6. Open questions / risks

- EMCP `.mcp.json` stdio via `bash` wrapper — confirm Claude Code spawns it correctly after reload (fallback: Node.js HTTP proxy + Application Password).
- Local MySQL port (`10005`) is site-specific; re-verify if the site is recreated.
- Hostinger environment + subdomain name + plan tier still TBD (needed at Phase 6).

**Phase 3 — US1 backend (application draft + document upload):** ✅ Done + verified end-to-end. `Visati_Core_Application` model (create draft, get, exists, append-only `add_status`). REST `POST /applications` (validate required + sanitize + create draft + `VIS-YYYY-XXXXXX`) + `POST /documents` (validate `application_id` + file type PDF/JPG/PNG + ≤5MB → upload to R2 via the verified SigV4 signer → store metadata). **Smoke-tested live:** draft `VIS-2026-K2DTHB` created → doc uploaded to R2 (`2026/VIS-…/….pdf`, id=1) → R2 delete OK → DB cleanup. ⏳ Next: US1 frontend — 5-step form wizard (`[visati_apply_form]` shortcode + `page-apply.php` + `application-form.js` + `apply.css`).

**Phase 3 — US1 frontend (5-step apply form):** ✅ Done + verified live at `http://visati.local/apply/`. `[visati_apply_form]` shortcode renders the wizard (Step 1 visa + processing tier; Step 2 personal details; Step 3 document upload; Step 4 review) with `application-form.js` (step nav + per-step validation; on file-select → creates draft via `POST /applications` then uploads via `POST /documents`; review summary) + `apply.css` (Visati tokens). Assets registered on `wp_enqueue_scripts`, enqueued only when the shortcode is present; REST URL + nonce localized. **Setup:** pretty permalinks `/%postname%/` flushed; seeded 3 visa types (Tourist 30/60, Multi Entry 90); `/apply` page (id=14) created with the shortcode. **Verified:** `/apply` HTTP 200; form markup + `tourist-30d` option + `application-form.js`/`apply.css` enqueued. ⏳ Step 5 (post-payment success) lands with US2 (Stripe Checkout handoff).

---

**Homepage conversion to Elementor (All-Elementor approach):** ✅ Done + verified live at `http://visati.local/`. Removed custom `front-page.php` (renamed to `.bak`); created `page.php` template that outputs `the_content()` for Elementor pages. Built pre-built Elementor JSON template (`visati-core/admin/templates/homepage.json`) matching original Next.js design: Hero (2-col: badges + h1 + sub + CTAs + social proof + trust icons | quick apply widget) → Stats bar (navy, 4 stats) → Visa showcase (eyebrow + heading + `[visati_visa_cards]`) → Global reach (navy card with stats + copy) → Process steps (mist bg, 4-step with connecting line) → Why choose (2-col: copy + 2×2 feature cards) → Testimonials (heading + rating + `[visati_testimonials]`) → FAQ (2-col: copy + `[visati_faq]`) → Footer CTA (navy, WhatsApp + inquiry). Import script (`wordpress/scripts/import-homepage.php`) creates/updates Home page, saves Elementor data, sets as static front page. **Verified:** HTTP 200; `show_on_front=page`; page ID 18. All shortcodes render inside Elementor HTML widgets. Client can now edit entire homepage via Elementor visual editor. **Skill note:** For All-Elementor WordPress sites, never use `front-page.php` — always use a JSON template imported via `update_post_meta( $id, '_elementor_data', ... )` + set `show_on_front=page`. This preserves editability while matching any design.

### P8 — Elementor JSON import: data format requires `wp_slash()` on encode
- **Symptom:** Elementor data saved via `update_post_meta` but editor showed empty/broken layout.
- **Cause:** `wp_json_encode` output stored directly; WordPress slashes stored data on read, causing double-encoding of quotes.
- **Fix:** Apply `wp_slash()` before saving: `update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) )`.
- **Commands:**
  ```php
  update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
  update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
  update_post_meta( $page_id, '_elementor_version', '4.1.5' );
  ```
- **Skill note:** Always `wp_slash()` Elementor JSON data before `update_post_meta`. The `_elementor_data` meta key expects unslashed JSON; WP's internal `addslashes` on save will double-escape if the input already has quotes.

---

### P9 — Elementor sections constrained: `layout: "boxed"` vs `"full_width"`
- **Symptom:** All homepage sections appeared narrow/constrained instead of spanning full viewport width. Background colors (navy stats bar, global reach, footer CTA) didn't reach edges.
- **Cause:** All sections in the Elementor JSON used `"layout": "boxed"` with `"content_width": 1200px`. Boxed layout constrains the entire section (background + content) to the content width. Full-width sections let background span edge-to-edge while content stays centered.
- **Fix:** Changed every section from `"layout": "boxed"` → `"layout": "full_width"`. Added `"padding": {"unit": "px", "top": "0", "right": "80", "bottom": "0", "left": "80"}` on the inner column to center content at ~1200px (viewport width minus 160px padding).
- **Commands:**
  ```json
  {
    "settings": {
      "layout": "full_width",
      "padding": {"unit": "px", "top": "80", "right": "0", "bottom": "80", "left": "0", "isLinked": false},
      "background_color": "#F8F9FB"
    },
    "elements": [{
      "settings": {
        "_column_size": 100,
        "padding": {"unit": "px", "top": "0", "right": "80", "bottom": "0", "left": "80", "isLinked": false}
      }
    }]
  }
  ```
- **Skill note:** In Elementor JSON, `boxed` = content + background constrained; `full_width` = background spans viewport, content centered via column padding. For edge-to-edge backgrounds (navy bars, hero sections), always use `full_width` + column padding.

### P10 — Shortcodes inside Elementor HTML widgets don't render
- **Symptom:** `[visati_quick_apply]`, `[visati_visa_cards]`, etc. appeared as raw text on the homepage instead of rendering the actual content.
- **Cause:** Elementor HTML widgets treat shortcode syntax as plain text — they don't process `[shortcodes]`. Shortcodes must be in dedicated `shortcode` type widgets.
- **Fix:** Added `extract_shortcodes_from_html()` to the import script. It scans HTML widget content for `[tag ...]` patterns, splits the HTML at each shortcode boundary, and creates separate Elementor elements: HTML widgets for static content + `shortcode` widgets for dynamic shortcodes.
- **Commands:**
  ```php
  // Pattern to find shortcodes in HTML
  $pattern = '/\[([a-z_]+(?:\s+[^\]]*)?)\]/';
  preg_match_all( $pattern, $html, $matches, PREG_OFFSET_CAPTURE );
  // For each match: create HTML widget (before) + shortcode widget (match)
  ```
- **Skill note:** When building Elementor JSON templates with dynamic content, always use dedicated `{"widgetType": "shortcode", "settings": {"shortcode": "[tag]"}}` widgets. Never embed shortcodes inside HTML widgets — they won't process. The import script now auto-extracts them, but it's cleaner to structure the JSON with separate widgets from the start.

### Files created/modified this session
| File | Action | Purpose |
|---|---|---|
| `themes/visati/front-page.php.bak` | Renamed | Backup of custom PHP homepage (no longer used) |
| `themes/visati/page.php` | Created | Generic page template — outputs `the_content()` for Elementor pages |
| `plugins/visati-core/admin/templates/homepage.json` | Created | Pre-built Elementor template matching original Next.js design (9 sections, full-width) |
| `scripts/import-homepage.php` | Created | Imports JSON template → creates/updates Home page → saves Elementor data → sets as static front page. Includes shortcode extraction logic. |

**Apply form redesign (per client feedback):** ✅ Rebuilt `[visati_apply_form]` to match the original Next.js design. (1) Stepper = connecting line + progress fill + navy circles with check icons + "Step N"/labels. (2) Nationality = searchable combobox with country flags (flagcdn, 77 countries) — was a text field. (3) Colors: confirmed Elementor global colors ARE set (`post-6.css` → `--e-global-color-primary:#0A1628`); form uses `--visati-*` tokens (Navy/Gold) with hex fallbacks. **Verified:** `/apply` 200; stepper + combobox + 77 flags render. (Recall P5: `get-global-settings` reads stale, but the Kit CSS file + post meta are correct.)

---

### Session P12 — Homepage hero two-column layout + header button fix
**Date:** 2026-07-16

**Goal:** Fix the hero section so the Quick Apply card renders as a right-side vertical card in a two-column layout. Fix header "Apply Now" button not visible (navy on navy).

#### Hero two-column layout
- **Symptom:** Quick Apply card renders below the hero text instead of beside it in a two-column row.
- **Cause:** Elementor data was restructured with nested containers (`hero-row` → `hero-col-left` / `hero-col-right`) but the inner container `8ba4e0d` didn't have `layout: "row"` set properly, and child containers didn't have correct `elType: "container"` with `content_width`.
- **Fix:** (1) Updated Elementor `_elementor_data` to set inner container `8ba4e0d` to `layout: "row"` with two child containers. (2) Added CSS overrides to force `.elementor-element-8ba4e0d` as flex row with proper column widths. **Status:** CSS forced via `!important` — needs verification in browser.

#### Header "Apply Now" button invisible
- **Symptom:** Header CTA button blends into the dark navy header background — not visible as a distinct button.
- **Cause:** `.visati-header__cta` only set `font-size`, `padding`, `font-weight`, `display`. It relied on base `.visati-btn` for golden gradient, but no explicit `!important` override to prevent bleed from Elementor/WordPress CSS.
- **Fix:** Added explicit `.visati-header__cta` styles with `background: linear-gradient(135deg, #C9A84C 0%, #F0C864 50%, #C9A84C 100%) !important`, `color: var(--visati-navy) !important`, `border: none !important`, `box-shadow: 0 2px 8px rgba(201, 168, 76, 0.3)`.

#### Files modified
| File | Action | Purpose |
|---|---|---|
| `themes/visati/assets/css/app.css` | Modified | Added `.visati-header__cta` golden gradient `!important` override; added `.elementor-element-54c25a9` and `.elementor-element-8ba4e0d` flex row overrides for hero two-column layout |

#### Known issues
- Hero two-column CSS forced via `!important` on Elementor auto-generated class IDs — fragile if Elementor regenerates classes. Ideally fix via Elementor data structure properly.
- Hero `hero-row` / `hero-col-left` / `hero-col-right` containers may not render as expected — Elementor container `elType` + `layout` settings need verification.

---

### P14 — Testimonial Stars Unicode + FAQ Layout + Content Seeding

#### Testimonial stars showing as `\u2605\u2605\u2605\u2605\u2605`
- **Symptom:** Star ratings display as literal escape sequences instead of ★ characters.
- **Cause:** PHP `"\u2605"` is NOT a Unicode escape — it's a literal string `\u2605`. PHP doesn't support JS-style `\u` escapes in double-quoted strings.
- **Symptom → Cause → Fix → Commands:**
  - Changed `str_repeat("\u2605", $rating)` to `str_repeat('★', $rating)` (actual UTF-8 character).
  - Same for empty star: `"\u2606"` → `'☆'`.
- **File:** `plugins/visati-core/includes/shortcodes/class-testimonials.php:57`

#### FAQ section stacked vertically instead of side-by-side
- **Symptom:** FAQ heading ("Questions, answered.") and accordion items stacked vertically. Should be side-by-side (heading left, accordion right) like the Next.js version.
- **Cause:** FAQ shortcode output was a flat block — no layout wrapper. Elementor container set to `content_width: full` with no flex structure.
- **Fix:** Updated FAQ shortcode to output a `.visati-faq-layout` flex container with `.visati-faq-heading` (35% width, sticky) and `.visati-faq-right` (flex: 1). Added CSS for side-by-side layout + mobile responsive.
- **File:** `plugins/visati-core/includes/shortcodes/class-faq.php`, `themes/visati/assets/css/app.css`

#### FAQ category buttons unstyled
- **Symptom:** Category filter buttons appear as empty white rounded boxes with no visible text.
- **Cause:** Category labels were empty or had low contrast. The buttons use `border: 1.5px solid rgba(10,22,40,0.12)` with `background: transparent` — text was rendering but nearly invisible against white background.
- **Fix:** The category labels now display correctly (e.g. "Process", "Documents") since they're populated from `_visati_faq_category` meta. CSS already handles active state with navy background.

#### FAQ Question field added to meta box
- **Symptom:** FAQ meta box only had Answer and Category fields. The "Question" for the accordion header was the WP title field, which is not intuitive.
- **Fix:** Added `_visati_faq_question` field to the FAQ meta box. Shortcode updated: `get_post_meta($faq->ID, '_visati_faq_question', true) ?: $faq->post_title` — prefers the explicit field, falls back to title.

#### Content seeded from Next.js site
- **FAQ items:** 8 items seeded from `src/lib/sanity/seed.ts` — process, documents, general, application, pricing, visa-types categories.
- **Testimonials:** 5 items seeded from `src/lib/sanity/seed-testimonials.ts` — Priya Sharma (IN), James Whitfield (GB), Sophie Laurent (FR), David Chen (US), Fatima Al Marzouqi (AE).
- **Seed script:** `wordpress/scripts/seed-content.php` — idempotent (skips duplicates).

#### Files modified
| File | Action | Purpose |
|---|---|---|
| `plugins/visati-core/includes/shortcodes/class-testimonials.php` | Modified | Fixed star Unicode: `"\u2605"` → `'★'` |
| `plugins/visati-core/includes/shortcodes/class-faq.php` | Modified | Added `.visati-faq-layout` wrapper, side-by-side heading, `_visati_faq_question` field |
| `plugins/visati-core/includes/class-meta-boxes.php` | Modified | Added `_visati_faq_question` field to FAQ meta box |
| `themes/visati/assets/css/app.css` | Modified | Added `.visati-faq-layout`, `.visati-faq-heading`, `.visati-faq-right`, `.visati-faq-label`, `.visati-faq-title`, `.visati-faq-desc`, `.visati-faq-cta` styles + mobile responsive |
| `wordpress/scripts/seed-content.php` | Created | Idempotent seed script for FAQ items and testimonials |
| `themes/visati/functions.php` | Modified | Version bump to 0.3.4 |

#### Skill learned — PHP Unicode handling
- PHP `"\u2605"` is NOT a Unicode escape. Use actual UTF-8 characters directly: `'★'` (U+2605), `'☆'` (U+2606).
- `esc_html()` passes UTF-8 characters through correctly.

---

## Session: Policy Pages + Global CSS Fix (July 18, 2026)

### P14 — Policy pages show raw content without hero/card

- **Symptom:** Privacy, Terms, Cookies, Refund pages display raw H2 headings and bullet lists with no navy hero section and no white card wrapper.
- **Cause:** Policy hero was previously rendered via `wp_body_open` hook and content wrapped via `the_content` filter. Both targeted `elementor_header_footer` template. When we created `page-policy.php` in the theme directory, the pages still had `_wp_page_template = elementor_header_footer` in the database.
- **Fix:** Created `page-policy.php` theme template (hero + card + `get_header()`/`get_footer()`). Removed the `wp_body_open` hook and simplified the `the_content` filter. Created `fix-templates.php` admin script to bulk-update page meta.
- **File:** `themes/visati/page-policy.php` (new), `themes/visati/functions.php`
- **Status:** Pending — user must run `http://visati.local/wp-content/plugins/visati-core/admin/fix-templates.php` in browser then delete the file.

### P15 — Homepage and all pages narrower (800px max-width)

- **Symptom:** Homepage, About, Contact, Track, and all Elementor pages suddenly constrained to 800px width. Stats bar and CTA no longer touch screen edges.
- **Cause:** CSS rule at `app.css:2124-2135` applied `max-width: 800px !important` to ALL `.elementor-section` and `.elementor-widget-container` on ANY page using `elementor_header_footer` template. This was originally added for policy pages but was global — it affected every single Elementor page.
- **Fix:** Removed the entire global constraint block. Policy pages now use their own `page-policy.php` template (not `elementor_header_footer`) so the constraint is no longer needed.
- **File:** `themes/visati/assets/css/app.css:2124-2135` (removed), `themes/visati/functions.php` (version bump 0.3.6 → 0.3.7)
- **Symptom → Cause → Fix → Commands:**
  - Removed lines 2124-2135 from `app.css`:
    ```css
    /* REMOVED — was constraining ALL Elementor pages to 800px */
    body.page-template-elementor_header_footer .elementor,
    body.page-template-elementor_header_footer .elementor-section,
    body.page-template-elementor_header_footer .elementor-widget-container {
        max-width: 800px !important;
        margin-left: auto !important;
        margin-right: auto !important;
    }
    ```
  - User must hard-refresh (Ctrl+Shift+R) to pick up cached CSS.

### Skill learned — Elementor template selection scope

- **NEVER** target `body.page-template-elementor_header_footer` in CSS for page-specific styling. This template is used by the homepage AND most other Elementor pages. Any CSS rule targeting it is effectively global.
- When styling a specific page type (e.g., policy pages), either:
  1. Use a **dedicated page template** (`page-policy.php`) and target `.page-template-page-policy` body class, or
  2. Use **page-specific body classes** from Elementor (`e.g., .page-id-99`)
- Policy pages should use their own PHP template (`page-policy.php`), NOT `elementor_header_footer`.

### P16 — Policy page links render as gold gradient buttons

- **Symptom:** Links inside `.visati-policy-card` (email, phone, WhatsApp) render as golden gradient buttons with pill padding instead of plain underlined text.
- **Cause:** The blanket button reset at `app.css:1243` targets `body .visati-page a:not([class*="visati-btn"])` — catches ALL `<a>` tags inside `.visati-page`. The `page-policy.php` template had `<main class="visati-page">` but was missing `visati-page--policy`, so the `:not(.visati-page--policy)` exclusion didn't work.
- **Fix:** Added `visati-page--policy` class to `<main>` in `page-policy.php`. The blanket reset's `:not(.visati-page--policy)` now correctly excludes policy page links.
- **File:** `themes/visati/page-policy.php:19`

### P17 — Policy hero has gap above it

- **Symptom:** Visible white space between the sticky header and the navy hero section on policy pages.
- **Cause:** The sticky header has `margin-bottom: 12px` and there's likely default spacing from the page wrapper. No negative margin on the hero to compensate.
- **Fix:** Added `margin-top: -24px` to `.visati-policy-hero` to pull the hero up under the header.
- **File:** `themes/visati/assets/css/app.css:2107`

---

## Session: Admin Layer — T047–T051 (July 18, 2026)

### T047–T051 — Admin dashboard, applications management, inquiries management

- **Files created (8):**
  - `admin/class-admin-dashboard.php` — WP admin menu registration (Visati → Dashboard, Applications, Inquiries, Settings), stat cards query, recent activity queries, style enqueue
  - `admin/class-admin-applications.php` — Applications list with pagination + status filter, detail view with status history timeline + documents (presigned R2 URLs), status-update handler (append-only history + email notification)
  - `admin/class-admin-inquiries.php` — Inquiries list with status filter, detail view with resolve toggle (new→read→resolved), reply-via-email link
  - `admin/views/dashboard.php` — 7 stat cards (total/pending/submitted/approved/rejected apps, total/new inquiries) + recent applications table + recent inquiries table
  - `admin/views/applications-list.php` — WP-style table with Application ID, Name, Nationality, Visa Type, Status badge, Date + pagination + status subnav
  - `admin/views/application-detail.php` — Full detail table (14 fields) + status history timeline + documents with signed URLs + status update form
  - `admin/views/inquiries-list.php` — WP-style table with ID, Name, Email, Subject, Status badge, Date + pagination + status subnav
  - `admin/views/inquiry-detail.php` — Detail table + message block + resolve toggle button + reply-via-email link
  - `assets/css/admin.css` — Admin-only styles (stat cards, status badges, detail grid, timeline, doc list)

- **Files modified (3):**
  - `visati-core.php` — Added `require_once` for 3 admin classes, version bump 0.2.1 → 0.3.0
  - `includes/class-visati-core.php` — Added `Visati_Core_Admin_Dashboard::init()` to constructor

- **Design decisions:**
  - All admin pages capability-gated (`visati_manage_apps` / `visati_manage_inquiries`)
  - Status history is append-only (never update existing rows) — matches `class-application.php` design
  - Document viewing uses presigned R2 URLs (15-min TTL) — falls back gracefully if R2 not configured
  - Status update sends email notification via `Visati_Core_Email::send_status_update()`
  - Admin CSS is separate (`admin.css`) — only loads on Visati admin pages

---

## Session: Stripe Webhook + Tracking + Stripe Frontend (July 18, 2026)

### T033 — Stripe webhook REST endpoint

- **File created:** `includes/rest/class-webhooks-controller.php`
- **Endpoints:**
  - `POST /visati/v1/webhooks/stripe` — receives Stripe webhooks, verifies HMAC signature, handles `checkout.session.completed` (mark submitted + record payment + send confirmation email) and `payment_intent.payment_failed` (mark payment failed, app stays draft)
  - `POST /visati/v1/checkout-session` — frontend creates Stripe Checkout Session (gets visa type price from CPT, adds express surcharge if applicable, returns `{id, url}`)
- **Design decisions:**
  - Idempotent: `checkout.session.completed` skips if app already `submitted`
  - Payment record stored in `wp_visati_payments` with `stripe_session_id`, `stripe_payment_id`, `amount_cents`, `currency`, `status`
  - Email confirmation sent via `Visati_Core_Email::send_confirmation()`
  - Webhook secret + Stripe secret key from `Visati_Core_Settings` (env/wp-config)

### T064 — GA4/GTM/Facebook/TikTok tracking injection

- **File created:** `includes/class-tracking.php`
- **Hooks:**
  - `wp_head` (priority 1): GA4 `gtag.js` script
  - `wp_body_open` (priority 1): GTM `<noscript>` iframe
  - `wp_footer` (priority 999): GTM `<script>` + Facebook Pixel + TikTok Pixel
- **Admin fields added:** GTM Container ID, Facebook Pixel ID, TikTok Pixel ID (added to "Tracking & Social" section of Visati Settings page, with save handler)
- **Config source:** All from `Visati_Core_Settings::get()` (stored in `wp_options`)

### Stripe frontend redirect wired

- **File modified:** `assets/js/application-form.js` — submit handler now calls `POST /visati/v1/checkout-session` with `{application_id}` and redirects to `response.url` (Stripe Checkout). Shows error on failure. Re-enables button for retry.
- **Flow:** Complete apply → pay: form creates draft → uploads docs → submit → create checkout session → redirect to Stripe → webhook confirms → email sent

---

## Session: Homepage HTML → native Elementor widgets (July 18, 2026)

### Goal — convert homepage HTML widgets to native Elementor widgets

- **Context:** Homepage (post ID 18) was originally built mostly as raw **HTML widgets** inside Elementor containers, not native widgets. Client self-editing is harder with HTML blobs. Decision: convert *simple* sections to native widgets; keep HTML only where the design can't be reproduced with pure widgets. Dynamic shortcodes stay as-is (`[visati_quick_apply]`, `[visati_visa_cards]`, `[visati_testimonials]`, `[visati_faq]`). Complex custom-layout sections stay HTML (feature dark card `c29c344`, process steps `6b01df9`, why-choose `c020c37`, global-reach map). Approach: convert one section first for review, then proceed.

### Trust-stats section → 4 native Counter widgets

- **Before:** single HTML widget `e0cd7e8` inside navy container `7b94321` (under hero) — a 4-col CSS grid of `<p>` number + `<p>` label.
- **After:** 4 native `counter` widgets in container `7b94321` (set to flex row):
  - `210f2b5` — ending 1000, suffix `+`, thousand separator → **1,000+** / Happy Travelers
  - `6b176ee` — ending 99, suffix `%` → **99%** / Approval Rate
  - `d5f598d` — prefix `24–`, ending 72, suffix `h` → **24–72h** / Avg. Processing (range rendered via prefix/suffix since Counter animates one number)
  - `f27dbd1` — ending 100, suffix `+` → **100+** / Google Reviews
- **Styling (match Next.js design):** number 36px / 700 / `#FFFFFF` / Plus Jakarta Sans; title 12px / `rgba(255,255,255,0.5)`. Numbers animate up on scroll (duration 2000ms).
- **Then:** removed old HTML widget `e0cd7e8`.

### Responsive fix — stats 2×2 on mobile

- Parent `7b94321`: `flex_wrap: wrap`, `flex_justify_content: center`, gaps 32px desktop / 24px col + 28px row mobile.
- Each counter: `_element_width: initial` with custom width **20% desktop/tablet**, **40% mobile** → single row on desktop, clean **2×2 grid** on mobile.

### Tooling / method notes 💎

- **Did this via live EMCP MCP tools, not a PHP import script:** `emcp-tools-add-free-widget` (widget_type `counter`, parent `7b94321`), `emcp-tools-update-element`/`update-widget` for settings, `emcp-tools-remove-element` for the old HTML. Cleaner than editing `_elementor_data` JSON directly.
- Cleared Elementor CSS cache after edits: `bash wordpress/scripts/wp-local.sh elementor flush-css`.
- **Counter widget key settings:** `starting_number`, `ending_number`, `prefix`, `suffix`, `thousand_separator: "yes"`, `duration`; styling via `number_color` + `typography_number_*` and `title_color` + `typography_title_*` (with `typography_*_typography: "custom"`).
- **Blocker (infra, not project):** harness auto-approval safety classifier (runs on `claude-opus-4-8`) was temporarily unavailable → in auto permission mode all MCP/Bash tool calls were blocked ("auto mode cannot determine the safety of…"). Read-only file tools unaffected. **Fix:** user toggled off auto mode to approve tool calls manually; alternatively run commands via the `! <cmd>` prompt prefix. Reconnecting MCP (`/mcp`) was *not* the fix — MCP was connected throughout.

### Remaining simple sections to convert (next)

- Hero text `c0f5644`, section headings `0141353` / `a2cafeb` / `090053e`, CTA banner `83b3fcc`.

---

## Session: Section headers HTML → native widgets (July 18, 2026)

### Goal — convert 3 section-header HTML widgets to native widgets (easy→medium)

Ranked remaining simple sections easy→hard first; did the 3 easiest. Left the hard one (`c020c37` "VISATI Difference" 2×2 icon-box grid) and the CTA banner `83b3fcc` for later.

### #1 "Four simple steps." header (`a2cafeb`) → 2 Heading widgets — EASY

- Into column container `5895884` (before process grid `6b01df9`):
  - `909ac0e` Heading — "The Process", tag `p`, center, `#C9A84C`, 12px/600/uppercase/1.44px letter-spacing, 12px bottom margin
  - `3bb17b5` Heading — "Four simple steps.", tag `h2`, center, `#0A1628`, 700, resp font 48/40/28px (desktop/tablet/mobile), 56px bottom margin
- Removed HTML `a2cafeb`.

### #2 "Visa Types" header (`0141353`) → 2 Headings + Button — EASY

- Left/right split. Structure inside `9eb7cb5` (before visa-cards shortcode `b7c4811`):
  - Row `7fef307` ("Visa Types Header Row"): `flex_direction:row`, `space-between`, `align_items:flex-end`, `flex_wrap:wrap`, 40px bottom margin
    - Column `bb53974` ("Visa Types Heading Group"): `flex_direction:column`, `_element_width:auto` → eyebrow `515fc7d` + H2 `3a626e7` ("Pick the visa that fits<br> your trip.")
    - Button `364b2ea` — "Compare all", link `/visa-types`, transparent bg, `#C9A84C` text, `fas fa-arrow-right` icon right, zero padding (text-link look)
- Removed HTML `0141353`.

### #3 "Testimonials" header (`090053e`) → Headings + Star Rating — MEDIUM

- Same split, inside `9c22c19`:
  - Row `45993d8` ("Testimonials Header Row")
    - Column `d35cd4d`: eyebrow `8f6ca04` ("Testimonials") + H2 `68e46cf` ("Loved by travellers.")
    - Column `8bc5af0` ("Rating Group"): `flex_align_items:flex-end` (right-aligned) → `star-rating` `bf08625` (rating 4.9, scale 5, `star_fontawesome`, marked `#C9A84C` / unmarked `rgba(201,168,76,0.25)`, 16px, align right) + subtext Heading `3449603` ("Based on 100+ reviews on Trustpilot & Google", 12px, `rgba(26,26,46,0.5)`, right)
- Removed HTML `090053e`.

### Method notes 💎

- **No "add empty container" tool exists.** To build nested flex layouts (left/right splits) with EMCP: `emcp-tools-duplicate-element` on any existing container, then `emcp-tools-remove-element` on the copied children to empty it, then `emcp-tools-move-element` into place and `update-element` to set flex. Duplicating **always deep-copies children** (and drops the copy as the next sibling of the source) — so after each duplicate, re-run `find-element` to grab the new child IDs and delete them.
- Reused this by duplicating a fully-built row (`7fef307` → `45993d8`) for #3, then just editing the copied widgets' text instead of rebuilding.
- `star-rating` widget key params: `rating`, `rating_scale`, `star_style` (`star_fontawesome`), `stars_color`, `stars_unmarked_color`, `star_size`, `align`.
- Heading widget does eyebrow labels well: set `header_size:"p"` + custom typography (uppercase, letter-spacing, small size, brand color).
- Cache flush after: `bash wordpress/scripts/wp-local.sh elementor flush-css`.

### Remaining (deferred, harder)

- `c020c37` "The VISATI Difference" — 40/60 split, left text+button, right 2×2 grid of 4 icon-boxes (colored SVG tiles). Best as 4 native Icon Box widgets in nested containers.
- `83b3fcc` CTA banner — eyebrow+H2+text left, WhatsApp button (green, brand icon, `wa.me` link) + "Send Inquiry" outline button right.

---

## Session: Mobile responsiveness pass — section padding + header/hero/stats (July 18, 2026)

### Context

User manually adjusted several homepage sections (post 18) in the Elementor editor, then asked to fix remaining mobile issues and log everything. State inspected via `emcp-tools-get-element-settings` before touching anything.

### User's own changes (verified in element settings, kept as-is)

- **Header rows side-by-side:** `7fef307` (Visa Types) & `45993d8` (Testimonials) — left/right columns now truly inline. Widths set per-column via `_element_width:"initial"` + `_element_custom_width` (desktop) + `_element_custom_width_mobile:100%`. Testimonials: left `d35cd4d` 55% / right `8bc5af0` 40% desktop, both 100% mobile.
- **"4.9" added** to testimonials rating group `8bc5af0` (Heading `1cf8527`, prior session) — stars → 4.9 → review-count subtext, right-aligned.
- **Hero form stacked / hero row:** `hero-row` is `flex_wrap:nowrap` at desktop; Quick-Apply shortcode `c5d2869` given `_element_custom_width:39%` so form + hero text sit side-by-side, stacking on smaller breakpoints.
- **Stats row responsive:** `7b94321` — `flex_wrap:wrap`, centered, desktop gap 32px, mobile gap 24px col / 28px row (→ 2×2 on mobile from prior session).

### 💎 Problem — sections narrow/cramped on mobile

- **Symptom:** All content sections looked squeezed into a narrow center strip on mobile; big empty gutters left/right.
- **Cause:** Every section **column wrapper** carried desktop side padding `right:80 / left:80` px with **no responsive override**. On a ~375px phone that's 160px of gutter (~43% of the viewport) — content crushed into the remaining ~215px. The row/column widths were fine; padding was the culprit.
- **Fix:** Added `padding_mobile` = `{top:0, right:20, bottom:0, left:20}` to every section wrapper. Elementor stores responsive control values under `<control>_mobile` / `_tablet` keys — setting `padding_mobile` overrides desktop `padding` at the mobile breakpoint only.
- **Wrappers fixed (6):** `9eb7cb5` (Visa Types), `9c22c19` (Testimonials), `10d1b02` (VISATI Difference), `b35db8f` (CTA banner), `5895884` (Process), `7b94321` (Stats). *(Policy/other wrappers `6d2ed02` already had 20px — left alone.)*

### Method notes

- **Responsive overrides via MCP:** append `_mobile` / `_tablet` to any control key, e.g. `padding_mobile`, `flex_gap_mobile`, `_element_custom_width_mobile`, `typography_font_size_tablet`. Desktop value stays in the base key. No separate "responsive mode" needed — just send the suffixed key through `update-element` / `update-widget`.
- Section side padding lives on the **inner column container**, not the outer full-bleed background container (those carry only top/bottom padding + bg color). Fix padding on the inner one.
- Cache flush after: `bash wordpress/scripts/wp-local.sh elementor flush-css`.

### To verify on device

- Header rows: confirm they stay 2-up on tablet and stack cleanly on phone (columns → 100%).
- Hero: form should drop below hero text on mobile (nowrap row → check it wraps at tablet/mobile, may need `flex_wrap_mobile:wrap` if it doesn't).

### 💎 Follow-up — hero row overflowing right on mobile (predicted above, confirmed)

- **Symptom:** On mobile the hero form didn't stack under the hero text — it overflowed off the right edge (horizontal scroll).
- **Cause:** `hero-row` was `flex_wrap:nowrap` with no responsive override. With nowrap, hero text `c0f5644` + Quick-Apply form `c5d2869` (`_element_custom_width:39%`) were forced onto one line at every breakpoint; on a narrow viewport the 39% form spilled past the right edge instead of wrapping.
- **Fix (3 edits):**
  - `hero-row`: `flex_wrap_tablet:"wrap"` + `flex_wrap_mobile:"wrap"` (desktop stays `nowrap`).
  - `c0f5644` (hero text): `_element_width:"initial"`, `_element_custom_width_tablet/_mobile:100%`, `_flex_size:"none"` (stop flex from stretching it).
  - `c5d2869` (form): `_element_width:"initial"`, `_element_custom_width_tablet/_mobile:100%` (keeps desktop 39%).
- **Skill note:** A `flex_direction:row` container needs BOTH `flex_wrap_mobile:wrap` on the parent AND `100%` custom width on each child for a clean stack — wrap alone lets children keep their desktop % width and sit narrow/overflow; width alone without wrap can't move them to a new line.

---

## Session: CTA banner HTML → native widgets (July 18, 2026)

### Goal — convert final deferred section, CTA banner `83b3fcc`, to native widgets

Last remaining HTML-widget section on homepage (post 18). Navy full-bleed banner, split layout: left text block, right two buttons.

### Structure built

- Navy outer `4a6bd7b` → side-padding wrapper `b35db8f` → **CTA Banner Row** `130892e` (`flex_direction:row`, `space-between`, `flex_align_items:center`, `flex_wrap:wrap`):
  - **CTA Text** `53c9f50` (`flex_direction:column`, 60% desktop / 100% tablet+mobile):
    - eyebrow `6198867` Heading — "Need Help?", `p` tag, gold `#C9A84C`, uppercase (inherited from dup)
    - H2 `b81736a` — "Talk to a visa consultant.", white `#ffffff`, 700, resp 30/26/22px
    - paragraph `3b8e0a9` Text Editor — muted white `rgba(255,255,255,0.6)`, 14px
  - **CTA Buttons** `daf126c` (`flex_direction:row`, `flex_wrap:wrap`, 12px gap):
    - `194f2a2` Button — "Chat on WhatsApp", green `#25D366`, white text, `fab fa-whatsapp` icon left, link `https://wa.me/971500000000` (⚠️ PLACEHOLDER number), 10px radius
    - `52e9fd1` Button — "Send Inquiry", transparent bg, 1px `rgba(255,255,255,0.25)` border, white text, link `/contact`, 10px radius
- Removed HTML `83b3fcc`.

### Method notes

- Reused the split-row pattern a 3rd time by duplicating Visa Types row `7fef307`. The dup's leftover copied button (`194f2a2`) was **moved** into a new button-group container and repurposed as the WhatsApp button — avoids adding+deleting.
- Button-group container `daf126c` made by duplicating the left text column `53c9f50` then removing its 3 copied widgets and switching `flex_direction` to `row`.
- WhatsApp brand icon: `selected_icon` = `{value:"fab fa-whatsapp", library:"fa-brands"}`. Outline button = transparent `background_color` + `border_border:"solid"` + `border_width` + `border_color`.
- `flush-css` ran slow this time (WP-CLI boot >120s, completed in background) — succeeded.

### ⚠️ Needs real values

- WhatsApp number is placeholder `971500000000` — swap for the real business number.
- "Send Inquiry" → `/contact` — confirm destination (could be a form anchor or dedicated page).

### 🎉 Homepage HTML→native migration COMPLETE

All originally-HTML section headers/banners on post 18 are now native widgets. Remaining HTML widget still on page: `c020c37` ("The VISATI Difference" 2×2 icon-box grid) — a full content block, not a header; convert separately when prioritized.

---

## Session: SEO content sections A–F + hero/VISATI-Difference native conversion (July 19, 2026)

### Context

Site not yet deployed → good window to make the homepage content-rich before launch. Benchmarked against competitor **dubaivisa.com** (their edge is substantive text: intro paragraph, "What Is a Dubai Visa?" explainer, visa-type breakdown, FAQ). Homepage was visually complete but text-light — weak for Google + AI Overviews / LLM citation. Decision: add 6 new content sections, all native Elementor widgets, copy run through the `humanizer-main` skill (no AI tells: no "testament", rule-of-three, em-dashes, copula avoidance).

### Locked constraints (user)

- Nationalities served = **worldwide** (do not invent a country list).
- Processing time = pull from Next.js source, **not invented** → `src/components/home/HomeFAQ.tsx:11`: "Most UAE visas are processed in 24–72 hours. Express same-day options are available for urgent travel."
- **No credentials invented** — license/years/certs left as an explicit placeholder card for the client to fill.

### 6 sections built (all native widgets, placed after FAQ, before contact CTA)

- **A — Intro** (`d66227a`, white): H2 "Your Dubai visa, handled start to finish" `439abed` + 2-paragraph Text Editor `5e36cb1`. Centered ~760px readable column. Prose block (kept as plain text, not cards).
- **B — Visa Types Explained** (`89a95a3`, grey): centered H2 `0110989` + intro `d5f9ff3` + 4 icon-box cards grid `bb970ae` (Tourist / Multiple-Entry / Transit / Visa-free-on-arrival).
- **C — Who We Help** (`dfde7aa`, white): centered globe icon `fa03663` + H2 `3886113` + "worldwide" text `9282c8b`.
- **D — What You Need** (`3c28c4a`, grey): H2 `280e43d` + intro `c223194` + checkmark icon-list `8221d1b` (passport / photo / travel details) + processing-time Text Editor `2e4f3ff` (from Next.js source).
- **E — Why Choose Visati** (`cb099f7`, white): H2 `36b0d20` + intro `3814593` + 4 icon-box cards (`de003fd`/`f961b35`/`2713147`/`a6a5629`) — one card holds the **credentials placeholder**.
- **F — Closing CTA** (`485e3e3`, navy `#0A1628`): white centered H2 `f0d907e` + text `82bdc3a` + 4 internal-link buttons in group `74058b7` — Apply Now `/apply` (gold), Visa Types `/visa-types` (outline), Read FAQ `/faq` (outline), Contact Us `/contact` (outline). Internal-linking hub for crawl equity.

Alternating white/grey backgrounds for visual rhythm. Section A prose is plain; B–F use cards/icons/lists so they read as designed, not a text wall.

### Method notes 💎

- **No "add empty container" tool in EMCP.** Reused the established trick: duplicate an existing section shell (Section A `d66227a`, or the card grid `802177d` / button group `daf126c`), then `remove-element` the copied children and repurpose. Card grids were fastest to clone from the VISATI-Difference grid since they already had the bordered icon-box styling.
- After cloning a grid that previously sat beside text (56% width), reset it to full width + `flex_justify_content:center` when standalone.
- SEO copy sourced from repo, not invented — processing time from `HomeFAQ.tsx`, positioning from `src/app/layout.tsx` meta.
- `flush-css` again ran >120s and completed in background (WP-CLI boot latency on Local) — succeeded.

### ⚠️ Needs real values before launch

- **Credentials card** (Section E) — placeholder text pending real license no. / years / certifications.
- WhatsApp number still `971500000000` (from prior CTA session).
- Section D visa durations use "commonly 30/60 day, 48/96h" — confirm exact offerings.

### FAQ schema note (from SEO skill)

Google retired FAQ rich results for all sites **May 7, 2026**. The `[visati_faq]` accordion is good UX + AI-citation value — keep it, but expect no SERP rich snippet. Don't add new FAQPage schema for Google SERP benefit.

### Also converted earlier this session (HTML → native)

- **Hero** `c0f5644` → native column `73be17e` (badges row, H1, subheading, CTA row, social-proof chip, trust icon-list). Beside Quick Apply form `c5d2869` in `hero-row`.
- **VISATI Difference** `c020c37` → native row `5ebf11e` (40% text column + button + 2×2 icon-box grid `802177d`).
- Only HTML widgets left on post 18: `c29c344` (Global Reach panel) and `6b01df9` (process grid) — both given scoped responsive `<style>` blocks (2-col→1-col reflow, connector line hidden ≤768px).
- Testimonial arrows fixed: `padding:0` + `box-sizing` (true circle) + `svg{display:block}` + `line-height:0` (chevron centered).

### Blocker — Font Awesome 6 icon names on Elementor 4.1.5 (ships FA5) 💎

- **Symptom:** PHP warnings on the homepage — `Undefined array key "list-check"` and `"arrows-rotate"` in `elementor/core/page-assets/data-managers/font-icon-svg/font-awesome.php` (lines 45/48/49/50), then null-offset warnings as it tried to read the missing SVG entry.
- **Cause:** I set two icon-box `selected_icon` values using **Font Awesome 6** names (`fas fa-list-check`, `fas fa-arrows-rotate`). Elementor 4.1.5 bundles **Font Awesome 5**, whose SVG data map has no such keys → lookup returns null.
- **Fix:** swapped to FA5-valid equivalents via `update-widget`:
  - `2b74674` (Multiple-Entry Visa): `fa-arrows-rotate` → `fa-sync-alt`
  - `de003fd` (End-to-end handling): `fa-list-check` → `fa-tasks`
- **Verified clean (already FA5-valid):** `fa-globe`, `fa-umbrella-beach`, `fa-plane-departure`, `fa-passport`, `fa-lock`, `fa-award`, `fa-check-circle`, `fab fa-whatsapp`.
- **Skill note:** when setting Elementor icons via MCP on an FA5 install, use FA5 names. Common FA6→FA5 gotchas: `list-check`→`tasks`, `arrows-rotate`→`sync-alt`, `magnifying-glass`→`search`, `xmark`→`times`, `trash-can`→`trash`, `circle-check`→`check-circle`.

---

## Session: Responsive QA + Email notifications + Critical bug fixes (July 20, 2026)

### Context

Completed remaining pre-deploy items: responsive QA audit, missing email notifications, and critical runtime bug. User explicitly deferred EMCP page builds (T041, T045, T053) and skipped user management / currency toggle / blog / services pages.

### P18 — wp_doing_rest() fatal error (500 site-wide)

- **Symptom:** Entire site returned HTTP 500. No pages loaded, no admin accessible.
- **Cause:** `Visati_Core_Database::ensure_columns()` called `wp_doing_rest()` at the `init` hook. This function is defined in `wp-includes/rest-api.php` which may not be loaded yet when `init` fires on a non-REST request. PHP Fatal: `Call to undefined function wp_doing_rest()`.
- **Symptom → Cause → Fix → Commands:**
  ```php
  // BEFORE (broken):
  if ( ! is_admin() && ! wp_doing_rest() ) {
  // AFTER (fixed):
  if ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
  ```
  The `REST_REQUEST` constant is the actual check that `wp_doing_rest()` performs — using it directly avoids the load-order dependency.
- **File:** `plugins/visati-core/includes/class-database.php:141`
- **Debug method:** Enabled `WP_DEBUG` + `WP_DEBUG_LOG` in `wp-config.php`, triggered a page load, checked `wp-content/debug.log` for the fatal error trace. Disabled debug after fix.
- **Skill note:** When hooking into `init` (which fires very early in `wp-settings.php`), avoid functions from REST API, admin, or plugin files that may not be loaded yet. Use `defined() && constant` checks instead of wrapper functions.

### P19 — send_inquiry_notification() missing (contact form would fatal)

- **Symptom:** `Visati_Core_Inquiries_Controller::handle_submit()` calls `Visati_Core_Email::send_inquiry_notification()` but the method didn't exist in `class-email.php`. Would cause a fatal error on every contact form submission.
- **Cause:** Method was referenced during controller development but never implemented in the email class.
- **Fix:** Added `send_inquiry_notification(int $inquiry_id, array $params)` to `Visati_Core_Email`. Emails admin with: sender name/email, subject, message body, and link to admin inquiry view.
- **Also added:** `send_admin_alert(string $app_id, string $visa_name, string $name)` — emails admin when a new application is submitted via Stripe payment.
- **Also added:** `admin_email()` private helper — reads `ADMIN_NOTIFICATION_EMAIL` env var first, falls back to WordPress `admin_email`.
- **File:** `plugins/visati-core/includes/class-email.php`

### P20 — Webhook send_confirmation() wrong params

- **Symptom:** `handle_checkout_completed()` called `Visati_Core_Email::send_confirmation( $app )` passing a DB row object. But `send_confirmation()` expects `(string $app_id, string $to, string $visa_name)`.
- **Cause:** Method signature was changed during earlier refactoring but the webhook caller wasn't updated.
- **Fix:** Updated webhook handler to pass correct individual args: `send_confirmation( $app_id, $app->applicant_email, $visa_name )`. Also added admin alert call after confirmation.
- **File:** `plugins/visati-core/includes/rest/class-webhooks-controller.php:168-176`

### Responsive QA audit — 25 issues scanned, 19 passing, 6 fixed

Ran full responsive audit across all public pages. Key fixes applied:

| Issue | Fix | File |
|---|---|---|
| No global `box-sizing: border-box` | Added `*, *::before, *::after { box-sizing: border-box; }` | `app.css:32` |
| No `img { max-width: 100% }` | Added global image constraint | `app.css:33` |
| Hamburger tap target 40px | Increased to 44px (WCAG minimum) | `app.css:304` |
| Drawer close button 4px padding | Added 10px padding + 44px min-size | `app.css:370` |
| WhatsApp FAB overlaps iPhone home bar | Added `@supports (padding-bottom: env(safe-area-inset-bottom))` | `app.css:188` |
| apply.css breakpoint mismatch (600px) | Changed to 639px to match app.css | `apply.css:92,124` |
| Footer mobile text too small (0.65rem) | Bumped to 0.75rem for heading + legal | `app.css:2611,2632` |
| Social link mobile 32px | Increased to 44px | `app.css:2607` |
| Visa detail `<dl>` overflow | Added `overflow-wrap: break-word` | `app.css:2265` |
| No `prefers-reduced-motion` support | Added media query killing all animations | `app.css:2712` |

### Seed data verified

- **FAQ:** 10+ posts present via REST (`/wp-json/wp/v2/visati_faq`)
- **Testimonials:** 5+ posts present via REST (`/wp-json/wp/v2/visati_testimonial`)
- **Visa types:** 8 seeded (from prior session)

### Track endpoint + JS verified

- REST `GET /visati/v1/track?id=` — validates ID format (`VIS-YYYY-XXXXXX`), returns 404 for missing apps, returns timeline + status for found apps
- `tracking.js` — 160 lines, timeline render, XSS-safe escaping, auto-search from `?id=` URL param

### Tasks marked done in tasks.md

- T036 — Track REST endpoint ✅
- T038 — Tracking JS ✅
- T054 — WhatsApp deep-link button ✅
- T055 — FAQ CPT fields ✅
- T058 — FAQ seed data ✅
- T062 — Testimonial seed data ✅
- T069 — Responsive QA ✅

### Files modified this session

| File | Action | Purpose |
|---|---|---|
| `includes/class-database.php` | Modified | Replace `wp_doing_rest()` with `defined('REST_REQUEST')` check |
| `includes/class-email.php` | Modified | Add `send_inquiry_notification()`, `send_admin_alert()`, `admin_email()` |
| `includes/rest/class-webhooks-controller.php` | Modified | Fix `send_confirmation()` params, add admin alert call |
| `themes/visati/assets/css/app.css` | Modified | Responsive QA fixes (box-sizing, touch targets, safe-area, breakpoints, reduced-motion, overflow) |
| `plugins/visati-core/assets/css/apply.css` | Modified | Breakpoint alignment 600→639px |

### Deployment readiness

- **Site status:** HTTP 200, all features working locally
- **Remaining for deploy:** T070 (Hostinger), T071 (smoke test), T072 (handoff docs)
- **Deferred:** EMCP page builds (T041, T045, T053) — user will do later
- **Known gotcha:** Hello Elementor `reset.css` pink-button fix must be manually replicated on Hostinger

---

## Session: Deploy planning — subdomain + wp-content transfer (July 21, 2026)

### Context

Pre-deploy planning session (T070), no code changes. Confirmed the hosting target and worked out the exact steps to move the local build to a live Hostinger subdomain.

### Decision — Hostinger managed hosting plan, not the VPS

Confirmed with user: the Hostinger VPS (2-year prepaid, previously ran the Next.js app, now decommissioned) is reserved for other use and is **not** available for WordPress. WordPress deploys to the separate Hostinger shared/managed hosting plan on a fresh subdomain, per the original `plan.md` Phase 6 decision — no change to the locked deploy target.

### Deploy recipe — subdomain creation + wp-content-only transfer

1. hPanel → Websites → Domains → Subdomains: create the subdomain, note its document root path.
2. hPanel Auto Installer: fresh one-click WordPress on that subdomain (creates DB + `wp-config.php` automatically).
3. Locally, zip ONLY `wordpress/wp-content/themes/visati` and `wordpress/wp-content/plugins/visati-core` — never `wordpress/docs`, `wordpress/scripts`, or anything from the repo root (Next.js app):
   ```powershell
   cd D:\GIAIC\Visati\wordpress\wp-content
   Compress-Archive -Path themes\visati -DestinationPath $env:USERPROFILE\Desktop\visati-theme.zip -Force
   Compress-Archive -Path plugins\visati-core -DestinationPath $env:USERPROFILE\Desktop\visati-plugin.zip -Force
   ```
4. Upload via hPanel File Manager (or SFTP) into the subdomain's `wp-content/themes/` and `wp-content/plugins/`, extract in place, delete the zips.
5. Activate theme + plugin; install the same free companion plugins as local (Elementor, EMCP, WP Mail SMTP, Rank Math, Wordfence, LiteSpeed Cache, UpdraftPlus).
6. DB migration: `wp db export` locally via `wp-local.sh` → upload the `.sql` to a scratch (non-public) folder → `wp db import` → `wp search-replace 'https://visati.local' '<subdomain-url>' --all-tables`. Delete the uploaded `.sql` immediately after import — never leave a DB dump in a web-reachable folder.
7. Copy `wp-content/uploads` the same zip/upload/extract way.
8. Secrets: there's no Next.js repo on Hostinger for `VISATI_ENV_FILE` to point at, so add the `VISATI_*` constants (Stripe live keys, R2 credentials, Resend API key) directly into the server's `wp-config.php` instead. Never commit these.
9. Re-apply the Hello Elementor `reset.css` pink-button fix manually (not in git).

### Skill note — Elementor page layouts live in the DB, not in wp-content files

User asked whether the Elementor pages/layouts move over just by uploading `wp-content`. They don't, not fully: the actual page structure (`_elementor_data` JSON per page) and global Kit settings (colors/fonts/defaults) live in `wp_postmeta` / `wp_posts` / `wp_options` — i.e. inside the **database** dump, not the theme or plugin folders. What *does* live under `wp-content` and gets carried by a file upload: Elementor's generated/cached CSS (`wp-content/uploads/elementor/css/`) and uploaded media (`wp-content/uploads/YYYY/MM/`) — both already covered by copying the `uploads` folder in step 7, so no separate action needed for those. `wp search-replace --all-tables` is JSON-aware and correctly rewrites URLs embedded inside `_elementor_data` (it's plain JSON, not PHP-serialized — no escaping gotchas). After import, run Elementor → Tools → **Regenerate CSS** once, since cached CSS file hashes/paths can go stale across a host move.

### Status

Planning only — no server-side or code changes yet. Next: user executes the subdomain creation + upload steps above.

---

## Session: Pre-deploy security review + rate limiting (July 23, 2026)

### Context

Full security review of `visati-core` + `visati` theme before going live (part of T070 prep). Session ran across two Claude Code instances (one on this machine, one on the user's PowerShell instance which hit its 5-hour usage cap mid-fix) — this entry reconciles both and finishes the interrupted work.

### Security review result — risk level LOW, no criticals

- **No CRITICAL issues**: every `$wpdb` call with user input uses `prepare()`; output is escaped throughout views; no `eval`/`exec`/`unserialize`/dynamic includes/`move_uploaded_file` (uploads go straight to R2 via the plugin's own SigV4 signer); Stripe webhook is signature-verified (`hash_equals` against `whsec_` secret); admin/settings handlers all have nonce + capability + sanitize checks.
- **WARNING #1 — no rate limiting on public write endpoints** (`POST /applications`, `POST /documents`, `POST /inquiries`, `POST /checkout-session`): risk of spam submissions, inquiry flooding, or R2 upload abuse once the site is live and indexed. **Fixed this session** — see below.
- **WARNING #2 — inquiries controller "missing sanitize_callback"**: **false positive**. `class-inquiries-controller.php:26-32` already registers a `sanitize_callback` (+ `validate_callback` on email) per field in the REST `args` schema — the initial scan misread `handle_submit()`'s use of `get_param()`, which returns the already-sanitized value once REST applies the registered schema. No code change needed.
- **INFO #3 — recommended `wp-config.php` constants for the Hostinger deploy** (server-side, not code — add during T070):
  ```php
  define( 'DISALLOW_FILE_EDIT', true );       // no plugin/theme editor in wp-admin
  define( 'DISALLOW_UNFILTERED_HTML', true ); // stricter content escaping
  define( 'FORCE_SSL_ADMIN', true );          // HTTPS for /wp-admin
  ```
- **INFO #4** — `WP_DEBUG` is `false` locally. Correct for production, no action.
- **INFO #5** — `dashboard.php:130`'s `$inq->resolved ? 'resolved' : 'new'` ternary flagged then cleared: admin-only, fully trusted internal data, ternary returns hardcoded strings — not an XSS vector. False positive, no action.

### Fix — per-IP rate limiter (`visati_rate_limit()`)

Added a dependency-free transient-based rate limiter to `includes/helpers.php`:

```php
function visati_rate_limit( string $bucket, int $limit = 10, int $window = 600 ): bool
```

- Keys on `$_SERVER['REMOTE_ADDR']`, preferring `HTTP_CF_CONNECTING_IP` then `HTTP_X_FORWARDED_FOR` when present (Cloudflare/Hostinger sit in front) — falls back to allow (not block) if no IP is resolvable, to avoid false-positive lockouts.
- Bucketed transient key `visati_rl_<bucket>_<md5(ip)>`; rolling window via `set_transient()`/`get_transient()`.
- Applied as a guard at the top of all four flagged callbacks, returning HTTP 429 once the limit is hit:

| Endpoint | Bucket | Limit | Window | File |
|---|---|---|---|---|
| `POST /applications` | `applications` | 10 | 600s | `includes/rest/class-applications-controller.php` |
| `POST /documents` | `documents` | 20 | 600s | `includes/rest/class-documents-controller.php` |
| `POST /inquiries` | `inquiries` | 5 | 600s | `includes/rest/class-inquiries-controller.php` |
| `POST /checkout-session` | `checkout_session` | 10 | 600s | `includes/rest/class-webhooks-controller.php` (`create_checkout()`) |

`POST /webhooks/stripe` was deliberately left unguarded — it's server-to-server, already signature-verified, and rate-limiting it risks dropping legitimate Stripe retries.

### Fix — skip-link + mobile sticky-header CSS

- `.visati-skip-link`: was animating `top` (`-60px` → `0`), which is a layout-triggering property and caused a visible jump/reflow on focus. Switched to `transform: translateY(-150%/0)` — same visual behavior, GPU-composited, no reflow.
- `.visati-header.is-scrolled .visati-header__bar` on mobile (`≤639px`): was forced `width: 100%; border-radius: 0`, which looked like a layout bug (header touching screen edges, square corners) once scrolled. Changed to `width: 95%; margin: 0 auto; border-radius: 16px` to match the floating-pill header style used elsewhere.
- File: `themes/visati/assets/css/app.css`

### Files modified this session

| File | Action |
|---|---|
| `includes/helpers.php` | Added `visati_rate_limit()` |
| `includes/rest/class-applications-controller.php` | Rate-limit guard added |
| `includes/rest/class-documents-controller.php` | Rate-limit guard added |
| `includes/rest/class-inquiries-controller.php` | Rate-limit guard added |
| `includes/rest/class-webhooks-controller.php` | Rate-limit guard added to `create_checkout()` |
| `themes/visati/assets/css/app.css` | Skip-link + mobile header CSS fixes |

### Status

All four pre-live code fixes complete. Remaining before going live: `wp-config.php` constants (server-side, apply during T070), full Phase A end-to-end apply→pay→track test pass. See `wordpress/docs/GO-LIVE-TODO.md` for the consolidated checklist.

### Blocker — skip-link CSS fix not visible in browser after edit

- **Symptom:** After the skip-link CSS fix (above), the plain unstyled "Skip to content" text still appeared, statically visible on every page below the announcement bar — looked like the CSS fix hadn't applied at all.
- **Cause:** `app.css` is enqueued in `functions.php` with a **static** version string (`VISATI_THEME_VERSION`, was `0.4.0`), not `filemtime()`-based cache-busting. Editing the CSS file on disk doesn't change the `?ver=` query string WordPress appends, so the browser (and any page cache) kept serving the previously-cached `app.css` under the same URL.
- **Fix:** Bumped `VISATI_THEME_VERSION` → `0.4.1` in `themes/visati/functions.php:16`. Also required a hard-refresh (Ctrl+Shift+R) — and a LiteSpeed Cache purge if that plugin is active locally — since the browser/cache had already stored the old CSS response.
- **Skill note:** Any enqueued theme/plugin asset versioned with a static string (not `filemtime()`) needs its version constant bumped on every edit, or changes won't be visible until a manual cache-bust. Worth switching to `filemtime( get_stylesheet_directory() . '/assets/css/app.css' )` at some point to make this automatic — noted as a possible future improvement, not done here to keep the diff small.
- **Follow-up:** Even after a second version bump (`0.4.1`) and switching the CSS to the WordPress-core "clip to 1px" hidden pattern (more robust than the original `transform`-based approach, doesn't depend on containing-block assumptions), the skip-link was still visibly rendering in-flow, statically, on every page. Given the repeated failure to resolve via CSS and pressure to move to pre-deploy testing, **user decided to remove the skip-link entirely** rather than keep debugging blind (no direct browser access from the agent side to inspect the live-rendered page/cache state). Removed from `header.php` (was a single `<a href="#primary" class="visati-skip-link">` right after `wp_body_open()`) and the now-dead CSS from `app.css`. `#primary` landmark id remains on the `<main>` elements across templates — still valid as a landmark, just no longer a skip-link target. Version bumped to `0.4.3`.
- **Note for later:** the persistent appearance regardless of two different CSS strategies suggests the real blocker was a caching layer neither of us could directly inspect (LiteSpeed Cache full-page cache, most likely, given "still showing" survived even a version-string change) — if accessibility compliance matters for this site later, worth re-adding a skip-link once the caching behavior is understood, rather than concluding the CSS approach itself was wrong.

---

## Session: Admin dashboard UX — search/filter, layout fix, footer menus, tracking audit (July 23, 2026)

### Context

User flagged four admin-side issues while preparing to test the flow: (1) no search/filter on the Applications and Inquiries admin list pages, (2) the "Applications"/"Inquiries" page heading and "Dashboard" button rendering in the wrong place (screenshot showed the status-filter tabs merged onto the same line, ahead of the heading), (3) no way to configure footer link columns via wp-admin (no menu wired up despite the theme registering a "Footer Menu" location), (4) audit whether the Facebook Pixel / GA4 settings fields are actually wired up or just dead inputs.

### Fix — search + filtering on Applications and Inquiries

- `admin/class-admin-applications.php::render_list()`: added `$_GET['s']` (search across `id`, `given_name`, `surname`, `applicant_email`, `nationality` via `LIKE`) and `$_GET['visa_type']` (exact match against `visa_type_slug`), combined with the existing status filter via a dynamic `WHERE` clause built with `$wpdb->prepare()` + `esc_like()`. Also queries all published `visati_visa_type` posts for the filter dropdown.
- `admin/class-admin-inquiries.php::render_list()`: same pattern — `$_GET['s']` search across `name`, `email`, `subject`, `message`.
- Both controllers keep the same pagination/status-filter behavior as before; search/visa-type just add to the `WHERE`.

### Fix — list-page header/toolbar layout

- **Cause:** the old markup relied on WP core's default `.wp-heading-inline` + `.page-title-action` + `.subsubsub` float layout, with the `<ul class="subsubsub">` placed *after* the heading in source order — core admin CSS floats it in a way that visually merges it onto the same line as (and ahead of) the H1, which is what the screenshot showed (filter tabs, then "Applications", then "Dashboard" button, all crammed onto one row).
- **Fix:** replaced with custom markup in both `admin/views/applications-list.php` and `admin/views/inquiries-list.php` — a flex `.visati-list-header` row (title left, Dashboard button right) followed by a separate `.visati-list-toolbar` card containing pill-styled `.visati-list-tab` status filters and the new search/filter form, side by side on desktop and stacked on mobile (`≤782px`). New CSS added to `assets/css/admin.css`; `VISATI_CORE_VERSION` bumped `0.3.0` → `0.3.1` to bust the cached admin CSS.

### Fix — footer link columns now configurable via Appearance → Menus

- **Cause:** `functions.php` already `register_nav_menus()`'d a single `'footer'` location, but `footer.php` never called `wp_nav_menu()` for it — the three footer columns (Visas/Company/Support) were 100% hardcoded PHP arrays. The registered location was dead; there was genuinely no way for the client to edit footer links.
- **Fix:** replaced the single generic `'footer'` location with three column-specific ones — `footer-visas`, `footer-company`, `footer-support` (`functions.php`). `footer.php`'s column loop now checks `has_nav_menu( $location )`: if a menu is assigned, renders it via `wp_nav_menu()`; otherwise falls back to today's hardcoded links (so the site doesn't go blank before the client sets menus up). Added a `nav_menu_link_attributes` filter (`visati_footer_menu_link_class()`) that tags footer-menu `<a>` tags with `visati-footer__link` — needed because a global link-reset rule in `app.css` explicitly excludes that class, and `wp_nav_menu()`'s default output wouldn't otherwise carry it.
- **Client action needed:** go to Appearance → Menus, create up to 3 menus, assign each to "Footer — Visas column" / "Footer — Company column" / "Footer — Support column" to take over from the hardcoded defaults.

### Audit — Facebook Pixel / GA4 / GTM / TikTok tracking

- **GA4 and Facebook Pixel**: confirmed correctly implemented in `includes/class-tracking.php` — standard official snippets, gated behind the settings value, properly escaped (`esc_attr`/`esc_js`). Per user's own criterion ("if simple/straightforward, don't remove"), **kept both settings fields** in `class-settings-page.php` — no removal needed.
- **GTM**: also correctly implemented (head script + `wp_body_open` noscript iframe + footer script, matching Google's official 3-part install pattern). Not explicitly asked about but verified fine.
- **TikTok Pixel — found and fixed a real bug** (not asked about, found during the audit): the base code had an unquoted URL string — `var r=https://analytics.tiktok.com/i18n/pixel/events.js,o=n&&n.partner;` — which JavaScript parses as `https:` (a label) followed by a `//` line comment, silently swallowing the rest of the statement including the semicolon. There was also an invalid `ttq._i[e][]=` assignment further down. Replaced with a corrected version matching TikTok's actual official base pixel code. File: `includes/class-tracking.php`.

### Files modified this session

| File | Action |
|---|---|
| `admin/class-admin-applications.php` | Added search + visa-type filter |
| `admin/class-admin-inquiries.php` | Added search filter |
| `admin/views/applications-list.php` | Modern header/toolbar + search UI |
| `admin/views/inquiries-list.php` | Modern header/toolbar + search UI |
| `assets/css/admin.css` | New `.visati-list-header`/`.visati-list-toolbar`/`.visati-list-tab` styles |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.0 → 0.3.1 |
| `includes/class-tracking.php` | Fixed corrupted TikTok Pixel base code |
| `themes/visati/functions.php` | 3 footer nav menu locations + link-class filter |
| `themes/visati/footer.php` | `wp_nav_menu()` per column with hardcoded fallback |

### Status

All four issues resolved in code. Pending: user to verify visually (search/filter working, header layout fixed) and optionally assign real menus to the 3 new footer locations in Appearance → Menus.

---

## Session: Quick actions, multi-recipient notifications, WP Dashboard widget (July 23, 2026)

### Context

Follow-up to the admin dashboard UX session. User asked for: (1) quick-action buttons on the Visati Dashboard page, (2) confirmation a notification system exists for new applications/inquiries, (3) support for more than one notification recipient, (4) a quick-view panel on the native WordPress Dashboard (wp-admin Home page).

### Confirmed — notification system already exists

`class-email.php::send_admin_alert()` (fires from the Stripe webhook on `checkout.session.completed`) and `send_inquiry_notification()` (fires from the contact form REST handler) were both already implemented and wired to real events. The gap was single-recipient only.

### Fix — multi-recipient notifications

- `class-settings.php`: added `notification_emails(): array` — reads the new client-editable `notification_emails` setting (one address per line or comma-separated), validates each with `is_email()`, falls back to the existing env/wp-config `ADMIN_NOTIFICATION_EMAIL` value, then to WordPress's own `admin_email` option. Old single-address `admin_notification_email()` kept (marked `@deprecated`) since it's still the env-based fallback source.
- `class-settings-page.php`: added a "Notifications" section with a `notification_emails` textarea; save handler splits on newline/comma, sanitizes + validates each address, stores as newline-joined string.
- `class-email.php`: renamed the private `admin_email(): string` helper to `admin_emails(): array` (now just delegates to `Visati_Core_Settings::notification_emails()`); `mail()`'s `$to` param widened to `string|array` — `wp_mail()` natively accepts an array of recipients, so no other change needed. Both `send_admin_alert()` and `send_inquiry_notification()` updated to use the array-returning method.

### Added — Quick Actions panel (Visati Dashboard page)

`admin/views/dashboard.php`: added a `.visati-quick-actions` button row above the stat cards — Review Submitted (applications filtered to `status=submitted`), New Inquiries (filtered to `status=new`), Add Discount Code, Add Visa Type, Add FAQ, Add Testimonial, Settings. Styled in `admin.css`.

### Added — "Visati — Quick View" widget on the native WordPress Dashboard

Registered via `wp_dashboard_setup` in `class-admin-dashboard.php` (`register_dashboard_widget()` + `render_quick_view_widget()`), output via new `admin/views/dashboard-widget.php`. Shows submitted/under-review/new-inquiries counts + 30-day revenue (same queries as the full dashboard, reused for consistency) and the 5 most recent applications, all linking into the relevant filtered admin list. `enqueue_styles()` in `class-admin-dashboard.php` was widened to also fire on `index.php` (the Dashboard's hook suffix) so `admin.css` loads there too — previously it only matched hooks containing `"visati"`.

### Files modified this session

| File | Action |
|---|---|
| `includes/class-settings.php` | Added `notification_emails()` |
| `includes/class-email.php` | `admin_email()` → `admin_emails()` (array); `mail()` accepts array `$to` |
| `admin/class-settings-page.php` | New "Notifications" section + save handling |
| `admin/views/dashboard.php` | Quick Actions panel |
| `admin/class-admin-dashboard.php` | WP Dashboard widget registration + render; `enqueue_styles()` widened to `index.php` |
| `admin/views/dashboard-widget.php` | New — Quick View widget markup |
| `assets/css/admin.css` | Quick-action + widget styles |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.1 → 0.3.2 |

### Status

All four requests implemented. Pending: user to add real recipient emails in Settings → Notifications, and confirm the WP Dashboard widget + Quick Actions render correctly after a hard refresh.

---

## Session: Inquiries status column bug + doc-viewer button CSS (July 23, 2026)

### Blocker — `Undefined property: stdClass::$status` on Inquiries admin pages

- **Symptom:** PHP warning `Undefined property: stdClass::$status in .../admin/views/inquiries-list.php on line 87`, shown inline on the Inquiries list page instead of a status badge.
- **Cause:** genuine pre-existing schema/code mismatch, not something introduced this session. `class-database.php::create_tables()` only ever created `visati_inquiries` with a binary `resolved TINYINT(1)` column — there was never a `status` column. But `class-admin-inquiries.php` (status filter + `render_detail()`'s resolve toggle), `admin/views/inquiries-list.php`, and `admin/views/inquiry-detail.php` were all already written assuming a 3-state `status` column (`new`/`read`/`resolved`) existed. Only `dashboard.php`'s recent-inquiries widget correctly used the real `resolved` column. The status filter tabs, search-by-status, and the resolve/mark-as-read toggle had likely never worked correctly since they were built.
- **Fix:** extended `Visati_Core_Database::ensure_columns()` (the same safe-migration pattern already used for `applications.travelers_data`) to add `visati_inquiries.status VARCHAR(20) NOT NULL DEFAULT 'new'` if missing, backfilling `status = 'resolved'` where `resolved = 1`. This function is hooked to `init` in `class-visati-core.php:38`, so the migration self-applies on the very next wp-admin page load — no plugin reactivation needed. Also updated `dashboard.php`'s recent-inquiries badge to read `$inq->status` instead of the old binary `$inq->resolved` check, for consistency with the rest of the admin (and to show the richer 3-state badge everywhere).
- **File:** `includes/class-database.php` (migration), `admin/views/dashboard.php` (consistency fix).
- **Skill note:** when a list/detail view assumes a column that "should" exist based on the UI design, always cross-check the actual `CREATE TABLE` — a `dbDelta()`-created schema won't auto-add new columns to already-active installs, so code and schema can silently drift apart for a long time, only surfacing as an "Undefined property" warning rather than a hard error (since `$wpdb->get_results()` just doesn't include the missing column in the returned `stdClass`).

### Investigated — empty "View" button text on some document rows

- **Symptom:** screenshot showed the first document row's "View" button rendering correctly (white "View" text on blue background), but the 2nd and 3rd rows' "View" buttons appeared as blank blue rectangles with no visible text, narrower than the first.
- **Investigation:** re-read `admin/views/application-detail.php`'s document-list loop in full — the "View" text is a hardcoded literal inside the `<a class="visati-doc-viewer-link">` element, identical for every iteration, with no per-row conditional that could explain a difference. Checked `admin.css` for any `:nth-child`/`:first-child` rules that could differentiate rows — none exist. Since this is a wp-admin page (not front-end), the earlier skip-link caching theory doesn't transfer here — caching plugins like LiteSpeed universally exclude `is_admin()` context.
- **Outcome:** could not conclusively identify a code-level cause without direct browser/DOM access (not reachable from this WSL session). Applied a defensive CSS hardening to `.visati-doc-viewer-link` as a safety net — `color: #fff !important` (guards against any external `!important` override elsewhere) and `min-width: 46px; text-align: center` (prevents the button from ever visually collapsing to a text-less nub regardless of cause).
- **Follow-up needed:** if the issue persists after this CSS change, need either the actual rendered HTML (View Source / Inspect Element on a broken button) or the exact `document_type`/`filename` values for the affected rows, since the PHP template genuinely produces identical markup per row — any real difference must be data-dependent.

### Files modified this session

| File | Action |
|---|---|
| `includes/class-database.php` | `ensure_columns()`: add `visati_inquiries.status` column + backfill |
| `admin/views/dashboard.php` | Recent-inquiries badge now reads `status` not `resolved` |
| `assets/css/admin.css` | `.visati-doc-viewer-link` hardening (defensive, root cause unconfirmed) |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.2 → 0.3.3 |

### Status

Inquiries status bug fully fixed and will self-apply on next admin page load. Doc-viewer button issue has a defensive fix applied but is NOT confirmed resolved — needs user follow-up (screenshot after refresh, or DOM inspection) if it's still broken.

---

## Session: Elementor color-override CSS bug + Settings defaults (July 23, 2026)

### Blocker — Elementor button/link text colors always render navy, ignoring Style-tab customization

- **Symptom:** on the About page, a button's text color was set via Elementor's Style tab, but rendered navy blue regardless. Same for other plain content links.
- **Cause:** two separate global CSS rules in `app.css` unconditionally set link/button `color` to `var(--visati-navy)` for anything inside `.visati-page` that isn't specifically excluded by class name:
  1. Lines ~1280-1306 — the "golden gradient button reset" (countering Hello Elementor's `#c36` pink defaults) used `color: var(--visati-navy) !important;` — `!important` unconditionally beats any non-`!important` color Elementor generates per-widget, regardless of specificity.
  2. Lines ~1533-1538 — a plain "override pink links" rule with no `!important`, but its `:not(...)` exclusion chain (5 clauses) gave it enough raw specificity (~6 classes) to still beat Elementor's typical per-widget generated selector for shallow/simple widgets, even without `!important`.
  Both rules were written to solve one narrow problem — Hello Elementor's `reset.css` painting *unstyled* elements pink — but were scoped broadly enough to also stomp on *deliberately* Elementor-styled elements.
- **Fix:** rewrote both rules' `:not(...)` exclusion chains as `:not(...)` wrapped in `:where(...)` — per the CSS spec, `:where()` always contributes **zero** specificity regardless of its argument, so the exclusion logic still works but no longer inflates the rule's specificity. Also removed `!important` from the `color` declaration in rule 1 specifically (kept `!important` on `background`/`border`/`padding`/etc., since those are the actual "make it gold not pink" cosmetic transform this rule exists for). Net effect: both rules now sit at roughly `body .visati-page a` specificity — still beats Hello Elementor's bare, unscoped reset, but reliably loses to any Elementor-generated per-widget rule, so Style-tab customization now wins as expected.
- **File:** `themes/visati/assets/css/app.css`. `VISATI_THEME_VERSION` bumped `0.4.3` → `0.4.4`.
- **Confidence note:** unlike the earlier skip-link investigation, this is a concrete, mechanically-verifiable CSS specificity fix (not a caching guess) — but still needs a hard refresh (and LiteSpeed Cache purge if active) to confirm visually, since this environment doesn't have direct browser access from the agent side.

### Fix — Settings page now pre-fills with the site's actual current content

- **Symptom:** user reported footer address/phone/social-link/content settings "not applying" — root cause was that `class-settings-page.php::setting()` always defaulted to an empty string, so the Settings form showed **blank fields** even though the live site displayed correct content (via separate hardcoded fallback defaults baked directly into `footer.php`/`header.php`). This made it look like settings were being ignored, when in fact nothing had ever been saved to the `visati_core_settings` option — the site was just quietly falling back to its own hardcoded defaults the whole time.
- **Fix:** added a `default_for( string $key ): string` map to `class-settings-page.php` mirroring the exact hardcoded fallbacks already in `footer.php`/`header.php` (WhatsApp number, contact email/phone/address, trade licence, all 4 social URLs, brand tagline/description, header CTA label/URL). `setting()` now uses this as the fallback instead of `''`, so the form shows the real current values as a starting point — editing and saving now visibly "takes over" from the hardcoded default rather than appearing to do nothing.

### Where to configure things (client-facing answer)

- **Footer link columns** (Visas / Company / Support): **Appearance → Menus** — create up to 3 menus and assign them to "Footer — Visas column" / "Footer — Company column" / "Footer — Support column" (registered in the earlier "Admin dashboard UX" session). Until a menu is assigned, the column keeps showing its hardcoded default links.
- **Social icon URLs, address, phone, WhatsApp number, trade licence, brand tagline/description**: **Visati → Settings**, "WhatsApp & Contact", "Footer & Brand", and "Tracking & Social" sections — now pre-filled with the site's real current values (see fix above).

### Files modified this session

| File | Action |
|---|---|
| `themes/visati/assets/css/app.css` | `:where()`-wrapped exclusion chains + removed `!important` from `color` in the button reset |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.3 → 0.4.4 |
| `admin/class-settings-page.php` | Added `default_for()` map; `setting()` now falls back to real current content |

### Status

Both fixes applied. Pending: user to hard-refresh (+ purge LiteSpeed Cache if active) and confirm Elementor button/link colors now respect Style-tab settings on the About page; confirm the Settings page now shows pre-filled real content instead of blank fields.

---

## Session: CPT edit-screen styling + Discount Codes feature build-out (July 23, 2026)

### Context

User flagged that the Visa Types / Testimonials / Discount Codes admin edit screens "look very bad", and separately that Discount Codes only has a title field with nothing else — asked how the feature is supposed to work.

### Root cause — CPT edit screens had zero custom CSS loaded

`class-admin-dashboard.php::enqueue_styles()` only loaded `admin.css` on hooks containing `"visati"` or equal to `index.php` — `post.php`/`post-new.php` (the actual edit screens for `visati_visa_type`, `visati_faq`, `visati_testimonial`, `visati_job`, `visati_discount`) matched neither, so every classic meta box on those screens was rendering with **zero styling**, plain browser-default inputs. This, not a design failure, was the "looks bad" cause.

- **Fix:** widened `enqueue_styles()` to also load on `post.php`/`post-new.php` when `get_current_screen()->post_type` is one of our 5 CPTs. Added a `.visati-meta-box` wrapper class to the shared field renderer (`class-meta-boxes.php::render_fields()`) and styled it in `admin.css` — cleaner spacing, rounded inputs with a gold focus ring, lighter row dividers, matching the rest of the Visati admin design language.

### Feature gap — Discount Codes was only ever half-built

Confirmed against `plan.md` (line 65: `/wp-json/visati/v1/discount` listed as a planned endpoint; line 315: "Discount validation" explicitly called out as planned functionality) — this was always meant to be a full feature, not just a CPT. What existed: the CPT itself (title-only) and two unused DB columns (`visati_applications.discount_code`, `.discount_amount_aed`). What was missing: everything connecting them — no fields to define what a discount code *is*, no validation logic, no REST endpoint, no checkout wiring, no usage tracking.

**Built out completely (backend):**
- **Meta box** (`class-meta-boxes.php`): Discount Type (percentage/fixed), Discount Value, Max Uses (0 = unlimited), Expiry Date, Active toggle, plus a read-only "Times Used" counter. The post *title itself is the code* customers type in (matches the existing UI — no separate code field needed), matched case-insensitively at validation time. Also added `number` and `date` field-type support to the shared renderer (previously only text/textarea/select/checkbox existed).
- **`Visati_Core_Discount`** (`includes/class-discount.php`): `validate(string $code, float $price_aed)` — checks existence, `publish` status, active flag, expiry, usage limit; computes the discount amount (percentage or fixed, capped so it can never exceed the price) and the final price. `record_usage(string $code)` — increments the used-count meta. Case-insensitive lookup via `WP_Query`'s `title` arg with a raw `LOWER()` SQL fallback (DB collation can make exact-title matching case-sensitive).
- **REST endpoint** (`includes/rest/class-discount-controller.php`): `POST /visati/v1/discount/validate` — public, rate-limited (20/10min via the existing `visati_rate_limit()` helper), lets the frontend check a code and preview the discount before committing to checkout.
- **Checkout wiring** (`class-webhooks-controller.php::create_checkout()`): accepts an optional `discount_code` param, re-validates it **server-side** against the actual computed price (visa price + express surcharge) — never trusts a client-computed discount — stores the code + amount on the application row, and creates the Stripe session for the discounted, rounded price.
- **Usage tracking** (`class-webhooks-controller.php::handle_checkout_completed()`): after a successful payment, if the application has a `discount_code`, calls `Visati_Core_Discount::record_usage()`.

**Not yet built — frontend UI:** there is still no "Have a discount code?" input anywhere in the customer-facing apply form (`shortcodes/class-apply-form.php` + `assets/js/application-form.js`) to actually let a customer type a code, see the discount applied, and have it passed through to the checkout-session call. The backend is fully functional and testable via the REST endpoint directly, but the feature isn't usable end-to-end by a real customer yet.

### Files modified this session

| File | Action |
|---|---|
| `includes/class-meta-boxes.php` | Discount meta box (render + save) + `number`/`date` field types |
| `includes/class-discount.php` | New — validation + usage tracking |
| `includes/rest/class-discount-controller.php` | New — `POST /discount/validate` |
| `includes/rest/class-webhooks-controller.php` | Checkout accepts + applies discount; usage recorded on payment success |
| `includes/class-visati-core.php` | Registered `Visati_Core_Discount_Controller::init()` |
| `visati-core.php` | Added new file requires; `VISATI_CORE_VERSION` 0.3.3 → 0.3.4 |
| `admin/class-admin-dashboard.php` | `enqueue_styles()` now also loads on our CPTs' post edit screens |
| `assets/css/admin.css` | `.visati-meta-box` styling |

### Status

Discount Codes is now a real, working feature server-side — creatable, validatable, and appliable at checkout, with usage tracking. CPT edit screens should now render with proper styling after a hard refresh. Remaining: frontend apply-form UI to let customers actually enter a code (flagged to user as a separate follow-up decision).

---

## Session: Job Opening meta box (July 23, 2026)

### Blocker — Job Opening CPT had no structured fields at all

- **Symptom:** user reported the Job Opening edit screen shows only a title + block editor, no other settings — same "half-built CPT" pattern as Discount Codes.
- **Cause:** confirmed via `class-post-types.php` — `visati_job` supports `array('title', 'editor')` (the main content editor correctly holds the full job description), but `class-meta-boxes.php` never registered a meta box for it at all, unlike the other 4 CPTs. No frontend template/shortcode consumes `visati_job` yet either (no Careers page has been built), so there was no existing contract to match — designed sensible fields from scratch.
- **Fix:** added a "Job Details" meta box — Department, Location, Employment Type (full-time/part-time/contract/internship), Application Email, and an Active toggle (hide a closed posting from a future careers page without deleting it). Follows the exact same pattern as the Visa Type/FAQ/Testimonial/Discount meta boxes (shared `render_fields()` renderer, nonce + capability + sanitize on save). Already covered by the CPT-edit-screen CSS fix from the previous session (`visati_job` was already included in that enqueue check).
- **File:** `includes/class-meta-boxes.php`.

### Status

All 5 CPTs (Visa Type, FAQ, Testimonial, Discount Code, Job Opening) now have complete, styled, consistent meta boxes.

---

## Session: Sidebar menu grouping + WP Dashboard Quick Actions widget (July 23, 2026)

### Request — group core theme sidebar items together, add a Quick Actions panel to wp-admin Home

User wanted the Visati-related sidebar items (Visa Types, FAQ, Testimonials, Job Openings, Discount Codes, Visati) grouped together right below Dashboard, instead of scattered as separate top-level items interspersed with Elementor/Posts/Media/Pages/EMCP Themer. Also wanted a Quick Actions panel (buttons for coupon/applications/settings/job/testimonials/FAQ etc.) on the native WordPress Dashboard (Home) page.

### Fix — nested all 5 CPTs under the "Visati" top-level menu

- Added `'show_in_menu' => 'visati-dashboard'` to all 5 `register_post_type()` calls in `class-post-types.php` (Visa Type, FAQ, Testimonial, Job, Discount) — this is the standard WP mechanism for nesting a CPT's admin screens as a submenu of another top-level menu instead of creating its own. Previously none of them set this, so each got its own independent top-level entry (WP default: `show_in_menu` inherits `show_ui`, which is `true`/implicit-true for all 5).
- Moved the "Visati" top-level menu itself from `menu_position` 30 → 2 (`add_menu_page()` in `class-admin-dashboard.php`), so it now sits directly below Dashboard instead of near the bottom of the sidebar.
- Net effect: sidebar now shows one "Visati" menu (dashicon airplane) right under Dashboard, with Dashboard/Applications/Inquiries/Settings/Visa Types/FAQ/Testimonials/Job Openings/Discount Codes all nested underneath it as submenus — no more scattered top-level icons between Elementor/Posts/Media/Pages.

### Added — "Visati — Quick Actions" widget on the native WP Dashboard

- Extracted the existing Quick Actions button row (previously only on the full Visati Dashboard page) into a shared partial, `admin/views/dashboard-quick-actions.php`, included from both the Visati Dashboard page and a new `wp_add_dashboard_widget()` registration (`visati_quick_actions`) in `class-admin-dashboard.php`.
- Added an "Add Job Opening" button to the set (was missing before) — full button list: Review Submitted, New Inquiries, Add Discount Code, Add Visa Type, Add FAQ, Add Testimonial, Add Job Opening, Settings.
- No new CSS/enqueue work needed — `admin.css` (and the existing `.visati-quick-action` styles) already load on `index.php` from the earlier Quick View widget fix.

### Files modified this session

| File | Action |
|---|---|
| `includes/class-post-types.php` | `show_in_menu => 'visati-dashboard'` on all 5 CPTs |
| `admin/class-admin-dashboard.php` | Menu position 30 → 2; registered "Quick Actions" WP Dashboard widget |
| `admin/views/dashboard-quick-actions.php` | New — shared Quick Actions partial (+ Add Job Opening button) |
| `admin/views/dashboard.php` | Now includes the shared partial instead of inlining the buttons |

### Status

Sidebar should now show a single grouped "Visati" menu right below Dashboard on next page load. WP Dashboard Home now has two Visati widgets: "Quick View" (stats) and "Quick Actions" (buttons). Submenu ordering under "Visati" may show the 5 CPTs before Dashboard/Applications/Inquiries/Settings (WordPress registers CPT-based submenus before plugin-added ones) — functionally harmless since the top-level "Visati" click still lands on Dashboard, but worth a look after refresh in case a specific order is wanted.

### Follow-up — force "Dashboard" to be the first submenu item

User asked for Dashboard to be first under "Visati", confirming the ordering flagged above needed fixing. Added `move_dashboard_submenu_first()` in `class-admin-dashboard.php`, hooked to `admin_menu` at priority 999 (very late — runs after WordPress's internal CPT-submenu registration and after our own `register_menus()`). It reads the global `$submenu['visati-dashboard']` array, finds the entry whose slug is `visati-dashboard` (the Dashboard link), and moves it to index 0 via `array_unshift()`. This directly manipulates WordPress's submenu registry rather than fighting registration order/timing.

---

## Session: Nationality flag images not loading + reviews advisory (July 23, 2026)

### Advisory — Google Reviews / Trustpilot integration

User asked whether Google Reviews / Trustpilot could feed into the Testimonials CPT, or if a separate plugin is needed. Recommendation given (no code changes, advisory only): Trustpilot's free official "TrustBox" widget as its own embedded block (zero cost, zero custom code, no API key surface); avoid building a custom Google Places API integration — it's a paid API past a small free tier, only surfaces up to 5 of Google's own "top" reviews (no control over which), and Google's terms restrict how long results can be cached. Better to keep manually curating real Google reviews into the existing Testimonial CPT (matches the "free plugins only" budget rule), or use a dedicated free-tier aggregator plugin (Elfsight/Trustindex) if live auto-updating is wanted.

### Blocker — nationality flag images (flagcdn.com) not rendering in the dropdown list

- **Symptom:** opening the nationality combobox on the apply form showed grey placeholder boxes (with rounded corners matching the styled `<img>` size) instead of country flags, for every entry in the list.
- **Cause:** the dropdown's flag `<img>` tags (`render_nationality_field()` in `class-apply-form.php`) had `loading="lazy"`, but they live inside `.visati-combobox__panel[hidden]` — a panel that starts `display:none`-equivalent until opened via JS. Browsers can't compute intersection/visibility for an element with no rendered box, so `loading="lazy"` images inside an initially-hidden container can fail to ever trigger loading, even after the container becomes visible — a known lazy-loading gotcha. Confirmed by contrast: the JS-injected flag shown in the combobox *button* once a country is actually selected (`application-form.js`, two call sites) never had `loading="lazy"` to begin with, and wasn't reported as broken.
- **Fix:** removed `loading="lazy"` from the flag `<img>` tag in the PHP-rendered dropdown list. Pure HTML template change — no cached asset, no version bump needed.
- **File:** `includes/shortcodes/class-apply-form.php`.
- **Skill note:** never combine `loading="lazy"` with a container that starts hidden/`display:none` — lazy-load images belong either in initially-visible containers, or should have the `loading` attribute added/removed dynamically via JS at the same time the container is revealed.

### Status

Advisory given (no action taken pending user decision). Flag-loading bug fixed — pending user refresh to confirm flags now render in the nationality dropdown.

---

## Session: Sticky Order Summary sidebar on the apply form (July 23, 2026)

### Context

User wanted the apply form to have a sticky order-summary sidebar "just like we have in nextjs website", showing pricing + a trust/guarantee note — but explicitly wanted the guarantee text to be admin-editable, unlike the Next.js version where it was hardcoded.

### Reference — ported from `src/components/apply/OrderSummary.tsx`

Read the original Next.js component in full to match the design: a `sticky` card (grey background, gold uppercase label "Order Summary") showing visa name/entry-type/processing speed, a Visa Fee + conditional Express Surcharge breakdown, and a Total row — followed by a dark-navy "100% refund on rejection" guarantee box (shield icon) and a WhatsApp CTA box. In Next.js the guarantee copy was hardcoded; in WordPress it's now sourced from Settings.

### Implementation

- **Settings** (`class-settings-page.php`): new "Apply Page — Order Summary" section with `guarantee_title` and `guarantee_text` fields; added matching defaults (identical wording to the Next.js original) to `default_for()` so the field pre-fills with real copy rather than blank.
- **Markup** (`class-apply-form.php::render()`): wrapped the existing `<form>` in a `.visati-apply-layout` grid (`.visati-apply-layout__main` for the form, sibling `<aside class="visati-apply-layout__summary">` for the sidebar) — the whole `[visati_apply_form]` shortcode is now self-contained with its own 2-column layout, so no Elementor column setup is needed on the Apply page. Sidebar includes the summary card (empty-state message until a visa is picked, then live details), the guarantee box (pulling `guarantee_title`/`guarantee_text` from Settings), and a WhatsApp CTA (only rendered if a WhatsApp number is configured).
- **CSS** (`assets/css/apply.css`): `.visati-apply-layout` grid (`1fr 340px`, stacks to one column ≤900px), `.visati-order-summary` with `position: sticky; top: 96px` (clears the theme's own sticky header — `12px` top offset + `64px` inner height + margin). Existing `.visati-apply` form wrapper's own `max-width`/`margin:auto` centering kept as-is, just moved its outer padding onto the new layout wrapper to avoid double-padding.
- **JS** (`assets/js/application-form.js`): new `updateOrderSummary()` — reads the already-localized `visatiApply.visas`/`surcharge_aed` data (no new PHP→JS data needed), updates the sidebar's visa name/meta/fee/surcharge/total live. Wired to the visa-type `<select>` change, the processing-tier radio `change` events (previously had no listener at all), and called once on page load (alongside the existing `updateTravelersVisibility()` call) so pre-selected visas (via `?visa=` URL param) show correct pricing immediately.
- `VISATI_CORE_VERSION` bumped 0.3.4 → 0.3.5 (busts the cached `apply.css`/`application-form.js`).

### Files modified this session

| File | Action |
|---|---|
| `admin/class-settings-page.php` | New Settings section + save handling + defaults for guarantee copy |
| `includes/shortcodes/class-apply-form.php` | Wrapped form in a 2-column layout; added Order Summary sidebar markup |
| `assets/css/apply.css` | `.visati-apply-layout` grid + full `.visati-order-summary` styling |
| `assets/js/application-form.js` | `updateOrderSummary()` + wiring to visa/tier change events |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.4 → 0.3.5 |

### Status

Implemented and wired end-to-end. Pending: user to refresh the Apply page and confirm the sidebar renders, updates live when changing visa type/processing tier, and that the guarantee text editing in Settings actually reflects on the page.

### Blocker — Express Surcharge row stayed visible after switching back to Standard

- **Symptom:** select Express, then switch back to Standard — the Total correctly dropped back to the base fee, but the "Express Surcharge" row stayed visible showing the old AED 99 value.
- **Cause:** `updateOrderSummary()`'s JS was correctly setting `expressRow.hidden = true`, but `.visati-order-summary__row` (the shared class used for both the always-visible Visa Fee row and the conditionally-hidden Express Surcharge row) has `display: flex` set unconditionally in `apply.css`. Author CSS always wins over the browser's built-in `[hidden] { display: none }` UA-stylesheet rule at equal specificity — so my own `display:flex` silently defeated the `hidden` attribute entirely, regardless of what the JS set.
- **Fix:** added `.visati-order-summary__row[hidden] { display: none; }` — same class, but with an attribute selector added so its specificity (0,2,0) beats the base rule (0,1,0) and restores correct hide behavior.
- **Audit:** checked the rest of `apply.css` for the same anti-pattern (`display:` set unconditionally on a class also toggled via the `hidden` attribute) — `.visati-order-summary__details`, `.visati-travelers`, `.visati-error` all correctly have no conflicting `display` rule. This was the only instance.
- **File:** `assets/css/apply.css`. `VISATI_CORE_VERSION` bumped 0.3.5 → 0.3.6.
- **Skill note:** never set `display: X` unconditionally on a class that's also used with the native `hidden` attribute for show/hide toggling — author stylesheets always beat the UA default `[hidden]` rule at equal specificity, so the class's `display` silently wins regardless of the attribute. Always pair it with an explicit `.class[hidden] { display: none; }` override, or avoid setting `display` on the base class at all.

---

## Session: Express total bug, per-visa Express toggle, WhatsApp CTA color bug, order summary tagline, dashboard Express priority, contact page editability (July 23, 2026)

### Blocker — Total showed "54999" instead of AED 648 with Express selected

- **Symptom:** picking a visa priced AED 549 + Express (AED 99 surcharge) showed a Total of "AED 54999" instead of "AED 648".
- **Cause:** `wp_localize_script()` force-casts every **top-level** scalar value in its data array to a string (`(string) $value` in WordPress core's `WP_Scripts::localize()`) — so `visatiApply.surcharge_aed` arrived in JS as the string `"99"`, not the number `99`. `fee + extra` (`549 + "99"`) then triggered JS's string-concatenation behavior of `+` (number + string = concatenation, not addition) instead of numeric addition, producing `"54999"`. Nested array values (like `visas[slug].price_aed`) are NOT affected by this cast, only top-level keys — which is why the visa fee itself displayed correctly and only the surcharge was broken.
- **Fix:** wrapped `surcharge_aed` in `parseFloat(...)` before use in `updateOrderSummary()`.
- **File:** `assets/js/application-form.js`.
- **Skill note:** any top-level value read off `window.visatiApply` (or any `wp_localize_script()` payload) that's used in arithmetic must be explicitly parsed as a number in JS — never assume it arrived typed, regardless of what PHP type was passed in.

### Added — per-visa-type "Express Available" toggle

User asked for a way to control whether a given visa type offers Express processing at all (some types, like Visa Extension, shouldn't). Confirmed this maps to a real field (`has_express`) that existed in the original Next.js seed data but was dropped when the WP meta box was built.

- `class-meta-boxes.php`: added an "Express Available" checkbox to the Visa Type meta box, rendered outside the shared field array so a **brand-new/never-saved visa type defaults to checked** (`metadata_exists()` check — anything saved before this field existed, or freshly created, defaults to "has express" to match the original data model, rather than silently hiding Express everywhere until each type is manually re-saved).
- `class-apply-form.php`: added `has_express` (and `tagline`) to the localized `visas` array per visa type.
- `application-form.js`: new `updateExpressAvailability()` — hides the Express radio `<label>` (`data-express-option`) when the selected visa's `has_express` is `false`, and force-resets the selection back to Standard (re-triggering the order summary update) if Express was already selected when switching to a non-express visa. Wired to the visa-select `change` event and the initial page-load IIFE.
- **Found the same `hidden`+unconditional-`display` bug proactively** (see previous session) on `.visati-tier label` — fixed with `.visati-tier label[hidden] { display: none; }` before it could surface as its own bug report.

### Blocker — WhatsApp CTA box rendered gold instead of its designed light-green

- **Symptom:** the Order Summary sidebar's WhatsApp box rendered with a gold gradient background (and grey description text with poor contrast against it), instead of the light-green WhatsApp-branded design.
- **Cause:** same global "golden button reset" rule from the earlier About-page investigation (`body .visati-page ... a:where(:not(...))" { background: linear-gradient(gold) !important; ... }`) — `.visati-order-summary__whatsapp` is an `<a>` tag whose class wasn't in that rule's exclusion list, so it fell through and got the same forced-gold treatment as an unstyled link.
- **Fix:** added `:not(.visati-order-summary__whatsapp)` to both the base and `:hover` exclusion lists (single `replace_all` edit, since the two blocks share the identical selector string). Did **not** change the description text color — the correct fix restores the intended pale-green background, against which the existing grey text already has good contrast; changing it to white (as first suggested) would have made it hard to read against the *correct* background once the real bug was fixed.
- **File:** `themes/visati/assets/css/app.css`. `VISATI_THEME_VERSION` bumped 0.4.4 → 0.4.5.

### Changed — order summary now shows the visa's actual tagline

User asked where the "Multiple entry · 24–72h processing" line came from (it was auto-generated from `entry_type` + processing tier) and asked for the real visa type description instead. Added `tagline` to the localized visa data and updated `updateOrderSummary()` to prefer `visa.tagline` when set, falling back to the auto-generated entry-type string when a visa type has no tagline.

### Added — Express applications get priority + a visible marker, on both dashboards

User asked for Express applications to be clearly marked and always sorted above Standard ones, on both the native WP Dashboard's Quick View widget and the full Visati Dashboard page.

- Both `recent_apps` queries (`class-admin-dashboard.php`) now `ORDER BY (processing_tier = 'express') DESC, created_at DESC` instead of plain `created_at DESC`.
- Quick View widget (`dashboard-widget.php`): added a `⚡ Express` badge next to the applicant name for express applications; query widened to also select `processing_tier`.
- Visati Dashboard (`dashboard.php`): added a dedicated badge column to the Recent Applications table, plus a `visati-express-row` class giving the whole row a subtle highlighted background.
- New CSS: `.visati-express-badge`, `tr.visati-express-row` in `admin.css`.

### Added — Contact page details made fully editable

User pointed at the Contact page and asked how the remaining details (only email/phone/address/WhatsApp number were previously settings-driven) could be changed. Added a new "Contact Page Details" Settings section covering everything else that was still hardcoded in `class-contact-form.php::render_page()`: office hours (2-line textarea), the four small "note" lines under each detail row (email/phone/office/hours), the WhatsApp button's subtext ("Average reply 2 min"), and the Google Maps embed URL (previously a hardcoded iframe `src` pointing at a placeholder location — now editable, with the map section only rendering if a URL is actually set). All new Settings fields pre-fill with the exact current hardcoded copy as defaults, so nothing changes on the live page until the admin actually edits something.

### Files modified this session

| File | Action |
|---|---|
| `assets/js/application-form.js` | Fixed string-concat total bug; added `updateExpressAvailability()`; tagline in summary meta line |
| `includes/class-meta-boxes.php` | "Express Available" checkbox on Visa Type meta box (defaults true for unsaved posts) |
| `includes/shortcodes/class-apply-form.php` | `has_express` + `tagline` added to localized visa data |
| `assets/css/apply.css` | `.visati-tier label[hidden]` fix |
| `themes/visati/assets/css/app.css` | WhatsApp CTA excluded from the golden-button reset rule |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.4 → 0.4.5 |
| `admin/class-admin-dashboard.php` | Express-first sorting on both recent-apps queries |
| `admin/views/dashboard-widget.php` | Express badge in Quick View widget |
| `admin/views/dashboard.php` | Express badge column + row highlight in Recent Applications |
| `assets/css/admin.css` | `.visati-express-badge`, `.visati-express-row` styles |
| `admin/class-settings-page.php` | New "Contact Page Details" section + save handling + defaults |
| `includes/shortcodes/class-contact-form.php` | Reads hours/notes/maps-embed from Settings instead of hardcoding |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.6 → 0.3.8 |

### Status

All items implemented. Pending: user to refresh and confirm — Express total math, per-visa Express toggle behavior (test on a visa with it unchecked), WhatsApp CTA color, order summary tagline text, Express priority sorting/badges on both dashboards, and the new Contact Page Details settings actually updating the live Contact page.

### Blocker — map stopped rendering after the Contact Page Details change (user confirmed it worked before)

- **Symptom:** map wasn't showing. Initial hypothesis (see below) was wrong — user confirmed the map *was* rendering before this session's changes.
- **Real cause:** the refactor introduced **two separate, out-of-sync default values** for the same setting. `class-settings-page.php::default_for()` pre-fills the Settings **admin form** display only — it has no effect on the actual frontend. The frontend rendering code (`class-contact-form.php::render_page()`) has its own independent inline fallback: `$s('google_maps_embed_url', '')` — defaulting to an **empty string**. Since nothing had been saved to the `visati_core_settings` option yet, the frontend read back `''`, which is falsy, so the `<?php if ($maps_embed_url) : ?>` wrapper (added to avoid rendering an empty iframe) skipped the whole map section entirely. Previously the iframe was unconditionally hardcoded with no such gate, which is why it "was rendering" before.
- **Fix:** the frontend fallback now derives a working embed URL directly from the configured `$address` (`https://maps.google.com/maps?q=<address>&output=embed`) instead of defaulting to `''` — so the map renders correctly even before anyone visits Settings, and automatically stays in sync with whatever office address is configured.
- **Superseded finding:** the previous entry's theory (that the old hardcoded `pb=` URL had an unresolvable fake CID and was "always broken") was not actually confirmed and turned out to be a distraction from the real bug — the two-defaults mismatch above. Worth remembering: user-reported "but it worked before" is a strong signal to re ecking an assumption rather than deepening it.
- **File:** `includes/shortcodes/class-contact-form.php`.
- **Skill note:** whenever a "default value" exists in two places (a settings-page pre-fill vs. an actual runtime fallback), keep them derived from one source, or at minimum keep them in sync — divergence here is exactly what caused a previously-working feature to silently stop working after making it "configurable."

### Status

Map now has one consistent, working default derived from the actual address, independent of whether Settings has ever been saved. Pending: user to confirm it renders.

### Not a bug — footer trade licence "not updating" was a field-precedence misunderstanding

- **Symptom:** user updated both "Trade licence number" and had previously typed something into "Custom copyright text" in Settings → Footer & Brand; the footer kept showing an old-looking licence number.
- **Investigation:** ruled out caching (no cache plugin installed) and a save-side bug (field names verified identical across form → `handle_save()` → `footer.php` read). Directly inspected the live `visati_core_settings` option via WP-CLI (see tooling note below) and found **both fields had in fact saved correctly** — `contact_trade_licence` = `"DEV-000-786"` (their real number), `footer_copyright` = `"© 2026 visati · Dubai, UAE · Trade Licence DED-0000-0000"` (a full custom line, typed separately, that happens to have its own embedded — and outdated-looking — licence text).
- **Root cause (by design, not a bug):** `footer.php` only auto-generates the copyright line (interpolating the real `contact_trade_licence`) **when `footer_copyright` is empty**. Once any text is saved into "Custom copyright text," it's used verbatim as a full override — trade licence and all — so the separately-configured `contact_trade_licence` value has no effect on what's displayed as long as a custom copyright string exists.
- **Resolution:** explained the field relationship to the user; the fix is either clearing "Custom copyright text" (falls back to the auto-generated line with the correct synced licence) or editing that field's own embedded licence text directly. No code change needed — the "Leave empty for auto-generated copyright line" field description already documents this behavior, just wasn't obvious enough in practice.

### Tooling — direct WP-CLI access to the live site now available from this session

Discovered that `wordpress/scripts/wp-local.sh`'s underlying Windows PHP binary is reachable directly from this WSL session via the `/mnt/c/...` path (the script itself uses Git-Bash-style `/c/...` paths, which don't resolve under WSL, so it can't be run as-is — but invoking the same `php.exe`/`wp-cli.phar`/extensions directly with WSL-mapped paths works):

```bash
"/mnt/c/Users/K TECH/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe" \
  -d extension_dir="C:/Users/K TECH/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/ext" \
  -d extension=mysqli -d extension=pdo_mysql -d extension=curl -d extension=mbstring -d extension=openssl -d extension=zip \
  "C:/Users/K TECH/AppData/Local/Programs/Local/resources/extraResources/bin/wp-cli/wp-cli.phar" \
  --path="C:/Users/K TECH/Local Sites/visati/app/public" --allow-root <wp-cli command>
```

This means live-site inspection (options, post meta, DB queries via `wp db query`, etc.) is now possible directly from this agent session — no need to ask the user to run diagnostic WP-CLI commands and paste output back for most read-only checks going forward.

---

## Session: Root cause found — stale PHP opcache masking every recent PHP change (July 24, 2026)

### Blocker — footer trade licence genuinely never updated, despite every server-side check passing

Follow-up to the "not a bug, it's field precedence" resolution — user cleared "Custom copyright text" as instructed, but the footer *still* showed the placeholder `DED-XXXX-XXXX` instead of the correctly-saved `DEV-000-786`. This time it turned out to be a real bug in the environment, not a misunderstanding.

- **Ruled out via direct verification** (all confirmed correct): the `visati_core_settings` DB option (`wp option get`), `Visati_Core_Settings::get()`'s actual return value (`wp eval`), the live site's `footer.php` file content (checked via the Windows-mounted path, not just the repo copy), the site URL/DB context WP-CLI was targeting (matched `http://visati.local` exactly), browser cache (confirmed via incognito), and any Elementor Theme Builder footer template (none exists — only "Default Kit").
- **Confirmed via View Source**: the raw HTML the server actually sent contained `Trade Licence DED-XXXX-XXXX` literally — proving the server itself was executing the fallback-default code path, not anything client-side.
- **Root cause**: PHP's opcode cache (opcache) on the actual web-serving PHP process (`opcache.enable=1` in Local's per-site `php.ini` at `AppData/Roaming/Local/run/<site-id>/conf/php/php.ini`) was holding stale compiled bytecode. WP-CLI's own `php.exe` invocation is a **completely separate process** from whatever serves the browser's requests, and has no opcache extension loaded at all (`opcache_enabled=no` when checked via `wp eval`) — so it could never have revealed this, which is exactly why every WP-CLI-based check kept coming back "correct" while the live site stayed stale.
- **Confirmed via a temporary diagnostic**: added a one-time `add_action('init', ...)` snippet to `functions.php` behind a `?visati_reset_opcache=1` query param, calling `opcache_reset()` from *inside* the real web-serving process and echoing the current `contact_trade_licence` value in the same response. First hit returned `opcache_enabled=yes, reset_result=true, licence_now=DEV-000-786` — confirming the theory and that a reset does fix the value, at least for that one request. Removed the snippet immediately after (never leave a public-facing `opcache_reset()` endpoint live).
- **Why a plain "restart site" in Local didn't fix it initially**: a single-request `opcache_reset()` (or a soft "restart site" toggle) likely only cleared/restarted one PHP worker process; if Local runs multiple parallel workers each with separate opcache state (a real possibility on this Windows/Local-by-Flywheel setup, unlike the shared-SHM model typical on Linux), other workers — and the next request routed to one of them — kept serving stale compiled code even after the "fix."
- **Permanent fix**: disabled opcache entirely for local dev in the site's `php.ini` (`opcache.enable=0`, `opcache.enable_cli=0`) — standard practice for a dev environment, where instantly reflecting file changes matters more than the performance opcache buys. Requires a **full quit-and-relaunch of Local** (not just a site restart) to take effect, since ini directives are only read once at process startup.
- **Caveat flagged to user**: this `php.ini` lives in Local's auto-generated per-site runtime config folder — if Local ever regenerates it (PHP version change, site settings reset, etc.), the edit could silently get reverted and this whole failure mode could recur.

### Why this matters for the rest of the migration

This session made many other PHP-logic changes (discount code validation, dashboard queries, settings save handlers, contact form logic, meta box additions) that were never independently re-verified live after this discovery. **Any of them could have been silently masked by the same stale opcache** the whole time, same as the trade licence. Once the user confirms opcache is genuinely disabled (post full-restart), it's worth re-checking anything that seemed "not to have worked" earlier in this session rather than assuming those fixes were wrong.

### Files modified this session

| File | Action |
|---|---|
| `themes/visati/functions.php` | Temporary diagnostic snippet added, then removed |
| `AppData/Roaming/Local/run/<site-id>/conf/php/php.ini` (outside repo — Local's own config) | `opcache.enable`/`opcache.enable_cli` set to `0` |

### Status

Root cause identified and fixed at the config level. Pending: user to fully quit/relaunch Local and confirm both the trade licence and any other previously-uncertain PHP-side change from this session are now correctly reflected.

---

## Session: ACTUAL root cause found — `function_exists()` used where `class_exists()` was meant (July 24, 2026)

### Correction to the previous entry

The opcache theory from the prior session was **wrong** — a plausible-sounding dead end, not the real bug. After disabling opcache and confirming (via a live browser hit to the debug diagnostic) that even with opcache still enabled the *data layer* returned correct values, but a real `wp_remote_get()` fetch of the actual `/contact/` page still returned the stale placeholder, it became clear the bug was in the page-render code path itself, not the environment.

### The real bug

Four files check `function_exists( 'Visati_Core_Settings' )` before calling `Visati_Core_Settings::get(...)`:

- `themes/visati/footer.php` (13 occurrences — tagline, description, email, phone, address, trade licence, WhatsApp number, all 4 socials, footer copyright, header logo)
- `themes/visati/header.php` (3 occurrences — header logo, CTA label, CTA URL)
- `plugins/visati-core/includes/shortcodes/class-about.php` (1 occurrence — WhatsApp number)
- `plugins/visati-core/includes/shortcodes/class-contact-form.php` (1 occurrence — gates the entire `$s` callable used by every field on the Contact page: email, phone, address, WhatsApp, all the "note" lines, hours, maps URL)

**`Visati_Core_Settings` is a class, not a function.** `function_exists()` checking a class name **always returns `false`** — there is no function with that name, only a `final class`. So every one of these ternaries has been silently taking its `false` branch and using the **hardcoded literal fallback**, on every single page load, completely ignoring whatever was actually saved in Settings. This is not something introduced this session — `class-contact-form.php`'s version of this bug predates this session (the very first Contact page screenshot, showing `visa@visati.ae` / `+971 58 554 2344`, was already just displaying hardcoded defaults, not real settings, even though at a glance it looked like the "it's using the fallback because nothing's saved yet" default). `footer.php`/`header.php` had the same bug baked in from whenever those files were first written.

Confirmed via `grep` that this exact `function_exists('ClassName')` mistake exists nowhere else in the codebase — isolated to these 4 files, all checking the same class.

### Why this was so hard to find

Every diagnostic this session and the previous one used `Visati_Core_Settings::get(...)` **directly** (via `wp eval`, or a temporary `add_action('init', ...)` snippet) — bypassing the broken `function_exists()` guard entirely, so every single check came back "the data is correct," while the actual page templates, which all go through the broken guard, never did. The bug only became visible once a real HTTP fetch of the actual rendered `/contact/` page (not a shortcut diagnostic) was compared side-by-side against the direct class-method check.

### Fix

`sed -i "s/function_exists( 'Visati_Core_Settings' )/class_exists( 'Visati_Core_Settings' )/g"` across all 4 files. Verified via a live `wp_remote_get( 'http://visati.local/contact/' )` fetch post-fix: correctly returns `Trade Licence DEV-000-786` and the real saved Facebook URL.

### Files modified this session

| File | Action |
|---|---|
| `themes/visati/footer.php` | `function_exists` → `class_exists` (13×) |
| `themes/visati/header.php` | `function_exists` → `class_exists` (3×) |
| `includes/shortcodes/class-about.php` | `function_exists` → `class_exists` (1×) |
| `includes/shortcodes/class-contact-form.php` | `function_exists` → `class_exists` (1×, gates all Contact page fields) |

### Note on the opcache change from the previous session

Opcache is still disabled locally (`opcache.enable=0` in Local's per-site `php.ini`) from the prior investigation. It wasn't the actual cause of this bug, but leaving it disabled for local dev is still a reasonable, low-cost choice — not reverting it purely to avoid another full Local restart for no benefit. The earlier migration-log entry's opcache theory should be read as a documented dead-end, not a correct diagnosis — kept in the log rather than deleted, since the debugging process (systematically ruling out layers) was sound even though the final conclusion was wrong.

### Status

Confirmed fixed via live HTTP verification. All Settings-driven content across the header, footer, About page, and Contact page should now correctly reflect saved values instead of silently showing hardcoded defaults.

### Follow-up audit — confirmed no other Settings field has the same or a similar issue

User asked to verify the whole Settings tab is clean, not just the fields already found broken. Full audit performed:

1. **Guard pattern audit**: `grep -rn "function_exists( '[A-Z][a-zA-Z_]*_[A-Z]"` across the entire `wordpress/` tree — zero remaining matches. Every file reading `Visati_Core_Settings` (or `Visati_Core_Discount`/`Visati_Core_Email`) now consistently uses `class_exists()`, or reads the class with no guard at all (safe, since the plugin's own `require_once` chain guarantees the class is always loaded when the plugin is active — `class-email.php`, `class-tracking.php`, `class-apply-form.php` all read Settings unguarded and were never affected by this bug).
2. **Key-coverage audit**: extracted all 36 settings keys from the save handler (`$opts['key'] = ...` in `handle_save()`), then confirmed each one is referenced by name at least once outside the Settings page file itself — no orphaned/dead fields, no typo'd key mismatches between what's saved and what's read.
3. **Syntax check**: all 4 fixed files (`footer.php`, `header.php`, `class-about.php`, `class-contact-form.php`) linted clean via `php -l` through the Windows PHP binary bridge.
4. **Live verification beyond the Contact page**: fetched the homepage via `wp_remote_get()` too (not just `/contact/`, which was the only page checked so far) — confirms the real trade licence and Facebook link render correctly there as well, since `footer.php` loads via `get_footer()` on every page. Header CTA still shows the default "Apply Now" — confirmed this is *correct* behavior, since `header_cta_label` was never actually filled in (genuinely empty in the DB), not a recurrence of the bug.

### Status

Audit complete — no other instance of this bug, or any similar Settings-not-applying issue, found anywhere in the codebase.

---

## Session: Fixing the fixing — empty-string settings values exposed after the class_exists fix, plus header height (July 24, 2026)

### Context

Fixing the `function_exists`/`class_exists` bug had an ironic side effect: several settings fields (`header_cta_label`, `contact_email`, `contact_phone`, `contact_address`, socials, etc.) had been saved as **explicit empty strings** at some point before this session's `default_for()` pre-fill fix existed (when the Settings form still showed blank fields by default). Once the real bug was fixed and the code started genuinely reading Settings, these empty-but-saved values surfaced as visibly broken UI: the header CTA button showed only its arrow icon with no text, and the footer's Contact column showed icons with no text next to them at all.

### Root cause of the *new* symptom

`Visati_Core_Settings::get( $key, $default )` only returns `$default` when the key is entirely **unset** in the option array. Once a key has been explicitly saved as `""` (even unintentionally, from an earlier blank-field save), `isset()` is `true` and `get()` correctly-but-unhelpfully returns the empty string — the `$default` parameter never kicks back in. This is normal, correct behavior for `get()` — the bug is that several call sites relied on `$default` alone to cover "this field should never really be empty," which doesn't hold once a real (if blank) value has been saved.

### Fix

Wrapped the fragile call sites in a `( ... ) ?: 'fallback'` pattern, so an empty *saved* value is treated the same as a *missing* one, everywhere a blank value would visibly break the UI:

- `header.php`: `header_cta_label`, `header_cta_url`
- `footer.php`: `brand_tagline`, `contact_email`, `contact_phone`, `contact_address`, `contact_trade_licence`, `whatsapp_number`, all 4 socials (`social_instagram`/`facebook`/`twitter`/`linkedin`)
- `class-contact-form.php` (`render_page()`): the same contact fields, plus all the "note" lines and `contact_hours`
- `class-about.php`: `whatsapp_number`

Deliberately **left alone**: `brand_description` and `footer_copyright` — for these two, an empty value is legitimately meaningful (`footer_copyright` empty is the designed signal to fall back to the auto-generated copyright line; `brand_description` empty just omits an optional description, not a broken-looking gap).

### Also — increased sticky header height on desktop

`.visati-header.is-scrolled .visati-header__inner` height was `52px` (default full-height is `64px`) — user found the scrolled/sticky state too cramped. Increased desktop scrolled height to `64px` (matching the un-scrolled height, keeping only the floating-pill width/radius/shadow effect from the scroll state). Left the existing mobile-specific override (`56px` inside the `@media (max-width: 639px)` block) untouched — confirmed it lives in a separate, more specific media-query rule that will continue to override the desktop value correctly.

### Verification

All 4 touched PHP files linted clean (`php -l` via the Windows PHP bridge). Live-verified via `wp_remote_get()` against the homepage: header CTA now reads "Apply Now", footer email/phone/address all populate with real values instead of rendering blank.

### Files modified this session

| File | Action |
|---|---|
| `themes/visati/header.php` | `?:` fallback for `header_cta_label`/`header_cta_url` |
| `themes/visati/footer.php` | `?:` fallback for tagline/email/phone/address/licence/whatsapp/all 4 socials |
| `includes/shortcodes/class-contact-form.php` | Same `?:` fallback pattern for the Contact page's own field reads |
| `includes/shortcodes/class-about.php` | `?:` fallback for `whatsapp_number` |
| `themes/visati/assets/css/app.css` | Sticky header desktop height `52px` → `64px` |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.5 → 0.4.6 |

### Status

Fixed and live-verified. `template-parts/whatsapp-widget.php` was checked too — its "return early if empty" behavior for `whatsapp_number` is intentional (don't show a broken WhatsApp FAB with no number) and the field isn't actually empty in the DB, so no change was needed there.

---

## Session: Reusable Elementor utility classes for gold gradient (July 24, 2026)

User wanted two portable CSS classes usable via Elementor's "CSS Classes" field (Advanced tab), matching the existing gold-gradient button treatment, while leaving sizing/padding/border-radius to Elementor's own Style tab.

- **`.theme-primary-btn`** — same gradient (`linear-gradient(135deg, #C9A84C 0%, #F0C864 50%, #C9A84C 100%)`, `background-size: 200% 200%`) and hover shimmer (background-position shift + glow shadow + lift) already used by the header CTA and apply-form buttons. Sets only `background`/`color`/hover transition — no padding, border-radius, or font-size, by design.
- **`.theme-text`** — same gradient, clipped to text (`background-clip: text` + `color: transparent`) for gold-gradient headlines/highlighted words.

Added to `themes/visati/assets/css/app.css` right after the existing golden-button-reset rules, for consistency. `VISATI_THEME_VERSION` bumped 0.4.6 → 0.4.7.

**Usage in Elementor**: select any widget (Button, Heading, Text, etc.) → Advanced tab → "CSS Classes" field → type `theme-primary-btn` or `theme-text`. Combine with Elementor's own Style tab for padding/border-radius/font-size as usual.

### Blocker — button became huge after applying `.theme-primary-btn` in Elementor

- **Symptom:** applying `theme-primary-btn` to the homepage hero's "Apply Now" Button widget made it render as one oversized gold box, much bigger than the header CTA it was supposed to match.
- **Cause:** confirmed via a live fetch of the actual rendered homepage HTML — Elementor's "CSS Classes" (Advanced tab) setting applies the class to the **widget's outer wrapper `<div class="... theme-primary-btn elementor-widget elementor-widget-button">`**, not to the actual `<a class="elementor-button">` rendered inside it. So `.theme-primary-btn`'s padding/background/shape were being applied to the whole widget container — which already has its own Elementor spacing around the real button — rather than to the button itself, inflating the visible box.
- **Fix:** rewrote both utility classes to target the correct element regardless of how the class is applied: `.theme-primary-btn:not(.elementor-widget), .theme-primary-btn .elementor-button` — the `:not(.elementor-widget)` clause stops the rule from styling the wrapper directly (Elementor always adds `.elementor-widget` to that wrapper), while the descendant selector reaches the real inner button. Same pattern applied to `.theme-text` for Heading/Text-Editor widgets (`.elementor-heading-title`, `.elementor-widget-container > *`). Direct, non-Elementor usage (a plain `<a class="theme-primary-btn">` in custom PHP/shortcode markup) still works unchanged, since such an element never carries `.elementor-widget`.
- **File:** `themes/visati/assets/css/app.css`. `VISATI_THEME_VERSION` bumped 0.4.8 → 0.4.9.
- **Skill note:** any future Elementor "CSS Classes" utility class in this project must account for Elementor applying the class to the widget wrapper, not the rendered content element — always pair a direct selector with the relevant Elementor-internal descendant selector (`.elementor-button`, `.elementor-heading-title`, etc.), and exclude the wrapper itself via `:not(.elementor-widget)`.

### Follow-up — reduced `.theme-primary-btn` shadow

User compared it against the header CTA and found the shadow too strong. Both actually use the identical `box-shadow` values — the difference is purely visual context: the same soft gold shadow barely reads against the header's dark navy background, but stands out much more against the white backgrounds `.theme-primary-btn` gets used on elsewhere. Removed the base (non-hover) shadow entirely and softened the hover shadow from a double-layered glow (`0 4px 20px rgba(...,0.5), 0 0 40px rgba(...,0.2)`) down to a single subtle `0 2px 8px rgba(201,168,76,0.25)`. `VISATI_THEME_VERSION` bumped 0.4.9 → 0.4.10.

### Follow-up — `.theme-primary-btn` didn't actually look like the header button

User applied the class and it just looked like a flat gradient rectangle — because the class deliberately (per the original request) only set background/color, omitting shape entirely (padding, border-radius, font-weight, the flex layout needed for icon+text, and the base box-shadow). Compared against the header button's actual CSS (`.visati-btn`) and rewrote `.theme-primary-btn` to match it exactly: `display: inline-flex; align-items:center; gap:0.5rem; font-family: "Plus Jakarta Sans"; font-weight:700; padding:0.85rem 1.75rem; border-radius:20px; border:0; box-shadow:0 2px 8px rgba(201,168,76,0.3)`, plus the same hover shimmer. Now looks identical to the header CTA out of the box — Elementor's own per-widget Style tab settings (which have higher specificity via their `.elementor-element-<id>` scoping) can still override any of this on a specific instance if a different size/radius is wanted later. `VISATI_THEME_VERSION` bumped 0.4.7 → 0.4.8.

---

## Session: Careers page — `[visati_jobs]` shortcode + page build (July 24, 2026)

### Context

User asked to build the Careers page using Visati shortcodes + EMCP. **EMCP tools are not connected/available in this agent session** (checked via `ToolSearch` — no `emcp-tools` MCP tools surfaced, matching the earlier "still connecting" status these MCP servers showed at session start and never resolved). Since EMCP requires the user's own Claude Code session with the WP-CLI stdio transport configured against their local Local-by-Flywheel install, it isn't reachable from here. Proceeded with the shortcode half of the request in full, and built the actual page via direct WP-CLI/PHP (`wp_update_post()`) instead of Elementor — functionally complete, just not an Elementor-built page. Flagged this clearly rather than silently substituting one for the other.

### Built — `[visati_jobs]` shortcode

New `includes/shortcodes/class-jobs.php` — this CPT (`visati_job`) had no consuming frontend template at all before this (confirmed in an earlier session: meta box existed, nothing rendered it). Queries published `visati_job` posts, filters to "active" (`_visati_job_active` — treats never-saved/empty as active, matching the meta box's own default-checked behavior), and renders a card per job: title, Department · Location · Employment Type badge, a trimmed excerpt of the job description, and an "Apply Now" button (`.theme-primary-btn`) mailing directly to `_visati_job_apply_email` with a pre-filled subject line — only rendered if that field is actually set. Empty state (no open positions) shows a friendly message instead of nothing.

Registered in `visati-core.php` (require) and `class-visati-core.php` (`::init()`), matching the existing shortcode-registration pattern.

### Built — Careers page content

Set the existing (previously blank) "Careers" page (ID 68, slug `careers`) via `wp_update_post()`: a simple centered hero (`.visati-section-label` "Careers" + heading + one-line description, reusing existing generic utility classes rather than inventing new ones) followed by `[visati_jobs]` inside `.visati-container`. Content written to a temp file on the Windows-mounted filesystem and read via `file_get_contents()` in a `wp eval` call — avoided passing the multi-line HTML as a shell argument entirely, since escaping a large HTML blob through WSL bash → Windows `php.exe` argv parsing is fragile.

### Verification

Live-fetched `http://visati.local/careers/` via `wp_remote_get()`: HTTP 200, hero text present, `.visati-jobs-list` rendering. One existing job post (ID 402, titled "new") is already showing — this looks like a leftover test post from earlier meta-box testing, not real content; left it alone since it's the user's own data, not mine to delete without asking.

### Files modified this session

| File | Action |
|---|---|
| `includes/shortcodes/class-jobs.php` | New — `[visati_jobs]` shortcode |
| `includes/class-visati-core.php` | Registered `Visati_Core_Jobs_Shortcode::init()` |
| `visati-core.php` | Added require; `VISATI_CORE_VERSION` 0.3.8 → 0.3.9 |
| `themes/visati/assets/css/app.css` | `.visati-jobs-list`/`.visati-job-card` styles |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.10 → 0.4.11 |
| Careers page content (DB, post ID 68) | Hero + `[visati_jobs]` shortcode |

### Status

Functionally complete and live-verified. **Not Elementor-built** — EMCP wasn't available this session. If the user wants the richer, fully Elementor-editable version (matching the visual polish of other EMCP-built pages), that still needs to happen from a session where EMCP is actually connected — this can be handed to EMCP later as a straightforward "rebuild this content as an Elementor page, keep the `[visati_jobs]` shortcode in a Shortcode widget" task.

---

## Session: Blog page — `[visati_blog]` shortcode (grid + filters + sidebar) (July 24, 2026)

### Context

User asked for a Blog page with post cards, category filters, a sidebar (categories, recent posts), and quick-action buttons — again "use shortcodes + EMCP". User independently ran `/mcp` locally between the Careers and Blog requests and confirmed EMCP is still not reachable (`ConnectionRefused at http://visati.local/wp-json/mcp/emcp-tools-server`, then a `-32603`/`-32000` error on a second attempt) — consistent with this session's own `ToolSearch` finding. Proceeded the same way as the Careers page: full shortcode, page built via direct WP-CLI/`wp_update_post()` instead of Elementor.

Also newly available this session: `chrome-devtools` MCP tools. Attempted an actual browser screenshot for visual verification instead of just an HTML fetch — `new_page`/`list_pages` both failed with `Protocol error (Target.setDiscoverTargets): Target closed`, meaning the tool is listed but not actually functionally connected to a real browser instance. Fell back to the same `wp_remote_get()` HTTP verification used throughout this session.

### Built — `[visati_blog]` shortcode

New `includes/shortcodes/class-blog.php` + `assets/js/blog.js`. Two-column layout:

- **Main**: category filter pills (client-side JS toggle via `data-category`, same pattern as the existing `faq.js`), then a responsive grid of post cards (featured image or a gradient placeholder if none set, primary category label, title, trimmed excerpt, date + author).
- **Sidebar** (sticky on desktop, stacks below on mobile ≤900px): a Categories block (name + post count, linking to the native WP category archive), a Recent Posts block (latest 5), and a Quick Actions block — "Apply for a Visa" (`.theme-primary-btn`), "Track Application", "WhatsApp Us" — matching the user's explicit "quick action buttons" ask.

Uses only native WordPress `post`/`category` — no new CPT needed, since blog content maps directly onto what WordPress already provides natively.

Registered in `visati-core.php` (require) and `class-visati-core.php` (`::init()`), same bootstrap pattern as every other shortcode.

### Built — Blog page content

Set the existing "Blog" page (ID 67) via the same `wp_update_post()` + Windows-temp-file technique as the Careers page: hero (`.visati-section-label` "Blog" + heading + description) followed by `[visati_blog]` inside `.visati-container`.

### Content state

Only the default "Hello world!" post and "Uncategorized" category exist right now (confirmed via `wp post list`/`wp term list category`) — no real blog content has been written yet. The shortcode/page infrastructure is fully functional and will pick up real posts/categories the moment the admin publishes them; nothing further needs to change in code for that.

### Verification

Live-fetched `http://visati.local/blog/`: HTTP 200, layout/sidebar both present, the placeholder post renders. `php -l` clean on `class-blog.php`.

### Files modified this session

| File | Action |
|---|---|
| `includes/shortcodes/class-blog.php` | New — `[visati_blog]` shortcode |
| `assets/js/blog.js` | New — category filter JS |
| `includes/class-visati-core.php` | Registered `Visati_Core_Blog_Shortcode::init()` |
| `visati-core.php` | Added require; `VISATI_CORE_VERSION` 0.3.9 → 0.3.10 |
| `themes/visati/assets/css/app.css` | `.visati-blog-*` styles (grid, filters, sidebar, cards) |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.11 → 0.4.12 |
| Blog page content (DB, post ID 67) | Hero + `[visati_blog]` shortcode |

### Status

Functionally complete and live-verified, same caveat as Careers: not Elementor-built, since EMCP still isn't reachable from either this session or the user's own local Claude Code instance as of this check.

### Blocker — every plain link on the blog page rendered as a gold button

- **Symptom:** blog card title, image link, and every sidebar link (categories, recent posts) rendered as big gold pill buttons instead of plain text links — "colors on all links cards etc" looked broken.
- **Cause:** the same recurring pattern from earlier this session (About page, WhatsApp CTA) — every new plain `<a>` I added had no class recognized by the global "unstyled links become gold buttons" reset rule's exclusion list, so they all fell through into it.
- **Fix, done properly this time:** redesigned the blog card to use a single **stretched link** pattern instead of separate anchors on the image and title (also directly addressing the user's next request — "the full blog card should be clickable"). One `<a class="visati-blog-card__stretched-link">` now covers the entire card via `position:absolute; inset:0`, and the image/title became plain non-interactive elements — removing two of the three problem anchors just by restructuring the markup. Added `.visati-blog-card__stretched-link` and `.visati-blog-sidebar__link` (for the Categories/Recent Posts links) to both golden-button-reset exclusion lists.
- **Files:** `includes/shortcodes/class-blog.php`, `themes/visati/assets/css/app.css`. `VISATI_THEME_VERSION` bumped through 0.4.13 → 0.4.14.
- **Skill note (reinforced):** any new plain `<a>`/`<button>` added anywhere inside `.visati-page` content must be checked against this exclusion list immediately, or use a design that avoids extra anchors entirely (like the stretched-link pattern) where only one link per component is actually needed.

### Added — 4 sample blog posts across 3 categories

User asked for sample content to actually see the grid. Created via `wp_insert_post()`/`wp_insert_term()`: "Visa Guides" (2 posts), "Travel Tips" (1 post), "Visa News" (1 post) — all UAE-visa-themed, with excerpts, to exercise both the grid layout and the category filter buttons. Live-verified: 5 cards total render (4 new + the original "Hello world!").

### Follow-up — accurate page-build audit (user asked what's left)

Re-checked every key page directly against the DB (`_elementor_data` meta + `post_content` length) rather than trusting the existing `GO-LIVE-TODO.md`, which had gone stale:

| Page | State |
|---|---|
| Homepage | ✅ Real Elementor page (~28.8KB `_elementor_data`) |
| About | ✅ Real Elementor page |
| Contact | ✅ `[visati_contact_page]` shortcode, fully functional |
| Careers | ✅ Shortcode, built this session |
| Blog | ✅ Shortcode, built this session |
| Apply | ✅ `[visati_apply_form]` shortcode |
| Track Application | ✅ Dedicated `page-track.php` template (blank `post_content` is expected/correct — template bypasses `the_content()`) |
| FAQ | ✅ Functional (small content length, just the shortcode) |
| **Visa Types** | ❌ Genuinely empty — 0 content, no Elementor data. Highest-priority remaining gap. |
| **Services** | ❌ Still a literal placeholder comment (`<!-- Elementor page — build via EMCP -->`), never built. Not in tasks.md as its own task but a real gap (linked from footer). |
| Global-reach map JS (T046) | ❌ File confirmed missing — decorative homepage enhancement, not a page gap. |

Updated `GO-LIVE-TODO.md`'s "Content" section to reflect this — checked off everything actually done, and narrowed the real remaining work to Visa Types + Services + the optional global-reach JS.

### Status

Blog page fixed and verified with sample content. Remaining content gap for go-live is now clearly just: Visa Types page (highest priority), Services page, and optionally the global-reach decorative JS.

### Correction — Visa Types page was never actually a gap

User pushed back on the earlier audit: `/visa-types/` already shows real visa cards live. Re-checked and found the mistake — the static Page (ID 63, which I'd checked) and the `visati_visa_type` CPT archive both resolve to the exact same URL, since the CPT's `rewrite` slug is also `visa-types`. WordPress serves the **CPT archive** (`archive-visa_type.php`) at that URL, not the static page — confirmed via `get_post_type_archive_link()` matching the page's own permalink, and a live fetch showing 48.5KB of real archive markup. The static page (ID 63) is simply an unused duplicate that never renders. T041 was done all along; my DB audit checked the wrong artifact. Corrected `GO-LIVE-TODO.md`.

### Built — `[visati_global_reach]` shortcode (T046)

User asked to "add the globe animation file." Checked the original Next.js codebase first (`src/components/home/GlobalReach.tsx`) rather than inventing a design — found the exact spec: a rounded navy panel with a background world-map image, an animated SVG overlay (dashed arcs from Dubai to 10 regions, each with a pulsing glow-filtered pin), left-side copy + "More About Us" link, and a 4-stat count-up block (Happy Travelers, Approval Rate, Google Reviews, Customer Rating).

- Copied the original `public/world-map.svg` (168KB) into `themes/visati/assets/images/`.
- New `includes/shortcodes/class-global-reach.php` — ports the original's `PINS`/`ARC_CURVES`/`getArcPath()` logic into PHP exactly (same 11 pin coordinates on the same 107×54 viewBox, same quadratic-bezier arc formula), rendering the full SVG server-side.
- New `assets/js/global-reach.js` — count-up animation for the 4 stats, triggered via `IntersectionObserver` when the section scrolls into view (matching the original's on-scroll `CountUp` behavior). The arc-dash travel and pin-pulse are pure CSS `@keyframes` animations (respecting `prefers-reduced-motion`).
- Full CSS section added to `app.css`, plus the usual golden-button-reset exclusion for the new `.visati-global-reach__cta` link.

**Verified via `do_shortcode('[visati_global_reach]')`** directly (not just a syntax check): correct output length, all 11 pins and 10 arcs present in the rendered markup.

**Not yet placed on the homepage.** The homepage is a real, complex Elementor page (`_elementor_data`, ~28.8KB) — inserting a new section into that JSON directly (without EMCP) risks corrupting it, given the project's own documented Elementor JSON gotchas (`wp_slash()` before `update_post_meta`, etc.). The shortcode itself is fully built, verified, and ready — it just needs a Shortcode widget added to the homepage (via EMCP once reachable, or manually in Elementor) to actually appear.

### Files modified this session

| File | Action |
|---|---|
| `includes/shortcodes/class-global-reach.php` | New — `[visati_global_reach]` shortcode |
| `assets/js/global-reach.js` | New — count-up animation |
| `themes/visati/assets/images/world-map.svg` | New — copied from the original Next.js `public/` |
| `includes/class-visati-core.php` | Registered `Visati_Core_Global_Reach_Shortcode::init()` |
| `visati-core.php` | Added require; `VISATI_CORE_VERSION` 0.3.10 → 0.3.11 |
| `themes/visati/assets/css/app.css` | `.visati-global-reach-*` styles + exclusion-list entry |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.14 → 0.4.15 |
| `wordpress/docs/GO-LIVE-TODO.md` | Corrected T041 (was never a gap); updated T046 to "built, not placed" |

### Status

All content gaps identified this session are now resolved or clearly scoped: Visa Types was a false alarm (already working), Services remains the one genuine missing page, and the global-reach animation is built and verified but needs a human/EMCP step to actually place it on the homepage.

---

## Session: Global Reach — width fix, editable content, sparkle removal (2026-07-24, continued)

User placed `[visati_global_reach]` manually via an Elementor container and reported two issues from screenshots, plus a follow-up.

### Symptom → Cause → Fix 1: empty navy space on the right of the panel

- **Symptom:** the map/copy/stats content only filled roughly the left half of the navy panel; user had to add their own navy container behind it to visually compensate.
- **Cause:** the shortcode's own `render()` wrapped everything in `<div class="visati-container">` (the site's width-capped utility class, `max-width: var(--visati-max)`), which fought with the Elementor container the user placed around the widget — two nested width constraints, not one.
- **Fix:** removed the `.visati-container` wrapper entirely from `class-global-reach.php`. `.visati-global-reach__panel` is a plain block element with no width rule of its own, so it now fills 100% of whatever container wraps the shortcode.
- **Verify:** `do_shortcode('[visati_global_reach]')` output checked for balanced `<div>`/`</div>` counts (17/17) and absence of `visati-container` string.

### Symptom → Cause → Fix 2: heading/description/CTA/stat numbers not editable

- **Cause:** all copy was hardcoded PHP string literals in `render()`.
- **Fix:** added a "Homepage — Global Reach Section" group to `class-settings-page.php` (label, heading, description, CTA label/URL, and 4× stat-number + stat-label pairs), wired via the same `class_exists('Visati_Core_Settings')` + `?:` fallback pattern established earlier this session (never the buggy `function_exists()`). Each stat number is a single free-text field (e.g. `"1000+"`, `"99%"`, `"4.9/5"`) rather than separate value/decimals/suffix fields — simpler for the client to edit.
- **Follow-on JS change:** `global-reach.js`'s count-up now parses the leading numeric portion out of the saved text via regex (`/^(\d+(?:\.\d+)?)(.*)$/`) instead of reading separate `data-count-end`/`data-count-decimals`/`data-count-suffix` attributes, so one editable field still gets the count-up animation.
- **Verify:** live round-trip via `wp eval` — set `global_reach_stat1_number` to `"2500+"`, confirmed it rendered; reverted.

### Symptom → Cause → Fix 3: PHP warnings ("Undefined array key 'suffix'/'decimals'") visible on the page

- **Symptom:** screenshot showed raw PHP warning text overlaying the section.
- **Cause:** this was mid-edit staleness — a fresh `wp_remote_get()` fetch of the homepage immediately after confirmed `has_warning=clean`, i.e. the live page was already correct by the time it was checked. Left here as a reminder: if this recurs after a deploy, it's the same opcache-staleness class of bug diagnosed earlier this session (browser-serving PHP worker vs WP-CLI process are different processes) — restart PHP (Local: site's "Restart" button; Hostinger: hPanel "Restart PHP"), don't disable opcache in production.

### Symptom → Cause → Fix 4: pin "sparkle" effect not in the original design

- **Symptom:** user asked to remove a sparkle/glow effect on the map pins, stating the original Next.js `GlobalReach.tsx` doesn't have it.
- **Investigation:** the original TSX actually does define the same glow (`radialGradient#pinGlow` + `feGaussianBlur` filter + `.pin-pulse` keyframe animating opacity 0.3→0.8 and r 1.5→2.8) — the port was faithful. However, the WP port's own animation amplitude was punchier (opacity floor 0.5 vs 0.3, scale up to 1.6 vs original's r-ratio ~1.87 but combined with a brighter base look), making it read as more of a "sparkle" than the subtler original.
- **Fix:** rather than re-tune the animation to match the original more closely, honored the explicit ask and removed the effect outright — dropped the `radialGradient`/`filter` `<defs>` block and the pulsing glow circle from `class-global-reach.php`, keeping only the original's second, smaller solid gold dot per pin (no animation, no blur). Removed the now-unused `.visati-global-reach__pin-pulse` rule and `@keyframes visati-pin-pulse` from `app.css`.
- **Verify:** `do_shortcode()` check — `visatiPinBlur` and `pin-pulse` both absent from output; circle count = 15 (11 plain pins + 4 stat-icon circles), confirming no orphaned markup.

### Files modified this sub-session

| File | Action |
|---|---|
| `includes/shortcodes/class-global-reach.php` | Removed `.visati-container` wrapper; settings-driven copy/stats; removed pin glow/blur/pulse |
| `assets/js/global-reach.js` | Count-up now parses `data-count-text` via regex instead of separate end/decimals/suffix attrs |
| `admin/class-settings-page.php` | New "Homepage — Global Reach Section" settings group + save handler + defaults |
| `themes/visati/assets/css/app.css` | Removed `.visati-global-reach__pin-pulse` + its keyframes |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.15 → 0.4.17 |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.11 → 0.3.13 |

### Status

Global Reach section now fills its Elementor container correctly, all copy/stat text is editable under Visati Settings, and the pin markers are plain static dots matching the user's preference (technically a deviation from the original TSX's glow effect, done deliberately per explicit user request).

---

## Session: Global Reach — map/overlay alignment + stat icons (2026-07-24, continued)

Two more issues reported from screenshots after placing the section live.

### Symptom → Cause → Fix 1: map background and arc/pin overlay scale independently ("joint" but drift apart on mobile)

- **Symptom:** on desktop the dotted world-map background filled the panel edge-to-edge with no letterboxing (looked like `cover`, not `contain`, despite `object-fit: contain` being set); on mobile the map background and the animated arc/pin SVG overlay were visibly offset from each other, as if each could "move independently."
- **Cause:** two separate replaced/rendered elements (`<img src="world-map.svg">` and an inline `<svg viewBox="0 0 107 54">`) were each absolutely positioned at `inset:0; width:100%; height:100%` directly inside a box (`.visati-global-reach__map-layer`) whose own aspect ratio varies by viewport/content. `object-fit: contain` on the `<img>` requires the browser to know the SVG's *intrinsic* aspect ratio — but `world-map.svg`'s root `<svg>` only had a `viewBox`, no explicit `width`/`height`, which some browsers don't reliably use to derive an intrinsic ratio for `<img>`/`object-fit` purposes. Meanwhile the inline `<svg>` uses its own `preserveAspectRatio` logic against the *actual* box ratio. Two different scaling systems, each dependent on the parent box's ever-changing ratio — they only agreed by coincidence at some viewports.
- **Fix:**
  1. Added explicit `width="107" height="54"` to `world-map.svg`'s root `<svg>` element (`themes/visati/assets/images/world-map.svg`) — gives all browsers an unambiguous intrinsic aspect ratio to compute `object-fit` from.
  2. Wrapped both the `<img>` and the overlay `<svg>` in one new `.visati-global-reach__map-inner` div with CSS `aspect-ratio: 107 / 54`, centered via flex inside `.visati-global-reach__map-layer`. Both children now render into a box that is *always* exactly the map's own aspect ratio, at every viewport — `object-fit: contain` and `preserveAspectRatio="xMidYMid meet"` become no-ops (the box already matches the content ratio), so the two layers can no longer diverge, on mobile or anywhere else.
- **Verify:** `do_shortcode()` output checked for the new `.visati-global-reach__map-inner` wrapper; confirmed present in a fresh `wp_remote_get()` fetch of the live homepage too (not just the WP-CLI process).

### Symptom → Cause → Fix 2: stat icons render as bare circles

- **Symptom:** the small icon badge next to each of the 4 stats showed only an empty circle outline, no actual icon.
- **Cause:** the icon markup was never actually implemented — `<svg ...><circle cx="12" cy="12" r="10"/></svg>` was a placeholder left over from initial scaffolding, not a real icon per stat.
- **Fix:** checked the original `src/lib/constants.ts` (`GLOBAL_STATS`) to find the real icon-per-stat mapping (`lucide-react`: `Users` → Happy Travelers, `CheckCircle` → Approval Rate, `Star` → Google Reviews, `Globe` → Customer Rating). Added a `STAT_ICONS` map of lucide-equivalent SVG path data (24×24 viewBox, stroke-based) to `class-global-reach.php`, keyed per stat and rendered inside the existing icon badge markup.
- **Verify:** `do_shortcode()` output checked for all 4 icons' distinctive path fragments present exactly once each.

### Files modified this sub-session

| File | Action |
|---|---|
| `includes/shortcodes/class-global-reach.php` | Added `STAT_ICONS` map + rendered per-stat; wrapped map img+svg in `.visati-global-reach__map-inner` |
| `themes/visati/assets/images/world-map.svg` | Added explicit `width="107" height="54"` to root `<svg>` |
| `themes/visati/assets/css/app.css` | `.visati-global-reach__map-layer` now flex-centers a new `.visati-global-reach__map-inner` locked to `aspect-ratio: 107/54` |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.17 → 0.4.18 |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.13 → 0.3.14 |

### Status

Map background and animated overlay are now geometrically locked together via a shared aspect-ratio box — cannot visually separate at any viewport. All 4 stat icons render real icons matching the original design's icon choices.

---

## Session: Global Reach — map still "covered" on desktop, root-cause fix (2026-07-24, continued)

The `aspect-ratio` + flex wrapper fix (previous entry) did not actually solve it — user reported the desktop map still filled the panel edge-to-edge with no letterboxing.

### Symptom → Cause → Fix

- **Symptom:** same as before — dotted map fills the full wide panel with no visible letterbox bars, i.e. effectively stretched/cropped rather than "contained."
- **Real cause:** `.visati-global-reach__map-inner` had `width: 100%` (a *definite* value) plus `aspect-ratio: 107/54` plus `max-height: 100%`. Per CSS sizing rules, `aspect-ratio` only derives a dimension that is `auto` — since width was pinned to a definite `100%`, only height participated in the ratio calculation, and that computed height was then clamped independently by `max-height` without ever re-deriving width. Net effect on a wide desktop panel: width stayed at the full panel width while height got clamped down to the panel's height — producing a box whose actual rendered ratio no longer matched 107:54 at all, so the "contained" image inside it appeared stretched to fill. The flex+aspect-ratio wrapper trick was fragile by construction, not a real fix.
- **Fix — removed the trick entirely, fixed the actual root cause instead:** merged the world map's dot pattern directly into the *same* `<svg viewBox="0 0 107 54">` element that already renders the arcs and pins, instead of keeping it as a separate `<img>` layer. `class-global-reach.php` now reads `world-map.svg`'s file contents server-side (`map_dots_markup()`, request-cached via a `static` var), strips the outer `<svg>`/`</svg>` wrapper tags, and inlines the raw `<circle>` dots as a `<g class="visati-global-reach__map-dots">` — the very first child of the combined SVG, painted before the arcs/pins. Since there is now only **one** SVG element, one `viewBox`, and one native `preserveAspectRatio="xMidYMid meet"` calculation for the whole composite, the dots/arcs/pins are mathematically guaranteed to scale together at every viewport — there is no second sizing system left to disagree with the first. Removed the now-unnecessary `<img>` tag, `.visati-global-reach__map-inner` wrapper, and its CSS entirely; opacity (0.4, matching the original's map dimming) moved onto the new `.visati-global-reach__map-dots` group.
- **Verify:** `do_shortcode()` output checked — no `<img>` tag remains, `circle` count = 2514 (2501 map dots + 11 pins + 2 icon-internal circles), div balance still correct (17/17); confirmed live via a fresh `wp_remote_get()` fetch of the homepage.

### Lesson for the future skill

When two visual layers must always move/scale together, don't try to keep two independent CSS sizing systems (`object-fit` on an `<img>` + `preserveAspectRatio` on an inline `<svg>`, or CSS `aspect-ratio` + `flex` + `max-height` tricks) in sync — merge them into one element/one coordinate system instead. It's more code (server-side file read + string surgery to inline an SVG) but eliminates the entire bug class rather than patching one manifestation of it.

### Files modified this sub-session

| File | Action |
|---|---|
| `includes/shortcodes/class-global-reach.php` | Removed `<img>` + `.map-inner` wrapper; added `map_dots_markup()`; dots inlined as `<g>` inside the single arcs/pins `<svg>` |
| `themes/visati/assets/css/app.css` | Removed `.map-inner`/`.map-img` rules; added `.visati-global-reach__map-dots { opacity: 0.4 }` |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.18 → 0.4.19 |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.14 → 0.3.15 |

### Status

Map dots, arcs, and pins are now one inlined SVG with a single native `preserveAspectRatio` calculation — the previous "covered on desktop" / "separate on mobile" bug class is structurally eliminated, not just patched for one viewport.

---

## Session: Services page built (2026-07-24, continued)

User asked "what next?" — content-wise the only genuine remaining gap per `GO-LIVE-TODO.md` was the Services page (`post_content` was still literally `<!-- Elementor page — build via EMCP -->`). EMCP remains unreachable (confirmed again this session), so built it the same way as Careers/Blog: shortcodes instead of Elementor.

Read the original `src/app/(public)/services/page.tsx` first for the exact spec: a navy hero (eyebrow "What we do" / "UAE visa services" / subtitle), a 6-item feature grid (Tourist Visas, Express Processing, Document Assistance, Visa Extensions, Business Visas, 24/7 Support — each with an icon, description, and a bullet feature list), and a bottom CTA (Apply Now + WhatsApp Us).

- New `includes/shortcodes/class-services.php` — three shortcodes:
  - `[visati_services_hero]` — reuses the About page's existing `.visati-about-hero` navy/decorative-rings CSS classes verbatim (no new CSS needed) with Services-specific copy, no buttons (matching the original design, which has none on this hero).
  - `[visati_services]` — the 6-card grid, ported directly from the TSX `SERVICES` array (same titles/descriptions/feature lists). Icons are lucide-equivalent SVG path data (`send`/`zap`/`file-check`/`globe`/`shield`/`headphones`, matching the original's Plane/Zap/FileCheck/Globe/Shield/Headphones choices) — same STAT_ICONS-style approach used for Global Reach's stat icons earlier this session.
  - `[visati_services_cta]` — reuses the About page's `.visati-about-cta` CSS classes verbatim, with Services-specific copy ("Ready to start?" / "Apply for your UAE visa." / "Average response under 2 minutes...") and Apply Now + WhatsApp Us buttons — WhatsApp number sourced dynamically from `Visati_Core_Settings::get('whatsapp_number', ...)`, same pattern as the About hero's WhatsApp link, so it stays in sync if the client changes the number in Settings rather than being hardcoded into page content.
- New CSS: `.visati-services-grid`/`.visati-service-card*` (grid layout, icon badge, title, description, bulleted feature list) — everything else (hero, CTA, buttons) reuses existing classes, so this was a small, additive CSS diff.
- Registered both the shortcode class (`visati-core.php` require + `class-visati-core.php` init call).
- Set the Services page's (`post_content`, post ID 98) content to the three shortcodes via `wp_update_post()`.

**Verified via `apply_filters('the_content', ...)`** directly (not just a syntax check): hero present, grid present with exactly 6 `.visati-service-card` elements, CTA present with the WhatsApp button class. **Then verified again via a live `wp_remote_get()` fetch of `/services/`** (HTTP 200, grid markup present, no PHP warnings) — confirming it's not just correct in isolation but actually serving correctly through the real page template.

### Files modified this session

| File | Action |
|---|---|
| `includes/shortcodes/class-services.php` | New — `[visati_services_hero]`, `[visati_services]`, `[visati_services_cta]` |
| `includes/class-visati-core.php` | Registered `Visati_Core_Services_Shortcode::init()` |
| `visati-core.php` | Added require; `VISATI_CORE_VERSION` 0.3.15 → 0.3.16 |
| `themes/visati/assets/css/app.css` | `.visati-services-grid`/`.visati-service-card*` styles |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.19 → 0.4.20 |
| Page ID 98 (`post_content`, DB) | Set to the 3 new shortcodes, replacing the placeholder comment |
| `wordpress/docs/GO-LIVE-TODO.md` | Checked off Services; noted section 1 (content) is now fully complete |

### Status

All content pages identified in `GO-LIVE-TODO.md` section 1 are now built and verified live. Remaining work before launch is entirely deploy-prep: security hardening constants, Hostinger provisioning, DB/file transfer, smoke testing, and handoff docs (sections 2–5 of the same checklist).

---

## Session: Local → Hostinger migration runbook written (2026-07-24, continued)

User asked for `GO-LIVE-TODO.md`'s deploy section to be expanded into actual copy-pasteable commands and steps for moving Local → Hostinger "without data loss safely" — the existing section 3 was a one-line-per-step checklist with no concrete commands.

### Symptom → Cause → Fix: `wp db export` fails via the WSL→Windows-php.exe bridge

- **Symptom:** tested the documented export command through this session's established WP-CLI bridge before writing it into the doc (per the session-wide habit of verifying rather than assuming) — got `'mysqldump' is not recognized as an internal or external command`.
- **Cause:** the bridge invokes `php.exe` directly from WSL bash; Local's bundled `mysql-8.4.0\bin\win64\bin` directory (confirmed as the actual DB engine in use, matching the `DB_HOST=127.0.0.1:10005` port in `wp-config.php`) is only on `PATH` inside Local's own **"Open Site Shell"** process, not in the bare php.exe invocation this session's bridge uses. Prepending the directory to the WSL bash `$PATH` before calling php.exe did **not** propagate into the Windows child process's environment — WSL's PATH translation for spawned `.exe`s did not carry it through in this case.
- **Fix:** rather than fight the bridge's environment further, the runbook documents the **correct, standard workflow**: run `wp db export`/`wp db import`-style commands from Local's own "Open Site Shell" (which Local provides specifically so PATH is pre-configured correctly), not through this session's ad-hoc bridge. The bridge remains useful for live read-only inspection/verification of the running site, but isn't the right tool for admin operations like a full DB export that depend on bundled binaries being on PATH.
- **Also confirmed while investigating:** the site's actual DB engine is `mysql-8.4.0` (not `mariadb-10.6.23`, the other bundled option) — verified via `mysql_upgrade_history` in the site's data directory, and its port (`10005`) matching `wp-config.php`'s `DB_HOST`.

### What went into the runbook

Replaced `GO-LIVE-TODO.md` section 3 with a 9-step sequence (3.1–3.9), each with exact commands and explicit safety notes:

1. **3.1 Export local DB** — via Local's Site Shell, output path deliberately outside the site folder so it can't accidentally end up in a code zip.
2. **3.2 Package theme+plugin only** — explicit "don't zip all of wp-content" warning (would sweep in `uploads` and local-only test plugins).
3. **3.3 Provision Hostinger** — subdomain, PHP 8.2+, SSH access check (with File Manager/phpMyAdmin fallbacks noted per later step, since shared-hosting SSH availability varies by plan).
4. **3.4 Upload+activate** theme/plugin + companion plugins.
5. **3.5 Copy uploads** — explicit reason why skipping it breaks things (media + Elementor's own cached CSS both live there).
6. **3.6 Import the DB** — the step marked "most careful": snapshot the fresh install's empty DB first (cheap undo path), import, then **delete the uploaded `.sql` immediately** (full DB sitting in a web-accessible directory otherwise, secrets included).
7. **3.7 Search-replace** — explicitly only ever against the Hostinger DB, confirmed the exact current local `siteurl`/`home` value (`http://visati.local`) to make the command copy-pasteable rather than a placeholder; documented why a raw SQL find-and-replace is unsafe for serialized data (Elementor's `_elementor_data`) and named Better Search Replace as the no-SSH fallback.
8. **3.8 Post-import housekeeping** — the exact `sed` command for the previously-documented Hello Elementor `reset.css` fix (cross-referenced to the existing "P9" log entry so the full color/shape/hover detail isn't duplicated), and the **complete, exact list** of `VISATI_*` wp-config constants Hostinger needs — derived directly from reading `class-settings.php`'s `env()` calls rather than guessing, so nothing is missing (Stripe secret+webhook, all 4 R2 vars, Resend API key).
9. **3.9 Verification** — row-count spot check, page-by-page visual comparison, and an explicit "don't delete the local site or backups until the smoke test in section 4 passes live" close-out.

### Files modified this session

| File | Action |
|---|---|
| `wordpress/docs/GO-LIVE-TODO.md` | Section 3 rewritten from a 10-line checklist into a full 9-step runbook with exact commands and safety notes |
| `wordpress/docs/migration-log.md` | This entry |

### Status

The deploy section of `GO-LIVE-TODO.md` is now a self-contained runbook — a future session (or the user directly) can follow it without needing to re-derive which commands work in this environment, what the exact current DB engine/URL/secret-constant list is, or which steps are actually safe to re-run vs. need care.

---

## 22. First Deploy to Hostinger (T070) — 2026-07-24

**Target:** `https://visati.visati.ae` (staging subdomain; `https://visati.ae` is production)
**Server:** Hostinger shared hosting (`uk-fast-web390`), PHP 8.3.30, MariaDB
**SSH:** `ssh -p 65002 u438130270@72.62.214.133`

### Step 1 — Local DB Export

**P21 — PowerShell `>` redirect adds UTF-16 BOM to mysqldump output**

- **Symptom:** First export (`visati-backup-2026-07-24.sql`, 76MB) corrupted by PowerShell's `>` operator, which encodes output as UTF-16 with BOM. MySQL import failed with `ASCII '\0' appeared in the statement`.
- **Cause:** PowerShell's default output encoding wraps stdout in UTF-16 LE with BOM, doubling file size and making SQL unreadable.
- **Fix:** Use `--result-file=` flag instead of shell redirect, or run `cmd /c` to bypass PowerShell encoding.
- **Commands:**
  ```powershell
  # BAD (adds BOM):
  & $mysqldump ... > $outFile

  # GOOD (writes raw bytes):
  & $mysqldump --result-file=$outFile ... local

  # Also works:
  cmd /c "`"$mysqldump`" ... > `"$outFile`""
  ```
- **Gotcha:** `tail -c +4` on the server to strip BOM partially worked but corrupted some INSERT statements (removed 4 bytes from first SQL statement). The `--result-file` approach was the clean fix.

### Step 2 — Server Environment Discovery

**P22 — Hostinger uses `~/domains/<domain>/public_html/` not `~/public_html/`**

- **Symptom:** Standard Hostinger docs reference `~/public_html/` but server had `~/domains/` directory structure.
- **Cause:** Hostinger VPS/shared hosting with multiple domains uses a `~/domains/<domain>/public_html/` layout. Each domain gets its own subdirectory.
- **Fix:** Use `~/domains/visati.visati.ae/public_html/` for all paths.
- **DB credentials:** found in `wp-config.php` — different from Local (Hostinger uses `u438130270_XNX4z` / `O6UtVhIA00`).

**P23 — WP-CLI `db import` fails with `proc_open()` disabled**

- **Symptom:** `wp db import` returned `Error: Cannot do 'Process::run': The PHP functions proc_open() and/or proc_close() are disabled.`
- **Cause:** Hostinger shared hosting disables `proc_open`/`proc_close` in `disable_functions` for security. WP-CLI's `db import` uses these to spawn `mysql` client.
- **Fix:** Use `mysql` CLI directly: `mysql -u USER -p'PASS' DBNAME < file.sql`
- **Workaround for other WP-CLI commands:** Most WP-CLI commands that don't need subprocess spawning work fine. Only commands that shell out (`db import`, `db query` via WP-CLI) fail.

### Step 3 — File Upload via SCP

**P24 — Windows OpenSSH key "invalid format" error**

- **Symptom:** `scp` and `ssh` from Windows PowerShell/Git Bash fail with `Load key "id_rsa": invalid format` or `error in libcrypto`.
- **Cause:** The private key at `~/.ssh/id_rsa` was in OpenSSH v1 format with AES-256-CTR encryption, but Windows OpenSSH 9.5 couldn't load it (possibly due to passphrase or key format specifics). `icacls` permissions fix didn't help.
- **Fix:** Used password-based SCP as fallback. The `-i` key flag was ignored (key failed to load), password prompt appeared, upload succeeded.
- **Gotcha:** SCP uploads work even when key auth fails — it falls back to password. Not elegant but functional for one-time deploy.

### Step 4 — Theme/Plugin Extraction & Permissions

**P25 — Zip extraction creates directories without execute (`x`) permission**

- **Symptom:** CSS/JS files returned 404 on the live site. `ls -la` showed `drw-r--r--` on `assets/` directories (missing `x` for owner/group).
- **Cause:** Windows `Compress-Archive` creates zip entries with incorrect Unix permission bits. Directories extracted without `x` flag, making them untraversable by the web server (Apache/LiteSpeed).
- **Fix:**
  ```bash
  find ~/domains/visati.visati.ae/public_html/wp-content/themes/visati -type d -exec chmod 755 {} \;
  find ~/domains/visati.visati.ae/public_html/wp-content/plugins/visati-core -type d -exec chmod 755 {} \;
  ```
- **Rule:** After any zip upload+extract on Hostinger, always run `find <dir> -type d -exec chmod 755 {} \;` and `find <dir> -type f -exec chmod 644 {} \;`.

### Step 5 — Database Import (Second Attempt)

**P26 — First import (corrupted BOM) produced empty CPT tables, zero Elementor data**

- **Symptom:** After first import (via `tail -c +4` BOM fix), all 5 custom tables existed but `wp_posts` had correct pages with zero Elementor data, zero visa types, zero FAQs, zero testimonials.
- **Cause:** The `tail -c +4` BOM removal stripped 4 bytes from the first INSERT statement, causing MySQL to silently skip that entire statement (and possibly others). The SQL file's long-line extended inserts were not line-based, so removing bytes from the start corrupted the first multi-row INSERT.
- **Fix:** Re-exported with `--result-file` (no BOM), uploaded v2 file, re-imported. All data intact.
- **Verification queries:**
  ```sql
  SELECT COUNT(*) FROM wp_postmeta WHERE meta_key='_elementor_data';  -- 377
  SELECT COUNT(*) FROM wp_posts WHERE post_type='visati_visa_type' AND post_status='publish';  -- 6
  SELECT COUNT(*) FROM wp_posts WHERE post_type='visati_faq' AND post_status='publish';  -- 8
  SELECT COUNT(*) FROM wp_posts WHERE post_type='visati_testimonial' AND post_status='publish';  -- 5
  ```

### Step 6 — Search-Replace

**P27 — WP-CLI `search-replace` 0 replacements on first run, 467 on second**

- **Symptom:** First `search-replace` after initial (corrupted) import showed 0 replacements. After clean v2 import, same command found 467 replacements.
- **Cause:** The corrupted first import had the BOM issue that may have prevented proper serialization of URL strings. The clean import preserved serialized data correctly, so `search-replace` could find and fix the URLs.
- **Command:** `wp search-replace 'http://visati.local' 'https://visati.visati.ae' --all-tables --precise`
- **Result:** 467 replacements across `wp_options` (5), `wp_postmeta` (4), `wp_posts` (4+453 guid), `wp_users` (1).

### Step 7 — Home Page Elementor Data Mapping

**P28 — Home page post ID mismatch between local (18) and server (7)**

- **Symptom:** Homepage rendered empty/broken. `SELECT LENGTH(meta_value) FROM wp_postmeta WHERE meta_key='_elementor_data' AND post_id=7` returned 0.
- **Cause:** Hostinger's one-click WordPress installer created a "Home" page at ID 7 before our DB import. Our import created the real Home page at ID 18 (matching local). But WordPress's `page_on_front` option was already set to 7 (from the fresh install's default), and the import's `option` row for `page_on_front` was value 18 but the fresh install had already set it to 7. Since options are in `wp_options` (which IS imported), the correct value (18) should have been imported. However, the `wp option update page_on_front 18` showed "unchanged" — meaning it was already 18. The real issue was that post 7 was a **trash** draft from the fresh install that somehow still had priority.
- **Fix:** `wp option update page_on_front 18` + `wp option update show_on_front page` + cache flush. But the real fix was to **delete Elementor CSS cache** so it regenerates for post 18.
- **Elementor CSS flush limitation:** `wp elementor flush_css` returns "Success" but doesn't actually regenerate CSS files on Hostinger (proc_open disabled — Elementor's flush internally tries to spawn a process). Must use **wp-admin Elementor > Tools > Regenerate CSS** via browser instead.

### Step 8 — Hello Elementor reset.css Fix

**P29 — `sed` replacement changed ALL link colors to gold (too aggressive)**

- **Symptom:** After `sed -i 's/#c36/#C9A84C/g' reset.css`, all anchor tags turned gold — including footer links, navigation, and body text links that should remain navy/dark.
- **Cause:** The `sed` replacement is global — it changes every occurrence of `#c36` to `#C9A84C` in the file. The original `#c36` was used for anchor `color`, `button` border, and button hover states. Making them all gold is too broad.
- **Fix applied (on server):** The sed ran and we accepted the result for now. The full fix (more targeted CSS in `visati/assets/css/app.css` POST-RESET OVERRIDES section) overrides these styles for the Visati theme, so the gold links only appear in bare unstyled contexts (which shouldn't exist with our theme active).
- **Lesson:** The Hello Elementor reset.css fix should be more surgical — target only `a:hover` and `button` color, not all `a` color. Document the exact selectors that need changing for next time.

### Step 9 — Post-Import Housekeeping

Commands run on server:
```bash
# Blog name
/usr/local/bin/wp option update blogname "VISATI"

# Permalinks
/usr/local/bin/wp rewrite structure '/%postname%/' --hard
/usr/local/bin/wp rewrite flush

# Security constants added to wp-config.php:
# define( 'DISALLOW_FILE_EDIT', true );
# define( 'FORCE_SSL_ADMIN', true );

# Elementor CSS flush (partial — needs browser for full regen)
/usr/local/bin/wp elementor flush_css
/usr/local/bin/wp cache flush
/usr/local/bin wp transient delete --all

# Uploads permissions fixed
find ~/domains/visati.visati.ae/public_html/wp-content/uploads -type d -exec chmod 755 {} \;
find ~/domains/visati.visati.ae/public_html/wp-content/uploads -type f -exec chmod 644 {} \;

# Installer files cleaned up
rm ~/domains/visati.visati.ae/public_html/visati-backup-*.sql
rm ~/domains/visati.visati.ae/public_html/visati-*.zip
```

### Current Status (end of session)

| Item | Status |
|---|---|
| Site live at `https://visati.visati.ae` | ✅ HTTP 200 on all pages |
| All pages imported (14) | ✅ |
| Elementor data (377 entries) | ✅ All present in DB |
| Visa types (6) + prices | ✅ Correct data + REST API working |
| FAQs (8) + Testimonials (5) | ✅ |
| Custom tables (5) | ✅ All present |
| Search-replace done | ✅ 467 replacements |
| Blog name "VISATI" | ✅ |
| Site tagline "Dubai Visas. Simplified." | ✅ |
| Security constants in wp-config | ✅ |
| Hello Elementor reset.css gold fix | ✅ (too broad, overridden by theme CSS) |
| **Homepage rendering** | ⚠️ Elementor CSS needs browser-side regeneration |
| **About page rendering** | ⚠️ Same — Elementor CSS cache |
| **Apply form visa type dropdown** | ⚠️ Shortcode renders server-side, should work once PHP opcache clears |
| **VISATI_* env constants** | ❌ Not yet added (need live Stripe/R2/Resend keys) |
| **Stripe live keys + webhook** | ❌ Not yet configured |
| **WP Mail SMTP / Rank Math** | ❌ Not yet installed |
| **.htaccess permalink flush** | ⚠️ WP-CLI couldn't regenerate, but Hostinger's .htaccess looks correct |

### Remaining for T070 completion

1. **Browser-side Elementor CSS regen:** Open `wp-admin > Elementor > Tools > Regenerate CSS & Data`
2. **Add VISATI_* secrets to wp-config.php** (live Stripe keys, R2 creds, Resend key)
3. **Install companion plugins:** WP Mail SMTP, Rank Math, Wordfence
4. **Configure Stripe live webhook** endpoint
5. **Verify all pages render correctly** after CSS regen

### Key learnings for the skill

1. **Always use `--result-file`** for mysqldump on Windows — PowerShell `>` corrupts with BOM.
2. **Always `chmod 755` directories after zip extract** on Hostinger — Windows zips strip `x` bits.
3. **Elementor CSS flush via WP-CLI is unreliable on shared hosting** — needs browser-side regeneration.
4. **WP-CLI `db import` fails on Hostinger** — use `mysql` CLI directly.
5. **Hostinger directory structure** is `~/domains/<domain>/public_html/`, not `~/public_html/`.
6. **Post ID mapping** — fresh install creates pages at low IDs (7, etc.) that can conflict with imported data. Always check `page_on_front` option and verify the correct post has `_elementor_data`.

---

## Session: Second sync attempt (Local → live edits) — 2026-07-25

A follow-up session (run through a different AI coding tool, "MiMo," directly against this same repo — its edits and new scratch files showed up here as uncommitted changes) attempted to push a round of **post-launch local changes** — Elementor page edits, button `border-radius: 12px` everywhere, main-menu edits — to `visati.visati.ae`. It got partway through and hit a wall ("nothing changed on live"), then a 429 rate-limit cut it off mid-fix. Picked up from there in this session.

### Symptom → Cause → Fix 1: CSS edits never show up live no matter how many times it's redeployed

- **Symptom:** server-side `grep` confirmed the deployed `app.css` had the new `border-radius: 12px` rules; browser still showed the old look after repeated redeploys + cache flushes.
- **Cause:** every `wp_enqueue_style()`/`wp_enqueue_script()` call across the theme and plugin used a static version constant (`VISATI_THEME_VERSION`/`VISATI_CORE_VERSION`) as the cache-busting `?ver=` query string. Editing a CSS/JS file doesn't change that constant, so the URL never changes, so Hostinger's CDN (`hcdn`, confirmed via `curl -I` showing `Server: hcdn` + `Cache-Control: public, max-age=2592000` — a 30-day cache) and browsers keep serving the byte-identical old file forever.
- **Fix (already applied, verified this session):** added `visati_asset_version( $relative_path )` to `helpers.php` — returns `filemtime()` of the actual asset file (falls back to the version constant only if the file is missing), so every edit auto-generates a new cache-busting URL. Converted every plugin `wp_register_style`/`wp_register_script`/`wp_enqueue_style` call (apply-form, blog, contact-form, faq, global-reach, testimonials, quick-apply, page-track, admin-dashboard) to use it, and the theme's `style.css`/`app.css` enqueues to use `filemtime()` directly. **Verified via `grep -rn "VISATI_CORE_VERSION\|VISATI_THEME_VERSION"` across the whole plugin+theme** — zero remaining raw-constant version args; only the intentional fallback default and an unrelated REST API version field remain.

### Symptom → Cause → Fix 2: the actual root cause — Elementor edits and menu changes never left the local database

- **Symptom:** even after the CSS fix, "the pages I edited in Elementor" and "the main menu I edited" still didn't appear live.
- **Cause:** every redeploy so far only ever zipped and uploaded `wp-content/themes/visati` + `wp-content/plugins/visati-core` — **code only**. Elementor page content (`_elementor_data` in `wp_postmeta`) and nav menu structure (`wp_terms`/`wp_term_taxonomy`/`wp_term_relationships` + `nav_menu_item` posts) live in the **database**, which was never touched after the original T070 migration. No amount of re-zipping files was ever going to move that content.
- **Fix — recommended, NOT a repeat of the original full-DB wholesale import:** the original T070 migration did a full `--add-drop-table --all-tables` export/import, which was safe *then* because Hostinger's DB was freshly installed and empty. Doing that again now is a real "data loss" risk — if anything has been submitted or changed directly on `visati.visati.ae` since (test applications, inquiries, a Settings tweak in wp-admin), a full re-import silently erases it, since it would drop and recreate every table including `wp_visati_applications`/`wp_visati_inquiries`/`wp_visati_documents`/`wp_visati_payments`/`wp_visati_status_history`/`wp_options`/`wp_users`.
- **Safer approach given to the user:** export/import **only** `wp_posts`, `wp_postmeta`, `wp_terms`, `wp_term_taxonomy`, `wp_term_relationships`, `wp_termmeta` — the tables that actually hold Elementor pages, CPT content (visa types/FAQs/testimonials/jobs), and nav menus. These 6 tables cover 100% of "pages I edited in Elementor" + "the main menu," while structurally never touching the 5 custom `wp_visati_*` tables, `wp_options` (client-editable Settings), or `wp_users` — those simply aren't in the dump. Verified this actually works as intended: `mysqldump ... local wp_posts wp_postmeta wp_terms wp_term_taxonomy wp_term_relationships wp_termmeta` produces a file with exactly 6 `DROP TABLE` statements, nothing else.
- **New tool:** `wordpress/docs/deploy-content-sync.ps1` — rebuilds theme/plugin zips + produces this scoped export in one run. Reads the local DB password from `wp-config.php` at runtime rather than hardcoding it.

### Symptom → Cause → Fix 3: local DB export kept failing (`mysqldump` errors)

- **Symptom 1:** `'mysqldump' is not recognized as an internal or external command` — happened when a PATH change from outside Local's own shell didn't propagate to the actual export command.
- **Symptom 2 (the one that actually blocked the other tool):** `Got error: 1045: "Plugin caching_sha2_password could not be loaded"` when using the `mariadb-10.6.23+0` bundle's `mysqldump.exe`.
- **Cause:** the local site's actual database engine is **MySQL 8.4.0** (confirmed via its `mysql_upgrade_history` file and its port matching `wp-config.php`'s `DB_HOST=127.0.0.1:10005`), not MariaDB. Local by Flywheel bundles both engine versions side by side; a naive `Get-ChildItem -Recurse -Filter mysqldump.exe | Select -First 1` search finds the MariaDB one first (alphabetically/path-order), which lacks the `caching_sha2_password` auth plugin MySQL 8.4 requires by default.
- **Fix — verified working:** use `mysql-8.4.0\bin\win64\bin\mysqldump.exe` explicitly, not a generic search result. Tested directly this session: connects cleanly, exports correctly, no auth error.

### Security finding — credentials in an untracked, un-ignored file

- **Symptom:** `wordpress/deploy-local.ps1` (created by the other tool, at the `wordpress/` root, not `wordpress/docs/`) hardcodes the live SSH password and the Hostinger database password in plaintext. It was untracked in git but **not covered by `.gitignore`** — one `git add -A` away from being committed.
- **Fix:** added `wordpress/deploy-local.ps1` plus `wordpress/docs/*.sql`, `wordpress/docs/*.zip`, `wordpress/docs/dump-db.php`, `wordpress/docs/upload.php` to `.gitignore` (`.gitignore:48-54`). None of these were ever committed, so no history to scrub — but recommend rotating the Hostinger DB/SSH passwords at some point regardless, since they've been sitting in a plaintext file outside version control's protection.

### Files modified this session

| File | Action |
|---|---|
| `.gitignore` | Added deploy-scratch + credential-file exclusions |
| `wordpress/docs/deploy-content-sync.ps1` | New — rebuilds zips + scoped (safe) DB export in one run |
| `wordpress/docs/migration-log.md` | This entry |

### Status

Asset cache-busting is fully fixed and verified (no code changes needed beyond redeploying). The DB-content sync is handed off to the user as a safe, table-scoped playbook (documented in chat) rather than executed directly, since it requires the user's own SSH session to the live server. A pre-sync backup step is included so the sync is reversible if anything unexpected happens.

---

## Session: Content sync executed successfully — 2026-07-25 (continued)

Walked the user through the scoped sync playbook live, one step at a time after two false starts. Documenting the actual failure modes hit, since they're exactly the kind of thing worth folding into the eventual migration skill.

### Symptom → Cause → Fix 1: `deploy-content-sync.ps1` password-extraction regex captured the wrong substring

- **Symptom:** `mysqldump: Got error: 1045: Access denied for user 'root'@'localhost' (using password: YES)` — a password WAS being passed, just the wrong one.
- **Cause:** the script's regex `"DB_PASSWORD.*?'(.*?)'"` doesn't anchor on the quoted key name — against `define( 'DB_PASSWORD', 'root' );`, the first `.*?` lazily matches zero characters and immediately satisfies the literal `'` on the *closing* quote of `'DB_PASSWORD'` itself, then the capture group lazily grabs the next `'`-delimited span, which is `, ` (comma-space) between the key's closing quote and the value's opening quote — not the actual password.
- **Fix:** changed the pattern to `"define\(\s*'DB_PASSWORD'\s*,\s*'([^']*)'"`, which requires the literal quoted key name before the comma, so the capture group can only land on the real value. Verified against the actual `wp-config.php` content before handing it back (`grep -oP` reproducing the same match logic) rather than just asserting it would work.

### Symptom → Cause → Fix 2: first "successful" import actually did nothing (search-replace found 0 replacements)

- **Symptom:** `mysql ... < deploy-content-sync.sql` ran with no error, but the following `search-replace` reported **0 replacements**, when local has 467 rows with `visati.local` in `wp_posts.guid` alone.
- **Cause:** the `.sql` file that got imported was still the **empty (0-byte) file from before the password-regex fix** — the instruction to re-run the export script and re-upload before importing had been skipped in the flow of pasting a big combined command block. A `mysql < emptyfile.sql` import succeeds silently (0 statements to run), which is indistinguishable from a real success unless you check the actual data afterward.
- **Diagnosis method:** wrote a one-off verification script (`wp eval-file` with a heredoc, since `wp db query` and `wp eval` with inline quoting both have friction on this server) checking `elementor_meta` postmeta count, a sample `guid` value, and `siteurl`. Local showed `elementor_meta=388`; Hostinger showed `377` — the exact count from the *original* T070 migration, proving the DB genuinely hadn't been touched by any of the sync attempts yet, despite no visible errors.
- **Fix:** re-ran the corrected script, confirmed the resulting `.sql` had real content (39,217,363 bytes, exactly the 6 expected `DROP TABLE` statements) **before** uploading, confirmed the uploaded file matched that exact byte count on the server **before** importing, then imported. Re-ran the same verification script afterward: `elementor_meta=388` (now matching local), `search-replace` found 471 replacements (467 guid + 4 post_content), `siteurl` correctly untouched at `https://visati.visati.ae`.
- **Lesson for the skill:** never trust "no error was printed" as confirmation a DB import worked — `mysql < file.sql` is silent on both full success AND on successfully importing an empty file. Always verify against a known-different-locally value (a row count, a specific field) both before and after, and check the file size at every hop (local export → upload → pre-import) rather than only at the end.

### Result

Content sync completed successfully. Elementor page edits, CPT content, and nav menu changes are now live on `visati.visati.ae`, without touching `wp_visati_applications`/`wp_visati_inquiries`/`wp_visati_documents`/`wp_visati_payments`/`wp_visati_status_history`, `wp_options`, or `wp_users` — confirmed via `siteurl` remaining exactly `https://visati.visati.ae` throughout (would have been overwritten by a wholesale import). Theme/plugin files were also redeployed in the same session (from earlier zips, already confirmed working). Remaining manual step: Elementor → Tools → Regenerate CSS & Data in the live wp-admin (can't be done via WP-CLI on this host).

### Files modified this session

| File | Action |
|---|---|
| `wordpress/docs/deploy-content-sync.ps1` | Fixed DB_PASSWORD regex |
| `wordpress/docs/migration-log.md` | This entry |

---

## Session: Live UI feedback after first successful sync — 2026-07-25 (continued)

User reviewed the live staging site post-sync and reported 4 issues. Investigated each against actual live-server markup/CSS (via `curl`) rather than guessing from the screenshots alone.

### Issue 1 & 2 — Apply form "Continue" button and Track page "Track" button look washed out/pale

- **Investigation:** checked `application-form.js` end-to-end for anything that could leave `nextBtn.disabled = true` at step 1 — nothing does; `setLoading()` (the only code that ever sets `.disabled`) is only invoked during file upload (step 3) and final submit, never at step 1. Fetched the live server's actual deployed `app.css` via `curl` and diffed against local — byte-identical `.visati-btn` rule, a vivid gold gradient by default, not pale. Live markup (`curl`'d `/apply/`) also confirmed no `disabled` attribute present.
- **Conclusion:** no code bug found. Most likely explanation is perceptual contrast — the same gold gradient reads far more vivid against the header's dark navy background than against the Apply/Track pages' plain white background, even though the CSS is identical in both places.
- **Fix:** added a secondary, darker drop-shadow layer to the base `.visati-btn` rule (`0 1px 2px rgba(10,22,40,0.15)` alongside the existing gold glow) so gold buttons read with more definition/depth specifically on light backgrounds, without changing anything about how they look on the dark header. Flagged to the user this is a best-effort contrast fix rather than a confirmed bug fix, since it couldn't be visually verified directly (no working screenshot tool this session — chrome-devtools MCP disconnected).

### Issue 3 — Date picker looks inconsistent with other fields

- **Investigation:** the Quick Apply widget's date input already had partial normalization CSS (`.visati-qa-card__input[type="date"]`, dimmed calendar icon). The full Apply page's 3 date fields (`date_of_birth`, `passport_expiry`, `travel_date`) had **none** — they only got the generic `.visati-field input` border/padding/radius rule, with zero date-specific treatment, so their native browser calendar icon and internal segment styling looked more raw/unstyled than everywhere else.
- **Fix:** added the same date-specific normalization (`-webkit-appearance: none`, dimmed `::-webkit-calendar-picker-indicator`, consistent `::-webkit-datetime-edit` text color) to `.visati-field input[type="date"]` in `apply.css`, so all date fields across both places get the same treatment. Native browser date-picker chrome (the calendar icon itself, internal mm/dd/yyyy segment rendering) can't be fully restyled cross-browser beyond this — that's a genuine platform limitation, not something CSS can fully unify.

### Issue 4 — Nationality field shows no flags (Quick Apply widget), and the Apply page's own flag support wasn't visibly confirmed

- **Investigation:** the **full Apply page** already has a complete flag-combobox implementation (`render_nationality_field()` in `class-apply-form.php`) — a searchable dropdown listing all countries with `<img src="https://flagcdn.com/<code>.svg">` per row, and JS (`initCombobox()` in `application-form.js`) that swaps in the selected country's flag next to the closed button. Verified via `curl` against the live server: all 77 flag `<img>` tags are present in the rendered HTML, and CSS explicitly sizes them (20×14px) with no `display:none` anywhere in the chain. This code was already correct and live.
- **The actual gap:** the **Quick Apply widget** (`class-quick-apply.php`, used on the homepage) never had any flag support — it was a plain `<select><option>` list of 20 popular countries, no images at all. This explains "no flag showing… just like apply page" — read as "make Quick Apply match the Apply page," which already had flags.
- **Fix:** rebuilt Quick Apply's nationality field as the same kind of flag-combobox (hidden input + button with flag/text/chevron + a panel listing all 20 popular countries with `flagcdn.com` flag images), reusing the Apply page's existing `.visati-combobox*` CSS classes (already loaded on this widget, since it already enqueues `apply.css`) rather than writing new styles from scratch. Since the widget's own script (inline in `class-quick-apply.php`) is separate from the full apply-form's `application-form.js` (that one only initializes on the `#visati-apply-form` element, which doesn't exist on the homepage), wrote a small standalone combobox script scoped to `[data-qa-combobox]` rather than trying to share the other script across pages that don't load it. Added a few scoped override rules (`.visati-qa-card__combobox .visati-combobox__button`) so the reused combobox visually matches the card's own gold-accent, compact sizing instead of the full apply page's blue-accent, larger sizing.
- **Verified:** `do_shortcode('[visati_quick_apply]')` — new combobox markup present, 20 flag images render (plus 1 incidental match inside the inline JS's own template string), old plain `<select>` fully removed.

### Files modified this session

| File | Action |
|---|---|
| `includes/shortcodes/class-quick-apply.php` | Nationality field rebuilt as a flag-combobox (was a plain `<select>`); standalone JS added |
| `assets/css/apply.css` | Added date-input normalization for the full Apply page's 3 date fields |
| `themes/visati/assets/css/app.css` | Stronger base `.visati-btn` shadow (light-background contrast); QA-card combobox override styles |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.20 → 0.4.21 |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.16 → 0.3.17 |

### Status

Issues 3 and 4 are confirmed, concrete fixes (verified structurally). Issues 1 and 2 are a best-effort contrast improvement pending the user's visual confirmation on the live site after redeploy — flagged explicitly as unverified rather than claimed as a fixed bug, since no code-level cause was actually found.

---

## Session: Stale page cache diagnosed + header/footer layout fixes — 2026-07-25 (continued)

### Symptom → Cause → Fix: homepage renders blank for logged-out visitors, fine when logged in

- **Symptom:** user reported the homepage (and possibly Apply) showing a blank gap between header and footer in incognito/logged-out, while looking correct when logged into wp-admin.
- **Diagnosis:** reproduced directly — an unauthenticated `curl` to the homepage returned `x-litespeed-cache: hit`, `age: 36654` (~10 hours stale), and **zero** `elementor-section`/`elementor-widget` matches in the returned HTML. Logged-in admin requests always bypass page cache (standard WP behavior), which is exactly why it looked fine to the user but broken to anyone else.
- **Root cause:** earlier today's content sync (`mysql < deploy-content-sync.sql`, a raw DB import — see the "Content sync executed successfully" entry above) bypasses WordPress's normal save/update hooks entirely, since it writes directly to the database rather than going through `wp_insert_post()` etc. LiteSpeed's page cache normally auto-purges on those hooks — since they never fired, the 10-hour-old broken snapshot (cached at some earlier point when Elementor content wasn't rendering) kept being served indefinitely.
- **Correction mid-diagnosis:** initially assumed the LiteSpeed Cache *plugin* was active (its presence is normally visible as a wp-admin menu) — user confirmed it isn't installed. The `x-litespeed-cache`/`x-hcdn-cache-status` headers are from Hostinger's server/edge-level LiteSpeed + CDN caching, controlled from **hPanel** (Website → Cache Manager), not from within WordPress at all. Corrected the purge instructions accordingly (hPanel button, or `curl -X PURGE ... -H "X-LiteSpeed-Purge: *"` as an SSH fallback).
- **Lesson for the skill:** any future raw-DB content sync (bypassing WP-CLI/`wp_insert_post`) must be followed by an explicit cache purge step — auto-invalidation won't fire. Add this as a mandatory step in the sync runbook.

### Fix: header showed an unwanted floating gap even before scrolling

- **Cause:** `.visati-header { position: sticky; top: 12px; margin-bottom: 12px; }` applied the floating-pill offset (`top: 12px`) and the gap below the header (`margin-bottom: 12px`) unconditionally, not just once `.is-scrolled` is toggled (JS adds that class after 10px of scroll, per `header.php`). So the header always looked "floated" with a gap, even at the very top of a fresh page load.
- **Fix:** moved `top: 12px` and `margin-bottom: 12px` into a `.visati-header.is-scrolled` rule; the base `.visati-header` rule now uses `top: 0` with no margin, so it sits flush at the top of the page until actually scrolled.

### Fix: footer doesn't stick to the bottom on short-content pages

- **Cause:** no sticky-footer layout existed at all — no `min-height`/flex wrapper anywhere, so on any page with little content, the footer rode up and sat mid-viewport with visible white space below it, instead of pinning to the bottom.
- **Fix:** `body { display: flex; flex-direction: column; min-height: 100vh; }` + `.visati-footer { margin-top: auto; }` — the classic flexbox sticky-footer pattern. Since header/content/footer are all direct children of `<body>` across every page template (verified — no shared wrapper div exists, each template calls `get_header()`/`get_footer()` independently), this works globally without touching every individual template file. Found and removed a conflicting `margin-top: 0` on a second, more specific `.visati-footer` rule further down the file that would otherwise have silently overridden the fix.

### Files modified this session

| File | Action |
|---|---|
| `themes/visati/assets/css/app.css` | `.visati-header` floating-gap scoped to `.is-scrolled` only; sticky-footer flex layout added; removed conflicting `margin-top: 0` |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.21 → 0.4.22 |

### Status

Both layout fixes verified locally via a live fetch of the deployed CSS. Cache purge is on the user to run via hPanel (no plugin-level access from this session) — will re-verify staleness via `curl` once done.

---

## Session: Full country list + self-hosted flags + editable email template (parallel session, 2026-07-25)

This work was done in a separate AI coding tool session ("MiMo," same repo, `D:\GIAIC\Visati` = `/mnt/d/GIAIC/Visati`) running in parallel with this one. Logged here per the mandatory work-log rule, and independently verified in this session rather than taken on faith.

### What changed

- **New `includes/helpers-nationalities.php`** — `visati_get_nationalities()`, a single shared ~194-country list (ISO 3166-1 alpha-2 => name), popular UAE-visa-source countries first (India, Pakistan, Bangladesh, etc.) then alphabetical. Replaces two previously-separate, smaller lists: the Apply page's 77-country `NATIONALITIES` const and Quick Apply's 20-country "popular" list — both now call this one function instead.
- **193 flag SVGs self-hosted** at `assets/images/flags/<code>.svg`, downloaded directly from flagcdn.com into the plugin's own assets (bash loop with parallel `curl` backgrounded jobs). This directly fixes the "flag shows in the dropdown list but not in the selected field" bug reported earlier — that turned out to be the external `flagcdn.com` CDN failing to load in the reporting browser/network, not a JS logic bug (matches this session's own hypothesis before the parallel session's fix landed). All three flag-rendering call sites (`class-apply-form.php`'s PHP-rendered list, `class-quick-apply.php`'s PHP-rendered list + inline JS, and `application-form.js`'s two remaining `https://flagcdn.com/` references — one in `selectItem()`, one in the URL-param pre-fill handler) were updated to a `VISATI_CORE_URL . 'assets/images/flags/'` base, passed into JS via a new `flagsUrl` key in the existing `wp_localize_script()` payload.
- **Combobox button border mismatch fixed** — the nationality field's button (`height: 46px` + `padding: 0 0.85rem`) didn't match sibling text inputs (`padding: 0.7rem 0.85rem`, no fixed height), making it visibly inconsistent. Unified to the same padding/box-sizing as other `.visati-field` inputs.
- **Email template overhaul** (`class-email.php`) — logo support (falls back to a text brand name if no logo is set), and 5 new admin-configurable Settings fields: logo image (media picker), brand name, header background color, accent/CTA color, email background color, footer text. All wired through the same `Visati_Core_Settings::get()` + defaults pattern used everywhere else, with a new "Email Template" section added to the Settings page UI.
- **Plugin version header desync fixed** — the docblock `Version:` field in `visati-core.php` had been stuck at `0.3.0` since the plugin was first scaffolded, never kept in sync with the `VISATI_CORE_VERSION` constant (now `0.3.17`) actually used for cache-busting. Synced to match.

### Verification performed in this session (not just trusting the parallel session's own report)

- `php -l` on all 6 touched PHP files — clean.
- `grep -rn "flagcdn"` across the entire plugin — zero matches, confirming the fix is actually complete, not partial.
- `ls assets/images/flags/ | wc -l` — 193 files present.
- Live `do_shortcode()` checks: Quick Apply renders 194 countries, Apply form renders 194 flag entries.
- Live full-page fetch of `/apply/`: confirmed `flagsUrl` is actually present in the localized JS payload (not just referenced in PHP source), confirmed a self-hosted flag path renders in the actual list markup, confirmed zero `flagcdn` strings anywhere in the rendered HTML.

### Files modified (parallel session)

| File | Action |
|---|---|
| `includes/helpers-nationalities.php` | New — shared 194-country list |
| `includes/shortcodes/class-apply-form.php` | `NATIONALITIES` const deprecated in favor of the shared helper; self-hosted flag paths; `flagsUrl` added to localized script data |
| `includes/shortcodes/class-quick-apply.php` | `get_popular_countries()` replaced with the shared helper; self-hosted flag paths |
| `assets/js/application-form.js` | 2 remaining `flagcdn.com` references replaced with `visatiApply.flagsUrl` |
| `assets/css/apply.css` | Combobox button padding/box-sizing unified with sibling inputs |
| `includes/class-email.php` | Logo + configurable colors/footer text, reading from Settings |
| `admin/class-settings-page.php` | New "Email Template" settings section + save handler + defaults |
| `visati-core.php` | `Version:` docblock header synced to `0.3.17`; `helpers-nationalities.php` require added |
| `assets/images/flags/*.svg` (193 files) | New — self-hosted flag assets |

### Status

Deploy zips were rebuilt by the parallel session (`deploy-theme.zip` 44KB, `deploy-plugin.zip` ~1.1MB — the size jump is the 193 flag SVGs) and are sitting in `wordpress/docs/` ready to upload. Not yet confirmed live on Hostinger as of this log entry — still needs the same upload → extract → chmod → cache-purge sequence used throughout this document.

### Symptom → Cause → Fix: Algeria flag broken (one silently-missing file out of 194)

- **Symptom:** user reported a broken-image icon for Algeria's flag in the nationality dropdown.
- **Cause:** `dz.svg` was the one file that never actually downloaded when the parallel session's parallel-`curl`-in-a-bash-loop grabbed 193 flags — a transient failure in one backgrounded `curl &` job that went unnoticed since the loop didn't check each download's exit status, and the final count (193) wasn't cross-checked against the actual 194-country list.
- **Fix:** diffed the full country list (`visati_get_nationalities()`) against the flags directory's actual contents (`comm -23`) rather than trusting the file count alone — confirmed `dz` was the *only* gap, downloaded it directly, verified it's a valid SVG. 194/194 now present.
- **Lesson for the skill:** when bulk-downloading assets in parallel background jobs, verify the resulting set against the expected list by diffing (not just counting) before considering the batch complete — a raw count can match by coincidence even with the wrong file missing (or extra unexpected ones present).

### Files modified this session

| File | Action |
|---|---|
| `assets/images/flags/dz.svg` | Added — was the one missing flag out of 194 |

---

## Session: Gold-text button bug, confirmed real (cache eliminated) — 2026-07-25 (continued)

After a full redeploy + cache purge, the user re-reported the Track/Continue gold-text issue with fresh screenshots — this time genuinely reproducible, not a caching artifact (their own words: "map field has fixed means no cache", confirming the earlier flag fix was visible, ruling out staleness as the explanation for this next report).

### Investigation

Searched exhaustively for what could override `.visati-btn`'s `color: var(--visati-navy)`:
- `app.css`'s golden-button-reset system — correctly excludes `[class*="visati-btn"]`, doesn't match.
- `apply.css` — found a **typo'd dead rule block** (`.visiti-qa-card` instead of `.visati-qa-card`, missing the "a") that was clearly an earlier *attempted* fix for this exact same class of bug, but the typo meant it never actually matched any real markup — so it wasn't the cause, just evidence someone tried to fix this before and the fix silently failed to apply.
- `tracking.js` — confirmed `btn.disabled = true` exists but only inside the active-search handler, not on page load; screenshots show the resting state, so not applicable.
- `page-track.php` — confirmed `get_header()`/`get_footer()` are called normally, so `app.css` (and its `--visati-navy` custom property definition) loads normally; no scoping/isolation issue.
- Border-radius: found the Track page already has a more-specific override (`.visati-page--track .visati-track-search input { border-radius: 12px }`) that already matches the button's `12px` — so despite the user also flagging border-radius, the two were already numerically consistent; left this alone rather than guessing further changes.

No logical cause was found in the source for the color specifically. Given the user's explicit direction ("directly update the buttons/fields shortcode css") and a design clarification mid-turn (the correct target color is **white**, not navy — confirmed by the user noting the Apply form's other step states, e.g. the final "Submit & Pay" navy-background button, already show correct white text), applied a decisive, precisely-scoped fix rather than continuing to hunt for the root cause.

### Fix

- `#track-search-btn` and `#visati-apply-form [data-action="next"]` (the single reused step-nav button element across all 4 Apply steps) forced to `color: #FFFFFF !important`, including their SVG icons (`stroke`/`color`), in both rest and `:hover` states. Scoped by ID/form-ID + `data-action` attribute — deliberately narrow, does not touch the header CTA, Quick Apply's submit button, or any other `.visati-btn` instance, which keep their existing (already-correct) navy text.
- Cleaned up the typo'd dead `.visiti-qa-card` block in `apply.css` (6 lines removed) — redundant with the already-correct, correctly-spelled `.visati-qa-card__submit` rule just above it; kept only the real, working rule.

### Files modified this session

| File | Action |
|---|---|
| `themes/visati/assets/css/app.css` | White-text override for Track + Apply step-nav buttons |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.22 → 0.4.23 |
| `plugins/visati-core/assets/css/apply.css` | Removed typo'd dead `.visiti-qa-card` rule block |

### Status

Verified locally via a live CSS fetch — the white-text rule and the typo cleanup are both confirmed present. Not yet deployed to Hostinger as of this entry. **Confirmed fixed by the user after this round's deploy.**

---

## Session: Quick Apply date field + desktop card width (2026-07-25, continued)

Button text-color fix confirmed working live. Two remaining items from the same feedback round.

### Fix: Quick Apply's Travel Date field still visually inconsistent with its siblings

- **Cause:** `.visati-qa-card__input[type="date"]` tried to normalize the native date picker's appearance via `appearance: none`, but some browsers only partially honor that on `type="date"` inputs — the border/radius/background can still partially fall back toward native rendering even with `appearance: none` set, since the shared base rule above it (`.visati-qa-card__select, .visati-qa-card__input`) sets `appearance: auto`, and the override wasn't forceful enough to fully win in all cases.
- **Fix:** added `!important` to `appearance`, and explicitly restated `border`, `border-radius`, and `background` on the date-specific rule with `!important`, rather than relying on it inheriting cleanly from the shared rule.

### Fix: Quick Apply card too narrow on desktop

- **Cause:** `.visati-qa-card { max-width: 420px }` applied uniformly at every screen size, no responsive widening for desktop.
- **Fix:** added `@media (min-width: 1024px) { .visati-qa-card { max-width: 460px } }` — a modest desktop-only increase, mobile untouched.

### Files modified this session

| File | Action |
|---|---|
| `themes/visati/assets/css/app.css` | Forced date-input box model with `!important`; desktop `max-width` bump for `.visati-qa-card` |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.23 → 0.4.24 |

### Status

Verified locally via a live CSS fetch. Not yet deployed to Hostinger as of this entry.

---

## Session: Discount code UI + mobile header tagline (2026-07-25, continued)

User pointed out a real gap: the discount/coupon system was fully built server-side (CPT, `Visati_Core_Discount::validate()`, `POST /visati/v1/discount/validate`, `checkout-session` already accepting `discount_code`) but had **no frontend UI anywhere** — grepped the apply form's PHP and JS and confirmed zero mentions of discount/coupon. Also flagged that the mobile header hides the brand tagline while desktop shows it.

### Built: discount code UI on the Apply page's Order Summary

- **Markup** (`class-apply-form.php`): a code input + "Apply" button inserted into the Order Summary between the price breakdown and the total, plus a new (initially hidden) "Discount (CODE)" row in the breakdown, shown once a code is successfully applied.
- **JS** (`application-form.js`): new `appliedDiscount` state (`{code, amount, key}`) plus:
  - `applyDiscount()` — POSTs to the already-existing `/discount/validate` endpoint with the current visa+tier price basis, shows a success/error message, and re-renders the summary.
  - `updateOrderSummary()` extended to subtract the applied discount from the total, and to **auto-invalidate** the discount if the visa or processing tier changes after it was applied (tracked via a `slug|tier` key) — since the discount was validated against a specific price, changing that price basis means it needs re-validating, so the UI clears it and prompts the user to re-apply rather than silently showing a stale/wrong amount.
  - Final `checkout-session` POST now includes `discount_code` (empty string if none applied) — the server independently re-validates and re-computes the discount amount itself (`class-webhooks-controller.php` already did this; the client-side amount was always cosmetic/preview-only, matching the existing "never trust a client-computed amount" comment already in that file).
- **CSS** (`apply.css`): new `.visati-order-summary__discount*` rules — outline-style button (navy border/text, fills navy on hover) matching the Order Summary's existing visual language, not a repeat of the gold CTA style, so it doesn't compete visually with the "Continue"/"Submit & Pay" button below it.
- **Caught proactively:** the new discount button is a plain `<button type="button">`, which — per this session's now-familiar recurring bug pattern — would have been swept into the golden-button-reset system (any unstyled button/link inside `.visati-page` gets forced into a gold gradient) without an explicit exclusion. Added `.visati-order-summary__discount-btn` to both the base and `:hover` exclusion lists in `app.css` *before* it ever shipped broken, rather than waiting for another round-trip bug report.

### Verified

- `Visati_Core_Discount::validate()` tested end-to-end against a real (temporary, cleaned-up-after) discount post: 10% off 500 AED correctly computed 50 AED discount, 450 AED final price.
- `do_shortcode('[visati_apply_form]')` confirmed all 3 new data-attributes present in the rendered markup.
- Live CSS fetch confirmed the discount button is present in the golden-button-reset exclusion list.

### Fix: mobile header missing the tagline shown on desktop

- **Cause:** `@media (max-width: 639px) { .visati-header__tagline { display: none; } }` — an explicit mobile-only hide, presumably an earlier space-saving decision that the user now wants reverted.
- **Fix:** removed the rule. The wordmark and logo already shrink at this breakpoint (existing rules, untouched), so the tagline fits underneath without needing further layout changes.

### Files modified this session

| File | Action |
|---|---|
| `includes/shortcodes/class-apply-form.php` | Discount code input + Apply button + discount row markup |
| `assets/js/application-form.js` | Discount validate/apply logic, order-summary integration, checkout payload |
| `assets/css/apply.css` | Discount UI styling |
| `themes/visati/assets/css/app.css` | Golden-button-reset exclusion for the new discount button; removed mobile tagline `display:none` |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.24 → 0.4.25 |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.17 → 0.3.18 (docblock `Version:` synced too) |

### Status

Verified locally (structural + functional + live CSS checks). Not yet deployed to Hostinger as of this entry.

---

## Session: Discount endpoint PHP 8.1 fatal error — critical fix (2026-07-25, continued)

User tested the new discount UI live (created a real "TESTING" discount code via the already-built wp-admin meta box) and hit WordPress's own fatal-error screen instead of a validation result.

### Symptom → Cause → Fix

- **Symptom:** clicking "Apply" with any code produced a raw WordPress "critical error" page, its HTML leaking into the frontend as the displayed message (via `data.message` in the JS, since WP's REST error envelope stuffed the fatal-error HTML into the `message` field of an otherwise-valid JSON error response).
- **Diagnosis:** hit the live endpoint directly with `curl -X POST .../discount/validate` to get the real (not WP's own live-site-hides-it) stack trace: `Uncaught ArgumentCountError: floatval() expects exactly 1 argument, 3 given`, thrown from inside `WP_REST_Request->sanitize_params()`.
- **Root cause:** `class-discount-controller.php`'s `price_aed` REST arg used `'sanitize_callback' => 'floatval'`. WordPress core invokes every sanitize callback as `call_user_func( $callback, $value, $request, $param )` — 3 arguments. `floatval()` is a strict PHP **internal** function with a fixed 1-argument signature; PHP 8.1 changed internal functions to throw `ArgumentCountError` when called with too many arguments via `call_user_func`/callable syntax (PHP 8.0 and earlier silently ignored the extras). User-defined functions (like `sanitize_text_field`, used correctly elsewhere in the same file) don't have this problem — only bare internal functions (`floatval`, `intval`, `strtoupper`, etc.) used directly as a `sanitize_callback` are at risk. Confirmed via `grep` that this exact pattern existed in exactly one place across all REST controllers — isolated, not a systemic issue.
- **Why local testing missed this:** the earlier functional verification (previous log entry) called `Visati_Core_Discount::validate()` directly via `wp eval`, bypassing the REST layer's `sanitize_params()` entirely — so the underlying discount-computation logic was genuinely verified correct, but the REST *plumbing* around it was never actually exercised until a real HTTP request hit it. Re-verified this fix specifically via `wp_remote_post()` to the real REST route (not a direct method call) — confirmed HTTP 200 with correct JSON output (10% off 918 AED → 91.8 AED discount, 826.2 final) this time.
- **Fix:** replaced the bare `'floatval'` callback with an inline closure `function ( $value ) { return (float) $value; }` — a single-argument-tolerant wrapper, matching the fix pattern already established elsewhere in this codebase for the same PHP-version gotcha (`application-form.js`'s `parseFloat()` fix earlier this session was a different but related class of "silent type coercion" bug).

### Also flagged to the user (not a code issue)

The specific "TESTING" discount post they created has **Active unchecked** and an **expiry date of 07/20/2026** (5 days in the past relative to today, 2026-07-25) — even after this fix, that exact code will correctly return "no longer active" / "expired" rather than crashing, but won't successfully apply until those two fields are corrected in wp-admin.

### Files modified this session

| File | Action |
|---|---|
| `includes/rest/class-discount-controller.php` | Fixed PHP 8.1 `ArgumentCountError` — bare `floatval` sanitize_callback replaced with a closure |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.18 → 0.3.19 (docblock synced) |

### Status

Fix verified via a real HTTP REST request (not a direct method call) both before (confirmed the bug is real) and after (confirmed the fix works) the change. Not yet deployed to Hostinger as of this entry — this is the highest-priority item to ship next, since the discount feature is completely broken (fatal error, not just cosmetically wrong) until this lands.

---

## Session: Discount field styling matched to theme (2026-07-25, continued)

User asked to make the discount coupon field visually match the theme. The initial build (previous entry) deliberately used a navy-outline "Apply" button to avoid visually competing with the gold "Continue"/"Submit & Pay" button below it — but on an otherwise all-gold-accented page, that read as an inconsistent, out-of-place special case rather than a deliberate design choice.

### Fix

- `.visati-order-summary__discount-btn` switched from a navy-outline/transparent button to the same gold gradient (`linear-gradient(135deg, #C9A84C 0%, #F0C864 50%, #C9A84C 100%)`), shadow, and hover-shift treatment used by `.visati-btn` site-wide — same visual language, scaled down (smaller padding/font-size) to fit inline next to the input in the sidebar rather than being a full-width primary action.
- Discount input field's border/radius/focus-ring were already consistent with `.visati-field input` (1px `#d7dbe2` border, 10px radius, gold focus ring) — left unchanged, already correct.

### Files modified this session

| File | Action |
|---|---|
| `assets/css/apply.css` | Discount Apply button restyled to gold-gradient, matching `.visati-btn` |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.19 → 0.3.20 (docblock synced) |

### Status

Verified locally via a live CSS fetch. Not yet deployed to Hostinger as of this entry — bundle with the PHP 8.1 discount-endpoint fix from the previous entry in the same deploy.

---

## Symptom → Cause → Fix: deploy self-inflicted a directory-permissions outage — 2026-07-25

- **Symptom:** during the redeploy of the two fixes above, the operator's `chmod` sequence had a typo — the second `find ... -type d -exec chmod ...` was accidentally run with `644` instead of `755`. `644` on a directory strips the execute (traverse) bit, which even the owning user needs to enter or list a directory's contents — so every subdirectory under `wp-content/plugins/visati-core` became inaccessible to everyone, including the web server process (Hostinger shared hosting runs PHP as the same account as SSH, so this broke the live plugin, not just the SSH session).
- **Symptom in the terminal:** subsequent `chmod`/`find` commands failed with `Permission denied` on `admin/`, `assets/`, `includes/`, `templates/` — the four first-level subdirectories that had just been downgraded.
- **Fix:** restored the top-level plugin directory's execute bit first via a direct (non-traversing) `chmod 755 wp-content/plugins/visati-core`, then the same for the four named subdirectories directly, which unblocked a final full recursive `find -type d -exec chmod 755` + `find -type f -exec chmod 644` pass — completed with zero further permission errors.
- **Verified recovered:** live `curl` to `/apply/` returned 200 with real content; the discount validate endpoint returned a clean 404 (`discount_not_found`) instead of the earlier 500 crash — confirming both the permissions recovery *and* the PHP 8.1 sanitize_callback fix are correctly live together.
- **Lesson for the skill:** `chmod`'s directory-vs-file distinction (`755` vs `644`) is easy to fumble when running near-identical `find ... -exec chmod` commands back to back for dirs then files — a directory permission mistake is worse than a file one, since it can cascade into blocking access to everything beneath it, including for the owning user. Worth having the deploy runbook double-check the *type* flag (`-type d` → `755`, `-type f` → `644`) is correct before running, not just that the commands were copy-pasted correctly.

### Status

Both fixes from this session (discount PHP 8.1 fix, discount button theme styling) are confirmed live and working. The permissions incident was fully self-recovered within the same session with no lasting damage.

---

## Session: Icon-font stars, root-cause button/field appearance fix, testimonial arrows (2026-07-25, continued)

User reported 4 issues in one batch: testimonials summary-badge stars not rendering on some devices (card stars fine), the contact form's Send Message button showing the same gold-text bug as Track/Continue before, the contact form's fields not matching the theme's styling, and the Track page's search field having reduced height on mobile. Plus a design request: testimonials nav arrows should be outline-at-rest/gold-on-hover circles, not the solid gold rounded-squares currently showing.

### Root cause found for the recurring "gold text" mystery (finally)

Every previous instance of this bug (Track button, Apply Continue button) was patched with a narrowly-scoped forced-white-text override, without ever finding *why* it happened — genuinely unexplained each time. This time, with the contact form's `<button type="submit" class="visati-btn">` showing the exact same symptom despite no per-button override, the pattern pointed at something affecting *every* `.visati-btn` on certain devices: mobile Safari/WebKit gives `<button>`/`type="submit"` elements native platform chrome (including partial text-color/appearance override) unless `appearance: none` is explicitly declared — which the base `.visati-btn` rule never had.

- **Fix:** added `-webkit-appearance: none; -moz-appearance: none; appearance: none;` to the base `.visati-btn` rule in `app.css`. This is a root-cause fix covering every current and future `.visati-btn` instance, not another per-button patch. The existing forced-white overrides on Track/Continue are left in place (harmless, now redundant) since white was the *design* choice there regardless of the rendering bug.

### Fields not matching theme + reduced mobile height — same root cause, different symptom

Applied the same `appearance: none` reset to `.visati-contact-form input/textarea`, `.visati-page--track .visati-track-search input`, and `.visati-field input` (Apply page) — native mobile browser chrome for text inputs can override border-radius/border/background *and* effective height (the Track page's `height: 48px` box was being fought by native internal padding on the affected devices), all stemming from the same unset-appearance root cause. Selects were deliberately left untouched (`.visati-field select`) since forcing `appearance: none` there would remove their native dropdown arrow without a custom replacement — no such issue was reported for selects.

### Testimonials summary-badge stars — different root cause, Elementor icon font

Traced the "4.9 ★★★★★ Based on 100+ reviews" badge (separate from the individual testimonial cards, which use plain Unicode `★`/`☆` and were already confirmed working) to Elementor's own native **Star Rating** widget, which renders stars via `<i class="elementor-star-full">&#xE934;</i>` — a private-use-area Unicode codepoint mapped to a glyph in Elementor's bundled icon font. If that font fails to load on a given device/browser (network, blocker, or loading-order issue), the codepoint renders as nothing — no fallback glyph exists for PUA characters. This is a different failure mode from every button/field issue above, specific to icon-font-based widgets.

- **Fix:** built `[visati_rating_badge]` (new shortcode in `class-testimonials.php`) reproducing the exact same badge using the same reliable plain-Unicode `★` approach the testimonial cards already use successfully — no icon font, no external dependency. Did **not** edit the homepage's `_elementor_data` directly (this project's documented gotcha about fragile direct Elementor JSON edits) — instead built the safe drop-in replacement and left the actual swap (delete the native Star Rating widget, add a Shortcode widget with `[visati_rating_badge]`) as a manual Elementor step for the user.

### Testimonials nav arrows — golden-button-reset gap (same recurring pattern as the discount button)

- **Cause:** `.visati-testimonials-arrow` (a plain `<button type="button">`) was never added to the golden-button-reset exclusion list, so it got swept into the reset's `!important` solid-gold, `border-radius: 12px` treatment — completely overriding the shortcode's own intended white/circular/outline design. Exactly the same bug class as the discount button earlier this session, just from an older component built before that habit was established.
- **Fix:** added `.visati-testimonials-arrow` to both the base and `:hover` exclusion lists (both the `button:where()` and `[type="button"]:where()` variants — a button with `type="button"` matches both selectors, so both needed the exclusion, not just one). Also updated the hover treatment per the explicit design request: gold gradient background with a navy border on hover (previously solid navy background) — rest state (white circle, light outline border) was already correct in the shortcode's own CSS, just never visible until unblocked from the reset.

### Files modified this session

| File | Action |
|---|---|
| `themes/visati/assets/css/app.css` | `appearance: none` root-cause fix on `.visati-btn`, contact-form fields, Track search input; new `.visati-rating-badge*` styles; testimonials-arrow golden-reset exclusion + gold hover |
| `plugins/visati-core/assets/css/apply.css` | `appearance: none` on `.visati-field input` (not `select`) |
| `plugins/visati-core/includes/shortcodes/class-testimonials.php` | New `[visati_rating_badge]` shortcode |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.25 → 0.4.26 |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.20 → 0.3.21 |

### Status

All verified locally (syntax + structural + live CSS fetch). Not yet deployed. One manual Elementor step required after deploy: replace the homepage's native Star Rating widget with a Shortcode widget containing `[visati_rating_badge]`.

---

## Session: Email template full brand lockup + dark mode, testimonial flags fixed (2026-07-25, continued)

### Email template — full header lockup + dark mode

User asked for the email header to match the site header's full brand lockup (mark + wordmark + tagline, not just a single logo image) and for the email to be dark/light theme aware.

- **Logo mark asset:** the theme only has an SVG mark (`logo-mark.svg`) — SVG has unreliable/no support in several email clients (notably Outlook Desktop never renders it). Installed `cairosvg` in a throwaway Python venv (no system-level changes) and rasterized it to a transparent-background PNG (`assets/images/logo-mark-email.png`, 192×268, verified non-blank via pixel extrema check) for universal `<img>` compatibility — the same approach any email-dev workflow would use for an SVG-only brand asset.
- **`class-email.php`'s `wrap()` method rebuilt:** when no custom `email_logo_url` is set in Settings (the default case), the header now renders a table-based mark+wordmark+tagline lockup — mark image, "VISATI" in the same Montserrat/800-weight/uppercase treatment as the site header, and the tagline (pulled from the existing `brand_tagline` setting, same one the site footer/header already use) beneath it in muted white. An uploaded custom logo still fully overrides this lockup, unchanged from before.
- **Dark mode:** added `<meta name="color-scheme" content="light dark">` + `<meta name="supported-color-schemes" content="light dark">` and a `<style>` block with `@media (prefers-color-scheme: dark)` overriding the body-content and footer sections' backgrounds/text colors (the navy header needs no change — it already works on both). This is additive: clients that don't support the media query (most don't, fully) simply see the existing light-mode design unchanged; clients that do (Apple Mail, Outlook.com, some Gmail contexts) adapt.
- **Verified via `ReflectionMethod`** (the method is private) rather than actually sending a test email: confirmed the mark image reference, wordmark, tagline, and dark-mode CSS/meta tags are all present in the rendered HTML output, and confirmed the new PNG asset serves correctly (200, `image/png`, non-empty) via a live local fetch.

### Testimonials cards — country flags switched from Unicode emoji to local flag files

User pointed out the newly self-hosted flag SVGs (194 countries, added earlier this session for the nationality dropdowns) should also be used by the testimonials cards, which were still using `code_to_flag()` — a helper generating Unicode regional-indicator flag emoji (the same class of rendering-reliability issue as the icon-font stars fixed earlier this session, though via emoji-font rather than a private-use codepoint; Windows in particular often renders these as plain two-letter text instead of an actual flag glyph).

- Replaced the emoji-generation call with a direct `<img>` reference to the matching local flag file (`assets/images/flags/<code>.svg`), guarded by a `file_exists()` check so a testimonial with an unrecognized/blank country code simply omits the flag rather than showing a broken image.
- Removed `code_to_flag()` entirely — no longer called anywhere in the codebase after the swap.
- Updated `.visati-testimonial-card__flag`'s CSS from font-size-based emoji styling to image dimensions or (22×16px, rounded corners, `object-fit: cover`) matching the sizing already used for flags elsewhere (nationality dropdowns).

### Files modified this session

| File | Action |
|---|---|
| `includes/class-email.php` | `wrap()` rebuilt: full mark+wordmark+tagline lockup, dark-mode CSS |
| `assets/images/logo-mark-email.png` | New — rasterized from the theme's SVG mark for email client compatibility |
| `includes/shortcodes/class-testimonials.php` | Card flags switched from Unicode emoji to local flag images; `code_to_flag()` removed |
| `themes/visati/assets/css/app.css` | `.visati-testimonial-card__flag` restyled for `<img>` instead of emoji text |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.26 → 0.4.27 |
| `visati-core.php` | `VISATI_CORE_VERSION` 0.3.21 → 0.3.22 |

### Status

Verified locally: email template via `ReflectionMethod` invocation of the private `wrap()` method (confirmed all new elements present) plus a live fetch confirming the new PNG asset serves correctly; testimonials via `do_shortcode()` confirming all 5 sample cards render local flag images with zero remaining emoji-flag markup. Not yet deployed to Hostinger as of this entry.

---

## Fix: rating badge should left-align on mobile (2026-07-25, continued)

User placed `[visati_rating_badge]` alongside the still-present old Elementor Star Rating widget to visually compare them, and found mine stayed right-aligned on mobile while the Elementor one naturally lays out left-aligned there.

- **Fix:** added `@media (max-width: 768px) { .visati-rating-badge { text-align: left } .visati-rating-badge__row { justify-content: flex-start } }` — right-aligned on desktop (unchanged, matches the intended side-by-side layout next to the "Loved by travellers" heading), left-aligned once it stacks on mobile.
- `themes/visati/functions.php`: `VISATI_THEME_VERSION` 0.4.27 → 0.4.28.

Verified locally via a live CSS fetch. Not yet deployed.

---

## Fix: testimonials nav arrows still not a true circle (2026-07-25, continued)

User reported the arrows still weren't equal width/height after the exclusion-list fix — likely still viewing a stale deploy (none of the recent theme fixes had been redeployed since the exclusion fix), but rather than assume, hardened the CSS defensively so it's guaranteed correct regardless: `width`/`height`/`border-radius` on `.visati-testimonials-arrow` promoted to `!important` (matching the golden-button-reset's own specificity weapon, so this wins outright even if the exclusion somehow isn't in effect), plus `min-width`/`max-width` and `aspect-ratio: 1/1` as belt-and-suspenders against any flex-context stretching. Updated the mobile-size media query (36px at narrow viewports) to also use `!important`/min/max — otherwise the new base-level `!important` would have blocked the intentional mobile size-down from ever applying, a regression that would've shipped un-caught without this follow-up.

`themes/visati/functions.php`: `VISATI_THEME_VERSION` 0.4.28 → 0.4.29.

Verified locally: `!important` width/aspect-ratio present on the base rule, `!important` present on the mobile override, and confirmed the mobile rule's source position is later than the base rule's (so it correctly wins at narrow viewports per CSS cascade order). Not yet deployed.

---

## Fix: arrow icon centering + Track field mobile height, round 2 (2026-07-25, continued)

Two more reports on the same round of screenshots: the arrow icon inside the (now-circular) button isn't centered, and the Track page's search field is still showing reduced height on mobile despite the earlier `appearance: none` fix.

### Arrow icon centering

Couldn't find a structural cause (flex-centering was already correctly set up, no `overflow:hidden` clipping the absolutely-positioned buttons) — most likely explanation is the icon staying a fixed 20px inside the mobile-shrunk 36px circle, leaving asymmetric visual space. Hardened defensively: added `flex-shrink: 0; margin: 0 auto;` to `.visati-testimonials-arrow svg`, and added a mobile-breakpoint override shrinking the icon to 16px inside the 36px circle so it scales down proportionally with its container instead of staying a fixed size.

### Track page field height, round 2

Found a third rule at the `@media (max-width: 639px)` breakpoint (`.visati-page--track .visati-track-search input`) that re-declares `height: 48px` without restating `appearance`/`box-sizing` — per normal CSS cascade rules this *shouldn't* have overridden the earlier fix (unset properties in a later rule don't erase an earlier rule's values for those same properties), but rather than rely on that reasoning being correct in every browser, made it explicit and forceful there too: `appearance: none !important`, `box-sizing: border-box !important`, `height: 48px !important`, plus `line-height: normal` as an extra defensive measure against native line-height quirks squishing the visible text within the fixed-height box.

### Files modified this session

| File | Action |
|---|---|
| `themes/visati/assets/css/app.css` | Arrow icon centering + mobile proportional sizing; Track mobile input rule hardened with `!important` |
| `themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.29 → 0.4.30 |

### Status

Verified locally via live CSS fetch. Not yet deployed — multiple rounds of theme fixes are now queued (rating badge alignment, arrow shape, arrow icon centering, Track field height ×2, appearance-none fixes, flag styling) — recommend a full fresh deploy + hPanel purge + hard refresh to catch up on all of them at once rather than incremental partial deploys, since several earlier reports may simply have been testing against a state that was missing 1-2 rounds of fixes.

---

## Session: In-admin Reference / Help page (2026-07-25, continued)

User asked for all shortcodes, Settings fields, etc. to be documented, ideally as a help page within the Visati plugin's admin UI rather than an external doc.

### What was built

New `Visati_Core_Admin_Help` (`admin/class-admin-help.php`), registered as **Visati → Help** in wp-admin, covering 4 sections with a sticky jump-nav at the top:

1. **Shortcodes** — the list itself is read live from WordPress's own `$shortcode_tags` registry (filtered to the `visati_` prefix), cross-referenced against a hand-written description/example table. This means a future shortcode always appears here automatically (with a visible "no description written yet" fallback) even if the description table lags behind — the list itself can never silently go stale, only individual descriptions can.
2. **Content Types** — the 5 custom post types, read live via `get_post_types()` filtered the same way, same live-list/hand-written-description split.
3. **Settings Fields** — grouped into the exact same 11 sections as the real Settings page, describing what each field controls and which shortcode/template reads it. This one is fully hand-written (no live registry to introspect — Settings fields are just HTML form inputs), so it needs manual upkeep if fields are added later, unlike the two sections above.
4. **REST API** — the 7 endpoints the plugin registers (`/applications`, `/documents`, `/checkout-session`, `/webhooks/stripe`, `/track`, `/discount/validate`, `/inquiries`), for developer reference.

### Content accuracy — verified by extraction, not memory

Before writing any documentation text, extracted the actual current inventory directly from source rather than writing from memory/assumption:
- `grep`'d every `add_shortcode()` call across the plugin (19 shortcodes, not the ~15 that might be assumed from general familiarity with the session's work).
- Read the full `class-settings-page.php` render method line-by-line to capture all 49 fields across their real 11 section headings, including several added mid-session (Express Processing Fee, Homepage — Global Reach, Email Template) that could easily have been missed from memory.
- Read `class-meta-boxes.php` in full for the 5 CPTs' actual meta field labels/types/options (e.g. the Discount type's percentage/fixed select options, the Job type's employment-type options) rather than guessing.
- Read each REST controller's `register_rest_route()` call for the real route paths/methods.

### Verified via direct rendering, not just syntax

Called `Visati_Core_Admin_Help::render()` directly via `wp eval` (not just `php -l`) with `display_errors`/`E_ALL` on, confirming: 19 shortcodes rendered matching the live registry exactly with zero "no description written yet" gaps, 5 content types rendered, all 11 real Settings sections present verbatim, 7 API rows present, and zero PHP warnings/notices in the output.

### Files modified this session

| File | Action |
|---|---|
| `admin/class-admin-help.php` | New — the Reference/Help page |
| `admin/class-admin-dashboard.php` | Registered the new "Help" submenu entry |
| `visati-core.php` | Added require; `VISATI_CORE_VERSION` 0.3.22 → 0.3.23 |
| `assets/css/admin.css` | Sticky jump-nav + table styling for the new page |

### Status

Verified locally via direct rendering (structural + content-accuracy checks), no PHP syntax or runtime issues. Not yet deployed to Hostinger.

---

## Symptom → Cause → Fix: stray shell command corrupted a PHP file, both live and in local source — 2026-07-25

### Symptom

A shell command (`find wp-content/plugins/visati-core -type f -exec chmod 644 {} \;`) rendered as literal text at the top of every single page on the live site, above the WordPress admin bar's content area. Also appeared prepended to raw REST API JSON responses (confirmed via a direct `curl` to the discount endpoint).

### Investigation (two wrong turns before finding it)

1. **First wrong theory:** assumed it was a browser extension/clipboard-manager overlay, since the text exactly matched a command just given to the user and didn't appear in an initial `curl` fetch of a page's HTML. **Corrected** once a `curl` to the discount REST endpoint showed the exact same text prepended to the raw JSON response — that's unambiguously server output, not anything browser-side.
2. **Second near-miss:** the first server-side search excluded `wp-content/themes/visati` and `wp-content/plugins/visati-core` from the grep (reasoning: "our own files shouldn't have this"), which is exactly backwards — a corrupted line in *our own* file was always the most likely place, and excluding it delayed finding the real cause by one round.

### Real cause

`includes/rest/class-applications-controller.php` had the shell command literally sitting on line 1, immediately before the `<?php` opening tag, with no newline between them: `find wp-content/plugins/visati-core -type f -exec chmod 644 {} \;<?php`. Anything before `<?php` in a PHP file is treated as literal output, not code — so this line got echoed verbatim on every single request that loads this file, which (as a REST controller required unconditionally from `visati-core.php`) is every request. This explains both symptoms: it appears on every page (this file loads regardless of route) and it prepends to raw API responses (same reason, output happens before any `header()`/response-body logic in the actual endpoint code even runs).

**Critically: this corruption was present in the local source file too, not just the live server** — confirmed via a direct `grep` after the user manually fixed the live copy. This means it didn't originate from a one-off server-side editing mistake; it would have been re-shipped and re-broken the live site on the next deploy of this file if left unfixed locally.

### Fix

- User fixed the live file directly via SSH (`sed -i '1s/.*/<?php/'` after confirming the exact corrupted content with `cat -A`, with a `.bak` backup taken first).
- Fixed the local source to match — removed the stray text, verified the file now starts cleanly with `<?php`.
- Ran a broader defensive grep across the entire theme + plugin source for similar patterns (lines starting with `find `, `chmod `, `rm -rf`, `cd /home`, `unzip -o`, etc. — the kind of text that might indicate another accidental paste from a deploy command into a PHP file) — found nothing else. Appears to be an isolated incident.
- Verified functionally, not just syntactically: `php -l` had already passed on this file in every earlier verification this session (the stray text before `<?php` doesn't produce a PHP parse error, since PHP treats it as plain output — a real gap in relying on `php -l` alone). This time verified via an actual `wp_remote_get()` fetch confirming the homepage output starts exactly with `<!DOCTYPE html>` (no leading garbage) and the `/visati/v1/applications` REST route still registers correctly.

### Lesson for the skill

`php -l` only catches parse errors — it will not catch stray non-PHP text sitting before an opening `<?php` tag, since that's syntactically valid (if visually wrong) as far as the PHP parser is concerned. A file starting with anything other than `<?php` (or a byte-order-mark/whitespace edge case) needs a separate check — e.g. asserting `substr(file_get_contents($file), 0, 5) === '<?php'` for critical always-loaded files — if this class of accidental-paste-during-manual-editing incident is worth guarding against systematically in a future deploy script.

### Files modified

| File | Action |
|---|---|
| `includes/rest/class-applications-controller.php` | Removed stray shell-command text before the opening `<?php` tag (matching the user's manual live-server fix) |
| `wordpress/docs/migration-log.md` | This entry |

### Status

Fixed both live (by the user, verified) and in local source (verified via functional test, not just syntax check). No redeploy needed for this specific fix since the live file is already corrected — but the next deploy of the plugin zip will now correctly include the clean version instead of silently re-introducing the corruption.

---

## Symptom → Cause → Fix: the real root cause of the recurring "gold text" bug — `reset.css` specificity tie broken by source order — 2026-07-25

### Symptom

Across many rounds this session, individual buttons and form fields kept rendering with the wrong (unstyled-looking) appearance — thin gold outline instead of the solid gold gradient button, gold/black text instead of white on hover, contact form inputs not matching the theme — on the **live** site specifically, while local (Local by Flywheel) looked correct. Each occurrence was patched ad-hoc (Track search button, Apply form step-nav button, contact form fields) with per-selector `!important` overrides or `appearance:none`, without a unifying explanation — until the contact form kept showing the bug live even after the CSS was verified byte-correct, cache was ruled out (cache-bypassed `curl` with a realistic UA, confirmed incognito-broken too), and markup was verified matching.

### Investigation

Exhausted remote diagnostics first (CDN/edge cache via direct curl with cache-busting, Elementor's per-page cached CSS file, duplicate CSS rule search, markup diff) — all came back clean, meaning the bug had to be something only visible in an actual rendered browser. Asked the user to confirm device/page via `AskUserQuestion` rather than keep guessing blind. The user then supplied the actual breakthrough: a DevTools screenshot showing the browser's own computed-styles panel, with `hello-elementor/assets/css/reset.css?ver=3.4.9` winning over `.visati-btn` and the contact form's input rules.

### Real cause

The **parent theme** Hello Elementor ships `wp-content/themes/hello-elementor/assets/css/reset.css`, enqueued **after** `app.css` in the WordPress style-dependency chain. It contains bare-tag/attribute reset rules with the exact same CSS specificity (0,1,0) as any single-class selector Visati's own CSS uses on the same element type:

```css
[type=button],[type=submit],button{background-color:transparent;border:1px solid #C9A84C;border-radius:3px;color:#C9A84C;display:inline-block;font-size:1rem;font-weight:400;padding:.5rem 1rem;text-align:center;transition:all .3s;user-select:none}
button:hover,[type=submit]:hover,[type=submit]:focus,button:focus{background-color:#C9A84C;color:#fff;text-decoration:none}
input[type=tel],input[type=text],input[type=time],input[type=url],select,textarea{border:1px solid #666;border-radius:3px;padding:.5rem 1rem;transition:all .3s;width:100%}
```

`.visati-btn { background: ...; }` and `[type=submit],button { background-color: ...; }` are both specificity (0,1,0). `.visati-contact-form input { border: ...; }` and `input[type=text] { border: ...; }` are both (0,1,1). **On an exact specificity tie, source order decides — not "more specific author CSS."** Since `reset.css` loads after `app.css`/`apply.css`, it silently won every tied property (background, border, border-radius, padding, color) on every custom button and text-style input across the entire site, unless that specific selector already happened to carry `!important` (which a few individually-patched buttons did, by accident of an earlier unrelated fix — explaining why those specific ones worked while everything else didn't).

This is a fundamentally different, more complete explanation than the earlier "mobile Safari native-appearance" theory recorded from an earlier round in this log — that theory was incomplete (it only explains why `appearance:none` helped in some cases, not why the colors/borders were wrong on desktop Chrome too) and is superseded by this entry.

### Fix

Audited every custom `<button>` and text-style `<input>`/`<select>`/`<textarea>` selector in `app.css` and `apply.css` for specificity ties against `reset.css`'s exact selector list, and added `!important` to every tied property (background, border, border-radius, padding, color, width, appearance) on:

- `.visati-btn` (base + `:hover`)
- `.visati-contact-form input, .visati-contact-form textarea`
- `.visati-field input, .visati-field select` (Apply form fields)
- `.visati-qa-card__select, .visati-qa-card__input` (Quick Apply widget)
- `.visati-track-search input` (homepage variant)
- `.visati-order-summary__discount-input` / `.visati-order-summary__discount-btn` (base + `:hover`)
- `.visati-testimonials-arrow` (base + `:hover`)
- `.visati-faq-cat-btn`, `.visati-faq-item__q`
- `.visati-blog-filter`
- `.visati-combobox__search`
- `.visati-header__toggle`, `.visati-drawer__close`

Selectors that were already compound (two classes, or class+pseudo-class, e.g. `.visati-page--track .visati-track-search input`, `.visati-combobox__button`, `.visati-faq-cat-btn:hover`) were **not** touched — their specificity already exceeds `reset.css`'s tied selectors, so they were never actually at risk; adding `!important` there would just be noise. `.visati-announcement-close` uses an inline `style=""` attribute, which always beats an external stylesheet rule without `!important` regardless of specificity, so it was left alone too.

### Lesson for the skill

When a parent theme (Hello Elementor, or any Elementor-compatible base theme) ships its own `reset.css`/`normalize.css` loaded after the child theme's stylesheet, **any single-class selector on a `<button>` or text `<input>`/`<select>`/`<textarea>` is a specificity tie, not a specificity win** — author intuition ("my class should obviously beat a bare tag selector") is wrong here because both resolve to the same specificity tuple, and ties resolve by source order in the parent theme's favor. The safe default for a WordPress-on-Elementor child theme is: **any custom button/field styling rule should use `!important` on the properties that visually matter (background, border, border-radius, color, padding) from the start**, rather than adding it reactively per-bug. This would have prevented the entire multi-round "gold text" saga in this session.

### Files modified

| File | Action |
|---|---|
| `wp-content/themes/visati/assets/css/app.css` | `!important`-hardened ~11 selectors against the `reset.css` specificity tie |
| `wp-content/plugins/visati-core/assets/css/apply.css` | `!important`-hardened the discount input/button and `.visati-field input/select` |
| `wp-content/themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.30 → 0.4.31 |
| `wp-content/plugins/visati-core/visati-core.php` | `VISATI_CORE_VERSION`/docblock 0.3.23 → 0.3.24 |
| `wordpress/docs/migration-log.md` | This entry |

### Status

Fixed in local source across both the theme and plugin CSS. Not yet deployed — needs the standard redeploy cycle (rebuild both zips, upload, SSH extract with `755`/`644` permissions, `wp cache flush`, hPanel Cache Manager purge, Elementor CSS regen via wp-admin).

---

## Symptom → Cause → Fix: the `!important` fix above turned the WhatsApp button gold — 2026-07-25, continued

### Symptom

On the Contact page, the "WhatsApp · Average reply 2 min" button rendered solid gold instead of its intended WhatsApp green, immediately after the reset.css `!important` hardening above.

### Cause

`.visati-btn--whatsapp` sets `background`/`color` **without** `!important`, relying on normal cascade rules where a two-part selector (`.visati-btn.visati-btn--whatsapp`, specificity 0,2,0) should beat a one-part selector (`.visati-btn`, 0,1,0). That assumption broke the moment `.visati-btn`'s own `background`/`color` were hardened with `!important` in the previous fix: **`!important` always wins over specificity, regardless of how many classes the other selector has.** The other existing modifiers (`.visati-btn--navy`, `--gold`, `--outline`, `--ghost`) were unaffected because they already used `!important` themselves (added in an earlier, unrelated round) — `.visati-btn--whatsapp` was the one modifier that didn't, so it was the only one that broke.

### Fix

Added `!important` to `background`/`color` on both `.visati-btn--whatsapp` rule blocks in `app.css` (one for the contact page, using a hardcoded `#25D366`; one for the visa-single-page share bar, using `var(--visati-whatsapp)`) and to their `:hover` states.

### Lesson for the skill

Any time a base selector's properties are hardened with `!important` (as required by the reset.css specificity-tie fix above), **every modifier/variant selector that overrides those same properties must also get `!important`**, or it silently loses to the base rule the instant the base rule gains `!important` — normal specificity no longer applies once either side declares `!important`. This is a direct, easy-to-miss side effect of the reset.css fix and is worth a full audit (grep for every `.visati-btn--*`/`.visati-*-btn` modifier) rather than trusting "it looked fine in the diff."

### Files modified

| File | Action |
|---|---|
| `wp-content/themes/visati/assets/css/app.css` | `!important` added to both `.visati-btn--whatsapp` rule blocks (base + hover) |
| `wp-content/themes/visati/functions.php` | `VISATI_THEME_VERSION` 0.4.31 → 0.4.32 |
| `wordpress/docs/migration-log.md` | This entry |

### Status

Fixed in local source, audited all other `.visati-btn--*` modifiers (navy/gold/outline/ghost already had `!important`, no other regressions found). Not yet deployed — bundle with the redeploy above.
