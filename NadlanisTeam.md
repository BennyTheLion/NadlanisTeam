# Build Spec — אתר נדלניס טים (Nadlanis Team Real Estate)

> **How to use this file:** drop it in the project root and reference it in Claude Code
> (`@nadlanis-build-spec.md`), or rename it `CLAUDE.md` so it loads automatically.
> Build in the phases at the bottom. Do not ask clarifying questions — every decision
> needed is written here. Where something is genuinely undecided it is marked
> **PLACEHOLDER** and you should use the stated default.

---

## 1. Project overview

A complete website for **נדלניס טים** — a real estate agency operating in **נתניה**,
with plans to expand to other cities in the Sharon region.

The agency has **several sales agents**. Each agent has their own profile page and their
own portfolio of listings. Every property belongs to exactly one agent.

**The site's job:** get a visitor from "I'm looking for an apartment in Netanya" to
"I just called/WhatsApp'd an agent" in as few taps as possible, on a phone.

**Primary audience:** Hebrew-speaking buyers, sellers and renters in Netanya, mostly
arriving on mobile from Google, Facebook or a WhatsApp-shared listing link.

---

## 2. Non-negotiable requirements

These are hard constraints. Do not deviate.

1. **Hebrew, RTL.** `<html lang="he" dir="rtl">` on every page. All UI text, admin panel
   included, is in Hebrew. Use CSS logical properties (`margin-inline-start`,
   `padding-inline-end`, `inset-inline-start`) — never `left`/`right` for layout.
2. **Mobile-first.** Write base CSS for ~375px. Add complexity upward with
   `@media (min-width: 700px)` and `@media (min-width: 1000px)`. Never write
   desktop-first with `max-width` queries.
3. **Tap targets ≥ 44px.** Every button, link in a nav, and form control.
4. **No build step.** No npm, no bundler, no Tailwind CLI, no Node. Plain PHP + CSS + vanilla
   JS, editable directly in the `htdocs` folder. Fonts from Google Fonts CDN.
5. **Works on XAMPP** at `C:\xampp\htdocs\nadlanisteam\` → `http://localhost/nadlanisteam/`.
   All internal URLs must be built through a `url()` helper that resolves the subfolder —
   never hardcode `/` as site root.
6. **Multi-city ready.** The city is a data field on each property, never hardcoded in
   templates. Adding a second city must require zero code changes. Netanya is only
   the current default.
7. **Accessibility floor:** visible `:focus-visible` outlines, real `<label>` for every
   input, alt text on every image, `prefers-reduced-motion` respected, skip-link to `#main`.

---

## 3. Stack & storage

- **PHP 8.0+**, procedural, no framework, no Composer.
- **Storage: MySQL (MariaDB via XAMPP), database `nadlanisteam`** — see §6 for the
  schema (`data/schema.sql`) and §19-adjacent build history (`PROGRESS.md`) for the
  migration. All data access goes through `includes/config.php` (PDO, prepared
  statements) — no other file touches the database directly, per the original design
  goal below, which is exactly what made the migration a contained change.
  - **Historical note, kept for context**: the site originally ran on a single JSON
    file (`data/data.json`, via `load_data()`/`save_data()`) — deliberately simple
    for an agency with tens of listings, not thousands. It was migrated to MySQL
    mid-build at the user's explicit request. `data/data.json` still exists on disk
    as the one-time migration source (`migrate.php`); it is no longer read by the
    live site. `data/seed.json` is still live — it's what "reset demo data" in
    `admin/settings.php` imports from.
- **Images** uploaded to `uploads/`, filenames randomized on upload.
- Add `data/.htaccess` and `uploads/.htaccess` blocking direct access to `.json` and
  any executable extension (`php`, `phtml`, `php3`, `phar`).

---

## 4. Folder structure

```
nadlanisteam/
├─ index.php                 בית
├─ properties.php            רשימת נכסים + סינון
├─ property.php              עמוד נכס בודד (?id=)
├─ agents.php                צוות הסוכנים
├─ agent.php                 עמוד סוכן בודד (?id=)
├─ about.php                 אודות + המלצות
├─ contact.php               צור קשר
├─ mortgage-calculator.php   מחשבון משכנתא (הערכת החזר חודשי)
├─ partners.php              עמוד שותפים ואנשי מקצוע — §18
├─ partner.php               פרופיל שותף בודד (?id=) — §18
├─ privacy.php                מדיניות פרטיות (noindex)
├─ terms.php                  תנאי שימוש (noindex)
├─ sitemap.php               מפת אתר XML — מוגש דרך /sitemap.xml (ראו .htaccess)
├─ 404.php
├─ migrate.php                סקריפט מיגרציה חד-פעמי ל-MySQL — ראו §3, PROGRESS.md
├─ agent-portal/             דשבורד אישי לסוכן מחובר — §19
│  ├─ login.php  logout.php
│  ├─ index.php              דשבורד + סטטיסטיקות מוגבלות לסוכן
│  ├─ properties.php         "הנכסים שלי"
│  ├─ property-edit.php      הוספה/עריכה — ללא בחירת סוכן, agent_id מהסשן
│  ├─ leads.php              "הפניות שלי"
│  └─ includes/ (auth.php, agent-header.php, agent-footer.php)
├─ includes/
│  ├─ config.php             נתונים + פונקציות עזר (data layer, helpers)
│  ├─ header.php
│  ├─ footer.php
│  ├─ property-card.php      partial — מקבל $p
│  ├─ agent-card.php         partial — מקבל $a
│  ├─ partner-card.php       partial — מקבל $partner — §18
│  └─ lead-form.php          partial — טופס פנייה (אופציונלי: property_id/agent_id/partner_id)
├─ assets/
│  ├─ css/style.css
│  ├─ js/main.js
│  ├─ js/properties-map.js   תצוגת מפה אינטראקטיבית ב-properties.php (Leaflet + markercluster)
│  ├─ js/partners.js         שיפור פרוגרסיבי לוויזרד "מציאת בעל מקצוע" — §18
│  └─ img/                   placeholder.svg, og-default.jpg, logo
├─ data/schema.sql           סכימת MySQL — מורץ על ידי migrate.php
├─ data/data.json            מקור המיגרציה החד-פעמית בלבד — לא נקרא יותר על ידי האתר החי
├─ data/seed.json            תמונת מצב נקייה של נתוני הדמו, לשחזור מ-admin/settings.php (לתוך MySQL)
├─ uploads/                  תמונות נכסים, סוכנים ושותפים
├─ robots.txt
├─ .htaccess                 מפנה /sitemap.xml ל-sitemap.php
└─ admin/
   ├─ login.php  logout.php  setup.php
   ├─ index.php              דשבורד
   ├─ properties.php         רשימה
   ├─ property-edit.php      הוספה/עריכה (כולל lat/lng לתצוגת המפה)
   ├─ agents.php  agent-edit.php
   ├─ partners.php  partner-edit.php   §18
   ├─ leads.php
   ├─ testimonials.php
   ├─ settings.php
   ├─ includes/ (auth.php, admin-header.php, admin-footer.php, upload.php)
   └─ assets/admin.css
```

---

## 5. Design system

The site must look like a natural extension of the agency's existing homepage:
**charcoal + blue on white, clean and modern, with a serif display face.**

> If `css/styles.css` from the current site is present in the folder, **read it first and
> use its exact hex values and font stack**. The values below are the fallback.

### 5.1 Color tokens

Declare once in `:root` at the top of `style.css`, with a Hebrew comment saying these
are the only place colors are defined.

| Token | Value | Use |
|---|---|---|
| `--ink` | `#17212E` | charcoal — headings, primary text, dark sections, footer |
| `--ink-2` | `#3D4C5E` | body text |
| `--ink-3` | `#6B7A8C` | captions, labels, meta |
| `--blue` | `#1668D8` | brand blue — buttons, links, active states |
| `--blue-dark` | `#0E4CA1` | hover |
| `--blue-tint` | `#EAF2FE` | chip and feature-pill backgrounds |
| `--paper` | `#FFFFFF` | page background |
| `--surface` | `#F4F7FB` | alternating section background, spec tiles |
| `--line` | `#DFE7F0` | borders, dividers |
| `--success` | `#12855F` | "פנוי" status |
| `--alert` | `#C2410C` | "נמכר" status |
| WhatsApp green | `#1EA855` | WhatsApp buttons only — never as a brand accent |

No gradients except the hero image overlay. No gold, no terracotta, no dark mode.

### 5.2 Typography

Match the existing site exactly:

```html
<link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;500;700;900&family=Heebo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
```

- `--display: "Frank Ruhl Libre", serif` — h1–h4, prices, big stat numbers, testimonial
  quotes. Used with restraint: never for body copy or UI labels.
- `--body: "Heebo", system-ui, sans-serif` — everything else, including all buttons,
  labels and admin UI.
- Scale (mobile → desktop): h1 `2.2rem → 3.8rem`, h2 `1.6 → 2.1rem`, h3 `1.15rem`,
  body `16px/1.7`, small `0.85rem`.
- Line-height 1.25 for headings, 1.7 for body — Hebrew needs the looser body leading.

### 5.3 Signature element — the roofline

The existing hero has an SVG roofline path (a house silhouette drawn as one continuous
line). **Carry it through the whole site as the brand's signature mark.** It is the one
memorable element; keep everything else quiet.

```svg
<svg class="roofline" viewBox="0 0 1000 260" preserveAspectRatio="none" aria-hidden="true">
  <path d="M0,230 L420,30 L560,155 L610,155 L610,75 L695,75 L695,165 L1000,230"/>
</svg>
```
Fill none, `stroke: currentColor`, `vector-effect: non-scaling-stroke`.

Use it in exactly three places — no more:
1. **Hero:** full-width along the bottom edge, `rgba(255,255,255,.35)`, 120px tall.
2. **Section eyebrows:** a 74px-wide clipped fragment in `--blue` sitting above the
   section title (`.roof-rule`), replacing a generic underline or `01 / 02 / 03` numbering.
3. **Empty states** in the admin panel and the "no results" state, at low opacity.

Do not add it to cards, buttons, or the footer. Chanel rule: it earns its place by being rare.

### 5.4 Shape & depth

- `--radius: 14px` cards, `--radius-sm: 10px` inputs, `999px` buttons and chips.
- Borders do the work, shadows are subtle:
  `--shadow-sm: 0 1px 2px rgba(23,33,46,.06), 0 4px 12px rgba(23,33,46,.05)`
  `--shadow-md: 0 6px 28px rgba(23,33,46,.10)` (hover only).
- Card hover: `translateY(-3px)` + `--shadow-md` + image `scale(1.04)`. Desktop only in
  effect; harmless on touch.

### 5.5 Motion

Restrained. Transitions 0.15–0.25s ease on color, border, transform. One optional
scroll-reveal (fade + 12px rise) via `IntersectionObserver` on section children,
disabled under `prefers-reduced-motion`. No parallax, no counters, no auto-carousels.

---

## 6. Data model

> Storage is MySQL (`data/schema.sql` is the authoritative, current schema — see §3).
> The shape below is still accurate as the logical data model — it's exactly what
> `includes/config.php`'s hydration functions (`hydrate_property()`, `hydrate_agent()`,
> etc.) reconstruct from SQL rows back into PHP, field-for-field, so every template
> in the site still works against this same shape. Kept here in its original
> `data/data.json`-era JSON form because it's the clearest illustration of the shape,
> not because the site still reads a JSON file.

```jsonc
{
  "settings": {
    "agency_name": "נדלניס טים",
    "tagline": "תיווך • שיווק • השקעות נדל״ן",
    "phone": "050-000-0000",
    "whatsapp": "972500000000",
    "email": "info@nadlanisteam.co.il",
    "address": "נתניה",
    "facebook": "", "instagram": "",
    "hero_title": "מכירים כל רחוב בנתניה",
    "hero_sub": "…",
    "about_text": "…",           // multi-line, rendered with white-space: pre-line
    "admin_user": "admin",
    "admin_hash": ""             // password_hash(), empty until first-run setup
  },
  "agents": [{
    "id": 1,
    "name": "", "role": "סוכן מכירות",
    "phone": "", "whatsapp": "", "email": "",
    "photo": "",                 // filename in uploads/
    "bio": "",
    "areas": ["נתניה"],          // neighborhoods/cities they cover
    "languages": ["עברית"],
    "active": true,
    "sort": 0,
    "username": "",              // §19 — empty = no agent-portal login access
    "password_hash": "",         // §19 — password_hash(), empty until admin sets one
    "last_login_at": null        // §19 — set only on successful agent-portal login
  }],
  "properties": [{
    "id": 1,
    "title": "",
    "deal": "sale",              // sale | rent
    "type": "דירה",              // see list below
    "status": "available",       // available | under_contract | sold | draft
    "city": "נתניה",
    "neighborhood": "",
    "address": "",               // street only — never a house number publicly
    "price": 0,                  // ₪, monthly if deal=rent
    "rooms": 0,                  // supports halves: 3.5
    "size": 0,                   // m² built
    "plot_size": 0,              // m², houses/land only, 0 = hide
    "floor": 0, "total_floors": 0,
    "parking": 0,
    "balcony": false, "elevator": false, "mamad": false,
    "storage": false, "renovated": false, "accessible": false, "furnished": false,
    "entry_date": "",            // free text: "מיידי" / "01.09.2026"
    "description": "",
    "images": [],                // filenames, first = cover
    "agent_id": 1,               // REQUIRED — every property has one agent
    "featured": false,           // pinned to homepage
    "created_at": "2026-08-06 10:00:00"
  }],
  "leads": [{
    "id": 1, "name": "", "phone": "", "email": "", "message": "",
    "property_id": null, "agent_id": null,
    "source": "property",        // property | agent | contact | home
    "created_at": "", "read": false
  }],
  "testimonials": [{ "id": 1, "name": "", "city": "", "text": "", "rating": 5 }]
}
```
`counters` (per-entity next-id tracking) no longer exists — it was a JSON-era
mechanism, replaced by MySQL `AUTO_INCREMENT` on each table's `id` column.

**Controlled vocabularies** (define as functions in `config.php`, never inline strings):

- `property_types()`: דירה, דירת גן, פנטהאוז, דופלקס, בית פרטי, קוטג׳, מגרש, מסחרי
- `deal_types()`: `sale` → למכירה, `rent` → להשכרה
- `status_labels()`: `available` → פנוי, `under_contract` → בתהליך, `sold` → נמכר,
  `draft` → טיוטה (draft never appears on the public site)

---

## 7. Required helpers in `includes/config.php`

Build these first; every page depends on them.

| Function | Behavior |
|---|---|
| `url($path)` | Prefix with the app's base path so it works in `/nadlanisteam/` |
| `e($v)` | `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` — **every** echoed value |
| `money($n)` | `₪1,850,000` |
| `money_short($n)` | `₪1.85 מ׳` / `₪850 אלף` — used on cards and in the sticky price |
| `media_url($file)` | `uploads/x.jpg`, passes through full URLs, falls back to `placeholder.svg` |
| `db()` | PDO singleton connection (MySQL) — see §3 |
| `get_settings()` / `update_settings($values)` | Single-row `settings` table read/partial-update |
| `find_*($id)` / `all_*()` / `insert_*($values)` / `update_*($id, $values)` / `delete_*($id)` | Per-entity CRUD, one set per table — see `includes/config.php` |
| `all_properties($publishedOnly)` | Sorted: featured first, then newest |
| `find_property($id)` / `find_agent($id)` | Single lookup, `null` if missing |
| `agent_properties($agentId)` | An agent's listings — powers the agent page |
| `filter_properties($props, $filters)` | deal, city, type, agent, rooms min/max, price min/max, free-text `q` |
| `sort_properties($props, $sort)` | `newest`, `price_asc`, `price_desc`, `rooms_desc`, `size_desc` |
| `cities_in_use()` | Distinct cities from live listings — feeds the city dropdown, so expansion is automatic |
| `wa_link($number, $text)` | Normalizes `05…` → `9725…`, URL-encodes a prefilled Hebrew message |
| `tel_link($number)` | `tel:` with non-digits stripped |
| `csrf_token()` / `csrf_field()` / `csrf_check()` | Session CSRF, on **every** POST |
| `flash($msg, $type)` / `flash()` | One-shot session messages |

---

## 8. Public pages

### 8.1 Global chrome

**Header** — sticky, 64px mobile / 76px desktop, white with `backdrop-filter: blur(10px)`
and a `--line` bottom border.
- Right (RTL start): wordmark `Nadlanis` in `--ink` + `Team` in `--blue`, Frank Ruhl Libre
  900, LTR-forced, with the Hebrew tagline in 0.62rem beneath. Swap for `logo-header.png`
  if present.
- Nav links: בית · נכסים · הצוות · אודות · צור קשר. Current page gets `.is-active` (a blue
  underline that scales in on desktop).
- Mobile: hamburger toggling a full-width panel that slides down from under the header;
  `aria-expanded`, `aria-controls`, closes on link click, on `Esc`, and on outside click.
- Desktop only: a "התקשרו עכשיו" primary button.

**Sticky bottom action bar — mobile only** (`display:none` at ≥1000px). Two buttons,
50/50: **התקשרו** (blue, `tel:`) and **וואטסאפ** (green, `wa.me` with a prefilled message).
On `property.php` the bar becomes three cells: the price in `money_short()` plus the two
buttons. Give `body` a `padding-bottom` so nothing hides behind it, and respect
`env(safe-area-inset-bottom)`.

**Footer** — `--ink` background. Wordmark + tagline, quick links, contact block (phone,
WhatsApp, email, city), social circles, and a bottom bar with
`© <?= date('Y') ?> נדלניס טים. כל הזכויות שמורות.` and a discreet "כניסת סוכנים" link
to `admin/login.php`.

### 8.2 `index.php` — Home

Order of sections:

1. **Hero.** Full-bleed background photo, charcoal gradient overlay
   (`.55 → .78 → .92` top to bottom so the text stays legible), roofline along the bottom.
   Eyebrow, `hero_title` from settings, `hero_sub`, then two CTAs
   (לצפייה בנכסים / שיחת ייעוץ חינם), then the line "זמינים גם בערבים ובסופ״ש".
2. **Quick search card.** White card overlapping the hero's lower edge.
   Pill tabs למכירה / להשכרה, then city select (from `cities_in_use()`), type select,
   min-rooms select, and a "חיפוש נכסים" button. Submits `GET` to `properties.php` —
   it must work with JS disabled.
3. **נכסים מובילים.** Up to 6 `featured` listings (top up with newest if fewer than 6),
   the shared property card, then a "כל הנכסים" outline button.
4. **השירותים שלנו.** Three cards — תיווך / שיווק / השקעות נדל״ן — reusing the existing
   site's line-art SVG icons in `--blue`. Copy: adapt the existing homepage's wording.
5. **הצוות שלנו.** Agent cards, each showing that agent's live listing count.
6. **אודות strip.** Photo + text from `settings.about_text`, plus three stats
   (שנות ניסיון / עסקאות שנסגרו / לקוחות ממליצים) — **PLACEHOLDER** values `12 / 340 / 200`,
   editable in admin settings.
7. **המלצות.** Three testimonials, stars in `#E8A33D`, quote in Frank Ruhl Libre.
8. **Contact band.** `--ink` background, heading, and three tap-to-act rows: phone,
   WhatsApp, email.

### 8.3 `properties.php` — Listings + filters

- Page head on `--surface` with breadcrumbs and a result-aware H1
  ("נכסים למכירה בנתניה" when the deal/city filters are set).
- **Filter panel**, a white card. Always visible: deal tabs, city, type, free-text search.
  Behind a "סינון מתקדם" toggle: rooms min/max, price min/max, agent, status.
  All filters are `GET` params so a filtered view is shareable and back-button-safe.
  Preserve every active param when re-submitting.
- **Results bar:** "נמצאו X נכסים" + a sort `<select>` that auto-submits on change.
- Grid: 1 column mobile → 2 at 700px → 3 at 1000px.
- **Empty state:** roofline mark, "לא נמצאו נכסים שתואמים לחיפוש", and a
  "נקו את הסינון" button — never a dead end.
- Pagination at 12 per page if the list exceeds it.

**Property card** (`includes/property-card.php`, the most reused component):
cover image 4:3 with `loading="lazy"`; badges top-start (deal type in blue, plus a status
badge when not `available`); price pill bottom-start on a charcoal scrim; title linking to
`property.php?id=`; city · neighborhood with a pin icon; a chip row of
חדרים / מ״ר / קומה; and a footer strip with the agent's avatar, name, and a small
"פרטים" button. If the agent has no photo, render their initials in a `--blue-tint` circle.

### 8.4 `property.php` — Single listing

- `404` handling: if the id is missing, unknown, or `draft`, render the 404 page.
- Two columns at ≥1000px: content `1.7fr` / sticky agent sidebar `1fr`.
- **Gallery:** main 4:3 image plus a horizontally scrollable thumbnail strip; clicking a
  thumb swaps the main image (vanilla JS, keyboard accessible). Single image → no strip.
- Title, address line, price in Frank Ruhl Libre ~1.9rem, badges. For rentals show
  "₪X לחודש".
- **Spec grid**, 2 columns mobile / 4 desktop, on `--surface` tiles:
  חדרים, מ״ר, קומה (`3 מתוך 8`), חניות, and where relevant מגרש and תאריך כניסה.
- **Feature pills** in `--blue-tint`, only for `true` values: מרפסת, מעלית, ממ״ד, מחסן,
  משופצת, נגישות, מרוהטת.
- Description with `white-space: pre-line`.
- **Agent sidebar:** photo, name, role, phone/WhatsApp buttons (WhatsApp prefilled with
  `שלום {name}, אשמח לפרטים על הנכס: {title} (#{id})`), a link to their profile, and the
  lead form.
- Below: "נכסים דומים" — up to 3 in the same city with a similar price band.
- WhatsApp share button for the listing URL.

### 8.5 `agents.php` / `agent.php`

- `agents.php`: intro + grid of agent cards (photo, name, role, areas, listing count,
  התקשרו / וואטסאפ buttons, link to profile).
- `agent.php`: header band with photo, name, role, bio, areas, languages, contact buttons;
  then **"הנכסים של {name}"** — that agent's listings in the standard grid, with the same
  deal/type filter chips; then a lead form addressed to that agent (`source: "agent"`,
  `agent_id` set). Empty state: "אין כרגע נכסים פעילים אצל {name}" + a link to all listings.

### 8.6 `about.php`, `contact.php`, `404.php`

- **about:** story from settings, the three services, stats, areas of operation
  (cards per city from `cities_in_use()`), full testimonials, team strip, closing CTA.
- **contact:** lead form (`source: "contact"`), contact methods, hours
  (**PLACEHOLDER:** א׳–ה׳ 9:00–19:00, ו׳ 9:00–13:00), and an embedded Google Map iframe
  with `loading="lazy"` — **PLACEHOLDER** address, Netanya city center.
- **404:** roofline mark, "הדף שחיפשתם לא נמצא", buttons to home and to listings.

### 8.7 Lead form (`includes/lead-form.php`)

Fields: שם מלא\*, טלפון\*, אימייל, הודעה, plus a required consent checkbox
"אני מאשר/ת יצירת קשר". Hidden: `property_id`, `agent_id`, `source`, CSRF, and a
honeypot input hidden with CSS.

On POST: verify CSRF, drop the submission silently if the honeypot is filled, validate the
name and an Israeli phone (`/^0(5\d|[2-4,8-9])\d{7}$/` after stripping non-digits),
append to `leads`, then `header('Location: …?sent=1')` and exit (POST-redirect-GET).
Success message: **"תודה! קיבלנו את הפנייה ונחזור אליכם עוד היום."** Errors name the
field and how to fix it. Never lose what the user typed on a validation error.

### 8.8 `mortgage-calculator.php`

Standalone page: property price, down payment, annual interest rate, and loan term
(10/15/20/25/30 years) → estimated monthly payment, loan amount, total interest, and total
repayment, using the standard amortization formula. `GET`-based like the rest of the site's
filters, so it works without JS and results are shareable/bookmarkable; a progressive-enhancement
script (`assets/js/main.js`) recalculates live client-side on input without a page reload,
using the same formula server- and client-side. Linked from the main nav, the footer, and — for
`sale` listings only — from `property.php`'s price line (`?price=` pre-filled from that
listing). Carries a standard disclaimer that it's an estimate only, not financial advice or a
loan offer. In the nav in the folder tree.

### 8.9 Map view (`properties.php`, `assets/js/properties-map.js`)

`properties.php` has a list/map view toggle (`?view=map`, shareable like the other filter
params). Map view shows the current filtered result set — not paginated, unlike the list view —
as price-pill markers (Leaflet + Leaflet.markercluster, OpenStreetMap tiles, no API key) next to
a synced compact list panel. Properties need `lat`/`lng` (set in admin → נכסים → עריכת נכס →
מיקום) to appear on the map; properties without coordinates are simply omitted from map view,
not treated as an error.

Interaction: hovering a marker (desktop) shows a floating preview card (image, title,
location, price, type, rooms, size, "צפייה בנכס" link to `property.php?id=`); clicking a
marker pins the card open and highlights the matching list item; hovering a list item
highlights its marker; clicking a list item re-centers/zooms the map to reveal that marker
(de-clustering if needed via `markers.zoomToShowLayer`) and pins its preview. On narrow
viewports (≤640px) the same preview card becomes a fixed bottom sheet instead of a
marker-anchored floating card — no separate mobile-only code path, just a CSS breakpoint.
Nearby markers cluster (custom circular count badge) and declutter automatically on zoom.

---

## 9. Admin panel (`/admin`)

Hebrew RTL like the rest of the site, but visually plainer: `--surface` background,
white cards, a sidebar that collapses to a top bar on mobile. Must be fully usable on a
phone — agents will add listings from the field.

### 9.1 Auth

- **First run:** if `settings.admin_hash` is empty, every admin URL redirects to
  `setup.php`, which asks for a username and a password (min 8 chars, entered twice) and
  stores `password_hash($pw, PASSWORD_DEFAULT)`. After that `setup.php` refuses to run.
  **Never ship a default password in the repo.**
- `login.php`: `password_verify`, `session_regenerate_id(true)` on success, generic error
  text ("שם משתמש או סיסמה שגויים"), and a simple throttle — 5 failures locks the session
  out for 10 minutes.
- `admin/includes/auth.php` is required at the top of **every** admin page and redirects
  guests to login. Verify this on each file; a single missed include is the whole breach.

### 9.2 Screens

| Screen | Contents |
|---|---|
| **דשבורד** | Counters (נכסים פעילים, נמכרו, סוכנים, פניות חדשות), the 5 newest leads, quick-add buttons |
| **נכסים** | Table/card list with thumbnail, title, city, price, agent, status. Filter by agent and status, search by title. Row actions: עריכה, שכפול, מחיקה (with confirm), and a featured toggle |
| **הוספת/עריכת נכס** | Grouped form: פרטים בסיסיים · מיקום (כולל `lat`/`lng` אופציונליים למפה, §8.9) · מאפיינים · תמונות · תיאור · שיוך סוכן. Multi-image upload, drag-free reorder via ↑/↓ buttons, "הגדר כתמונה ראשית", delete per image |
| **סוכנים** | List + add/edit: name, role, phone, WhatsApp, email, photo, bio, areas, languages, active toggle, sort order. Deleting an agent who has listings is blocked with a message telling the user to reassign them first |
| **פניות** | Newest first, unread bolded, filter by agent/property, mark read, delete, click-to-call and click-to-WhatsApp straight from the row, plus **ייצוא ל-CSV** with a UTF-8 BOM so Hebrew opens correctly in Excel |
| **המלצות** | CRUD: name, city, text, rating |
| **הגדרות** | Agency details, contact info, social links, hero title/subtitle, about text, stats, and change-password |

### 9.3 Uploads (`admin/includes/upload.php`)

Validate with `finfo` MIME **and** extension, accepting only `jpg/jpeg/png/webp`.
Reject >8MB. Generate the filename as `bin2hex(random_bytes(8)) . '.' . $ext` —
never trust `$_FILES['name']`. Downscale anything wider than 1600px with GD and re-encode
at ~82% quality. Return the filename only; the DB stores filenames, not paths.
When an image is removed from a listing, unlink the file too.

---

## 10. Security checklist

- Escape every echoed value with `e()`. No exceptions, admin included.
- CSRF token on every POST form, verified server-side before any write.
- Session cookie: `httponly`, `samesite=Lax`, `secure` when HTTPS is on.
- `.htaccess` in `data/` and `uploads/` denying `.json` and script execution.
- Never write user input into a filename or path.
- Rate-limit the public lead form: max 5 submissions per session per hour.
- Send `X-Content-Type-Options: nosniff` and `X-Frame-Options: SAMEORIGIN`.

---

## 11. SEO & sharing

- Unique `<title>` and `<meta name="description">` per page. Listing pattern:
  `{type} {rooms} חדרים ב{neighborhood}, {city} | נדלניס טים`.
- Open Graph tags on every page; listings use the cover image — WhatsApp sharing is the
  main distribution channel, so this must render correctly.
- `RealEstateListing` JSON-LD on `property.php`, `RealEstateAgent` on the homepage.
- `sitemap.xml` generated by a PHP script covering all live listings and agents; plus
  `robots.txt` disallowing `/admin/`.
- `lang="he"`, `dir="rtl"`, and a canonical URL on every page.

---

## 12. Copy guidelines (Hebrew)

- Second person plural, warm and direct: "בואו נדבר", not "צור קשר עכשיו!!".
- Use the geresh forms properly: נדל״ן, מ״ר, ממ״ד, קוטג׳, סופ״ש.
- Buttons say what happens: "שליחת פנייה", not "שלח". The confirmation uses the same
  verb as the button.
- Empty states invite an action; error messages say what to fix, without apologizing.
- No exclamation marks in UI labels. No English words in the interface.

---

## 13. Seed data

Ship `data/data.json` with **3 agents** and **9 listings** spread across Netanya
neighborhoods (עיר ימים, קריית נורדאו, אגמים, רמת פולג, מרכז העיר, נאות שקד), a mix of
`sale` and `rent`, varied types, 2–3 marked `featured`, one `sold`, and 3 testimonials —
so the site looks alive on first load and the filters have something to bite on.

Use Unsplash URLs for seed images (the `media_url()` helper already passes full URLs
through) and generic Hebrew names. Add a "טעינת נתוני דמו מחדש" / "מחיקת נתוני דמו"
action in admin settings — backed by `data/seed.json`, a snapshot of the pristine
`agents`/`properties`/`testimonials`/`counters` taken right after seeding (never mutated
afterward), so the reset action has a real, up-to-date target to restore rather than the
empty-arrays fallback in `default_data()`. Seed listings each carry a real approximate
`lat`/`lng` for their neighborhood (§8.9's map view), not `0,0` placeholders.

---

## 14. Build phases

Work through these in order, committing after each. Verify a phase renders before moving on.

- [ ] **Phase 1 — Foundation.** `config.php` with the full helper set, `data.json` schema
      + seed data, `header.php` / `footer.php`, `style.css` tokens and base layer,
      `main.js` (nav toggle, gallery, filter toggle, scroll reveal). Ship a working
      `index.php` skeleton to prove routing and RTL.
- [ ] **Phase 2 — Public catalog.** `property-card.php`, `properties.php` with filters,
      sorting, pagination and the empty state, then `property.php` with gallery, specs,
      agent sidebar and similar listings.
- [ ] **Phase 3 — Agents.** `agent-card.php`, `agents.php`, `agent.php` with per-agent
      listings and a per-agent lead form.
- [ ] **Phase 4 — Content pages + leads.** `index.php` in full, `about.php`,
      `contact.php`, `404.php`, `lead-form.php` with validation and POST-redirect-GET.
- [ ] **Phase 5 — Admin.** Auth and first-run setup, dashboard, properties CRUD with
      image upload, agents CRUD, leads inbox with CSV export, testimonials, settings.
- [ ] **Phase 6 — Polish.** SEO tags and JSON-LD, sitemap, 404 wiring, `.htaccess` files,
      accessibility pass, Lighthouse pass, README with install instructions.

---

## 15. Acceptance criteria

The build is done when all of these are true:

1. At 375px wide, no horizontal scroll on any page, and the sticky action bar never
   covers content.
2. A visitor can go home → filter to "3+ rooms for sale in Netanya" → open a listing →
   WhatsApp the agent, in under 6 taps.
3. Every listing page shows exactly one agent, and that agent's profile page lists that
   listing.
4. A filtered listings URL can be copied, pasted, and reopened with the filters intact.
5. Adding a listing in a new city makes that city appear in the filter dropdown with no
   code change.
6. Submitting the lead form stores it, shows the Hebrew confirmation, and survives a page
   refresh without resubmitting.
7. Admin: login → add an agent → add a listing with 3 photos → it appears on the public
   site immediately, without touching any file by hand.
8. `data/data.json` fetched directly over HTTP returns 403.
9. Keyboard only: every interactive element is reachable and has a visible focus ring.
10. All Hebrew text renders RTL with no mirrored punctuation or reversed numbers.
11. No PHP notices or warnings with `error_reporting(E_ALL)` on.

---

## 16. Open placeholders

Replace these once the agency supplies real values; keep them obvious until then.

| Item | Placeholder to use |
|---|---|
| Phone | `052-529-9482` |
| WhatsApp | `972525299482` |
| Email | `info@nadlanisteam.co.il` |
| Office address | נתניה (city only) |
| Logo | text wordmark; swap in `assets/img/logo-header.png` if supplied |
| Stats | 12 שנות ניסיון · 340 עסקאות · 200 לקוחות |
| Social links | empty → hide the icon rather than linking to `#` |
| License number | footer line, empty until supplied |

---

## 17. Hebrew, RTL & Localization — Mandatory

This standard applies to the entire site as built (all pages under §8 and §9) and to any
future feature area added to it (e.g. a future partners/professional-directory section) —
not a one-time pass to run once and forget.

The whole system must be designed and implemented as a **Hebrew-first, RTL (Right-to-Left)
experience**. This is a critical requirement.

### Language

All user-facing content must be in **Hebrew** — page titles, headings, subheadings,
buttons, filter labels, search placeholders, form labels, validation messages, error
messages, empty states, success messages, listing/category copy, descriptions, navigation,
CTAs, tooltips, badges, sorting options, pagination, loading messages.

Do NOT use English text in the interface unless it is a proper brand/company name, a URL,
an email address, or a technical term that genuinely needs to stay in English.

### RTL

The entire UI must use proper RTL layout: `<html lang="he" dir="rtl">` at the root, and
every relevant container relying on RTL correctly — not just right-aligned text. Layout
logic (flexbox, grid, nav, cards, forms, filters, buttons, icons, breadcrumbs, pagination,
modals, bottom sheets, sidebars, mobile nav) must be RTL-aware, not an LTR structure with
text nudged to the right.

### Direction-aware CSS

Prefer logical properties over hard-coded physical ones:

```
margin-inline-start / margin-inline-end
padding-inline-start / padding-inline-end
inset-inline-start / inset-inline-end
border-start-start-radius / border-end-end-radius
```

instead of `margin-left`/`margin-right`, `padding-left`/`padding-right`, `left`/`right`,
except where a physical property is deliberately correct (e.g. a `transform: translateX()`
offset that must stay physical regardless of `dir` — see decision #4 in `PROGRESS.md` for a
concrete case where the opposite mistake happened).

### Hebrew typography

Use `Frank Ruhl Libre` (display) / `Heebo` (body) — already the site's font stack (§5.2),
chosen for Hebrew readability, not a font that only looks good in Latin script. Clear
hierarchy, comfortable line-height (1.7 for body per §5.2 — Hebrew needs the looser
leading), good mobile readability, and correct handling of mixed Hebrew/English/numeric
text.

### Hebrew content

Natural, professional Hebrew — not literal English translations. E.g. "מצאו את איש
המקצוע המתאים", not a word-for-word rendering of "Find a Professional." This site's
existing copy (see §12 Copy guidelines) already follows this; keep new copy to the same
bar.

### Hebrew forms

Natural Hebrew labels (שם מלא, טלפון, כתובת אימייל, עיר, הודעה, שליחת פנייה — see
`includes/lead-form.php`, §8.7) and Hebrew validation/error messages (e.g. "נא להזין מספר
טלפון ישראלי תקין", not "Invalid phone number").

### Hebrew numbers & currency

Display Israeli currency naturally (`₪2,150,000` via `money()`/`money_short()` in
`includes/config.php`) and use Hebrew-appropriate formatting for prices, sizes, phone
numbers, dates, ratings, years of experience. Never let RTL rendering visually reverse a
number or move the ₪ symbol to the wrong side. Mixed RTL/LTR content — phone numbers,
emails, URLs, English company names — must render correctly; wrap with `dir="ltr"` (as
already done for phone/WhatsApp values) where needed rather than letting bidi algorithm
guesswork decide.

### Icons

Icons stay visually correct in RTL — don't auto-mirror icons that shouldn't be (e.g. a
phone or camera icon). Directional icons (arrows, back/forward, pagination chevrons) must
point the way that reads correctly in Hebrew — e.g. this site's `link-arrow` "‹—" reads
"more info this way" in RTL flow; don't flip it to look correct in LTR instead.

### Mobile RTL

The mobile experience is fully RTL too — filters, bottom sheets (e.g. the map preview
card, §8.9), swipeable galleries, cards, nav, sticky CTAs (the mobile action bar),
forms, search, category chips. No separate LTR mobile layout, ever.

### Database / backend

`data.json` and all form handling must preserve Hebrew/Unicode correctly end to end —
storage (`JSON_UNESCAPED_UNICODE` on every `json_encode` in `save_data()`), search
(`mb_stripos()`, not `stripos()`, throughout `filter_properties()`), sorting, and any
slug/URL handling (this site uses numeric `?id=` rather than Hebrew slugs, which sidesteps
slug-encoding pitfalls entirely — keep that pattern for new ID-addressed content).

### SEO

`<html lang="he" dir="rtl">` plus a Hebrew `<title>`, meta description, H1, canonical URL,
and Open Graph tags on every page (already the pattern in `includes/header.php`, §11) — and
the same bar for any future page.

### Critical acceptance criteria (adds to §15)

The Hebrew/RTL bar is NOT met if: the interface is primarily English; text is translated
but the layout logic stays LTR; Hebrew text is merely right-aligned inside an LTR
structure; mobile RTL breaks anywhere; numbers/phone numbers/prices render visually
reversed; icons or arrows point the wrong way for RTL reading; forms or search/filter UI
show English validation or labels. The result should read as a native Israeli Hebrew
real-estate platform, not an English app translated into Hebrew.

---

## 18. Partners — professional network (`partners.php`, `partner.php`)

Added mid-build at the user's request, after a research-then-plan pass confirmed the
existing `agents` entity/CRUD/lead pattern was the right template to reuse — see
`PROGRESS.md` for the full before/after. Built on the same infrastructure as everything
else (§3's single-JSON data layer, §9's admin CRUD pattern, §8.7's lead form) — not a
parallel system.

### Data

`data.json` gains `partners[]` (flat records, same shape philosophy as `agents`/
`properties`) and `counters.partner`. Each partner: `name`, `category` (one of
`partner_categories()` in `includes/config.php` — ⚖️ עורכי דין מקרקעין, 🏦 יועצי
משכנתאות, 🏗️ יזמים, 🏢 קבלנים וחברות בנייה, 📐 שמאי מקרקעין, 🏠 אדריכלים ומעצבי פנים,
💰 יועצים פיננסיים, 🔑 חברות ניהול ואחזקה, 🧾 יועצי מיסוי מקרקעין, 🏡 חברות תיווך, 🔗
אחר — adding a category is a one-line edit there, not a schema change), `business_type`,
`regions[]` (a partner can serve multiple areas — never a single string), `description_short`/
`description_full`, `services[]`, `phone`/`whatsapp`/`email`/`website`, `logo`/`gallery[]`
(same upload pipeline as agent photos/property images), `years_experience`/`rating` (both
nullable — **never rendered if absent, never fabricated**), `verified`/`featured`/`active`/`sort`.

`leads[]` gains `partner_id` and `service` (both optional, same pattern as `agent_id`),
and `source` accepts `'partner'`. This means the entire lead pipeline — CSRF, honeypot,
rate-limit, POST-redirect-GET, admin inbox with filter/CSV/mark-read — works for partner
leads with zero new infrastructure.

### Pages

- **`partners.php`** — dark-gradient hero (reuses `--blue-deep`/`--blue-deep-2`, no new
  color tokens), a category chip row that's simultaneously the "categories displayed
  visually" requirement and a one-click filter (real `<a href>` links, not JS-only), a
  free-text search box, a "מציאת בעל מקצוע" finder card (category buttons → region select
  → submit, JS-progressive: step 2 and the submit button are hidden until a category is
  picked, but ungated and fully functional as a flat form if JS is off), a "⭐ שותפים
  מומלצים" section for `featured` partners, then the full filtered grid. All filters are
  `GET` params (`?category=&region=&q=`), shareable like every other filtered view on
  this site.
- **`partner.php`** (`?id=`) — profile: logo, name, category, badges that only appear when
  the underlying field is actually set (rating, years, "✓ פרופיל מאומת"), full description,
  services as pills, optional gallery, and a sidebar with contact buttons + the standard
  lead form (with an extra "סוג השירות" `<select>` populated from that partner's own
  `services[]`).
- **`admin/partners.php`** / **`admin/partner-edit.php`** — list with category filter +
  featured/active toggles, and the add/edit form (logo + gallery upload via the existing
  `admin/includes/upload.php`, unmodified).

### Property page integration

`property.php` shows up to three contextual CTAs below the description — "צריכים עורך דין
לעסקה?" / "צריכים ייעוץ משכנתא?" / "רוצים הערכת שווי לנכס?" — each linking to
`partners.php?category=X&region=<property's city>`. A CTA only renders if
`partners_serving_region()` actually finds a match; never a dead-end link to an empty
result. Matching is a plain string comparison between `property.city` and a partner's
`regions[]`, same simplicity level as the rest of the site's filtering (no geocoding).

### Explicitly out of scope (by design, not an oversight)

Partner registration/login/dashboard, subscriptions/paid featured placement, a real
reviews system, and analytics (profile views, phone/WhatsApp click tracking) were named
by the user as future work, not now. The schema doesn't block any of them — e.g. adding
`email`+`password_hash` to a partner record for login later is an additive field, not a
migration — but none of it exists yet. "How many leads per partner" already works today
for free, since `admin/leads.php`'s existing filter+CSV export just needed the
`partner_id` field to apply to a new entity.

---

## 19. Agent login & personal dashboard (`agent-portal/`)

Added mid-build at the user's request: each real-estate agent gets their own login and
a personal dashboard scoped to only their own properties and leads; the single admin
account keeps unrestricted full CRUD over everyone/everything, unchanged. Went through
the same research-then-plan-then-build workflow as Partners (two parallel research
passes over the existing auth/CRUD code, then a design pass, approved before
implementation) — see `PROGRESS.md` for the full trace.

### Data

Agent records (`data.json` → `agents[]`) gain three fields: `username` (string, empty
= no dashboard access), `password_hash` (`password_hash()` output), `last_login_at`
(nullable timestamp, set only on successful login). No migration — existing agents
simply have no dashboard access until the admin explicitly sets credentials for them
via `admin/agent-edit.php`'s new "פרטי כניסה לדשבורד" fieldset. Every read of these
fields is defensive (`?? ''`/`?? null`), matching the rest of the codebase's style for
optional fields.

### Auth

A second, independent auth gate lives in `agent-portal/includes/auth.php`, structurally
identical to `admin/includes/auth.php` but keyed on its own session variables
(`agent_logged_in`, `agent_id`, `agent_name`) — deliberately **not** sharing any session
key name with the admin auth (including its brute-force lockout counters), since both
run under the one shared PHP session started in `includes/config.php`. An agent is
re-validated as active + credentialed on every request, so admin deactivating an agent
or clearing their username immediately ends any open session for that agent. Login has
the same 5-attempt/10-minute lockout and CSRF protection as the admin login, plus a
"זכור אותי" (remember me) option that extends the session cookie to 30 days, and a
show/hide toggle on the password field — both present on `admin/login.php` too, for
consistency.

### Pages

`agent-portal/index.php` (dashboard stats), `properties.php` ("הנכסים שלי" — list,
duplicate, delete, toggle-featured), `property-edit.php` (add/edit, including image
upload/reorder/cover, reusing `admin/includes/upload.php` unmodified), `leads.php`
("הפניות שלי"). All four are adapted from their `admin/*` equivalents with the same
markup/CSS (`admin/assets/admin.css`), but every list query and write path is scoped to
`$_SESSION['agent_id']` server-side — never a GET/POST-controllable value like the
admin panel's own `?agent=` filters use. Any attempt to read or mutate another agent's
record (via a guessed/direct URL, or a forged form POST) is treated identically to "not
found": silent redirect back to the agent's own list, never partial data exposure. This
is the one meaningful divergence from the admin panel's own CRUD pattern, where
`agent_id` is always a trusted, freely-editable value because only the single trusted
admin uses those pages.

### Public entry point

`includes/header.php`'s `.header-actions` shows a person icon (→ dashboard) + door icon
(→ logout) when an agent is logged in, or a single person icon (→
`agent-portal/login.php`) when not — the actual "login/logout icon" requested. The
footer's pre-existing "כניסת סוכנים" link (which had always pointed at the admin login,
a leftover mislabel) was relabeled "כניסת מנהל" to keep the two systems unambiguous.

### Explicitly out of scope (by design, not an oversight)

No agent self-service password reset/profile page in v1 — the admin sets/resets every
agent's credentials from `admin/agent-edit.php`, consistent with the admin keeping full
control over who has access. No CSV export from the agent dashboard (admin's export
already covers everything). No agent self-registration. All straightforward additive
extensions later if ever needed.