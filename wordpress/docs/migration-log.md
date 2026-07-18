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
- [ ] Phases 3–12 (US1–US10), Phase 13 (polish/deploy).

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
