---

description: "Task list for the Visati WordPress migration (Next.js → WordPress/Elementor)"
---

# Tasks: Visati → WordPress Migration

**Input**: Design documents from `/specs/002-wp-migration/` (`plan.md`) + user stories from `/specs/001-visati-platform/spec.md` (P1–P10) + reference implementation in `/docs/wordpress-migration-plan.md` (Stripe/R2/DB/email/SEO — Appendix A of plan).
**Prerequisites**: plan.md ✅, spec stories ✅ (from 001), data-model/contracts via `docs/wordpress-migration-plan.md` §8/§19.
**Branch**: `002-wp-migration`
**Build environment**: Local by Flywheel → deploy to Hostinger subdomain.

**Tests**: Not requested. No test tasks generated (manual browser + WP-CLI verification per story's "Independent Test").

**Organization**: Tasks grouped by user story (US1–US10) so each story is independently implementable/testable. All code lives under `wordpress/wp-content/` (repo root Next.js app is untouched).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Parallelizable (different files, no dependency on an incomplete task)
- **[Story]**: US1–US10 (setup/foundational/polish have no story label)
- Exact file paths under `wordpress/wp-content/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Stand up the local WordPress environment, EMCP connection, and code scaffolds.

- [x] T001 Create `002-wp-migration` feature branch (done — confirm `git branch --show-current`)
- [x] T002 Install Local by Flywheel; create local site named `visati` (PHP 8.2+, MariaDB/MySQL 8); record site path + admin user (manual — document in `wordpress/README.md`)
- [x] T003 [P] Install plugins via WP-CLI in Local Site Shell: Elementor, Hello Elementor theme, EMCP (release ZIP from github.com/msrbuilds/elementor-mcp), Rank Math, WP Mail SMTP, Wordfence, LiteSpeed Cache, UpdraftPlus
- [x] T004 [P] Symlink `wordpress/wp-content/themes/visati` and `wordpress/wp-content/plugins/visati-core` into the Local site `app/public/wp-content/` (cmd in `wordpress/README.md`)
- [x] T005 [P] Wire EMCP WP-CLI stdio into project root `.mcp.json`; verify connection (`get-global-settings`) from Claude Code
- [x] T006 [P] Finalize child theme scaffold in `wordpress/wp-content/themes/visati/` (style.css header, functions.php, theme.json stub)
- [x] T007 [P] Scaffold plugin subfolders in `wordpress/wp-content/plugins/visati-core/` (`includes/`, `includes/rest/`, `includes/shortcodes/`, `admin/`, `admin/views/`, `templates/`, `templates/email/`, `assets/css/`, `assets/js/`)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Design system, global chrome, CPTs, custom tables, and shared service libraries that every user story depends on.

**⚠️ CRITICAL**: No user-story work begins until this phase is complete.

- [x] T008 [P] Define design tokens in `wordpress/wp-content/themes/visati/theme.json` (Navy `#0A1628`, Gold `#C9A84C`, Blue, Mist `#F8F9FB`; font families Inter + Plus Jakarta Sans/Sora for headings, JetBrains Mono)
- [x] T009 [P] Enqueue compiled `wordpress/wp-content/themes/visati/assets/css/app.css` + Google Fonts in `wordpress/wp-content/themes/visati/functions.php`
- [x] T010 [P] Register nav menus + theme supports in `wordpress/wp-content/themes/visati/functions.php`
- [x] T011 Register site Options page (contact, social, header/footer, announcement, tracking, reviews) in `wordpress/wp-content/plugins/visati-core/includes/class-options.php`
- [x] T012 Build global header in `wordpress/wp-content/themes/visati/parts/header.php` (nav + announcement slot)
- [x] T013 Build global footer in `wordpress/wp-content/themes/visati/parts/footer.php` (+ WhatsApp widget slot)
- [x] T014 [P] Build WhatsApp widget in `wordpress/wp-content/themes/visati/template-parts/whatsapp-widget.php` (deep-link from Options)
- [x] T015 [P] Build announcement bar in `wordpress/wp-content/themes/visati/template-parts/announcement-bar.php` (cookie-dismiss + end-date)
- [x] T016 Register CPTs (`visati_visa_type`, `visati_faq`, `visati_testimonial`, `visati_job`, `visati_discount`, all `show_in_rest`) in `wordpress/wp-content/plugins/visati-core/includes/class-post-types.php`
- [x] T017 [P] Register `visati_consultant` role + capability map in `wordpress/wp-content/plugins/visati-core/includes/class-roles.php`
- [x] T018 Create custom tables on activation via `dbDelta` (`applications`, `status_history`, `documents`, `payments`, `inquiries`) in `wordpress/wp-content/plugins/visati-core/includes/class-database.php` (DDL from `docs/wordpress-migration-plan.md` §19)
- [x] T019 [P] Implement Cloudflare R2 storage helper (S3-compatible upload / presigned URL / delete) in `wordpress/wp-content/plugins/visati-core/includes/class-r2-storage.php` (ref `docs/wordpress-migration-plan.md` §10)
- [x] T020 [P] Implement Stripe helper (Checkout Session create + webhook signature verify) in `wordpress/wp-content/plugins/visati-core/includes/class-stripe-payments.php` (ref §9)
- [x] T021 [P] Implement email notifications (Resend over WP Mail SMTP, 6 templates) in `wordpress/wp-content/plugins/visati-core/includes/class-email-notifications.php` (ref §13)
- [x] T022 [P] Implement REST API bootstrap (namespace `visati/v1`, base controller, permission helpers) in `wordpress/wp-content/plugins/visati-core/includes/class-rest-api.php`
- [x] T023 [P] Implement helpers (input validation/sanitization, formatting, `VIS-YYYY-XXXXXX` generator) in `wordpress/wp-content/plugins/visati-core/includes/helpers.php` (ref §8)
- [x] T024 Wire main plugin class to autoload includes + activation hook in `wordpress/wp-content/plugins/visati-core/visati-core.php`

**Checkpoint**: Foundation ready — tokens, chrome, CPTs, tables, and service libs in place. User-story work can begin.

---

## Phase 3: User Story 1 — Visa Application Submission (Priority: P1) 🎯 MVP

**Goal**: Applicant completes a 5-step form, uploads documents to R2, and a draft application (`VIS-YYYY-XXXXXX`) is created. (Payment is US2.)
**Independent Test**: Complete Steps 1–4 → a row exists in `visati_applications` (status `draft`), uploaded files exist in R2 with metadata in `visati_documents`, app ID generated.

### Implementation for User Story 1

- [x] T025 [P] [US1] Implement application CRUD/query class in `wordpress/wp-content/plugins/visati-core/includes/class-application.php` (depends T018)
- [x] T026 [US1] REST `POST /applications` (create draft, validate via helpers, generate ID) in `wordpress/wp-content/plugins/visati-core/includes/rest/class-applications-controller.php`
- [x] T027 [P] [US1] REST `POST /documents` (validate PDF/JPG/PNG ≤5MB, upload to R2, store metadata) in `wordpress/wp-content/plugins/visati-core/includes/rest/class-documents-controller.php` (depends T019)
- [x] T028 [US1] Build apply page template + `[visati_apply_form]` shortcode in `wordpress/wp-content/plugins/visati-core/templates/page-apply.php`
- [x] T029 [US1] Build 5-step wizard JS in `wordpress/wp-content/plugins/visati-core/assets/js/application-form.js` (step nav, client validation, doc upload, review summary)
- [x] T030 [US1] Build apply form styles in `wordpress/wp-content/plugins/visati-core/assets/css/apply.css`
- [x] T031 [US1] Build Step 5 success renderer (reads `?success=1&id=`, shows app ID) in `templates/page-apply.php`

**Checkpoint**: Applicant can fill, upload, and submit a draft application (no payment yet).

---

## Phase 4: User Story 2 — Stripe Payment (Priority: P2)

**Goal**: On submit, applicant pays via Stripe Checkout; webhook moves status → `submitted`, records payment, sends confirmation email.
**Independent Test**: After US1 draft → "Pay Now" → Stripe test card → webhook → `applications.status = submitted`, `payments` row created, confirmation email delivered; duplicate webhook is a no-op.

### Implementation for User Story 2

- [x] T032 [US2] Create Stripe Checkout Session endpoint (price from visa CPT + Express +AED99) in `wordpress/wp-content/plugins/visati-core/includes/class-stripe-payments.php` (depends T020, T026)
- [x] T033 [US2] REST `POST /webhooks/stripe` (verify signature, set status `submitted`, insert payment, append `status_history`, send confirmation + admin-alert; idempotent on PI id) in `wordpress/wp-content/plugins/visati-core/includes/rest/class-webhooks-controller.php` (depends T021)
- [x] T034 [US2] Handle `payment_failed` (status stays `draft`, user sees retry) in `class-stripe-payments.php`
- [x] T035 [US2] Expose Stripe test keys + webhook secret via plugin settings; register local Live-Link/ngrok webhook URL for test mode

**Checkpoint**: Full apply → pay → confirm flow works end-to-end. **(MVP reached.)**

---

## Phase 5: User Story 3 — Application Tracking (Priority: P3)

**Goal**: Applicant enters app ID on `/track` and sees the status timeline (Submitted → Under Review → Processing → Decision).
**Independent Test**: Seed an application with multiple `status_history` rows → `/track?id=` renders ordered timeline with latest note; invalid ID → "not found"; vertical on mobile.

### Implementation for User Story 3

- [x] T036 [P] [US3] REST `GET /track?id=` (regex-validate ID, return timeline + current status + latest note) in `wordpress/wp-content/plugins/visati-core/includes/rest/class-track-controller.php`
- [x] T037 [US3] Build track page + `[visati_track]` shortcode in `wordpress/wp-content/plugins/visati-core/templates/page-track.php`
- [x] T038 [US3] Build tracking JS in `wordpress/wp-content/plugins/visati-core/assets/js/tracking.js` (timeline render, approved/rejected chips, mobile-vertical layout)

**Checkpoint**: Public tracking works independently.

---

## Phase 6: User Story 4 — Visa Types Browsing (Priority: P4)

**Goal**: Visitor browses/filterable visa cards and a top-3 comparison table; "Apply Now" routes to `/apply?visa=<slug>`.
**Independent Test**: `/visa-types` shows all active visas with live AED prices; duration/entry/price filters + sort work; comparison table renders; Apply routes correctly.

### Implementation for User Story 4

- [x] T039 [P] [US4] Add ACF/fields for `visati_visa_type` CPT (entry_type, duration_days, standard_price_aed/usd, has_express, features, badge, is_featured) in `wordpress/wp-content/plugins/visati-core/includes/class-post-types.php`
- [x] T040 [US4] Build `[visati_visa_cards]` shortcode (query active visas, render cards) in `wordpress/wp-content/plugins/visati-core/includes/shortcodes/class-visa-cards.php`
- [ ] T041 [US4] Build visa-types page via EMCP (Elementor shell + filter UI + shortcode + comparison table) — page created with EMCP `build-page`
- [x] T042 [US4] Build filter/sort JS in `wordpress/wp-content/plugins/visati-core/assets/js/visa-filter.js`
- [x] T043 [US4] Seed 8 visa-type CPT entries via custom `wp visati seed visas` command in `wordpress/wp-content/plugins/visati-core/includes/class-cli.php`

**Checkpoint**: Visa catalogue browsable with live, admin-editable pricing.

---

## Phase 7: User Story 5 — Home Page & Quick Apply (Priority: P5)

**Goal**: Home page renders hero, trust signals, Quick Apply widget, visa strip, process steps, global reach, testimonials, FAQ, CTA.
**Independent Test**: `/` renders all sections; Quick Apply (nationality + visa) → opens `/apply` pre-filled; prices accurate from CPT.

### Implementation for User Story 5

- [x] T044 [P] [US5] Build `[visati_quick_apply]` widget/shortcode (nationality + visa select → deep-link to `/apply`) in `wordpress/wp-content/plugins/visati-core/includes/shortcodes/class-quick-apply.php`
- [ ] T045 [US5] Build homepage via EMCP `build-page` on `front-page` (hero + quick-apply + `[visati_visa_cards]` + process + why-choose + global-reach + `[visati_testimonials]` + `[visati_faq]` + CTA)
- [ ] T046 [US5] Build global-reach dotted-map section JS in `wordpress/wp-content/themes/visati/assets/js/global-reach.js`

**Checkpoint**: Home page complete (depends on US4 + US9 for embedded shortcodes).

---

## Phase 8: User Story 6 — Admin Application Management (Priority: P6)

**Goal**: Admin sees dashboard stats, applications list, detail view with documents, and can update status (appending history + emailing applicant).
**Independent Test**: Log in as admin → dashboard stats correct; open application → documents open via 15-min signed R2 URL; change status → new `status_history` row appended (old untouched) + applicant email sent.

### Implementation for User Story 6

- [x] T047 [US6] Admin menu + dashboard (stat cards: total/pending/approved-30d/rejected-30d) in `wordpress/wp-content/plugins/visati-core/admin/class-admin-dashboard.php` + `admin/views/dashboard.php`
- [x] T048 [US6] Applications list table (paginated, status filter) in `wordpress/wp-content/plugins/visati-core/admin/class-admin-applications.php` + `admin/views/applications-list.php`
- [x] T049 [US6] Application detail view + document viewer (signed R2 URLs, 15-min TTL) in `admin/views/application-detail.php`
- [x] T050 [US6] Status-update handler (append-only `status_history`, email applicant) in `class-admin-applications.php` (depends T021)

**Checkpoint**: Staff can process applications end-to-end.

---

## Phase 9: User Story 7 — Admin Inquiry Management (Priority: P7)

**Goal**: Admin lists contact inquiries, reads them, marks resolved.
**Independent Test**: Submit a contact inquiry (US8) → appears in admin Inquiries → mark resolved → chip updates.

### Implementation for User Story 7

- [x] T051 [US7] Inquiries list + detail + resolve toggle (capability-gated) in `wordpress/wp-content/plugins/visati-core/admin/class-admin-inquiries.php` + `admin/views/inquiries-list.php` + `admin/views/inquiry-detail.php`

**Checkpoint**: Inquiries manageable (depends on US8 producing rows, or seeding).

---

## Phase 10: User Story 8 — Contact & WhatsApp (Priority: P8)

**Goal**: Contact form saves an inquiry + emails admin; WhatsApp deep-link button.
**Independent Test**: Submit contact form → row in `visati_inquiries` + admin email; missing field → inline error; WhatsApp button opens `wa.me`.

### Implementation for User Story 8

- [x] T052 [P] [US8] Build `[visati_contact_form]` shortcode (validate, insert inquiry, admin email) in `wordpress/wp-content/plugins/visati-core/includes/shortcodes/class-contact-form.php` (depends T021)
- [ ] T053 [P] [US8] Build contact page via EMCP (Elementor shell + shortcode + Options-driven contact info sidebar + map embed)
- [x] T054 [P] [US8] Build WhatsApp deep-link button (Options number/text) in `wordpress/wp-content/themes/visati/template-parts/whatsapp-widget.php` (extend T014)

**Checkpoint**: Contact + WhatsApp conversion paths work.

---

## Phase 11: User Story 9 — FAQ Page (Priority: P9)

**Goal**: FAQ page with search, category sidebar, popular tags, single-open accordion, CTA.
**Independent Test**: `/faq` search filters live; category scroll + active highlight; one accordion open at a time; new FAQ CPT entry appears after revalidation.

### Implementation for User Story 9

- [x] T055 [P] [US9] Add FAQ CPT fields (answer, category, sort_order) in `class-post-types.php`
- [x] T056 [US9] Build `[visati_faq]` shortcode (search + categories + accordion) in `wordpress/wp-content/plugins/visati-core/includes/shortcodes/class-faq.php`
- [x] T057 [US9] Build FAQ page via EMCP (Elementor + shortcode + CTA) + JS in `wordpress/wp-content/plugins/visati-core/assets/js/faq.js`
- [x] T058 [US9] Seed FAQ items via `wp visati seed faq` in `class-cli.php`

**Checkpoint**: FAQ self-service reduces support load.

---

## Phase 12: User Story 10 — CMS Content Self-Management (Priority: P10)

**Goal**: Client edits homepage/section copy, testimonials, visa descriptions, SEO — all from WP admin without code.
**Independent Test**: Edit a homepage section via Options/ACF → reflects on site; edit testimonial → updates; Yoast/Rank Math title+meta render.

### Implementation for User Story 10

- [x] T059 [P] [US10] Options fields for homepage section copy (hero, why-choose, process, CTA) in `class-options.php`
- [x] T060 [P] [US10] ACF field groups for `visati_testimonial` (country, rating, text) + visa descriptions in `class-post-types.php`
- [x] T061 [US10] Build `[visati_testimonials]` shortcode (carousel) in `wordpress/wp-content/plugins/visati-core/includes/shortcodes/class-testimonials.php`
- [x] T062 [US10] Seed testimonials via `wp visati seed testimonials` in `class-cli.php`
- [x] T063 [US10] Configure Rank Math/Yoast per-CPT titles + sitemap + JSON-LD (Organization, FAQPage, LocalBusiness) in `wordpress/wp-content/themes/visati/inc/seo.php`

**Checkpoint**: Client owns all content editing.

---

## Phase 13: Polish & Cross-Cutting Concerns

**Purpose**: Tracking, reviews, QA, deploy.

- [x] T064 [P] Tracking injection (GA4/GTM/FB/TikTok) from Options → `wp_head`/`wp_footer` in `wordpress/wp-content/plugins/visati-core/includes/class-tracking.php`
- [x] T065 [P] Wire announcement bar + review badges (Trustpilot/Google) to Options
- [x] T066 Security review via `wp-security-review` skill (nonces, capability checks, escaping, R2 private bucket, Stripe webhook signature, secrets in `wp-config.php`)
- [x] T067 Performance review via `wp-performance-review` skill (queries, object cache, LiteSpeed, autoloaded options)
- [x] T068 Accessibility review via `wp-accessibility-review` skill (a11y ≥ 90 on public pages)
- [x] T069 Responsive QA across all public pages (mobile-first) + admin desktop-only
- [ ] T070 Deploy to Hostinger subdomain (plan Phase 6): git push theme+plugin → install EMCP + companions → migrate DB+media (`wp search-replace`) → EMCP `restore-content` → Stripe live keys + webhook → SSL + Wordfence + UpdraftPlus
- [ ] T071 Final smoke test on subdomain: apply → Stripe (live/test) → webhook → confirmation email; tracking page; admin status update
- [ ] T072 [P] Client handoff docs + short Loom tutorials in `wordpress/docs/`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no deps — start immediately (T002 is manual/local).
- **Foundational (Phase 2)**: depends on Setup — **BLOCKS all stories**.
- **User Stories (Phase 3–12)**: depend on Foundational; then run in priority order or parallel where independent.
- **Polish & Deploy (Phase 13)**: depends on desired stories being complete.

### User Story Dependencies

- **US1 (P1)**: after Foundational (needs T018 tables, T019 R2, T023 helpers).
- **US2 (P2)**: depends on **US1** (application must exist) + Foundational Stripe/email.
- **US3 (P3)**: depends on US1 data (or seed) + Foundational.
- **US4 (P4)**: after Foundational — **independent of US1**.
- **US5 (P5)**: depends on **US4** (visa cards) + **US9** (faq) + US10 (testimonials).
- **US6 (P6)**: depends on **US1** (applications to manage).
- **US7 (P7)**: depends on **US8** (inquiries) or seeded inquiries.
- **US8 (P8)**: after Foundational — independent.
- **US9 (P9)**: after Foundational — independent.
- **US10 (P10)**: after Foundational (Options/ACF) — independent; **enables US5** content.

### Within Each User Story

Models/CPTs/tables before services → services before REST endpoints → REST before shortcode/widget/template → core before integration.

### Parallel Opportunities

- Phase 1: T003–T007 are parallel (distinct setup concerns).
- Phase 2: T008–T023 are largely `[P]` (distinct files) once the main class (T024) interface is agreed.
- After Foundational, these story groups run in parallel: **{US1, US4, US8, US9, US10}** → then {US2, US3, US6, US7} → then {US5} → Polish/Deploy.

---

## Parallel Example: Post-Foundational Wave

```text
# Launch these independent stories together (different files, no cross-deps):
Task: "[US1] application CRUD + REST + apply template"
Task: "[US4] visa CPT fields + [visati_visa_cards] shortcode + catalogue page"
Task: "[US8] [visati_contact_form] shortcode + contact page + WhatsApp"
Task: "[US9] FAQ CPT fields + [visati_faq] shortcode + page"
Task: "[US10] Options homepage copy + testimonial ACF + [visati_testimonials]"
```

---

## Implementation Strategy

### MVP First (Foundational + US1 + US2)

1. Complete **Phase 1** (Setup) — local WP + EMCP + scaffolds.
2. Complete **Phase 2** (Foundational) — **CRITICAL**, blocks all stories.
3. Complete **Phase 3** (US1) — application draft + document upload.
4. Complete **Phase 4** (US2) — Stripe payment + webhook + confirmation.
5. **STOP and VALIDATE**: full apply → pay → confirm flow works.
6. Deploy early build to Hostinger subdomain for client preview.

### Incremental Delivery

7. Add **US4** (catalogue) + **US8** (contact) + **US9** (FAQ) + **US10** (content) — independent, parallelizable.
8. Add **US3** (tracking) + **US6** (admin) + **US7** (inquiries).
9. Add **US5** (home page) — integrates the shortcodes above.
10. Polish: tracking, security/perf/a11y reviews, responsive QA, final deploy + smoke test.

### Parallel Team Strategy

With multiple streams after Foundational: Stream A → US1+US2+US6 (application spine); Stream B → US4+US5 (catalogue/home); Stream C → US8+US9+US10 (content/contact); Stream D → US3+US7 (tracking/inquiries).

---

## Notes

- **MVP scope** = Foundational (Phase 2) + US1 + US2 — the apply→pay→confirm revenue loop.
- All file paths are under `wordpress/wp-content/`; the Next.js repo root is never touched.
- Invoke the mapped `wp-*` skill before each implementation area (constitution §IV) — see `plan.md` "Claude Code build workflow".
- Reference `docs/wordpress-migration-plan.md` (Appendix A) for Stripe/R2/DB/email specifics — do not re-derive.
- Commit after each task or logical group; mark `- [x]` as tasks complete.
- EMCP `list-changes`/`rollback-change` is the safety net while building Elementor pages.
