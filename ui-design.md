# UI-DESIGN.md — Visual design source of truth

## 0. Status of this document
**Extraction complete (2026-07-03).** Every `TODO` below has been filled by direct read-only
inspection of the local `Patrimo-BitHack` repository (Next.js 14.2 App Router + Tailwind 3.4
monorepo: `apps/web` frontend, `apps/api` Express backend, `packages/db` Prisma schema).
The old repo has **not** been deleted or archived yet — that step is gated on user confirmation
(see `Claude.md` §2.4).

---

## 1. Source of truth
- Legacy project: `Patrimo-BitHack` (Next.js + Tailwind, previous Node/Express/Prisma stack).
- Goal: the new Laravel + Filament app must be **visually equivalent** — same layout structure,
  color language, spacing rhythm and component look‑and‑feel — even though the implementation
  technology changes completely.
- Legacy app identity: title **"Patrimo — UBMA"**, wordmark **Patri**(teal‑800)**mo**(teal‑500)
  with subtitle "GESTION DU PATRIMOINE", logo `apps/web/public/logo-UBMA.png` (also used as favicon
  and PWA icon — copy this asset into the new app before archiving).

## 2. Design extraction checklist — DONE
- [x] `tailwind.config.js` → tokens extracted (see §3). Custom colors (Material Design 3‑style
      role names), borderRadius, spacing, fontFamily, fontSize scales. Plugins: `@tailwindcss/forms`,
      `@tailwindcss/container-queries`.
- [x] `globals.css` → no CSS variables; global styles: `body { bg-background text-on-surface font-body-md }`,
      Material Symbols variable-font settings, custom teal scrollbar (thumb `#0d9488` on `#f0fdf9`),
      `.map-bg` 20px grid, `.calendar-grid` (7-col, 120px rows), `.swiss-border` (`#e2e8f0` hairline),
      MapLibre popup overrides (white card, 10px radius, no tip arrow).
- [x] Route inventory under `apps/web/src/app` → see §5.
- [x] Component library: **none** (no shadcn/Radix) — hand-built Tailwind components: custom modals,
      custom `FilterSelect` (native `<select>` overlaid transparent on a styled chip), custom SVG
      donut charts, custom weekly timetable grid. Toasts via **sonner** (bottom-right).
- [x] Icon set: **Material Symbols Outlined** (Google variable font, weights 100–700, FILL axis;
      helper classes `.icon-fill`, `.icon-bold`). **Not** Heroicons — see §4 for the swap note.
- [x] Branding assets: `public/logo-UBMA.png`, `public/manifest.json` (PWA via `next-pwa`,
      service worker `sw.js`). Font: **Plus Jakarta Sans** via Google Fonts (400/500/600/700).
- [x] Breakpoints: desktop-first admin shell (sidebar `w-64` fixed, collapsible to `w-16`;
      mobile = overlay drawer + hamburger under `lg`). The QR report page (`/report/[code]`)
      is the one **mobile-first** screen: single centered card, `max-w-sm`, large touch targets.

## 3. Design tokens *(extracted)*
| Token | Value | Source |
|---|---|---|
| Primary color | `#004c4c` (deep teal); container `#006666`; fixed `#a2f0ef`; fixed-dim `#86d4d3`; surface-tint `#096969` | tailwind.config `colors.primary*` |
| Interactive teal accents | Tailwind default teal scale used directly in components: `teal-700 #0f766e` (links, active nav, map markers), `teal-600 #0d9488` (avatar bg, scrollbar), `teal-50 #f0fdfa` (active nav bg, badges) | component classes |
| Secondary | `#5b5f5f` (neutral gray-teal); container `#dde0e0` | tailwind.config |
| Tertiary (warning/pending accent) | `#693700` (brown-amber); container `#8c4a00`; fixed `#ffdcc3`; fixed-dim `#ffb77d` — used for "review"/pending badges and KPI accents | tailwind.config |
| Success | no custom token — Tailwind `green-600 #16a34a` (map free-slot badges), `teal-50/700` pairs (IN_SERVICE status) | component classes |
| Warning | Tailwind `amber-50/200/500/700` (MAINTENANCE status, already-reported card) + tertiary tokens | component classes |
| Danger / error | `#ba1a1a`; container `#ffdad6`; on-container `#93000a` (urgent badge bg = `#93000a`, urgent time-chip = `#ffdad6` bg / `#93000a` text) | tailwind.config |
| Background / surfaces | background & surface `#f9f9ff`; surface-container `#e7eeff`, -low `#f0f3ff`, -high `#dee8ff`, -highest `#d8e3fa`, -lowest `#ffffff`, dim `#cfdaf1`; **cards are plain white on `slate-50/‑100` page zones** | tailwind.config + components |
| Text | on-surface `#111c2c`, on-surface-variant `#3f4948`; components also use Tailwind `slate-400/500/600/700` heavily | tailwind.config + components |
| Outline / borders | outline `#6f7979`, outline-variant `#bec9c8`; in practice hairlines are `slate-200 #e2e8f0` ("swiss-border") and `slate-100` | globals.css + components |
| Font family | **Plus Jakarta Sans** (Google Fonts; weights 400/500/600/700), single family for all roles incl. "data-mono" (rendered `font-mono` only for ticket refs/inventory codes) | layout.tsx |
| Type scale | headline-lg 20/28 −0.02em 600 · headline-md 16/24 −0.01em 600 · body-md 14/20 400 · body-sm 13/18 400 · data-mono 13/18 500 · label-caps 11/16 +0.05em 700 UPPERCASE | tailwind.config `fontSize` |
| Base border radius | deliberately squared-off: DEFAULT `0.125rem` (2px), lg `0.25rem` (4px), xl `0.5rem` (8px), full `0.75rem` (12px). Shell dropdowns/cards use `rounded-lg/xl`; pills use `rounded-full` (12px) | tailwind.config `borderRadius` |
| Spacing scale | xs 4 · sm 8 · md 16 · lg 24 · xl 32 · gutter 16 · margin 24 (page padding `p-margin` = 24px) | tailwind.config `spacing` |
| Card/table shadow style | Tailwind defaults: cards `shadow-sm` (hover `shadow-md`), dropdowns `shadow-lg` + `border-slate-200`, map markers CSS `drop-shadow`; no custom shadow tokens | components |
| Scrollbar | thin, thumb `#0d9488`, track `#f0fdf9`, 6px, fully rounded | globals.css |
| Icons | Material Symbols Outlined, sizes 14–24px inline, `FILL` axis toggled for emphasis (urgent) | globals.css + components |
| Toasts | sonner, bottom-right, inherits font | layout.tsx |

**Shell specs** (replicate in the Filament layout): sidebar fixed `w-64` (collapse `w-16`), white,
`border-r slate-200`, logo header h‑72px, nav items `rounded-lg`, active = `bg-teal-50 text-teal-700`,
inactive = `text-slate-600 hover:bg-slate-50`; floating circular collapse toggle on the sidebar edge.
Top header `h-14 sticky bg-white/80 backdrop-blur border-b slate-200` with global search input
(`bg-slate-50`, `focus:ring-teal-500`), notification bell dropdown (`w-72 rounded-xl shadow-lg`),
profile dropdown with initials avatar (`w-8 h-8 rounded-full bg-teal-600`, white bold initials),
role label in `[10px]` uppercase tracking-wider.

## 4. Filament theming approach
- Use a **custom Filament theme** (`php artisan make:filament-theme`) built on §3 tokens, compiled
  via Vite — do not hand‑edit vendor CSS.
- Panel Provider `->colors([...])` mapping: `primary` → custom teal scale built around `#004c4c`/`#0f766e`
  (Filament needs a 50–950 scale; generate from these anchors), `gray` → slate, `danger` → `#ba1a1a`
  family, `warning` → amber/tertiary family, `success` → the green/teal pair, `info` → teal-600.
- Load **Plus Jakarta Sans** in the theme (Filament `->font('Plus Jakarta Sans')` with Google Fonts
  provider or self-hosted via Vite).
- **Icon set swap needed**: old app uses **Material Symbols Outlined**, Filament ships Heroicons.
  Approach: keep Heroicons for Filament-internal chrome (nav, actions) where shapes are near-identical,
  but use a Blade icon set for Material Symbols (e.g. a `blade-icons` Material Symbols package) for
  ported components (map, Kanban cards, report page) so those match the old app exactly. Decide the
  exact package in Phase 0; do not hand-inline SVGs.
- Radius: override Filament CSS vars in the theme to the squared-off scale (2/4/8/12px) so inputs,
  cards and badges match the old app's crisp look.
- Prefer Filament native components (Tables, Infolists, Forms, Widgets) — custom Livewire/Blade only
  for: campus map (MapLibre port), Kanban board, QR report page, weekly timetable grid, SVG donut
  widgets if Filament charts can't match (donuts are simple enough to keep as custom SVG Blade).

## 5. Page inventory for R13 Patrimoine *(confirmed against old repo routes)*
| Old route | What it is (old app) | Filament construct (new app) |
|---|---|---|
| `/login`, `/register` | auth screens, teal-branded card | Filament auth pages, themed (register likely disabled — accounts provisioned; confirm in Phase 1) |
| `/dashboard` | "Analytics": KPI cards, custom SVG **donut charts**, **live SLA countdown** (hh:mm:ss, OVERDUE state), recent activity | Filament Dashboard + stat widgets; port donut + countdown as small Blade/Livewire widgets |
| `/assets` | inventory list + Add/Edit/Affect modals | Resource `Equipment` → List (filters: status, category) + modal actions |
| `/assets/[id]` | detail: info card, **QR block (256px SVG) + PRINT QR** (popup → A4, mono code under QR), current affectation + revoke, event history timeline | Resource `Equipment` → View/Infolist + relation managers; QR via `simplesoftwareio/simple-qrcode`, same print flow |
| `/campus` | **CampusMap (MapLibre GL)** + building side panel: room list, weekly schedule grid per room | Custom Filament Page (Livewire) hosting the ported map — see §6 |
| `/tickets` | **drag‑and‑drop Kanban board** + filter chips + stats footer (MTTR, SLA compliance, pending approval, critical flares) | Custom Livewire Kanban (see §6) inside a Filament Page; evaluate `mokhosh/filament-kanban` first per `Phases.md` Phase 7 |
| `/tickets/[id]` | ticket detail: activity timeline, comment + photo modals, QR-scan description block | Resource `MaintenanceTicket` → View + relation manager for interventions/activity |
| `/reservations` | **custom weekly timetable grid** (`calendar-grid`), filters Faculté→Département→Spécialité, week nav, slot statuses confirmed/pending/changed | Split across three surfaces (Phase 5 addendum, 2026-07-06): `RoomReservationResource` (N2/A3 table — confirm/reject ad-hoc requests, list/edit timetable rows); `TimetableBuilder` custom page — **the visual grid, ported as-is** (6 fixed periods × Sat–Thu columns from `data.ts`'s exact seed values, §9.5), the primary way N2 (own faculty's departments) / A3 fills a department's timetable for a picked Academic Term, with a side "Add to timetable" panel matching the legacy `ReservationsView.tsx`'s Assignment Builder panel; `RequestReservation` — Enseignant's ad-hoc/one-off booking form. **`timetable` slots entered directly `confirmed`** — the faculty's emploi du temps; **`request` slots** ad-hoc, `pending` until N2/A3 confirms. `tout_utilisateur`/everyone sees the read-only `ReservationAvailability` grid (room-based, not department-based). |
| `/report/[code]` | **QR landing page, mobile-first**: auth-gated, shows asset summary + status badge, one-textarea report form, duplicate-ticket guard ("Already Reported" if active ticket), success screen with ticket ref | Plain Blade/Livewire route **outside** the admin panel, heavily rate-limited (`Security.md` §5) |
| `/purchases`, `/purchases/[id]`, `/purchases/[id]/print` | R7-ish module: suppliers, purchase requests, orders, reception PVs, print view w/ QR | **Out of R13 scope** — maps to Phase 10 integration stubs (`purchase_references`); do not rebuild the full module |
| `/settings` | user management (N3 only) | Filament user/role management via `filament-shield` UI |

Old sidebar nav (role-gated, in order): Analytics · Asset Management (A3/N2/N3) · Campus ·
Maintenance · Reservations · Achats Équipements (A3/N2/N3) · Settings (N3). Replicate order and
role gating via Shield permissions.

## 6. Component inventory *(confirmed from old app)*
- **Status badges**: tiny uppercase pills — `text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wider`.
  Urgent = `bg-[#93000a] text-white`; review/pending = `bg-tertiary-fixed text-on-tertiary-fixed-variant`;
  closed = `bg-slate-200 text-slate-500`; default = `bg-slate-100 text-slate-600`. Asset status pairs:
  IN_SERVICE teal-50/700, MAINTENANCE amber-50/700, STORAGE slate-100/600, DECOMMISSIONED red-50/600.
- **Time/SLA chips**: icon + label chip, urgent = `bg-[#ffdad6] text-[#93000a]` with `icon-fill`.
- **QR display + print**: `qrcode.react` `QRCodeSVG` 256px level M; printed QR encodes a **URL**
  `{origin}/report/{code}` (NOT the raw code — any phone camera opens it). Print = hidden SVG →
  `window.open` → A4 page with QR + monospace inventory code, auto `window.print()`. New app: same
  flow, but URL contains the **opaque `qr_codes.token`**, not the sequential inventory code
  (old app's `UBMA-YYYY-NNNN` codes were guessable — see §9).
- **QR scanner**: **none in-app** — the phone's native camera scans the printed URL QR. Keep this;
  no camera widget needed for v1.
- **Availability calendar (rooms)**: custom CSS-grid weekly timetable (6 columns Lun–Sam × 6 fixed
  slots 08:00–17:45), slot cards colored by status (confirmed = primary accent, pending = tertiary,
  changed = slate), week navigation, drafts before save. Not a calendar library — port as Livewire grid.
- **File/photo upload with preview**: ticket photo modal, equipment photos (string[] paths).
- **Interactive campus map — port as-is**:
  - Library: **MapLibre GL v5** via **react-map-gl v8** wrapper → new app: `maplibre-gl` JS directly
    inside a Livewire/Alpine component (no React), same options.
  - Tiles: `https://tiles.openfreemap.org/styles/bright` (no API key). Initial view: lng `7.7198301`,
    lat `36.8133517` (UBMA campus), zoom 17, **pitch 45**.
  - Controls: GeolocateControl (top-left, `trackUserLocation`), NavigationControl (compass +
    `visualizePitch`), ScaleControl (bottom-left, metric). Attribution shrunk to 10px/0.6 opacity.
  - Markers: custom **SVG rounded-rect flags** per building — teal `#0f766e`, width `max(80, name*7+16)`,
    h 46 + pointer triangle, building name (white 800), room count line, free-slots line
    (`{n} libres` green `#86efac` / `complet` dim), green `#16a34a` count badge top-right when > 0;
    selected = scale 1.08 + white inner stroke + stronger drop-shadow.
  - Hover tooltip: white card (name, faculty, `{n} salles`, free-slot line green/slate).
  - User location: pulsing blue dot (`#3b82f6`, CSS `pulse` keyframes).
  - Interactions: click marker → select building → side panel; "picking mode" = crosshair cursor,
    map click returns lat/lng (used when creating/editing a building's coordinates).
  - Data shape consumed: `Building { id, name, code, faculty, latitude, longitude, rooms: Room[] }`,
    `Room { id, name, code, type(amphi|classroom|lab|tp|office), capacity, floor, status, schedule: TimeSlot[] }`
    — keep the JSON contract identical when feeding from Laravel (`buildings` + `locals` per `Schema.md`).
  - MapLibre popup CSS overrides from `globals.css` (`.campus-popup`) come along.
- **Maintenance ticket board — drag‑and‑drop Kanban, port as-is**:
  - Drag tech: **native HTML5 drag & drop** — NO library. `draggable` cards, `dataTransfer`
    `text/plain` = ticket id, `onDragOver`/`onDrop` on columns. Livewire/Alpine can replicate 1:1.
  - Columns (old): `NEW → PLANNED → IN PROGRESS → REVIEW → CLOSED`; fixed width 320px, horizontal
    scroll; header = label-caps + count pill; tones: in-progress "active" (teal), closed "muted"
    (slate-400 + cards at `opacity-60`, code line-through). **New app keeps the interaction but uses
    `Schema.md` statuses** — see §9 mapping.
  - Drop target feedback: column gets `bg-teal-50 ring-2 ring-teal-400`; dragged card `opacity-40`;
    cursor `grab/grabbing`; 300ms just-dropped guard so the drop doesn't trigger card click-through.
  - Card anatomy: mono ticket code (teal-700), priority badge top-right, semibold title, location
    line, SLA time-chip bottom-left, assignee avatar bottom-right. Urgent in-progress cards get
    `border-l-4 border-l-teal-700`.
  - Behavior: optimistic move + rollback on error + sonner toast; then refetch. **Old app did a raw
    `PATCH {status}` with only role-string checks and no transition rules — the new build must route
    the drop through the permission-checked state machine instead** (`Phases.md` Phase 7).
  - Filters row: PRIORITY / STATUS / SORT chip-selects + CLEAR; stats footer: 4 KPI cards (MTTR,
    SLA COMPLIANCE, PENDING APPROVAL, CRITICAL FLARES) with big Material icons.
- **Dashboard widgets**: custom SVG donut charts (stroke-dasharray segments, 80px), live SLA
  countdown (`hh:mm:ss`, red OVERDUE state), KPI stat cards (label-caps gray label + headline number
  + colored icon right).
- **FilterSelect pattern**: white chip w/ label + value + `expand_more`, transparent native select
  overlaid — replicate look with Filament select filters (function equivalent, style via theme).
- **Modals**: hand-rolled centered cards — map to Filament modals/slide-overs; keep white,
  `rounded-xl`, slate borders.
- **Empty states**: big slate-300 Material icon + muted text (e.g. notifications, not-found).
- **Notification bell**: old app had a TopHeader bell + dropdown (`w-72 rounded-xl shadow-lg`,
  "0 new" counter, `notifications_off` empty state) — purely static. New app: Filament database
  notifications slide-over with a **realtime badge via Laravel Echo/Reverb** (bootstrap in
  Phase 1, see `Phases.md`). Realtime is notifications-only — the Kanban board, map and lists do
  NOT live-update; keep the old app's fetch/refresh behavior there.

## 7. Localization
- **English is the primary UI language** *(project-owner decision 2026-07-05, reversing the
  earlier French-first plan — and incidentally matching the old app, whose labels were mostly
  English: "Asset Management", "NEW TICKET"…)*.
- All strings still go through translation files — `lang/en/patrimoine.php` is primary,
  `lang/fr/patrimoine.php` is maintained in parallel so a French pass is a locale switch,
  never a refactor. Nothing hardcoded in Blade/Filament.
- Reserve `lang/ar` for a future Arabic/RTL pass.

## 8. Accessibility & responsiveness
- The QR‑scan/anomaly‑reporting flow (Étape 4) is the one screen guaranteed to be used on a
  phone in the field — it must be tested on mobile viewport first, not as an afterthought.
  (Confirmed: the old `/report/[code]` page is already mobile-first — single card, max-w-sm.)
- Keep contrast ratios AA‑compliant when finalizing the token palette, even where the old app
  didn't strictly enforce this (checked: primary `#004c4c` on white passes AAA; the `#93000a`
  urgent badge on white passes; watch out for `slate-400` on white in small text — old app uses it
  for meta text at 10–11px, which fails AA; bump to slate-500+ where Filament allows).

---

## 9. Extraction findings & discrepancies to carry into the rebuild *(new section — cross-reference log)*

### 9.1 Old stack (for the record)
Monorepo: `apps/web` Next.js 14.2 (App Router, `next-pwa`), `apps/api` Express + zod + bcryptjs
(cookie sessions, `sessions` table), `packages/db` Prisma + PostgreSQL. Demo mode shipped static
JSON snapshots under `apps/web/public/api/*`.

### 9.2 Kanban status mismatch (decision needed at Phase 7, mapping already agreed)
- Old board columns: `NEW / PLANNED / IN_PROGRESS / REVIEW / CLOSED`.
- New spec (`Schema.md` §2.8 + user instruction): `new → assigned → in_progress → resolved → closed`
  (+ `cancelled`, not a board column).
- Mapping: `PLANNED → assigned`, `REVIEW → resolved`. Interaction, layout and card design are ported
  1:1; only the status vocabulary follows `Schema.md`.

### 9.3 Security deltas the new build must fix (all already spec'd in `Security.md`)
- Old QR/report codes are **sequential and guessable** (`UBMA-2026-0001`); QR URL used the raw code.
  → New: opaque `qr_codes.token` (UUID) in the URL.
- Old drag-drop status change = raw PATCH, role check only (`A3` strings), **no transition rules**,
  and the **client** posted its own activity-log entries. → New: server-side state machine + policy +
  `activitylog` written server-side only.
- Old roles were a hardcoded Prisma enum `A3 | N2 | N3 | TEACHER` — **no Service technique, no
  Tout utilisateur**; frontend nav/role gates were string checks. → New: spatie roles as data,
  full 6-role matrix from `Security.md` §3.
- Old sessions lacked 2FA; register endpoint was open. → New: per `Security.md` §2.

### 9.4 Old Prisma → new `Schema.md` cross-reference
| Old model | New table | Notes |
|---|---|---|
| `Building` (faculty as string, lat/lng Float) | `buildings` | faculty becomes real FK scoping via `faculties`; keep lat/lng for map |
| `Room` (type: amphi/classroom/lab/tp/office) | `locals` | type enum widens per `Schema.md` (bureau, salle_cours, amphi, labo, atelier, entrepot, salle_reunion, autre) |
| `Asset` (+ `qrData` string, warranty fields, photos[]) | `equipments` + `qr_codes` | QR becomes its own polymorphic table with opaque token |
| `AssetAffectation` (responsibleName denormalized) | `assignments` | responsible becomes real `users` FK; adds service_id |
| `AssetEvent` | replaced by `spatie/laravel-activitylog` | |
| `Ticket` (denormalized badge/time UI fields) | `maintenance_tickets` | UI fields computed, not stored; adds SLA columns, source, category, assigned_service_id |
| `TicketActivity` | activity log / interventions | `interventions` is the structured replacement |
| `Reservation` (date + fixed `TimetableSlot`) | `room_reservations` (start_at/end_at + RRULE) | old system was slot-based; new is timestamp-based with recurrence for Enseignant |
| `TimetableSlot/Assignment/Professor/Course/ClassGroup/Speciality/AcademicDepartment` | out of R13 scope (R9-ish academic timetabling) | keep only what the reservation grid UI needs to render |
| `Supplier/PurchaseRequest/PurchaseOrder(+Line)/ReceptionPV` | `purchase_references` stub only | full module = R7, Phase 10 interface |
| `User` (role enum) / `Session` | `users` + spatie tables + Laravel sessions | |
| `Faculty` | `faculties` | |

### 9.5 Data points for open questions (`Schema.md` §6 — still `TODO(confirm)`, do not decide)
- Old app's week — **corrected 2026-07-06** (direct read of
  `apps/web/src/features/reservations/data.ts`'s exported `weekDays` constant, actually driving
  the `/reservations` grid, rather than the earlier note's secondhand impression): the UI grid
  genuinely renders `Sat, Sun, Mon, Tue, Wed, Thu` — it **matches** the Prisma `WeekDay` enum
  (Algerian working week, Friday off), it was not inconsistent after all. This resolves the day
  set for the **timetable grid UI** specifically (ported as-is in Phase 5's `TimetableBuilder`,
  see §5); the broader **SLA business-day calendar** question (holidays, exact working-day
  arithmetic) is a separate matter and stays open (`Schema.md` §6).
- Old reservation slots: 6 fixed daily periods `08:00–09:30 … 16:30–17:45` — ported as-is into
  `TimetableBuilder`'s grid rows (Phase 5 addendum, 2026-07-06); the underlying schema stays
  timestamp-based (`room_reservations.start_at/end_at`), the fixed slots are a UI-layer concept.
- No monetary threshold for PAdES/N3 found anywhere in the old app (its `ReceptionPV` had no
  threshold logic) — confirms the seuil remains an open question.

### 9.6 Assets to copy before archiving `Patrimo-BitHack`
- `apps/web/public/logo-UBMA.png` (logo/favicon/PWA icon)
- Nothing else is binary/unique — all styling is reproduced by this document; map tiles are a public
  URL; fonts come from Google Fonts.
- PWA (`next-pwa`, manifest, SW) existed in the old app; not in `Phases.md` scope — flag only if the
  university asks for installable/offline behavior on the report page.
