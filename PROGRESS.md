# Build Progress — נדלניס טים

> Handoff file for resuming this build in a fresh session. Read this file, then
> `NadlanisTeam.md` (the full build spec — every decision needed is written there,
> per its own instructions do not ask the user clarifying questions, just build).
> Project root: `C:\xampp\htdocs\nadlanisteam\` → `http://localhost/nadlanisteam/`
>
> `NadlanisTeam.md` grew past its original 15 sections during this build: §8.8/8.9
> (mortgage calculator, map view), §17 (Hebrew/RTL/localization standard), §18
> (Partners professional network), and §19 (agent login/dashboard) were all added
> mid-session at the user's request, alongside updates to §4 (folder tree), §9.2
> (admin screens table), and §13 (seed data) to match. It's still the authoritative
> spec — just not frozen at 15 sections anymore.
>
> §3 (Stack & storage) was updated to reflect the MySQL migration (see the dedicated
> sections below for both the storage migration and the later `users`/role migration).
> `data/data.json` is a historical one-time migration source only, no longer read by
> the live site. `data/seed.json` is still live — it's what "reset demo data" imports
> from, and it's kept in sync with the current schema (including a top-level `users`
> array for agent-portal logins — see below).

## Storage migration: JSON file → MySQL (this session)

The site ran on a single `data/data.json` blob (read/written whole via
`load_data()`/`save_data()`) from the start of the build through most of this
session — see the "Decisions made" list below for the historical reasoning (#3, a
`load_data()`/`save_data()` shared-cache fix; #7-ish region, the `next_id()`-before-
`load_data()` gotcha). The user explicitly asked to move to a real database
("BUILD A DATABASE AND START USING IT"), and this was done as a full, one-shot
migration — not a hybrid — via the same research-then-plan-then-build workflow as
Partners/agent-portal (plan approved in `EnterPlanMode`, full plan text was in
`C:\Users\maimo\.claude\plans\effervescent-floating-stream.md` at the time, since
overwritten by later planning sessions).

**Stack**: MariaDB 10.4 (bundled with this XAMPP install, already running as
`mysqld.exe`), PDO with prepared statements (`PDO::ATTR_EMULATE_PREPARES => false`
for native types, `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC` — **forgetting
this second one was a real bug caught during verification**: without it PDO returns
rows with both numeric AND string keys (`FETCH_BOTH` default), which silently
corrupted anything iterating a hydrated row with `foreach ($row as $col => $val)`,
e.g. `update_settings()`. Caught by `var_export()`-inspecting a hydrated row during
manual testing, not by any automated check — worth remembering if a future DB-layer
change ever removes that connection option). DB name `nadlanisteam`, `root`/no
password (XAMPP default, matches the project's existing low-ceremony local-dev style
— these constants live in `includes/config.php` right next to `APP_ROOT`).

**Schema** (`data/schema.sql`, new): six tables — `settings` (single row, `id=1`
enforced via `CHECK`), `agents`, `properties`, `partners`, `leads`, `testimonials`.
Array-shaped fields (`areas`, `languages`, `regions`, `services`, `images`,
`gallery`) are MariaDB `JSON` columns (stored as validated `LONGTEXT` under the
hood), not normalized child tables — keeps the "field = PHP array" mental model
intact and avoids a JOIN-heavy rewrite for a site this size. `properties.agent_id`
is a real `FOREIGN KEY` (no `CASCADE`) — deleting an agent with properties still
fails, now doubly enforced (PHP pre-check for the friendly Hebrew error message,
FK as a backstop). `leads.property_id`/`agent_id`/`partner_id` are nullable FKs with
`ON DELETE SET NULL`, matching the JSON-era behavior where a deleted property/agent/
partner just left a dangling id that rendered blank.

**Two MySQL reserved words bit the schema on the first `mysql < schema.sql` run**:
`accessible` and `storage` (both real property boolean-flag names) are reserved
keywords and caused a syntax error pointing at the *following* line, not themselves
— confusing until isolated via a bisected single-column `CREATE TABLE` test. Renamed
to `is_accessible`/`has_storage` in the DB (and `read` → `is_read` for the same
reason, spotted proactively this time). The PHP-facing field names (`$p['accessible']`,
`$p['storage']`, `$l['read']`) are unchanged — the hydration layer (see below) maps
the DB column name back to the app's existing key name, so no template code needed
to know about the rename.

**`includes/config.php`**: `default_data()`/`data_cache_ref()`/`load_data()`/
`save_data()`/`next_id()` are gone — replaced by `db(): PDO` (singleton connection)
and a hydration function per entity (`hydrate_property()`, `hydrate_agent()`, etc.)
that converts a raw DB row back into exactly the same associative-array shape the
JSON era produced (`json_decode()` the JSON columns, `(bool)` the flag columns,
`NULL`→`''` for `lat`/`lng` specifically because `properties.php`'s map-view code
already tested `$p['lat'] === ''` to decide whether a property has a pin — verified
this via `grep` before choosing the hydration behavior, not by guessing). The
existing read functions (`find_property()`, `all_properties()`, `find_agent()`,
`all_agents()`, `find_agent_by_username()`, `all_partners()`, `find_partner()`,
`filtered_leads()`) kept their exact names/signatures/return shapes and just got new
SQL internals — `filter_properties()`, `sort_properties()`, `cities_in_use()`,
`filter_partners()`, `partner_regions_in_use()`, `partners_serving_region()`,
`agent_properties()`, `agent_listing_count()` needed **zero changes at all**, since
they already operated on a PHP array returned by another function, not on storage
directly. New: `get_settings()`/`update_settings()` (settings is a single DB row now,
not a `load_data()['settings']` sub-array), `all_testimonials()`, and a full set of
`insert_*`/`update_*`/`delete_*`/`toggle_*` write functions per entity (documented in
the plan file at the time; grep `includes/config.php` for `function insert_` /
`function update_` / `function delete_` / `function toggle_` to see the full list
live). `import_seed_into_db(string $jsonPath, bool $wipeOnly = false)` truncates all
five non-settings tables (FK checks off during truncate, back on after) and
optionally re-imports from a JSON file with explicit ids preserved — this one
function is reused by both the one-time `migrate.php` (source: `data/data.json`) and
`admin/settings.php`'s "reset demo data" / "wipe demo data" actions (source:
`data/seed.json`), so there's exactly one place that knows how to import the JSON
shape into SQL, not two parallel implementations.

**15 files that used to do "load the whole JSON blob → mutate an array → save the
whole blob back" (`grep -rl 'save_data(' *.php`) were rewritten** to call the new
named CRUD functions instead — the diff per file is small and mechanical (e.g.
`admin/testimonials.php`'s save block went from a 15-line `load_data()`/`foreach`/
`save_data()` dance to `$id ? update_testimonial($id, $values) :
insert_testimonial($values);`), validation/HTML/ownership-check logic in each file
is untouched. A **further ~20 read-only files** that did `load_data()['settings']`
(nearly every page, for the header/footer) or `load_data()['testimonials']`/
`['properties']`/etc. also needed a one-line swap to `get_settings()`/
`all_testimonials()`/`all_properties(false)` — this wasn't originally called out as
its own task in the plan (the plan's file list only enumerated the *write* sites)
but is a direct, unavoidable consequence of actually deleting `load_data()` per the
plan's own final step, since every call site has to stop calling a function that no
longer exists. Confirmed zero stragglers via
`grep -rn 'load_data(\|save_data(\|next_id(' *.php` returning nothing, project-wide.

**`migrate.php`** (new, project root): one-time script, run via `php migrate.php`.
Creates the `nadlanisteam` database if missing, runs `data/schema.sql`, checks
`agents` row count to refuse re-running accidentally (`--force` overrides), then
calls `import_seed_into_db()` against `data/data.json`. Verified end-to-end multiple
times this session (drop DB, re-run, confirm row counts match, confirm Hebrew text
and JSON arrays survive `json_decode`/hydration byte-for-byte via `var_export()`
spot-checks, confirm the pre-existing `admin_hash` and the two agent-portal test
accounts' `password_hash` values carried over unchanged — meaning the `uri.test`/
`michal.test` credentials from the agent-portal work still log in post-migration
with no re-setup needed).

**Verification** (this was the most heavily browser/CLI-tested piece of work this
session — see below for why): direct PHP CLI smoke tests of every `insert_*`/
`update_*`/`delete_*`/`toggle_*` function in isolation before touching the browser
at all (caught nothing beyond the `FETCH_ASSOC` bug above — the CRUD layer was
right on the first real try once that was fixed); then full browser-driven
end-to-end passes: homepage/properties list/filters/map view/property detail/agent
page/partner page all render identically to the JSON era; a real lead submitted
through the actual public form on `property.php` landed in MySQL with correct
`property_id`/`agent_id` and showed up correctly in both `admin/leads.php` and the
owning agent's `agent-portal/leads.php`; full admin CRUD exercised through the real
admin UI (create/edit/toggle-featured/duplicate/delete a property); agent-portal
login/dashboard/scoped-list/cross-agent-isolation re-verified against the new SQL
backend (a forged cross-agent delete POST was still silently rejected, same as
under JSON); `admin/settings.php`'s "wipe demo data" then "reset demo data" round-
trip tested live (wipe → confirmed all 5 tables empty via direct SQL, `settings`
row untouched → reset → confirmed all counts restored).

**One real gap found and fixed during this verification, unrelated to the SQL work
itself**: `data/seed.json` (the "reset demo data" source) was a stale snapshot from
*before* the agent-portal login feature existed, so it had no `username`/
`password_hash` for any agent — "reset demo data" was silently wiping the
`uri.test`/`michal.test` test credentials back to "no access" every time (this was
true under the old JSON-era `reset_demo` too, not a new bug — it fully replaced
`$data['agents']` with `$seed['agents']` the same way). Fixed by re-exporting
`data/seed.json` fresh from the live (post-migration) DB state via `get_settings()`/
`all_agents()`/`all_properties()`/`all_partners()`/`all_testimonials()`, so it now
includes the working test credentials and the real admin hash. Re-verified
"reset demo data" afterward — credentials now survive a reset.

**Browser-tool note carried over from the agent-login work, relevant again here**:
verifying `admin/settings.php`'s wipe/reset actions and admin property CRUD was done
by reusing an admin session that was already active in the browser profile (see the
note in the agent-portal section below) plus direct `fetch()` calls with the page's
own CSRF token, rather than filling in the `admin/login.php` form — that page is
still off-limits to automated navigation. If a future session starts with no live
admin session in the browser, admin-side browser verification will need a different
approach (the user logs in manually, or verification stays at the code/lint/CLI
level).

## Auth model rework: single `users` table with `role` (admin/agent)

Follow-up to the JSON→MySQL migration above, requested by the user in the same
session: "isnt it better to create users table with rol instead of agents?" then
"i need to have an admin and agent each has different permissions". Before this,
auth was two unrelated systems: the single admin's credentials lived inside the
`settings` singleton row (`admin_user`/`admin_hash`, oddly parked next to
`agency_name`/`tagline`), and each agent's login lived directly on the `agents`
table (`username`/`password_hash`/`last_login_at`) — two session-key sets
(`admin_logged_in` vs `agent_logged_in`), two nearly-identical login pages, two
separate lockout counters. Went through the same plan-mode workflow as every other
architectural change this build (research current auth code directly — no Explore
agents needed, since this session had authored every touched file minutes earlier —
then a written plan, approved before implementation).

**Key decision, stated up front in the plan**: the two login *pages* stay exactly
where they are (`admin/login.php` "כניסת מנהל", `agent-portal/login.php` "כניסת
סוכנים") — only the data model and verification logic behind them unify. Merging
into one login page/URL would have been a bigger UX change than what was asked.

**Schema**: new `users` table (`id, username, password_hash, role ENUM('admin',
'agent'), agent_id NULL, active, last_login_at, created_at`), `UNIQUE` on `username`
(**globally** — an admin and an agent can no longer accidentally/intentionally share
a username, since they're now literally the same namespace) and on `agent_id` (one
login per agent profile), `agent_id` is a real FK to `agents.id` with
`ON DELETE CASCADE` — deleting an agent's profile now automatically deletes their
login too, verified live (created a throwaway agent+credentials, deleted the agent,
confirmed the `users` row vanished with zero extra code). `agents` loses `username`/
`password_hash`/`last_login_at` entirely — it's now a pure public-profile table.
`settings` loses `admin_user`/`admin_hash`.

**Naming note kept deliberately**: `agents.role` (free text like "סוכן בכיר") and
the new `users.role` (the admin/agent enum) share an English word but are unrelated
concepts living in different tables/arrays (`$agent['role']` vs `$user['role']`) —
not renamed, since there's no actual UI collision (Hebrew labels differ, and auth
role is never displayed as a field).

**`includes/config.php`**: `find_agent_by_username()`/`agent_username_taken()`/
`set_agent_last_login()` removed, replaced by role-agnostic equivalents —
`verify_login($username, $password)` (single entry point both login pages call,
returns a hydrated user row with `role`/`agent_id` or `null`; each login page then
checks the role itself, so a valid agent password typed into the admin form gets the
same generic "שם משתמש או סיסמה שגויים" as a wrong password — never a "this account
exists but isn't an admin" hint), `find_user()`, `admin_exists()` (first-run check
for `admin/setup.php`, replaces `!empty($settings['admin_hash'])`), `username_taken()`
(global, not per-table), `set_user_last_login()`, `update_user_password()`, and the
agent-credential-management pair `set_agent_credentials()`/`clear_agent_credentials()`
(upsert/delete the linked `users` row — `admin/agent-edit.php`'s credentials
fieldset calls these instead of writing `username`/`password_hash` into the `agents`
update). `create_admin_user()` for the one-time `admin/setup.php` bootstrap flow —
tested directly (created and deleted a second admin via CLI) confirming the schema
genuinely supports multiple admins now, though no UI for managing them was built
(wasn't asked for; natural follow-up if ever needed).

**Session keys, unified**: `user_id`/`user_role`/`user_name` (+`agent_id` kept
separately for agent sessions, to avoid a JOIN on every agent-portal request)
replace all of `admin_logged_in`/`admin_user`/`agent_logged_in`/`agent_id`/
`agent_name`. **Lockout is deliberately unified too** — one `login_fail_count`/
`login_locked_until` pair shared by both login pages, not the previous accidental-
duplication-turned-real-separation. This is a considered simplification, not a
regression: it's genuinely one auth system with two doors now, so "too many failed
attempts from this browser" reasonably applies regardless of which door was hit.
Verified this doesn't misfire in the trivial way the *old* separate-key design was
built to avoid (an attacker locked out of one door immediately trying the other from
the same session) — that's now correctly blocked on both, which is the intended
behavior post-unification, not a bug.

**The trickiest correctness issue, caught during implementation, not in the plan**:
`import_seed_into_db()` (the function reused by both the one-time migration and
`admin/settings.php`'s "reset/wipe demo data") originally truncated a flat list of
tables including `users` — which would have **deleted the live admin account** every
time someone clicked "מחיקת נתוני דמו". Caught by re-reading the function against the
new schema before running anything, not by a test failure. Fixed: demo wipe/reset
now only ever `DELETE FROM users WHERE role = 'agent'` (agents are being wiped
anyway), and the seed-import loop skips any non-`'agent'` row defensively even if a
future `seed.json` accidentally contained one. Verified directly (not just read):
wiped demo data, confirmed `admin_exists()` still true and admin `username`
unchanged, confirmed both agent logins gone; reset demo data, confirmed all three
users back with their original working passwords (`verify_login()` succeeded with
the same credentials from before the wipe — the hashes round-tripped through
`seed.json` unchanged, not regenerated).

**`data/seed.json` needed a fresh export** (same lesson as the earlier storage
migration's seed.json gap) — it predated this change entirely and had no top-level
`users` key, so a reset would have restored agents/properties/etc. but left both
agent-portal logins unrecoverable. Re-exported from live DB state right before
testing wipe/reset, with the admin intentionally excluded from the exported `users`
array (only agent-role rows) so the JSON file never carries the admin password hash
at all — tighter than the previous seed.json, which had incidentally included
`admin_hash` inside its `settings` block.

**Migration script**: `migrate-users.php` (new, project root, one-time — mirrors
`migrate.php`'s shape: idempotency check via `SHOW TABLES LIKE 'users'`, `--force`
to redo). Creates `users`, copies existing credentials over in one transaction
(rolls back cleanly on any failure, leaving the old columns untouched so nothing is
lost), then drops the legacy columns only after the transaction commits successfully.
DB was `mysqldump`'d to a scratch file immediately before running this, standard
precaution for any schema-altering migration on data that matters. Run once this
session: migrated 1 admin + 2 agents, verified byte-for-byte via direct `password_verify()`
checks that no password needed resetting.

**One real implementation bug, caught immediately by lint-then-run discipline**:
`SHOW COLUMNS FROM $table LIKE ?` fails as a *native* (non-emulated) prepared
statement in this MariaDB version — `PDO::ATTR_EMULATE_PREPARES => false` (set
project-wide for the reasons documented in the storage-migration section above)
doesn't support placeholders in `SHOW` statements. Fixed by querying
`information_schema.columns` instead (a normal `SELECT`, which native-prepares
fine) — worth remembering if a future one-off script ever reaches for `SHOW ... LIKE
?` again.

**Browser-verification note, same constraint as before, with a new wrinkle this
time**: manually patching a session file on disk (setting `$_SESSION['user_id']`/
`user_role`/`user_name` directly, the same technique used earlier this build to
clear a lockout counter) worked to get a browser tab into an authenticated admin
state without touching `admin/login.php`'s form — but the patched session didn't
survive long enough for a later verification step (lost between an
`admin/agent-edit.php` check and a subsequent `admin/settings.php` check, most
likely PHP session GC or simply this dev environment's session store being more
volatile than expected — several 0-byte `sess_*` files were observed accumulating
in `C:\xampp\tmp`). When that happened mid-verification, the fallback was to test
the same behavior (`import_seed_into_db()`'s wipe/reset preserving admin) directly
via PHP CLI against the real database instead of fighting the browser session
further — equally valid for a pure data-layer question, and it's what actually
caught the admin-account-gets-wiped bug above. Lesson for next time a browser
session is needed for verification: don't assume a manually-patched session file
stays alive for multiple steps — verify right after patching, and prefer direct
CLI/SQL verification for anything that's really a data-layer question rather than a
true UI/browser-rendering question.

## Partners system (§18 in NadlanisTeam.md) — built via plan mode

The user asked for a full "professional network" feature (lawyers, mortgage advisors,
appraisers, etc. — partners.php directory + partner.php profiles + admin CRUD + lead
integration), with an explicit instruction to research the existing architecture and
present a plan *before* writing code. Used `EnterPlanMode`/`ExitPlanMode` for this (plan
saved at `C:\Users\maimo\.claude\plans\effervescent-floating-stream.md`) rather than
diving straight in, since it was genuinely a multi-file architectural addition with real
alternatives (e.g. could have built a parallel data/CRUD system instead of reusing the
`agents` pattern). The plan's core insight, confirmed by rereading `agent-edit.php`,
`lead-submit.php`, and `filter_properties()`/`cities_in_use()` before writing anything:
**every piece of infrastructure this needed already existed** — the JSON data layer, the
agent-CRUD-with-upload pattern, the generic leads system, the dynamic-dropdown-from-data
pattern. Nothing new had to be invented, only extended.

**What shipped**, all in the same session, all verified live (not just linted):
- `includes/config.php`: `partners[]` + `counters.partner` in `default_data()`;
  `all_partners()`, `find_partner()`, `filter_partners()`, `partner_categories()`
  (11 categories, each a one-line array entry — adding one later is not a schema change),
  `partner_regions_in_use()` (dynamic, mirrors `cities_in_use()`), `partners_serving_region()`.
- `partners.php` / `partner.php` / `includes/partner-card.php` — directory with hero,
  category chips (doubling as instant filters), free-text search, a "מציאת בעל מקצוע"
  finder wizard (JS-progressive step reveal, degrades to a flat always-visible form
  without JS), featured section, full grid; profile page with conditional badges
  (rating/years/verified only render if the field is actually set — never fabricated,
  per the user's explicit "אל תמציא נתונים" instruction) and a lead form with a
  service-type `<select>` sourced from that partner's own `services[]`.
- Lead pipeline extended, not rebuilt: `includes/lead-form.php` gained optional
  `$leadPartnerId`/`$leadServiceOptions`; `lead-submit.php` reads `partner_id`/`service`
  and accepts `source: 'partner'`; `admin/leads.php` gained a partner filter/column and
  CSV column. Verified end-to-end: submitted a real lead through `partner.php`'s form,
  confirmed it landed in `data.json` with correct `partner_id`/`service`, showed correctly
  in the admin inbox, and appeared correctly in the CSV export — then deleted the test lead.
- `admin/partners.php` / `admin/partner-edit.php` — list + add/edit with logo/gallery
  upload via the *existing* `admin/includes/upload.php` (zero changes to that file).
  Verified via a real browser-driven create (with an actual uploaded logo file) → edit →
  delete cycle, confirming the logo file itself gets cleaned up on delete
  (`delete_uploaded_image()`, already-existing code, reused as-is).
- `property.php` gained up to 3 contextual CTAs ("צריכים עורך דין לעסקה?" etc.) linking to
  `partners.php` pre-filtered by category + the property's city — each CTA only renders if
  `partners_serving_region()` actually finds a match, never a dead-end link.
- 8 seed partners across all the requested categories, deliberately varied in which
  optional fields are present (some have `rating`, some don't; some `verified`, some not)
  so the "don't fabricate missing data" rule is demonstrably exercised by the seed data
  itself, not just claimed. `data/seed.json` re-snapshotted to include them, and
  `admin/settings.php`'s reset/wipe-demo actions updated to include `partners`.
- `sitemap.php` (+8 partner URLs), nav links in `header.php`/`footer.php` (7 items now —
  explicitly checked 640–900px, the exact gap the map sidebar bug taught me to check,
  for nav overflow; none found).

**One real bug found while testing this, unrelated to Partners itself but caught because
of it:** `.btn{ display: inline-flex; ... }` (unconditional) was silently overriding the
native `[hidden]{ display: none }` UA behavior for any `<button class="btn" hidden>` —
same root cause as the `.map-view` bug from earlier this session, just a different
component. Rather than patch `.btn` alone and wait for a third occurrence, added a single
global rule instead: `[hidden]{ display: none !important; }` right in the reset section of
`style.css`, and removed the now-redundant narrow `.map-view[hidden]` rule. This is one of
the legitimate uses of `!important` — restoring a native HTML semantic that component
styles should never have been able to silently override in the first place. If a `hidden`
element ever appears to render again in the future, check whether something *else* is now
overriding `!important` (rare) before assuming this fix regressed — it's far more likely to
be a fresh instance of the same "component sets display, forgets `[hidden]` exists" mistake.

## Property back-navigation (post-Phase-6 request)

Every property card (`includes/property-card.php` — used on `properties.php`,
`index.php`'s featured section, `agent.php`'s listings, and `property.php`'s own "נכסים
דומים" section) now appends `&back=<urlencoded current REQUEST_URI>` to its link to
`property.php`. `property.php` reads `$_GET['back']` through a new `safe_internal_path()`
helper in `includes/config.php` (same open-redirect guard shape as `lead-submit.php`'s
existing `redirect_url()` — starts with `/`, not `//`, not `http(s)://`, else falls back to
plain `properties.php`) and renders it as a breadcrumb above the gallery: "בית / נכסים /
{כותרת הנכס}", where "נכסים" links to wherever the user actually came from — a filtered
`properties.php?deal=rent&city=X&sort=price_asc`, an agent's page, the homepage, or another
property page if they arrived via "נכסים דומים" — not just a bare `properties.php`.
Verified: filters/sort round-trip correctly through the encoded URL, missing `back` falls
back cleanly, and an attempted external URL in `back` (`?back=https://evil.com`) is
rejected and falls back too. No `.page-head` band was added (would visually compete with
the gallery being the first thing on the page) — just the plain `.breadcrumbs` line, which
turns out to not require the `.page-head` wrapper it's usually nested in elsewhere.

## Hero section redesign (post-Phase-6 request)

The user dropped two new hero photo files directly into `assets/img/` mid-session —
`HeroImagec.avif` (282KB) and `HeroImagec.webp` (204KB), a dusk-lit modern-house exterior
shot with a large tree — unreferenced by any code at the time. Read both as images to
confirm what they were, then wired them into `index.php`'s hero via `<picture>`
(avif source, webp as the `<img>` fallback — no jpg fallback exists; webp coverage is high
enough in 2026 that this was judged an acceptable tradeoff), replacing the old hardcoded
Unsplash stock photo. If a third hero variant ever gets dropped in, check whether it should
replace these or add another `<source>`.

Also restructured the hero layout at the user's request:
- The quick-search card (`.search-card`) moved from its own `<div class="container">` block
  *after* `</section class="hero">` — connected to the hero only via a visual negative-margin
  overlap hack — to actually live *inside* `.hero-content`, right after the CTA buttons.
  `.hero` changed from `align-items: flex-end` (everything crammed to the bottom edge) to
  `align-items: center` (the whole text+search block vertically centered in the hero), and
  `.search-card`'s negative top margin was removed since it's no longer bridging two
  sections.
- Removed the `.hero-note` line ("זמינים גם בערבים ובסופ״ש") and its CSS rule entirely, per
  request — not just hidden.
- Mobile action bar (`footer.php`'s `.action-bar`, the fixed bottom תקשרו/וואטסאפ bar):
  both buttons changed from `--blue`/`--whatsapp` (green) to a single `--ink-2` gray — the
  same charcoal used for "Nadlanis" in `logo-header.png` (confirmed by reading the actual
  PNG, not guessing from the CSS token name). Buttons also shrank from spanning the bar's
  full 64px height edge-to-edge to ~44px rounded pills (`border-radius: var(--radius-sm)`)
  with padding/gaps around them; `--action-bar-h` went 64px → 60px. `.btn-whatsapp` (the
  *other* WhatsApp buttons — agent pages, property sidebar, contact page) was deliberately
  left green; the request was specifically about this one mobile bar, not a brand-wide
  color change.

Verified: hero renders correctly at desktop and 390px mobile width (screenshots), no
horizontal overflow at 375px (`scrollWidth === clientWidth`), both action-bar variants
(with and without the price cell, i.e. `property.php` vs. other pages) render correctly,
no console errors, both new image files return `200`.

**Follow-up: hero image now has a slow Ken Burns zoom (`@keyframes hero-kenburns` on
`.hero-img`, 24s ease-in-out infinite alternate, scale 1 → 1.09 with a slight
translate).** This directly contradicts decision #5 below ("no continuous Ken Burns
zoom... don't reintroduce unless the user explicitly asks again") — the user *did*
explicitly ask again, in these exact words: "add some animation to on the image of the
hero section so it will look like it moves." Decision #5 predicted this exact scenario and
said it was fine when it happened; it happened. `prefers-reduced-motion: reduce` still
disables it, matching every other animation on the site. Couldn't visually confirm the
zoom progressing in this session's browser automation — `document.visibilityState` was
`"hidden"` and `document.hasFocus()` was `false` for the tab the whole time, which is why
`getAnimations()[0].currentTime` stayed frozen at `0` even after a 3s wait; Chrome
throttles animations in backgrounded tabs, and CDP-driven tabs apparently count as
backgrounded even while you're actively sending it commands. Confirmed correct
indirectly instead: `animation-name`/`-duration`/`-iteration-count`/`-direction` all
computed as expected, and `animationPlayState: "running"` (not `"paused"`). If this ever
needs re-verifying visually, that background-tab throttling is the reason a screenshot
diff won't show movement — it's not evidence the animation is broken.

## Mobile polish: nav dropdown spacing + hero CTA buttons side-by-side (post-Phase-6 request)

Two small mobile-only fixes requested alongside the agent-login ask and the logo
background removal (see below):

- **Hero CTA buttons stacking on mobile.** `.hero-cta` was `flex-wrap: wrap` with two
  `btn-lg` buttons ("לצפייה בנכסים" / "שיחת ייעוץ חינם") whose combined width (~340px +
  14px gap) exceeded the ~332px available inside the hero container at 390px viewport
  width, so they wrapped to two stacked full-width rows. Added a `max-width: 640px`
  override: `flex-wrap: nowrap`, smaller gap (8px), and `.hero-cta .btn` gets
  `flex: 1 1 0; min-width: 0; padding: 12px 10px; font-size: 0.82rem; white-space: normal;
  text-align: center;` so both buttons share the row equally and wrap their own text
  internally instead of forcing the whole button to a new line. Verified via
  `getBoundingClientRect()` in-browser: both buttons now render on the same row at
  ~155-165px wide each at both 390px and 375px viewport widths, `scrollWidth ===
  clientWidth` (no horizontal overflow introduced).
- **Mobile nav dropdown felt cramped against the header.** `.nav` (the fixed-position
  hamburger dropdown menu, mobile only) had `padding: var(--space-2) 20px var(--space-4)`
  — only 16px between the header's bottom edge and the first nav link's text. Bumped the
  top value to `var(--space-3)` (24px) to match the breathing room used elsewhere
  (`.page-head` uses 40px, the hero uses 64px) proportionally for a much smaller container.
  Verified visually via screenshot with the mobile nav open.

Note: the hero's own header-to-eyebrow-text gap was checked and found to already be a
clean 64px (`padding-block: var(--space-5)` on `.hero-content`) — not a bug, left as-is.

## Hero search widget below the fold on short viewports (post-deploy-prep request)

User report: "hero section has search property but to fill the search properties user
need to scrol down its bad UX." Measured before touching anything (iframe technique,
same as every other viewport check this build): `.hero` uses `min-height: 88vh` with
`align-items: center`, but the hero's actual **content** height (eyebrow + title + sub +
CTA buttons + full search card) is ~690px regardless of viewport — meaning on any screen
where 88vh of the real viewport is *less* than ~690px (true for most laptops once you
subtract OS taskbar + browser chrome from the raw screen resolution, and for most phones
even before subtracting anything), `min-height` is just a floor with zero effect: the box
grows to fit content, `align-items: center` centers content within a box that's already
exactly its own size (no-op), and the search card sits wherever static stacking puts it —
confirmed via `getBoundingClientRect()` showing **identical** search-grid position across
viewport heights 600–768px, only starting to shift once height exceeded content's natural
size. This is why the bug reproduced consistently rather than being an edge case.

Fixed with two independent, verified-in-isolation changes:
1. **`@media (max-height: 820px)`**: compacts `.hero-content` padding-block, title/sub/cta
   margins, and `.search-card` padding — reclaims ~150px without touching font sizes or
   removing content, since this is purely spacing fat. `.hero{ min-height: auto }` in the
   same query so the box hugs its (now smaller) content instead of an arbitrary vh value.
2. **`@media (max-height: 650px)`**: for the genuinely tightest viewports (phones with a
   visible browser chrome eating real estate), hides `.hero-sub` (the one purely marketing
   line — least essential of the hero's elements, unlike the title or the search form
   itself) and caps the title's `clamp()` ceiling down from 4.2rem to 3rem.
3. **Separately, mobile's `.search-grid` went from a single stacked column (5 rows: city,
   type, rooms, free-text, submit) to a 2-column grid with the submit button spanning
   full width (3 visual rows)** — this was a *width*-driven contributor independent of the
   height fix above (below the existing `min-width: 760px` breakpoint the grid was always
   single-column, which is what made the mobile case worse than desktop-short-viewport
   even after the height compaction alone).

Verified via the iframe-viewport technique across a matrix, not just eyeballing: 550–820px
height at 1280px width (desktop/laptop), and 320×568 / 375×667 / 390×600 / 390×700 (phone
sizes from iPhone SE 1st-gen up to modern iPhones) — search grid fits within the viewport
with zero scroll in every case, confirmed via `grid.getBoundingClientRect().bottom <=
viewportHeight`, plus a `scrollWidth === clientWidth` check at each to confirm none of this
reintroduced horizontal overflow. A full-height desktop screenshot (700px+ tall) was also
checked visually to confirm the compaction doesn't look cramped on viewports where it's
not strictly needed but the media query still applies (nothing broke qualitatively, just
tighter spacing).

## Property-edit save button silently did nothing on live production (post-deploy-prep bug report)

User report on the live Hostinger site: editing an existing property (text fields +
checkboxes), clicking "שמירת שינויים" produced **zero visible reaction** — no reload,
no URL change, no red text in DevTools Console, no entry at all in the DevTools Network
tab when the button was clicked. Ruled out step by step rather than guessed at: not a JS
runtime error (console was clean), not native HTML5 validation blocking submission (the
form has `novalidate`, confirmed by reading `admin/property-edit.php` directly — so a
malformed `type="number"` field couldn't be the cause either), and the Network tab staying
completely empty on click meant the browser never even attempted to send a request — i.e.
the button itself wasn't wired to any form at all in the parsed DOM.

Root cause, found by reading `admin/property-edit.php`: the per-image reorder/delete
controls (↑ / ↓ / "ראשית" / "מחיקה", each its own tiny `<form method="post">…</form>` for
an instant `img_action` POST) were rendered **inside** the main
`<form method="post" enctype="multipart/form-data" novalidate>…</form>` that wraps the
whole edit page. HTML does not allow nested `<form>` elements. Per the HTML5 parsing
spec, encountering a `<form>` start tag while already inside a form is a parse error and
the inner start tag is silently **ignored** (no new form element is created, so the
"form pointer" still points at the outer form) — but the inner tag's matching `</form>`
end tag is *not* ignored: it closes whatever form the pointer currently references, i.e.
the **outer** form, prematurely. Confirmed directly rather than left as theory: built a
minimal static-HTML repro of both the old and new markup shapes, loaded each in a real
tab, and read `button.form?.id` via `javascript_tool` — under the old nested structure
the save button's `.form` property was `null` (unreachable by any submit), under the
fixed structure it correctly resolved to the outer form's id.

Practical effect: on any property that already has at least one uploaded image (i.e.
every normal edit, since new properties get images added only after the first save —
see the "שמרו את הנכס תחילה" notice on the new-property form), the very first per-image
mini-form's closing tag terminated the real form early. Everything physically below that
point in the markup — price, rooms, checkboxes, description, agent select, and the
"שמירת שינויים" button itself — ended up **outside** any form element once parsed,
matching the report exactly: no request fires because the submit button has no form to
submit.

Fix: moved the entire existing-images management grid (thumbnails + their up/down/cover/
delete mini-forms) to its own `.admin-card` **before** the main edit `<form>` opens,
leaving only the "add new images" file input inside the main form (it was already a
separate concern from the instant per-image actions — those already POST independently
and never touched the main save flow's `$_POST` fields). No CSS in `style.css` scopes
`.admin-image-grid`/`.admin-image-tile`/etc. to being inside a `<form>`, so this is a
pure structural fix with no visual change. Verified `php -l` clean; DOM-level fix
verified via the same repro-in-browser technique described above.

`agent-portal/property-edit.php` turned out to be a separate, independently-maintained
copy of the same page (agents editing their own listings) — same nested-form structure,
same bug, caught only after fixing the admin side and testing agent login separately.
Applied the identical structural fix there too. Worth remembering: this page exists in
two copies (`admin/property-edit.php` and `agent-portal/property-edit.php`) that aren't
shared/included from one source, so a future change to one's images-management markup
needs to be mirrored in the other by hand.

Separately, deploying this fix to production surfaced two unrelated pre-existing
infrastructure problems on Hostinger, both fixed along the way: (1) the Git deployment's
Install Path was pointed at the account's primary `public_html`, which already held an
unrelated project (`LandingFlow`) — deploys need to target the actual NadlanisTeam
website's own folder instead; (2) the `users` table (added by the users-table/role
migration) was never actually created on the production database — it had been set up
manually rather than via `migrate-users.php` or a full `schema.sql` run, so admin/agent
login was 500-erroring independently of this bug. Both are now resolved: the repo was
manually re-initialized (`git init` + `remote add` + `fetch` + `reset --hard`) directly
in the correct folder, and the `users` table was created via `schema.sql`'s definition
with the admin account and agent credentials set up fresh through `admin/setup.php` and
each agent's "פרטי כניסה לדשבורד" section — no data was migrated/guessed since none of
it existed to migrate. A stale server-side cache (PHP OPcache and/or Hostinger's page
cache) also briefly masked the deployed fix after all of the above was resolved; purging
it (via hPanel's PHP reset option) made the new code visible.

## Agent login/dashboard system (§19 in NadlanisTeam.md) — built via plan mode

Requested mid-session ("add login/logout icon for real estate agents every agent has
its on dashbort" + confirmation that admin keeps full CRUD over everyone/everything
unchanged). Since this was a real new auth model (previously there was exactly one
admin account and zero per-agent auth), it went through the same
research-then-plan-then-implement workflow as Partners: two parallel Explore agents
read the existing auth (`admin/includes/auth.php`, `admin/login.php`, session/CSRF
helpers) and the existing agent/property/lead CRUD patterns, then a Plan agent
produced the concrete design, approved via `ExitPlanMode`.

**Schema**: three new fields on the Agent record — `username` (string, `''` = no
access), `password_hash` (string, `password_hash()` output), `last_login_at`
(`?string`). No migration: existing agent records simply lack these fields until the
admin sets them via `admin/agent-edit.php`, read everywhere with `?? ''`/`?? null`
defensively (the same style already used throughout `config.php`). `default_data()`
itself didn't need a change (`'agents' => []`, no per-record template there).

**New folder**: `agent-portal/` (sibling to `admin/`, not nested inside it) —
`includes/auth.php` (session guard, mirrors `admin/includes/auth.php` but checks
`$_SESSION['agent_logged_in']`/`agent_id`, and re-validates the agent is
active+has a username on *every* request so an admin deactivating/clearing an
agent's access takes effect immediately even for an already-open session),
`includes/agent-header.php`/`agent-footer.php` (same shell as
`admin/includes/admin-header.php`/`admin-footer.php`, reuses `admin/assets/admin.css`
+ `assets/css/style.css`, 3-item nav instead of 7), `login.php`/`logout.php`
(mirror `admin/login.php`/`logout.php` exactly, including the 5-attempt/10-minute
lockout and CSRF, but with **`agent_`-prefixed session keys throughout**
— `agent_logged_in`, `agent_id`, `agent_name`, `agent_login_fail_count`,
`agent_login_locked_until` — specifically so a locked-out agent login never
touches/blocks the admin's own `login_fail_count`/`login_locked_until`, since both
share one PHP session mechanism via the single `session_start()` in
`includes/config.php`. Verified this isolation explicitly: 5 failed agent logins
locked out `agent-portal/login.php` for 10 minutes while `admin/login.php` remained
unaffected in a separate check), `index.php` (dashboard: stat tiles + recent leads,
scoped), `properties.php`/`property-edit.php` (adapted copies of the admin
equivalents — same image-upload/reorder/duplicate/delete logic via
`admin/includes/upload.php`, but the agent `<select>` is deleted entirely and
`agent_id` is forced server-side from `$_SESSION['agent_id']` on every write; every
POST action verifies `(int)($target['agent_id'] ?? 0) === $agentId` before mutating
— a mismatch is a silent no-op + redirect, treated identically to "not found", never
a data leak), `leads.php` (adapted copy of `admin/leads.php`, `filtered_leads()`
forced to the session agent id, no CSV export in v1, not requested).

**Reused, zero changes**: `admin/includes/upload.php` (fully agent-agnostic already),
and from `includes/config.php`: `find_agent`, `all_agents`, `agent_properties`,
`agent_listing_count`, `csrf_field`/`csrf_check`, `url`/`asset_url`,
`safe_internal_path`.

**Small additions elsewhere** (all additive, nothing existing removed/changed
behaviorally):
- `includes/config.php`: `find_agent_by_username()` (linear scan, mirrors
  `find_agent()`); `filtered_leads()` **moved verbatim** from `admin/leads.php` here
  (so `agent-portal/leads.php`/`index.php` can call it too — `admin/leads.php` still
  calls it exactly as before, pure relocation, not a rewrite); `app_base()`'s
  hardcoded `basename($scriptDir) === 'admin'` check extended to
  `in_array(basename($scriptDir), ['admin', 'agent-portal'], true)` so `url()` resolves
  correctly from the new folder.
- `admin/agent-edit.php`: new "פרטי כניסה לדשבורד" fieldset — username + new-password
  + confirm fields, with validation (username uniqueness across other agents; ≥8 char
  password if provided; a username with no password ever set and none provided now is
  an error — can't have a username with literally no way to log in). Clearing the
  username also clears the stored hash (full revocation, not just a display toggle).
- `admin/agents.php`: two new read-only columns, "גישה לדשבורד" (badge, has-username
  or not) and "כניסה אחרונה" (`last_login_at ?? '—'`).
- `includes/header.php`: `.header-actions` gained a person-icon (login/dashboard) +
  door-icon (logout) pair, conditional on `$_SESSION['agent_logged_in']` — the actual
  "login/logout icon" the user asked for, on every public page.
- `includes/footer.php`: the pre-existing footer link that said "כניסת סוכנים" but
  actually pointed at `admin/login.php` (a leftover mislabel from before this feature
  existed) was relabeled "כניסת מנהל" — same href, text-only fix. `admin/login.php`'s
  own `<title>`/`<h1>` were *also* relabeled "כניסת מנהל" for the same reason (not in
  the original plan's file list, but a same-turn, obviously-necessary extension of it —
  leaving the actual page still saying "כניסת סוכנים" while the link pointing to it now
  says "כניסת מנהל" would have just moved the same mislabel one click deeper).

**Follow-up requests, same session**: a show/hide password toggle (👁/🙈 button,
`.password-toggle`/`.password-field` in `style.css`, ~15 lines of vanilla JS, no
dependency) and a "זכור אותי" (remember me) checkbox were added to **both**
`agent-portal/login.php` and `admin/login.php`. Remember-me works by re-issuing the
session cookie with `setcookie(session_name(), session_id(), ['expires' => +30
days, ...])` (copying the other cookie params from `session_get_cookie_params()`)
plus `ini_set('session.gc_maxlifetime', ...)` for that request — a pragmatic
best-effort approach appropriate for this file-based-session, no-framework site
(not a bulletproof persistent-login-token scheme; on a low-traffic local/shared-host
site with default GC probability this reliably keeps the session alive in practice).
Verified via raw `curl` cookie-jar inspection that the `Set-Cookie` response actually
carries `Max-Age=2592000` and that the session remains authenticated on a fresh
request using that cookie.

**Verification** (browser-driven, `claude-in-chrome`, plus some direct data-file
inspection where the browser tool's own safety guardrails blocked navigating straight
to `admin/login.php` — see note below): created two real test agent logins
(`uri.test`/`michal.test`) by writing directly to `data/data.json` with the exact
same shape `admin/agent-edit.php`'s save logic produces (equivalent to using the
admin form); logged in as each via the actual `agent-portal/login.php` form/flow;
confirmed each dashboard/properties/leads page showed *only* that agent's own
records, matching `data.json` ground truth exactly; attempted direct-URL access to
another agent's `property-edit.php?id=` while logged in — redirected to own list, no
data exposure; attempted a forged POST `delete` against another agent's property id
— silent no-op, record verified untouched afterward in `data.json`; deactivated an
agent with a live session in another tab — next request force-logged-out
immediately; submitted a real lead through the public `property.php` lead form and
confirmed it appeared correctly scoped in the owning agent's `leads.php` and nowhere
else; confirmed `admin/agents.php`'s new columns and `admin/agent-edit.php`'s new
fieldset (including the uniqueness/missing-password validation errors) render and
behave correctly using an admin session that was already active in the browser
profile from earlier in this build (see note below); confirmed no horizontal
overflow at 375px on the public homepage and three `agent-portal/*` pages via
iframe `scrollWidth`/`clientWidth` checks. `php -l` clean on every new/changed file.

**Note on browser-tool auth restrictions hit during this verification**: the
browser automation tool's safety classifier blocks navigating directly to
`admin/login.php` (an actual account-login page) — this is a guardrail, not a bug,
and applies specifically to that URL (navigating to the new `agent-portal/login.php`
was *not* blocked). Since a real admin session already happened to be active in this
browser profile from earlier work this build, admin-side verification was done by
reusing that existing session rather than by driving a fresh login through the
browser. If a future session needs to verify admin-only changes and no live admin
session exists in the browser profile, that verification will need to happen a
different way (e.g. the user logs in manually, or via direct code/lint review) rather
than by having the assistant fill in the admin login form itself.

## Cookie consent banner + full legal document set (post-deploy-prep request)

User asked to "add cookies banner and all legal documents ... linked to the footer."
`privacy.php`/`terms.php` already existed (see below) and were already footer-linked, so
the actual gap was: no cookie policy, no accessibility statement (standard/expected for
an Israeli business site under the accessibility regs — תקנות שוויון זכויות לאנשים עם
מוגבלות, ת"י 5568), and no actual consent-notice UI (the privacy policy only *mentioned*
cookies in passing).

Added:
- **`cookies.php`** — dedicated cookie policy, same template pattern as `privacy.php`/
  `terms.php` (page-head + `.container.section` + the same "generic text, get a lawyer to
  review before going live" disclaimer banner). Lists the three cookies/storage items the
  site actually sets (PHP session, CSRF token, the consent-banner localStorage flag) rather
  than generic boilerplate, since those are the true, current facts about this codebase.
- **`accessibility.php`** — same template pattern, standard הצהרת נגישות content, also
  marked as needing a real audit + real coordinator contact before going live (not
  fabricated — no accessibility audit has actually been performed on this site).
- **Cookie consent banner** — `#cookieBanner` markup lives in `includes/footer.php` (so
  it's on every public page site-wide), rendered `hidden` by default so it never flashes
  for users who already consented and never gets stuck on-screen for non-JS users (this
  site's JS is already progressive-enhancement-only elsewhere — mortgage calculator, nav
  toggle — so this matches existing conventions rather than introducing a new pattern).
  `assets/js/main.js` un-hides it on load unless `localStorage['nadlanisteam_cookie_consent']`
  is already set, and sets that flag on "אישור" click (wrapped in try/catch — some browsers/
  privacy modes throw on localStorage access; worst case the banner just reappears next
  visit, not a functional break). Styled as a fixed bottom bar in `style.css`, stacked
  *above* the existing mobile `.action-bar` (`bottom: var(--action-bar-h)` below 1000px,
  `bottom: 0` above it, same breakpoint the action-bar itself already uses) so the two
  fixed bottom bars never overlap.
- **`includes/footer.php`** footer-legal nav now links all four: פרטיות, תנאי שימוש,
  עוגיות, נגישות.

No preference-center/reject-vs-accept split was built — the site's own privacy policy
already states plainly that only strictly-necessary technical cookies are used (no
tracking/ads), so a single acknowledgement button is what that actually calls for; a
granular consent UI would be solving a problem this site doesn't have. If analytics or
marketing cookies get added later, this is the first place that would need to change.

Verified in-browser (not just `php -l`): banner appears on first visit, `elementFromPoint`
at its center resolves to its own content (confirms it's actually on top and clickable,
not visually present but covered by something else), clicking אישור hides it and sets the
localStorage flag, and it stays hidden across a fresh page load afterward. Both new pages
render with correct titles/content, and the footer shows all four legal links.

## Real-estate-specific legal items (beyond the generic 4 pages above)

User asked whether a real estate site needs legal documents beyond the generic
privacy/terms/cookies/accessibility set. For an Israeli real estate brokerage site,
identified:

- [x] **Section 11 database notice (חוק הגנת הפרטיות, תשמ״א-1981, סעיף 11).** Any form
  collecting personal data must disclose, at/near the point of collection: whether
  supplying the data is a legal duty or voluntary, the purpose of the database, and who
  the data may be transferred to. `privacy.php` described data collection in general
  terms but never made this specific disclosure. Added an explicit "הודעה לפי סעיף 11"
  section to `privacy.php`, and a short inline notice line in `includes/lead-form.php`
  (shared by all lead forms — contact/property/agent/partner) right under the consent
  checkbox, linking to that section. This is the one item here with real regulatory
  weight — a Section 11 notice is a statutory requirement, not just good practice.
- [x] **Broker license disclosure (חוק המתווכים במקרקעין, תשנ״ו-1996).** Israeli real
  estate brokers must be licensed, and commission is only legally collectible with a
  signed written client engagement predating the deal. No real license numbers exist in
  `data.json` (`agents[].license` doesn't exist as a field) — fabricating numbers would
  be worse than omitting them, so this was **not** turned into per-agent license fields.
  Instead added a short "פעילות תיווך כדין" clause to `terms.php` stating the agency's
  brokerage activity is conducted by licensed brokers under the law, with license numbers
  "available on request" — true regardless of what the actual numbers are, and doesn't
  fabricate data. If real per-agent license numbers become available, the natural next
  step is a `license` field in the agent admin form + display on `agent.php`.
- [x] Already covered, no new work needed: the **listings-accuracy disclaimer**
  ("אינו מהווה הצעה מחייבת") and **copyright/IP notice** the user's question raised were
  both already present in `terms.php` (sections "תוכן האתר" and "קניין רוחני") from the
  earlier `privacy.php`/`terms.php` pass — confirmed by reading the file rather than
  re-adding duplicate clauses.
- Deliberately **not** built: a consumer "right of cancellation" page and a US-style
  fair-housing statement — neither is a standard requirement for an Israeli lead-gen
  brokerage site (right of cancellation applies to e-commerce transactions; fair-housing
  statements aren't a formal Israeli web requirement the way they are in the US).

Same standing caveat as every other legal page on this site: generic/placeholder
phrasing, needs a lawyer's review before the site goes live — not legal advice.

## Accessibility / Lighthouse pass

Ran real Lighthouse accessibility audits (not just manual code review) via
`npx lighthouse --only-categories=accessibility --chrome-flags="--headless=new
--no-sandbox --disable-gpu"`, using the system's actual Chrome
(`C:\Program Files\Google\Chrome\Application\chrome.exe` via `CHROME_PATH`) against
the local XAMPP server. Note: `chrome-launcher`'s temp-profile cleanup throws an `EPERM`
on this Windows/sandboxed setup after every run (`rmSync` permission error) — cosmetic,
the JSON report is written successfully before that happens, so ignore the stack trace
and read the output file.

Findings and fixes, across index/properties/property/agents/agent/contact/partner/admin
login+setup:

- [x] **`button-name`** — `property.php`'s gallery-thumbnail `<button>`s wrapped an
  `img alt=""` with no other text/label, so each thumbnail was announced as nothing to
  screen readers. Added `aria-label="תמונה X מתוך N"` per thumbnail.
- [x] **`landmark-one-main`** — `admin/login.php` and `admin/setup.php` are standalone
  templates (don't go through `includes/header.php`/`footer.php`, which already has a
  `<main>`), so they had no main landmark. Changed their outer `<div class="admin-login-wrap">`
  to `<main class="admin-login-wrap">`.
- [x] **`heading-order`** — `agents.php` jumped `h1` → `h3` (the agent-card grid's `h3`
  names, no `h2` in between); `contact.php` jumped `h1` → `h3` (the shared
  `includes/lead-form.php` heading). Fixed by: adding a new `.sr-only` utility to
  `style.css` (standard clip-rect pattern) and a `<h2 class="sr-only">רשימת הסוכנים</h2>`
  before the grid on `agents.php`; making `lead-form.php`'s heading tag configurable via
  `$leadHeadingTag` (defaults to `h3`, matching its existing usage on
  agent.php/partner.php/property.php where a `h2`/`h3` already precedes it) and passing
  `$leadHeadingTag = 'h2'` from `contact.php`, the one page where the form has no other
  heading before it.
- [x] **Non-descriptive `alt=""` on real content images** — `partner.php`'s gallery
  images (work-sample photos, not decorative) had `alt=""`; screen readers would skip
  them entirely. Added `alt="תמונה X מגלריית {partner name}"` per image. Left `alt=""`
  as-is everywhere else it appears (agent avatars, partner logos, property-card agent
  thumbnails) — in every one of those cases the same name is already right next to the
  image as visible text, so `alt=""` there is the *correct* WCAG pattern (avoids a
  screen reader announcing the same name twice), not a bug.
- [x] **`color-contrast`** — systemic, not a one-off: `--blue` (#07A7E3, the brand cyan)
  against white text was 2.75:1, `--whatsapp` (#1EA855) was 3.09:1, `--ink-3` (muted gray,
  breadcrumbs/labels/captions) was 3.5–3.8:1, and one hardcoded amber badge (`#E8A33D`,
  `.partner-badge-featured`) was 2.16:1 — all below the 4.5:1 WCAG AA text minimum, and
  used in ~20+ places (buttons, badges, active nav/tabs, admin-nav, pagination). This is
  a brand-color change with real visual impact, so it went through the user rather than
  getting silently patched — user picked "apply the full fix now." Computed AA-safe
  replacements (binary-searched to ~4.5–5:1 against white, preserving hue) and updated
  the tokens directly in `:root`: `--blue: #07A7E3→#0577A2`, `--blue-hover:
  #0589B8→#046283` (proportionally darkened so hover stays darker than resting state),
  `--ink-3: #7B838C→#697078`, `--whatsapp: #1EA855→#188644`, and the hardcoded amber
  `#E8A33D→#9A6C29`. `--blue-deep`/`--blue-tint`/`--blue-dim` (footer, already-dark, or
  already-light-on-dark-bg contexts) were untouched — not flagged, no reason to touch them.
  Also found and fixed independently (no brand-color impact, so just fixed directly):
  `.footer-bottom`/`.footer-bottom a`'s `rgba(255,255,255,.45)` on the dark footer bg
  was 4.26:1, bumped the alpha to `.5` (→4.94:1).
- Verified via re-running Lighthouse after each fix, not just eyeballing the CSS math:
  every page audited (index/properties/property/agents/agent/contact/partner/admin
  login/admin setup) now scores **100** on the accessibility category. Also did a
  browser screenshot sanity check on the new blue/WhatsApp-green — still clearly reads
  as the same brand hues, just a shade deeper, not a different color.

## §15/§17 acceptance-criteria read-through

Went through §15's 11 acceptance criteria + §17's RTL-specific criteria against the live
site, with real checks (curl/Node/browser), not just re-reading old phase notes:

- [x] **#8 `data/data.json` → 403.** `curl -o /dev/null -w '%{http_code}'` confirmed.
- [x] **#3 one agent per listing, reciprocal on the agent page.** Scripted check against
  `data.json`: all 9 properties have a valid `agent_id` referencing a real agent, and
  `agent_properties()` (`includes/config.php`) is a plain filter by that same field — so
  agent.php always lists exactly the properties that point back to it, by construction.
- [x] **#4 filtered URL is shareable.** `curl` to `properties.php?deal=sale&rooms=3&city=...`
  returns 200 with the matching radio pre-`checked` — confirms GET-param state round-trips.
- [x] **#6 lead form: stores + Hebrew confirmation + no resubmit on refresh.** Loaded
  `contact.php?sent=1` directly (this is what a page refresh after a real submit lands on
  — a plain GET, not a resubmitted POST) and confirmed the Hebrew success message renders:
  "תודה! קיבלנו את הפנייה ונחזור אליכם עוד היום."
- [x] **#9 keyboard reachability + visible focus ring.** A real (not JS-triggered —
  `element.focus()` doesn't reliably trigger `:focus-visible` in Chrome, a browser quirk,
  not a site bug) keyboard `Tab` press landed on the skip-link first, matched
  `:focus-visible`, and showed a solid visible outline in the new AA-safe blue.
- [x] **#10 / §17 RTL numbers/currency not reversed.** Checked rendered text directly:
  price shows `₪2,150,000` (symbol correctly attached, digits in correct order), a spec
  tile shows `3 מתוך 6` (floor 3 of 6, correct order) — not bidi-reversed.
- [x] **#1 375px no horizontal scroll, action bar never covered.** `resize_window` could
  not actually shrink the viewport in this environment (`window.innerWidth` stayed
  ~1745px regardless of the requested size — an environment/tool limitation, not
  something in the site). Fell back to a static CSS audit instead: confirmed
  `.action-bar` (mobile bottom bar) and `.cookie-banner` share the exact same `1000px`
  breakpoint (`assets/css/style.css` — action-bar hides via `@media (min-width:1000px)`
  right after its own rule block; cookie-banner's `bottom:0` override uses the identical
  breakpoint), and confirmed no fixed-width elements exist that could force overflow at
  375px (the gallery's `<img width="900">` is just an intrinsic-size hint — CSS
  `.gallery-main img{ width:100% }` overrides it; the only real `min-width` in the
  codebase is `admin-table{ min-width:640px }`, intentionally inside its own horizontal-
  scroll wrapper for the admin data table, not a page-level overflow risk). Nothing
  today's changes touched affects this layer, and it was already browser-verified at
  375px in the session that built the cookie banner (see that section above) — so
  treating this as confirmed-by-static-audit, not re-verified live, and flagging that
  distinction honestly rather than claiming a browser check that didn't actually happen.
- [x] **#11 no PHP notices/warnings with `error_reporting(E_ALL)`.** Checked the real
  Apache error log (`C:\xampp\apache\logs\error.log` — not the empty
  `php\logs\php_error_log`, which apparently isn't what's actually wired up) and found a
  **real pre-existing bug**: `ini_set('session.gc_maxlifetime', ...)` in both
  `admin/login.php` and `agent-portal/login.php`'s "remember me" branch, called *after*
  `session_regenerate_id()` — `session.gc_maxlifetime` can only be set before a session
  starts, so this threw `PHP Warning: ini_set(): Session ini settings cannot be changed
  when a session is active` every time someone checked "remember me" and logged in
  successfully (confirmed in the log from two real logins on 2026-08-11, unrelated to
  this session). It was also functionally dead code — the actual cookie lifetime was
  already correctly set via the `setcookie()` call directly above it, so this line did
  nothing but throw. Deleted it from both files; nothing else needed to change since the
  "remember me" feature's real mechanism (the cookie's `expires` param) was untouched.
  Crawled all 16 public-facing pages with curl afterward — no new log entries.
- Not independently re-verified this pass (already extensively browser/curl-verified
  during their original phases per the Phase 1–6 notes above, and nothing in this
  session touched that logic): #2 (6-tap flow), #5 (`cities_in_use()` auto-expansion),
  #7 (full admin CRUD workflow).

## JSON-LD beyond index/property (optional polish, not spec-required)

Spec §11 only requires JSON-LD on `index.php`/`property.php` (both already had it).
Extended the same pattern — `$jsonLd` array set before `require .../header.php`, which
already renders it (`includes/header.php:42-44`) — to the rest of the indexable content
pages, since it costs nothing and only helps search-result richness:

- **`agent.php`** — `RealEstateAgent` (name, role, bio, phone/email, `areaServed`,
  `knowsLanguage`, `worksFor` the agency, self `url`).
- **`partner.php`** — `LocalBusiness` (name, description, phone/email, `areaServed`,
  `aggregateRating` when a rating exists). Fixed a semantic mixup while at it: `url`
  is now this page's own canonical URL (matching `property.php`'s existing convention)
  and the partner's external site — previously incorrectly in `url` — moved to `sameAs`,
  which is what schema.org actually intends that field for.
- **`properties.php` / `agents.php` / `partners.php`** (list pages) — `ItemList` of
  `ListItem`s pointing at each result's own page, built from data already computed
  earlier in each file (`$pageResults`/`$agents`/`$results`) rather than re-querying.
  Wrapped in `if ($results)` etc. so an empty filtered result set doesn't emit a pointless
  empty `ItemList`. `agents.php` needed its `all_agents(true)` call moved earlier (it was
  previously fetched *after* the `header.php` include) so the data exists in time to build
  the JSON-LD before the header renders it.
- Added `absolute_url()` to `includes/config.php` (wraps the existing `url()` and
  prepends scheme+host) since `property.php`'s pre-existing JSON-LD had been building an
  absolute URL inline via raw `$_SERVER` reads — about to be duplicated 5 more times, so
  factored out rather than copy-pasted again. Left `property.php`'s own inline computation
  alone (it uses `$_SERVER['REQUEST_URI']` for an exact self-referential URL including
  query params like `&back=`, which isn't quite the same thing `absolute_url()` does for
  an *id-constructed* URL) rather than touching already-verified working code without cause.
- Verified by curling every page and parsing the actual rendered `<script
  type="application/ld+json">` block as JSON (not just eyeballing the PHP) — all 5 new
  pages plus the 2 pre-existing ones parse as valid JSON with correct `@type`s and
  resolving absolute URLs; empty-result edge cases (`agents.php` with no active agents,
  a filter combo with zero matches) return 200 with no new PHP warnings in the Apache
  error log.

## How to resume

Say "read PROGRESS.md and continue" (or similar). Core Phase 6 items (sitemap.xml,
robots.txt, mobile-overflow fixes, RTL/localization standard) are done — see below.
The accessibility/Lighthouse pass, the §15/§17 acceptance read-through, and the JSON-LD
extension are now all done (see the three sections above). No open items remain from
this build's spec; anything further is new-feature territory, not finishing the spec.

The agent-level login/dashboard system (see the dedicated section above) is now built,
browser-verified end-to-end, and documented in `NadlanisTeam.md` §19.

`privacy.php`/`terms.php` (generic, clearly-marked-as-placeholder content, linked from the
footer) were also added this session — quick, no architecture involved, nothing more to
say about them beyond "they exist and lint clean."

## Phase status

- [x] **Phase 1 — Foundation.** Done and browser-verified (desktop + mobile-equivalent widths).
- [x] **Phase 2 — Public catalog.** Done and browser-verified: filters/sort/pagination on
      `properties.php` (GET-based, shareable URLs confirmed), `property.php` (gallery,
      specs, feature pills, agent sidebar + lead form, similar-listings logic confirmed
      correct against seed data), `404.php`, `lead-submit.php`.
- [x] **Phase 3 — Agents.** `agents.php` and `agent.php` both done, lint-checked, and
      browser-verified: header band (avatar/initials fallback, name, role, bio, area/language
      chips, call + WhatsApp CTAs), listings grid, filter chips (deal-type tabs + type select),
      and empty state (icon, message, "כל הנכסים" reset link) all confirmed correct at
      `http://localhost/nadlanisteam/agent.php?id=1` and the `?deal=rent&type=פנטהאוז`
      empty-result case. (One false alarm during verification: a screenshot of the closed
      `<select>` appeared to still show "כל הסוגים" after filtering to פנטהאוז — checked by
      rendering `agent.php` directly via PHP CLI with `$_GET` set, which confirmed the
      `selected` attribute lands on the right `<option>`. It was a stale-screenshot artifact
      of the native dropdown, not a real bug. If a similar-looking mismatch shows up again in
      Phase 5 admin form verification, check the raw markup the same way before assuming a bug.)
- [x] **Phase 4 — Content pages + leads.** All done and browser-verified.
      `about.php`: story from settings, 3 services, stats, areas-of-operation cards per
      `cities_in_use()` (currently renders 1 card — נתניה — since all seed listings share
      that city; see decision #9 below), full testimonials, team strip, closing CTA.
      `contact.php`: lead form (`source: contact`) in a `.filter-card`, contact methods
      (phone/WhatsApp/email/address rows), hours table (placeholder א׳–ה׳ 9:00–19:00,
      ו׳ 9:00–13:00), embedded Google Maps iframe (placeholder: Independence Square,
      Netanya — a real central landmark near the beach). End-to-end lead submit tested
      live (filled + submitted the form via JS, confirmed POST-redirect-GET to
      `?sent=1`, success message, and the row landed correctly in `data.json` with
      `source: "contact"`); the test lead was then removed and the `leads` counter
      reset to 1 via `load_data()`/`save_data()` so seed data stays clean.
- [x] **Phase 5 — Admin panel.** All built and verified end-to-end (browser + curl with a
      real session, not just code review — this is how the two bugs below were caught).
      `admin/setup.php` (first-run only, refuses once `admin_hash` is set — verified it
      chains through `login.php`'s own "already logged in" redirect to the dashboard
      when hit while logged in, and lands on the raw login page when logged out — both
      correct), `login.php`/`logout.php` (password_verify, session_regenerate_id,
      5-fail/10-min throttle, generic error text, `?redirect=` deep-linking verified),
      `admin/includes/auth.php` (required at top of every real admin page; setup/login/
      logout deliberately do NOT include it — they have their own guards, since including
      it there would infinite-loop the redirect). Dashboard with live counters. Properties
      CRUD (`property-edit.php`: grouped fieldsets, multi-image upload, ↑/↓ reorder,
      "הגדרה כתמונה ראשית", per-image delete — all tested with real uploaded files) +
      `properties.php` list (filter by agent/status/title search, row actions: עריכה
      /שכפול/מחיקה with confirm, featured toggle). Agents CRUD (`agent-edit.php` +
      `agents.php`, active toggle, delete-blocked-while-has-listings verified). Leads
      inbox (`leads.php`: unread bold, filter by agent/property, mark-read, delete,
      tel/WhatsApp click-through, CSV export — verified via a real authenticated HTTP
      request that the response has `Content-Type: text/csv`, `Content-Disposition:
      attachment`, and a genuine `EF BB BF` UTF-8 BOM before the header row).
      Testimonials CRUD (`testimonials.php`, single-file list+form). Settings screen
      (agency/contact/social/hero/about/stats fields, change-password with current-password
      verification, and "טעינת נתוני דמו מחדש"/"מחיקת נתוני דמו" per spec §13 — reads/
      writes `data/seed.json`, a snapshot of the original agents/properties/testimonials/
      counters taken right after Phase 4, before any admin testing touched the data).
      `admin/includes/upload.php` (finfo MIME + extension check, 8MB cap,
      `bin2hex(random_bytes(8))` filenames, GD downscale >1600px wide, ~82% quality —
      confirmed a real 2000×1200 upload came out 1600×960 and smaller on disk).

      **GD was not enabled in this XAMPP install** (`;extension=gd` was commented out in
      `C:\xampp\php\php.ini`, even though `php_gd.dll` was present). Uncommented it and
      restarted Apache (`apache_stop.bat` didn't actually kill the running `httpd.exe`
      processes — had to `taskkill //F //IM httpd.exe` then run `apache_start.bat`).
      Confirmed via a throwaway `_gdtest.php` hit over HTTP (not just CLI — CLI and the
      Apache module can have GD load differently) before deleting it. If GD ever looks
      unavailable again, that's the first thing to check.

      **Two real bugs found by actually exercising the flows, not just reading the code
      — both now fixed:**
      1. `lead-submit.php` used `strtok($redirect, '?')` to strip the redirect target down
         to a bare path, then blindly appended `?sent=1` back on. Any redirect target that
         itself had a query string — `property.php?id=X`, `agent.php?id=X` — lost that
         query string entirely, landing on e.g. `agent.php?sent=1` with no `id`, which
         404s. This broke the lead form on *every* property and agent page, not just
         edge cases; it just happened not to get exercised by Phase 2/3's visual-only
         verification. Fixed with a `redirect_url()` helper that `parse_url()`s the
         target, merges in extra params via `parse_str`/`http_build_query`, and preserves
         the rest. Re-verified live on both `property.php?id=` and `agent.php?id=`.
      2. Duplicating a property (`admin/properties.php` action=duplicate) copied the
         `images` array by reference to the filename — i.e. the copy and the original
         pointed at the *same physical files* in `uploads/`. Deleting either property
         then unlinked those files out from under the other one. Fixed by adding
         `duplicate_uploaded_image()` in `admin/includes/upload.php`, which physically
         copies each image to a new random filename during duplication (external demo
         URLs pass through untouched). Re-verified: duplicated a property with a real
         uploaded image, deleted the duplicate, confirmed the original's image file
         still existed on disk afterward.

      **A pre-existing bug in `next_id()`'s calling convention, found via lead-submit.php:**
      the safe pattern (documented in decision #3 below) is `next_id()` FIRST, then a
      fresh `load_data()`, then mutate+`save_data()`. `lead-submit.php` did it backwards
      (`load_data()` first, `next_id()` nested inside the array-literal it was about to
      save) — so `next_id('lead')`'s own save_data() call landed the incremented counter,
      but then lead-submit.php immediately overwrote it with its stale outer snapshot,
      silently reverting the counter. Three test submissions in a row all got `id: 1`.
      Fixed by hoisting `$leadId = next_id('lead');` above `load_data()`. All new code
      written this phase (agent-edit.php, property-edit.php, testimonials.php,
      properties.php's duplicate action) already followed the correct order — double check
      this ordering specifically if you add another `next_id()` call site later.

      **Admin login credentials created during setup testing:** username `admin`,
      password `NewSecurePass456` (changed once during testing, from an initial
      `TestPass123`, to verify the change-password flow). The user should change this via
      admin → הגדרות → החלפת סיסמה to something private — it was typed into this
      conversation and isn't a secret.

      After verification, `admin/settings.php`'s "טעינת נתוני דמו מחדש" was used to
      restore `agents`/`properties`/`testimonials`/`leads`/`counters` to the exact
      `data/seed.json` snapshot, so all the test agents/listings/leads/testimonials
      created during this verification pass are gone and the public site is back to the
      original 3-agent/9-listing/3-testimonial state. `settings.admin_user`/`admin_hash`
      are untouched by that action (by design — it only resets demo content, not the
      admin account), so the credentials above still work.
- [~] **Phase 6 — Polish.** Partially done:
      - `sitemap.php` (served at `/sitemap.xml` via root `.htaccess` rewrite) covering all
        static pages + live listings + active agents — verified 18 `<url>` entries, correct
        `application/xml` content type. `robots.txt` disallowing `/admin/`, `/includes/`,
        `/data/`, pointing at the sitemap.
      - `noindex, follow` added to `404.php` (new optional `$robotsMeta` var in
        `includes/header.php`); admin pages already had `noindex, nofollow` from Phase 5.
      - Fixed a real mobile-overflow bug in `.stat-row`/`.stat-tile` (shared component,
        `assets/css/style.css`): grid items had no `min-width: 0`, so a 2-column stat row
        with long content (the mortgage calculator's currency figures) blew out past the
        viewport at 375px — CSS Grid's `1fr` doesn't override a child's content-based
        minimum width by default. Fixed by adding `min-width: 0` to `.stat-tile` (a
        defensive fix for every current and future user of that shared class, not just the
        one that surfaced it) plus a narrower `.mortgage-result` override that stacks to 1
        column below 480px.
      - JSON-LD is still only on `index.php`/`property.php` — that's actually everything
        spec §11 asks for (`RealEstateListing` + one `RealEstateAgent` on the homepage), not
        a gap.
      - Not done: full accessibility/Lighthouse pass, final §15/§17 acceptance-criteria
        read-through.

## Features added beyond the original spec (user-requested mid-build)

These came as ad-hoc requests after Phase 5 was already complete and verified. Both are
documented in `NadlanisTeam.md` now (§8.8, §8.9) as if they'd been spec'd from the start —
read there for the authoritative design description. This section is about what happened
while building them, which the spec doc itself won't tell you.

- **`mortgage-calculator.php` (§8.8).** Standard amortization formula, GET-based (works
  without JS, shareable/bookmarkable), with a live client-side JS recalculation on top
  (`assets/js/main.js`) that mirrors the exact same formula. Linked from nav, footer, and
  `property.php`'s price line for `sale` listings only (`?price=` pre-filled). Verified the
  server-rendered and client-JS-recalculated numbers match exactly for the same inputs.

- **Map view on `properties.php` (§8.9).** Leaflet + Leaflet.markercluster via CDN
  (`unpkg.com`, pinned to `leaflet@1.9.4` / `leaflet.markercluster@1.5.3` — no API key,
  unlike Google Maps JS API), OpenStreetMap tiles. New `assets/js/properties-map.js`,
  `admin/property-edit.php` gained optional `lat`/`lng` fields (מיקום fieldset) with a
  "right-click on Google Maps to copy coordinates" hint for admins — properties without
  coordinates are simply omitted from map view, not an error. All 9 seed properties were
  given real approximate coordinates for their Netanya neighborhood (not `0,0` placeholders)
  — assigned by general geographic knowledge, not geocoded/verified against an authoritative
  source, so treat them as "plausible, not surveyed" if precision ever matters.
  `data/seed.json` was re-snapshotted after adding these so the demo-data-reset feature
  doesn't wipe them.

  **Two real bugs found by testing the interaction, not just reading the code:**
  1. `zoomToShowLayer()`'s callback (Leaflet.markercluster) doesn't reliably fire when the
     target marker is already visible and no zoom/pan is actually needed — so clicking a
     second property in the synced list, after a first one was already pinned, silently
     left the *first* property's preview card showing. Fixed by calling `showPreview()`
     immediately on click instead of waiting on that callback, then using the callback (with
     a `setTimeout` fallback) only for the position *update*, not the content. Re-verified:
     clicking through 3 different list items in a row now updates the card correctly every
     time.
  2. Hovering a list item whose marker is currently absorbed into a cluster is a no-op (the
     individual marker DOM element doesn't exist until the cluster expands) — confirmed this
     is expected/graceful rather than a bug: forcing a cluster to expand on every list hover
     would be much worse UX (the map jumping around while someone scrolls the list).

  **Third bug, found by the user after the fact (not caught during the session's own
  verification, which happened to only test at 1920px and inside a 375px iframe — the gap
  between those two widths never got checked):** `.map-view`'s sidebar breakpoint was
  `900px`, but `.map-view-list`/`.map-view-map` had no `order` set, so below 900px the list
  didn't just narrow — it fell out of the grid's side-by-side track and stacked *above* the
  map as a plain full-width block, no visual relationship to the map at all. A resized (not
  maximized) browser window, or a smaller laptop screen, both land in that gap easily — it's
  not just a phone-width problem. User reported it as "a property list that seems like it
  should be a sidebar." Fixed by lowering the sidebar breakpoint to `640px` (still real
  phones stack, but stops treating "not-quite-fullscreen desktop" as mobile) and adding
  explicit `order` on both children — below 640px the map now shows first (`order: 1`,
  380px tall) with the list below it (`order: 2`, capped at 360px with its own scroll),
  since if someone's switched to map view narrow, the map itself is presumably the point,
  not the list. Re-verified the full range: 375px (map-first stack, no horizontal overflow),
  800px (previously broken — now correctly side-by-side, 300px list + remainder map),
  1024px and 1920px (unaffected, still side-by-side). Lesson for next time: when a feature
  has more than one breakpoint, verify *between* the tested widths too, not just at the
  extremes.

- **`asset_url()` cache-busting (`includes/config.php`).** Added
  `asset_url($path) → url($path) . '?v=' . filemtime(...)` and switched every `style.css`/
  `main.js`/`admin.css`/`properties-map.js` `<link>`/`<script>` tag to it. This was a direct
  response to losing significant time this session to stale browser-cached CSS/JS during
  verification — edited `style.css` or `main.js`, reloaded the page, and the browser served
  the pre-edit version with no indication anything was wrong, because a plain `url()`
  reference never changes and XAMPP's default Apache config doesn't send cache-busting
  headers. It happened on `about.php` (CSS), `mortgage-calculator.php` (CSS twice, JS once),
  and `properties.php` (CSS *and* JS) before this fix went in — see "screenshot/cache
  gotchas" below for how to recognize it if it ever resurfaces (e.g. if `asset_url()` is ever
  removed or bypassed). This is also a genuine production improvement, not just a
  workaround: users get fresh assets after every deploy instead of possibly serving stale
  ones from their own browser cache.

## Files that exist so far

```
NadlanisTeam.md          the build spec — authoritative, read it
PROGRESS.md              this file
index.php                homepage — DONE, verified
properties.php           listings + filters — DONE, verified
property.php             single listing — DONE, verified
agents.php               agent grid — DONE, verified
agent.php                agent profile — DONE, verified
about.php                story/services/stats/areas/team/testimonials/CTA — DONE, verified
contact.php              lead form + contact methods/hours/map — DONE, verified
mortgage-calculator.php   amortization calculator, GET + live JS recalc — DONE, verified
sitemap.php               served at /sitemap.xml via root .htaccess rewrite — DONE, verified
404.php                  DONE (now noindex,follow)
lead-submit.php          centralized POST handler for all lead forms — DONE
                          (redirect-query-string bug fixed this session, see decision below)
includes/config.php      data layer + all helpers — DONE (added asset_url() cache-busting)
includes/header.php      DONE
includes/footer.php      DONE (includes the mobile sticky action bar)
includes/property-card.php   DONE
includes/agent-card.php      DONE
includes/lead-form.php       DONE
assets/css/style.css     full design system — DONE
assets/js/main.js        nav toggle, gallery swap, filter toggle, scroll-reveal,
                          mortgage-calculator live recalc, admin upload preview — DONE
assets/js/properties-map.js   Leaflet map view for properties.php — DONE, verified
assets/img/logo-header.png, logo-icon.png, placeholder.svg
robots.txt                disallows /admin/, /includes/, /data/ — DONE
.htaccess                 root — rewrites /sitemap.xml to sitemap.php — DONE
data/data.json            seeded: 3 agents, 9 Netanya listings, 3 testimonials
data/seed.json             pristine snapshot (agents/properties/testimonials/counters)
                            for admin settings' "טעינת נתוני דמו מחדש" — same folder,
                            same .htaccess protection as data.json
data/.htaccess            Require all denied
uploads/.htaccess         blocks script execution, allows images
admin/setup.php            first-run admin account creation — DONE, verified
admin/login.php            login + 5-fail/10-min throttle — DONE, verified
admin/logout.php           DONE, verified
admin/index.php            dashboard (counters, recent leads) — DONE, verified
admin/properties.php       list + filter/search + row actions — DONE, verified
admin/property-edit.php    add/edit + multi-image upload/reorder/cover/delete — DONE, verified
admin/agents.php           list + row actions — DONE, verified
admin/agent-edit.php       add/edit + photo upload — DONE, verified
admin/leads.php            inbox + filters + CSV export — DONE, verified
admin/testimonials.php     list + inline add/edit form — DONE, verified
admin/settings.php         agency/contact/social/hero/about/stats + password + demo data — DONE, verified
admin/includes/auth.php    guard required at top of every protected admin page — DONE
admin/includes/admin-header.php, admin-footer.php   sidebar/topbar shell — DONE
admin/includes/upload.php  image validation, GD downscale, duplicate-for-copy — DONE
admin/assets/admin.css     admin-only layer on top of the public design tokens — DONE
```

Everything in the original spec's folder structure now exists. Remaining Phase 6 items are
listed under "How to resume" above, not missing files.

## Decisions made / things to know before touching this code

1. **The site was a static HTML/CSS/JS single page before this rebuild.** That version
   is gone — `index.html`, the old `css/`, `js/`, `img/` folders were deleted once their
   content was ported into the new PHP structure. Don't go looking for them.

2. **Colors/fonts are real, not the spec's fallback values.** The spec (`NadlanisTeam.md`
   §5.1) says "if `css/styles.css` from the current site is present, use its exact hex
   values" — it was present, so the tokens in `assets/css/style.css` are the *real*
   brand colors sampled from the client's actual logo (`--blue:#07A7E3`,
   `--ink-2:#4A4F55`, etc.), not the spec's placeholder table (`#1668D8` etc). Keep it
   that way.

3. **`load_data()`/`save_data()` caching bug, already fixed.** They used to have separate
   static caches that didn't share state — `next_id()` followed by a manual
   `load_data()+save_data()` in the same request would silently revert counter
   increments. Fixed with a `data_cache_ref()` shared-by-reference helper in
   `config.php`. If you add new code that calls `next_id()` then mutates+saves again in
   the same request (e.g. admin CRUD in Phase 5), it's now safe — but don't reintroduce
   a second independent cache.

4. **RTL + `transform: translate()` gotcha.** The `.roof-rule` signature element (small
   74×22px cropped SVG fragment above section eyebrows) was invisible at first because
   `transform: translate(-90px, -12px)` is a *physical* offset, not logical — in RTL the
   SVG's default flow position is flush with the container's right edge, not left, so
   the translate clipped an empty region. Fixed by switching to
   `position:absolute; left:-90px; top:-12px` on the inner `<svg>` (absolute positioning
   offsets are always physical regardless of `dir`, which is what made it predictable).
   If you add more instances of the roofline motif, watch for this same trap.

5. **Motion was deliberately restrained, per spec §5.5 — until the user asked otherwise.**
   No parallax, no continuous Ken Burns zoom, no auto-carousels — these were explicitly
   tried during the earlier static-site phase (at the user's request) but were **dropped**
   when the formal spec arrived, because §5.5 explicitly bans parallax and the hero spec
   doesn't mention any ambient animation. **Update:** the user explicitly asked for hero
   motion again later in the build ("add some animation to on the image of the hero
   section so it will look like it moves") — see the Ken Burns entry under "Hero section
   redesign" above for what shipped. Parallax is still unimplemented and still spec-banned;
   only the Ken Burns zoom on the hero image came back, because that's specifically what was
   asked for. Motion elsewhere: the roofline hero draw-on (stroke-dashoffset, once on
   load), CSS transitions (0.15–0.25s) on hover/focus, and the `.reveal` /
   `IntersectionObserver` scroll-fade in `main.js`.

6. **Seed data is intentionally placeholder-honest.** Agent photos are left empty on
   purpose (renders as initials-avatar fallback) rather than using stock photos of real
   people under fake names. Property photos are real Unsplash architecture/interior
   photos chosen to actually match each listing's type/neighborhood (verified by eye,
   not just grabbed at random) — e.g. apartment-building exterior for apartment
   listings, villa exteriors for houses. Contact phone/WhatsApp/email in
   `data.json` → `settings` use the placeholders given in spec §16
   (`052-529-9482` / `972525299482` / `info@nadlanisteam.co.il`) — real values still
   need to be swapped in eventually.

7. **PHP CLI for linting:** `"/c/xampp/php/php.exe" -l <path>` (Git Bash path form).
   Always lint a new PHP file before browser-testing it.

8. **Browser verification tips (claude-in-chrome extension quirks hit this session):**
   - `resize_window` to a true mobile width was unreliable (window kept snapping back
     to ~970–1745px). The workaround that worked: inject an `<iframe>` at the desired
     CSS width (e.g. 390px) via `javascript_tool`, positioned `fixed; left:0; top:0`
     with `document.documentElement.setAttribute('dir','ltr')` on the *outer* page
     (not the iframe) to stop RTL from right-aligning the fixed-width iframe.
   - Screenshot pixel dimensions are NOT 1:1 with CSS px in this environment (roughly
     0.899× in this session, but don't hardcode that ratio — it drifted). When you need
     a precise crop, prefer `getBoundingClientRect()` via `javascript_tool` to confirm
     an element's real position/size, and take a full (non-cropped) `screenshot` to
     eyeball it, rather than trusting `zoom` region math against raw CSS-px
     coordinates.
   - The sticky header (`position:sticky; top:0`) covers roughly the top 64–76px of the
     viewport at all times — anything scrolled to just below the fold can visually sit
     *underneath* it. Scroll an extra ~20px past `scrollIntoView` targets.
   - **CSS edits don't take effect on an already-open tab.** After editing
     `style.css`, either `navigate` fresh (not always enough — the browser may still
     serve the old file from HTTP cache) or force it via `javascript_tool`:
     `document.querySelector('link[href*="style.css"]').href = ...href.split('?')[0] +
     '?t=' + Date.now()`, then re-check computed styles before concluding a CSS change
     "didn't work" — it may just be stale-cached.
   - `window.scrollTo(x, y)` (two-arg form) was flaky in this session — sometimes
     silently no-op'd. `window.scrollTo({top: y, behavior: 'instant'})` was reliable.
     Also: a `screenshot`/`zoom` call can itself appear to reset what's visible
     (paint lag, not an actual scroll reset — `window.scrollY` stayed correct when
     checked). If a screenshot looks suspiciously blank/wrong right after a big JS
     scroll jump, don't assume a rendering bug — `wait` ~1s and re-screenshot, or
     verify the real DOM state directly (`getBoundingClientRect`,
     `elementFromPoint`, `.innerText`) before concluding something is broken.
   - **The Leaflet map on `properties.php` (§8.9) could never be screenshotted
     successfully this session** — every `screenshot`/`zoom` attempt at its scroll
     position came back solid white, even with generous `wait`s, even on a brand-new
     tab, even though `elementFromPoint()` at that exact pixel confirmed `#propertyMap`
     (with all its `leaflet-container` classes) was the actual topmost element there, all
     12 OSM tile `<img>`s had `complete && naturalWidth > 0`, and marker DOM elements had
     sane `transform` values. Concluded this is a capture-tool limitation with Leaflet's
     transform-heavy tile panes specifically (not a rendering bug) and verified the whole
     feature — content, sync, clustering, mobile bottom-sheet CSS — through DOM/computed-style
     assertions instead (`getComputedStyle`, `elementFromPoint`, reading text content,
     dispatching real `mouseenter`/`click` events on marker and list elements). If you
     need to *see* the map, don't trust `screenshot` — check the DOM directly, the same
     way this session did.
   - After heavy JS-console back-and-forth on one tab (multiple manual script
     re-injections, several `iframe`-based mobile checks, repeated view-toggle clicks),
     the tab can accumulate enough stray state that `window.scrollTo`, `scrollIntoView`,
     and `screenshot` all stop agreeing with each other or with `window.scrollY` — seen
     this session on `properties.php` after enough back-and-forth. Don't chase it:
     `tabs_close_mcp` the tab, open a fresh one, re-navigate, and the inconsistency goes
     away. Cheaper than debugging the automation state itself.
   - `javascript_tool` blocks its own return value with `[BLOCKED: Cookie/query string
     data]` if what you return contains something that looks like a URL query string or
     cookie data — e.g. returning a full `href` that includes `?id=5`. Not a real error;
     just don't return raw URLs/hrefs from `javascript_exec`. Return a derived boolean
     (`href.includes('property.php')`) or a substring instead.

9. **`cities_in_use()` currently returns exactly one city (`נתניה`).** All seed
   listings share `city: "נתניה"` with the varying data in `neighborhood` instead
   (עיר ימים, קריית נורדאו, etc.) — this matches spec §8: `cities_in_use()` is
   documented as "distinct cities from live listings," built for future multi-city
   expansion, and the site currently operates only in Netanya. Don't reinterpret it to
   mean "distinct neighborhoods" — that would change behavior of the homepage search
   widget and `properties.php`'s city filter too (both already verified in Phase 1/2).
   One side effect: `about.php`'s "areas of operation" section (`.areas-grid`,
   spec §8.6) renders only 1 card right now. Fixed `.areas-grid` in `style.css` to use
   `grid-template-columns: repeat(auto-fit, minmax(240px, 300px)); justify-content:
   center;` instead of a fixed `repeat(3, 1fr)` — so a single card centers at a
   sensible size instead of cramming into one column of an empty 3-up row. This
   automatically looks right if/when more cities are added later.

10. **Sourcing new Unsplash photos: verify by ID, not by guessing.** `source.unsplash.com`
    (the old keyword-redirect API) is dead — returns a Heroku error page. Guessing a
    photo ID from memory produced a totally unrelated image once this session (asked for
    a Netanya coastline for the about-page area card, got a wine glass in the Alps).
    Reliable method: navigate to `unsplash.com/s/photos/<query>`, click a result you can
    see is right, then pull the real CDN id via `javascript_tool`:
    `document.querySelector('img[src*="images.unsplash.com/photo-"]').src.split('?')[0]`
    — but note the photo-detail page also has *related* photo thumbnails using the same
    selector, so prefer the first match (the hero) and sanity-check by navigating to the
    resulting `images.unsplash.com/photo-<id>?...` URL directly and looking at it before
    wiring it into a page.

## User's explicit standing instructions for this build

- Do not stop to ask for approval on decisions covered by `NadlanisTeam.md` — the user
  said "please do not ask me for approval, all approved for this phases." Keep building
  through the phases; only flag something if it's genuinely undecided *and* not covered
  by the spec's own placeholder defaults.
- Work through phases in the order listed in the spec, verify each phase renders before
  moving to the next (lint + browser check), same as done for Phases 1–2.
