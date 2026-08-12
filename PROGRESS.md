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
> **§3 (Stack & storage) is now out of date and needs a manual look**: it still says
> "single JSON file, no database" — that was true until this session's MySQL
> migration (see the dedicated section below). Data storage is now MySQL via PDO;
> `data/data.json` is a historical migration source only, no longer read by the live
> site. `data/seed.json` is still live — it's what "reset demo data" imports from.

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

## How to resume

Say "read PROGRESS.md and continue" (or similar). Core Phase 6 items (sitemap.xml,
robots.txt, mobile-overflow fixes, RTL/localization standard) are done — see below.
Still open: JSON-LD on pages beyond `index.php`/`property.php` (not spec-required, spec
§11 only asks for those two), a full accessibility/Lighthouse pass, and a final read-through
of §15 Acceptance criteria + the new §17 Hebrew/RTL criteria against the live site.

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
