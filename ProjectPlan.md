# PersonnelPlanner - Full Product, UX, and Engineering Specification

## 1. Document Purpose

This document is intended to be sufficient for a product, design, frontend, and backend team to build the project from scratch in the intended form.

It defines:

- product vision
- scope boundaries
- UX and visual direction
- user flows
- feature requirements
- behavior rules
- backend architecture
- data model
- API contracts
- non-functional requirements
- acceptance criteria

This document should be treated as the single source of truth unless explicitly superseded by a later specification.

### Current Implementation Note

The project has not been built yet. The project directory is currently empty. This specification is the starting point for the initial build, not a record of an existing system.

The scheduling rules, shift patterns, and terminology in this document were derived in part from real weekly paper/Excel schedules currently used at the factory (Week 31-37, July-September 2026), photographed and reviewed with the user. Where this document conflicts with what the factory actually does in practice, defer to the user for clarification before implementing.

---

## 2. Product Vision

The product is a shift-scheduling platform for an industrial bakery that runs 3 production lines, 3 shifts per day, 7 days a week, with a mixed workforce of permanent staff (`vast`) and temp-agency staff (`uitzendkracht`).

The target experience is not "a spreadsheet replacement." The target experience is "a reliable planning tool that removes the manual, error-prone parts of building a legally-compliant weekly schedule, while keeping a human in control of every final decision."

The product should feel:

- dependable
- transparent about *why* a suggestion was made
- fast to review and adjust
- respectful of labor-law constraints without being rigid about them
- calm and utilitarian, not a dashboard full of vanity metrics

### Primary outcome

Let a planner generate a rule-aware draft weekly schedule across 3 lines and 3 shifts in minutes instead of hours, edit it freely, and have every employee automatically know what their week looks like.

### Secondary outcome

Give the factory a single, auditable record of who worked when, on which line, doing which task, and at what cost (permanent vs. agency), replacing the current paper/Excel process.

---

## 3. Target Users

Primary users:

- the **admin/planner** who builds and approves the weekly schedule, manages staff records, and configures scheduling rules
- **employees** (both `vast` and `uitzendkracht`) who need to know their own shifts, or their line's shifts, or the whole factory's shifts, depending on what the admin has granted them

Secondary users:

- factory leadership/management who want headcount and cost reports (permanent vs. agency cost split) without having to ask the planner directly

Important assumptions:

- the admin works primarily from a desktop/laptop browser to build and edit the schedule
- employees primarily check their schedule from a phone, often without wanting to install a native app from an app store - hence a PWA rather than native
- shift start times are not limited to a small fixed set - the real data shows many different start times (0:00, 2:00, 6:00, 7:00, 8:00, 9:00, 10:00, 11:00, 16:00, 17:00, 18:00, 19:00), so the system must not hardcode a small enum of shift times
- staff move between lines during the week - line assignment is a property of a single shift assignment, not a fixed property of the employee
- the leadership/management team (e.g. shown as "Leiding" in the current paper schedule) is not tied to any one line

---

## 4. Product Principles

### 4.1 UX Principles

- The planner should never have to build a schedule from a blank grid - the system always proposes a starting draft
- A rule violation is a visible warning, not a silent block - the planner can knowingly override it
- Employees should never need to ask "what does my line look like this week?" manually - the notification comes to them
- One primary workflow per screen: generate suggestion -> review/edit -> approve

### 4.2 Scheduling Principles

- Only an **approved** schedule is real to employees and to notifications; draft/proposed edits are internal planning noise
- A day with no assignment for a person is simply a day off ("vrij") - it is not a leave request and does not need approval
- Real leave (`Vakantie`, annual leave) and real sick leave (`ziek`) are the only statuses that must remove a person from the candidate pool automatically
- Every scheduling rule (starting with per-person max weekly hours) is enforced by an isolated, independently testable rule class, not inline conditionals scattered through the suggestion logic

### 4.3 Engineering Principles

- Thin controllers, business logic in services
- The rule engine is open for extension (new rule classes) without modifying existing rule classes
- Avoid hardcoding shift times, line counts, or rule thresholds where the real-world data has already shown they vary
- Notifications and PWA installability are additive layers on top of a clean core data model, not baked into the core tables

---

## 5. Scope

### 5.1 In Scope

- authentication (admin and employee roles)
- staff management (permanent and temp-agency employees, agencies)
- line and shift-pattern management
- weekly schedule generation (rule-based suggestion), manual editing, and approval
- leave/sick tracking that feeds into the suggestion engine
- per-person and company-level scheduling rules (starting with max weekly hours)
- weekly/monthly reporting and PDF/Excel export, split by permanent vs. agency cost
- employee-facing schedule view with three visibility levels (own / line / company), delivered as an installable PWA
- email and PWA Web Push notifications on approved-schedule changes
- full multi-language support (English, Turkish, Dutch, Spanish, Romanian, Ukrainian) for both admin and employee roles, selected at registration and changeable anytime

### 5.2 Out of Scope for Initial Build

- full constraint-solver/optimization-based auto-scheduling (this is a rule-based *suggestion* tool, not an optimizer)
- native mobile apps (iOS/Android app store builds)
- payroll processing or direct payroll system integration
- shift-swap marketplace / employee-initiated shift trading
- in-app chat between employees and planner
- multi-factory/multi-tenant support (single factory assumed)
- full Dutch ATW rule set beyond per-person max weekly hours (other ATW rules are architected for but not implemented - see §9.3)

---

## 6. Product Modules

The product is organized into eight product domains:

1. `Staff & Agencies` - employees, employment terms, temp agencies
2. `Lines & Shift Patterns` - production lines, shift time definitions
3. `Roles & Competency Matching` - line-specific and line-independent roles, employee qualifications
4. `Leave & Sick Tracking` - leave types, leave requests, approval
5. `Time & Attendance` - imported actual clock-in/out and break data, used for cost calculation
6. `Weekly Scheduling` - schedule generation, rule engine, suggestion, manual edit, approval, alternative-candidate lookup
7. `Reporting & Export` - headcount and cost reports, PDF/Excel export
8. `Employee Schedule View` - visibility-scoped schedule display (PWA) and notifications

There is also one internal support domain:

9. `Rule Engine & Notification Infrastructure`

---

## 7. Visual and Thematic Design

### 7.0 Brand

This build is for a real customer, **Bakkerij Visser**, an industrial bakery. The product carries their brand identity throughout the admin application (not a generic "PersonnelPlanner" skin) - name, wordmark, and the color direction below are Bakkerij Visser's, not a placeholder.

### 7.1 Design Theme

The visual direction should be:

- `warm industrial` - a bakery's own palette (deep brown, warm gold) rather than a generic cool-toned admin tool
- `operational clarity` - readable at a glance from across a factory office
- confident and branded, not a raw admin scaffold (e.g. default Laravel Nova/Filament styling left unstyled), while staying calm enough for a dense weekly schedule grid to remain legible

### 7.2 Color Direction

Primary (brand):

- deep brown `#3D2817` (darker variant `#2C1D10` for the sidebar header/footer bands, mid variant `#4E3420` for sidebar hover states)
- gold `#F5C518` (darker variant `#D4A017` for hover/active states) - the accent used for the active nav item, primary buttons, and key numbers

Secondary:

- warm cream/off-white background `#FAF9F5`, with a slightly deeper warm neutral `#F3F0E6` and hairline borders `#EBE7DF` for card separation
- amber-tinted neutrals for secondary badges and icon chips (e.g. `amber-50`/`amber-100` backgrounds with `amber-800`/`amber-900` text)

State colors:

- approved/confirmed/active: muted emerald green
- proposed/draft/pending/warning: warm amber (distinct from the brand gold - reserve brand gold for brand/action elements, not status)
- rule violation/rejected: controlled rose/red
- leave (Vakantie): distinct cool tone, consistent with the color-coding already used in the factory's paper schedule
- sick (ziek): distinct from leave, e.g. a warmer red-adjacent tone
- agency/uitzendkracht badge: a cool blue, deliberately outside the warm brand palette so permanent-vs-agency staff are visually unambiguous at a glance

Rules:

- keep the same color meaning consistent across the calendar grid, reports, and notifications
- avoid a rainbow-per-employee scheme - color should encode *status/type* (leave, sick, proposed, confirmed), not identity
- brand gold is for navigation/actions; state colors (green/amber/rose/blue) are for data - don't let the two systems collide (e.g. a pending badge is amber, not gold)

### 7.3 Typography Direction

- Inter as the primary typeface, with a bold/black weight reserved for headings, KPI numbers, and the wordmark
- strong distinction between a date/day header and cell content
- tabular figures for shift times, hour totals, and KPI numbers so columns of numbers align
- compact but legible at small sizes, since the core view is a dense weekly grid (3 lines x 3 shifts x 7 days)

### 7.4 Layout Direction

Rules:

- the admin application uses a fixed left sidebar for primary navigation (brand header at top, nav links, user profile/logout at the bottom) with a scrollable content area to its right, rather than a top nav bar - collapses to a slide-out drawer on mobile/tablet widths
- content is organized into rounded cards (`rounded-2xl`, hairline border, soft shadow) on the warm cream background (§7.2) - this card language is consistent across every admin screen (staff, lines, work stations & tasks, shift patterns, pay rate rules, leave requests), not just the dashboard
- the admin's weekly schedule view is desktop-first and grid-dense (rows = employees grouped by line, columns = days)
- the employee's schedule view is mobile-first and simplified (a single week, one's own or one's line's shifts only, by default)
- avoid forcing the admin grid layout onto the employee PWA view - they are different information densities for different audiences
- the employee-facing PWA (§15) keeps the same brand colors but does not need the sidebar shell - it's a focused single-purpose mobile view, not a multi-section admin app

### 7.5 Surface System

The UI should use only a few surface levels:

- `plain` (page background)
- `soft` (grouped panels, e.g. a line's block in the schedule grid)
- `elevated` (modals, the rule-violation warning panel, the approval confirmation)

### 7.6 Motion

Use subtle animation only for:

- a rule violation appearing/disappearing on a cell as the planner edits
- schedule status transitioning (draft -> proposed -> approved)
- a toast confirming a notification was queued/sent

Do not rely on decorative motion.

---

## 8. Frontend Architecture

Recommended frontend stack:

- React
- TypeScript
- React Router
- Tailwind
- TanStack Query (server state/cache for schedule data)
- `@dnd-kit/core` (drag-and-drop for the admin schedule grid - see §12.3a)
- vite-plugin-pwa (installable PWA, service worker, manifest)
- react-i18next (UI translation across the 6 supported locales - see §21a)

### 8.1 Frontend Requirements

- mobile-safe spacing on the employee view
- desktop-dense grid rendering on the admin view without horizontal scroll hacks that break usability
- route-based page architecture
- reusable design tokens and UI primitives shared between the admin and employee views
- no business-critical rule logic duplicated in the frontend - the frontend renders what the backend's RuleEngine already computed, it does not re-implement rule checks

### 8.2 Suggested Frontend Structure

- `src/pages`
- `src/components`
- `src/components/ui`
- `src/components/layout`
- `src/features/staff`
- `src/features/lines`
- `src/features/leave`
- `src/features/schedule`
- `src/features/reports`
- `src/hooks`
- `src/lib`
- `src/api`
- `src/types`
- `src/utils`

### 8.3 Shared UI Components

Required base components:

- Button
- IconButton
- Input
- Select
- DateRangePicker
- Badge (for status: draft/proposed/approved/leave/sick)
- Card
- WeeklyScheduleGrid (admin, dense, drag-and-drop enabled - see §12.3a)
- ScheduleAssignmentCard (the draggable unit within the grid: employee + shift + station)
- WeeklyScheduleList (employee, mobile-simplified)
- RuleViolationBanner
- EmptyState
- ConfirmDialog (used for approval, destructive edits)

---

## 9. Backend Architecture

Recommended backend stack:

- Laravel (latest stable), API-only
- PostgreSQL
- `spatie/laravel-permission` for role/authorization
- feature-first service modules with explicit service classes rather than fat controllers

### 9.1 Backend Design Rules

- Controllers validate, delegate to a service, and respond
- Business logic belongs in `Services/`, not controllers or models
- A `shift_assignment` only triggers a notification once it belongs to an **approved** schedule
- The rule engine must be extensible without editing existing rule classes (Strategy pattern, see §9.3)
- email/push sending must be queueable and must not block the HTTP request that triggered it

### 9.2 Suggested Module Structure

```
backend/
├── app/
│   ├── Http/Controllers/Api/V1/
│   ├── Http/Requests/
│   ├── Http/Resources/
│   ├── Models/
│   ├── Services/
│   │   ├── RuleEngine/
│   │   ├── Scheduling/
│   │   └── Notifications/
│   ├── Notifications/
│   ├── Jobs/
│   ├── Policies/
│   └── Exports/
├── database/{migrations,seeders,factories}/
└── routes/api.php
```

```
frontend/
└── src/{pages,components,features,api,hooks,lib,types,utils}/
```

### 9.3 Rule Engine Architecture

Strategy pattern under `app/Services/RuleEngine/`:

- `Contracts/SchedulingRule.php` - interface: `evaluate(EmployeeScheduleContext $context): RuleResult`
- `Rules/MaxWeeklyHoursRule.php` - the only mandatory rule for the initial build; reads a per-employee `employment_terms.max_weekly_hours` value (default 40, but overridable higher or lower per person)
- `Rules/MinRestBetweenShiftsRule.php`, `Rules/ConsecutiveNightShiftsRule.php`, `Rules/WeeklyMandatoryRestDayRule.php` - skeleton classes present in the codebase, registered but `enabled => false` in `config/scheduling_rules.php`. These represent future Dutch ATW (Arbeidstijdenwet) constraints (11-hour minimum rest between shifts, limits on consecutive night shifts, mandatory weekly rest) that are architected for now and activated later by filling in their logic - they must not be implemented with placeholder logic that silently passes or fails.
- `RuleEngine.php` - runs all config-enabled rules in sequence for a given candidate assignment and returns a `RuleResult` collection. A violation is surfaced as a UI warning, never a hard database constraint - the planner can consciously override it.

### 9.4 Suggested Service Layer

- `ScheduleSuggestionService` - generates a draft schedule (see §12)
- `RuleEngine` - evaluates scheduling rules (see §9.3)
- `ScheduleApprovalService` - transitions a schedule draft/proposed -> approved, and is the single trigger point for notifications
- `NotificationDispatchService` - wraps Laravel's Notification system for email + PWA Web Push, queued
- `ScheduleExportService` - PDF/Excel generation for reports

---

## 10. Page Map

Required routes (admin-facing, desktop):

- `/login`
- `/dashboard` - admin landing
- `/staff` - employee list/CRUD
- `/staff/:id` - employee detail incl. employment terms and qualified roles (§11a.3)
- `/agencies` - temp agency list/CRUD
- `/lines` - production line list/CRUD
- `/roles` - role list/CRUD (line-specific and line-independent, §11a.2)
- `/shift-patterns` - shift time definitions list/CRUD
- `/leave-requests` - leave/sick tracking and approval
- `/schedules` - weekly schedule list
- `/schedules/:id` - weekly schedule grid (generate/edit/approve)
- `/reports/headcount`
- `/reports/cost-breakdown`
- `/users/:id/visibility-scope` - admin sets an employee's schedule visibility level

Required routes (employee-facing, PWA, mobile):

- `/my-schedule` - schedule view, content depends on the user's `visibility_scope` (own / line / company)
- `/my-leave` - view own leave/sick history (optional in initial build, see open items)

---

## 10A. Wireframe-Level Screen Descriptions

### 10A.1 Admin Dashboard

Desktop order from top to bottom:

1. current week status strip (draft/proposed/approved, with a one-click "go to this week's schedule")
2. a row of KPI cards, each backed by real data this product actually has - no fabricated production/IoT metrics (e.g. no "daily bread output" or "oven temperature," since this product tracks scheduling, not manufacturing execution):
   - active employee count (with a vast/uitzendkracht split, e.g. "142 permanent · 22 agency")
   - employees with no linked account (§11.2b)
   - pending leave requests (§13)
   - per-line staffing rate for the current/selected week: assigned headcount vs. `requires_coverage` station slots that need filling, per line (this is the one legitimate "how full is line X" metric, since it's derived directly from `shift_assignments` and `scheduling_roles`, not invented)
3. a two-column section below the KPI row:
   - wider column: per-line staffing detail (a progress bar per line showing filled vs. required station coverage for the current week, linking through to that line's slice of the schedule grid)
   - narrower column: a quick-actions card (generate this week's suggestion, review pending leave requests, jump to reports) styled as the one dark/brand-colored panel on an otherwise light page (§7.2) - it's the deliberate visual anchor of the dashboard
4. shortcuts to staff, lines, shift patterns
5. recent reports

Layout rules:

- the current week's schedule status must be the single most prominent element
- pending leave requests must be visible without navigating away, since they block accurate suggestion generation
- every number on this screen must trace to a real query against this product's own tables - if a metric would require data this product doesn't collect (production volume, equipment sensors, etc.), it does not appear here, even as a placeholder

### 10A.2 Weekly Schedule Grid (Admin)

Desktop order from top to bottom:

1. week selector and schedule status (draft/proposed/approved) with an approve action, disabled with an inline reason while a mandatory-coverage station is unfilled (§12.3b)
2. "Generate Suggestion" action, scoped to selectable lines/shifts, with a `ranking_mode` toggle ("Fair" vs "Cost-based") the planner picks before generating
3. the grid itself: rows grouped by line (plus a leadership/unassigned group), columns = the 7 days of the week; a cell can show one or more assignments (each showing shift time, role/position label, and a station-vs-secondary-task visual distinction when a person holds both at once), be blank ("vrij"), or be **unfilled** (§12.2a - a station slot the suggestion engine could not staff), with unfilled mandatory stations visually distinct from unfilled optional ones
4. a rule-violation panel/banner that lists any current violations with a jump-to-cell link
5. an unfilled-slots panel listing every slot the suggestion engine could not staff, separating **blocking** (mandatory-coverage) from advisory ones, each expandable into its ranked alternative-candidates list (§12.2a)
6. per-employee weekly hour totals in a summary column/row

Layout rules:

- the grid must clearly distinguish `auto_suggested` vs `manual` assignments (e.g. a small icon or border style) so the planner can see what they changed
- a cell in Vakantie or ziek state must be visually distinct from a normal shift cell and from a blank "vrij" cell, which must in turn be visually distinct from an **unfilled** slot (nobody qualified/available was found, vs. nobody was ever needed there)
- when an employee holds a station for only part of a shift (a manual time-split, §11a.3b), the cell must show the split visually (e.g. two stacked segments with their time ranges) rather than implying they held one role for the whole shift
- editing a cell must show any new rule violation inline, immediately, without a page reload
- every assignment card in the grid is draggable; dropping it onto a different line/shift/day cell moves that person there - see §12.3a for the full drag-and-drop behavior
- clicking an unfilled slot (or any filled slot, to ask "who else could do this?") opens the ranked alternative-candidates list (§12.2a) inline or in a side panel, showing each candidate's exclusion reason and rank

### 10A.2a Roles (Admin)

Desktop order from top to bottom (list):

1. filter bar: line (or "line-independent"), role kind (station/secondary task)
2. table grouped by line (then a line-independent group), each row showing name, kind, and (for a station) whether it requires coverage, or (for a secondary task) its attachment mode and target

Desktop order from top to bottom (form):

1. name and line selection (or "line-independent")
2. role kind selector (station / secondary task) - selecting one changes which of the following fields appear
3. if station: a `requires_coverage` toggle (on by default)
4. if secondary task: an attachment mode selector (station-attached, with a station picker limited to the same line if one is chosen; line-attached; unattached)

Layout rules:

- the form must not show `requires_coverage` for a secondary task, and must not show the attachment mode selector for a station - these fields are mutually exclusive by role kind, not just conditionally relevant
- when attachment mode is "station-attached," the station picker should only list stations on the same line as this secondary task (or, if this task is being created as line-independent, only line-independent stations - though this combination is expected to be rare per §11a.2)

### 10A.2b Pay Rate Surcharge Rules (Admin)

Desktop order from top to bottom (list):

1. table: name, day(s) of week, time window, surcharge percentage, active status

Desktop order from top to bottom (form):

1. name
2. day(s) of week - multi-select (e.g. checkboxes for Mon-Sun)
3. start time / end time (a time range picker; crossing midnight is allowed and handled the same way as `shift_patterns`, §10A - no separate "crosses midnight" toggle needed, it's derived from the times)
4. surcharge percentage (numeric input, e.g. "25" for +25%)

Layout rules:

- the list should make it easy to spot overlapping rules at a glance (e.g. sorted by day, then start time) since §11.2c's "highest surcharge wins" behavior means overlaps are a normal, expected configuration, not a mistake to prevent
- this screen has no direct relationship to the employee list - it configures rules that apply automatically wherever they overlap a shift, not per-employee

### 10A.3 Staff List / Detail (Admin)

Desktop order from top to bottom (list):

1. filter bar: employee type (vast/uitzendkracht), agency, line, active/inactive
2. table: name, type, agency (if any), default line, active status

Desktop order from top to bottom (detail):

1. identity block (name, contact, phone, email)
2. linked account block: shows the currently linked `user` (name/email) if any, with a "Link account" search/dropdown (searching existing users by name/email) when none is linked, or an "Unlink" action when one is - see §11.2a
3. employment block (type, agency if applicable, hourly rate, default line)
4. employment terms block (max weekly hours, effective dates)
5. qualified roles block: a checklist/multi-select of all `roles` (grouped by line, plus a line-independent group, and within each group separated into stations vs. secondary tasks) showing which ones this employee is currently qualified for, editable inline
6. recent assignment history

Layout rules:

- employment terms must be visibly editable independent of the base identity fields, since they change on a different cadence
- the qualified roles block must make the line-specific vs. line-independent distinction visually obvious (e.g. grouped headers), since "Dough Prep - Line 1" and "Dough Prep - Line 3" look similar but are unrelated qualifications (§11a.2)
- the qualified roles block must also make the station-vs-secondary-task distinction visible, since a secondary task qualification is meaningless without an accompanying station qualification (§11a.3b)
- the linked account block reads as "not linked yet" (§11.2b) rather than an error - it's outstanding work for the admin to eventually resolve, not a broken state, and does not block anything on this screen

### 10A.4 Leave Requests (Admin)

Desktop order from top to bottom:

1. filter by status (pending/approved/rejected) and by type (Vakantie/ziek/other)
2. table: employee, type, date range, status, approve/reject actions

Layout rules:

- pending requests must be sorted to the top by default, since they are the actionable ones

### 10A.4a Time Clock Import (Admin)

Desktop order from top to bottom:

1. shape selector: "Simple" vs "Detailed" (§13a.3), chosen before a file can be uploaded
2. file upload control
3. column-mapping step: once a file is selected, its header row is shown so the admin can map file columns to the fields the chosen shape needs (employee identifier, date, and either clock-in/clock-out/break-minutes for Simple, or event-time/event-type for Detailed)
4. an import summary after processing: counts of imported/skipped rows, and a list of per-row errors with enough detail to fix and re-upload (§13a.4)
5. a plain list/table of already-imported entries below, filterable by employee and date range, each row editable inline (§13a.4's manual-correction path)

Layout rules:

- the shape selector must be answered before the column-mapping step appears - the two shapes need different columns, so showing both at once would be confusing
- per-row errors must reference the source file's row number, since that's what the admin will use to find and fix the problem in their original export

### 10A.5 Reports (Admin)

Desktop order from top to bottom:

1. date range selector (week or month)
2. headcount-per-line-per-shift summary
3. cost breakdown: permanent vs. agency, with a per-agency subtotal
4. export actions (PDF, Excel)

### 10A.6 Employee Schedule View (PWA, Mobile)

Mobile order from top to bottom:

1. current week label, with prev/next week navigation
2. today's shift highlighted at the top if one exists this week
3. a simple 7-day list (not a dense grid): day, shift time, line, task if applicable, or "day off" / "vacation" / "sick" state
4. (if `visibility_scope` is `line` or `company`) a toggle or section grouping to see other employees' shifts within the granted scope

Layout rules:

- default view on load is always "my own shifts", even if the user has broader visibility - broader scope is an expansion, not the default
- this screen must be installable as a PWA and usable offline for the currently-cached week

### 10A.6a Account Creation

Mobile/desktop order from top to bottom (this screen is used by both an admin creating their own account and an admin creating an employee's account, so it must work at both sizes):

1. standard identity fields (name, email, password, phone)
2. language selector - one of the six supported locales (§21a.4), required, no default selected
3. a short, one-sentence explanation of why installing the app matters ("Install this app on your phone to get notified the moment your schedule changes.") followed by the platform-specific install step (§21.2a):
   - on a detected Android/Chrome-family browser: a single "Add to Home Screen" button
   - on a detected iOS/Safari browser: a small numbered visual guide (Share icon → "Add to Home Screen")
4. the account-creation submit action

Layout rules:

- the install step must read as a helpful recommendation alongside registration, not a gate - the submit action is never disabled by whether the employee has installed the app yet
- the install step's copy and visuals must themselves be localized once a language is picked in step 2, consistent with the rest of the product (§21a.3)

---

## 11. Staff & Agencies Module Specification

### 11.1 Goal

Maintain an accurate, typed record of every person who can be scheduled, including which temp agency they come through if any, and what their personal scheduling limits are.

### 11.2 Required Behavior

- an employee is either `vast` (permanent) or `uitzendkracht` (temp-agency); this is a single enum field, not a role hierarchy, because scheduling rules apply identically to both types (confirmed with the user) - the only difference is cost and agency attribution
- `flex` and `VZM`, observed as informal labels in the factory's current paper schedule, are both temp-agency categories and are represented as rows in the `agencies` table, not as special-cased employee types
- an agency-linked employee must have an `agency_id` and an `hourly_rate` (temp-agency staff are always billed hourly)
- a permanent (`vast`) employee has a `pay_type` of either `hourly` (with `hourly_rate` set) or `monthly` (with `monthly_salary` set), so cost-based scheduling (§12.2) has a real per-hour cost to compare even for salaried staff (a monthly salary is converted to an effective hourly cost using the employee's contracted hours, for ranking purposes only - it is not a payroll calculation)
- a `default_line_id` on an employee is a weighting/default hint only for the suggestion engine - it never hard-restricts which line an employee can be assigned to, since the real schedules show staff moving between lines within the same week
- leadership/management staff (e.g. "Leiding" in the current paper schedule) are modeled as employees with `default_line_id = null`
- separately from `default_line_id`, each employee has an explicit set of **qualified roles** (see §11a) - which line-specific or line-independent positions they are allowed to be automatically scheduled into

### 11.2a Employee-User Account Linking

An `employee` (the scheduling record: name, type, agency, pay, qualifications) and a `user` (the login account: email, password, locale, visibility_scope, role) are created independently, in either order, and are not linked automatically. The relationship is asymmetric: a `user` is not required to have a linked `employee` (e.g. an admin-only account with no scheduling record of its own), but every `employee` is **expected**, eventually, to have a linked `user` - that's the whole point of the link, since it's what lets that person log in and see their own schedule (§15).

- an admin can create an `employee` record with no linked account yet - this is a normal, temporary state (the account may not be set up yet), not a permanent one, and is surfaced accordingly (see below), not silently accepted as fine forever
- an admin can separately create a `user` account (their own, or on behalf of an employee) at any time, independent of whether an `employee` record exists yet
- linking the two is a **manual, explicit admin action**: from the employee detail screen (§10A.3), the admin selects an existing `user` from a search/dropdown and assigns it to that employee's `user_id` (§17.3) - there is no automatic matching by email or any other heuristic, since the user explicitly confirmed this should stay under admin control rather than being inferred
- unlinking is equally explicit - the admin can clear an employee's linked `user_id` without deleting either record
- a `user` can be linked to at most one `employee` (enforced as a unique constraint on `employees.user_id` where not null); an `employee` has at most one linked `user_id` by definition (it's a single column)
- until an employee is linked to a user account, that person has no way to log in and see their own schedule - they still appear normally in the admin's scheduling grid and reports, since scheduling never depends on the link, but this state is visible to the admin as outstanding work (see §11.2b), not hidden
- this is deliberately **not** a hard block anywhere: an unlinked employee can still be scheduled, and a schedule can still be approved, with unlinked employees in it - the visibility described in §11.2b is informational, prompting the admin to eventually close the gap, never preventing anything

### 11.2b Surfacing Unlinked Employees

Because every employee is expected to eventually have a linked account, the admin needs to see at a glance who's missing one, without it blocking anything:

- the staff list (§10A.3) shows a distinct badge (e.g. "No account") on any employee row with `user_id = null`
- the admin dashboard (§10A.1) shows a count of employees with no linked account, alongside the other outstanding-work counters already specified there (pending leave requests, rule violations)
- this count and badge are purely informational - no workflow in this product requires resolving it before proceeding (see §11.2a)

### 11.2c Pay Rate Surcharge Rules

The base `hourly_rate`/`monthly_salary` on an employee (§11.2) is not the whole cost picture: the user confirmed that pay is higher for certain days and time windows (e.g. weekends, night hours), and rather than hardcoding a fixed set of bands (weekday/night/Saturday/Sunday), the admin defines this as its own configurable rule table - a **pay rate surcharge rule** - so the specific bands and percentages can be changed without a code change.

- a **surcharge rule** consists of: which day(s) of the week it applies to (multi-select, e.g. "Saturday", or "Monday-Friday"), a start time and end time (may cross midnight, e.g. 18:00-06:00), and a surcharge percentage (e.g. 25 meaning +25% over the base rate)
- surcharge rules apply to **both** `pay_type = hourly` and `pay_type = monthly` employees - for hourly employees they increase the effective rate for the overlapping portion of a shift; for monthly employees they apply as an overtime-style premium on top of the salary-derived effective hourly cost (§11.2), for the overlapping portion only - a monthly employee's fixed salary itself never changes, only what a surcharge-covered shift is considered to *cost* for scheduling/reporting purposes
- a rule is not all-or-nothing against a whole shift: if a shift only partially overlaps a rule's time window (e.g. an 8-hour shift where only the last 2 hours fall in a 18:00-06:00 window), the surcharge applies only to the overlapping hours - the shift's cost is computed by splitting it into segments against all applicable rules and summing the segment costs
- **when multiple rules overlap the same time segment** (e.g. a Saturday-night hour matches both a "Saturday" rule and a "nightly 18:00-06:00" rule), only the single **highest** surcharge percentage among the overlapping rules applies to that segment - surcharges do not stack/add
- a segment with no applicable rule is costed at the employee's plain base rate (0% surcharge)
- this feeds directly into `cost`-mode ranking (§12.2) and into cost reporting (§14): the "effective hourly cost" for a given candidate slot is no longer a single static number per employee, but is computed per shift instance from the base rate plus whatever surcharge rules overlap that specific day/time

### 11.3 Acceptance Criteria

- an admin can create, edit, and deactivate an employee of either type
- an admin can create and edit agencies, and link/unlink an employee to one
- an admin can link an existing user account to an employee record, and later unlink it, without either record being deleted
- creating an employee record never requires a user account to exist, and creating a user account never requires an employee record to exist
- an employee with no linked account is visibly flagged (staff list badge, dashboard count), without being blocked from scheduling or reporting
- an admin can set and later change an employee's `max_weekly_hours` without losing the history of the previous value (see §17, `employment_terms`)
- an admin can view and edit an employee's qualified roles (§11a) from the employee detail screen
- an admin can create, edit, and deactivate pay rate surcharge rules (day(s), time window, percentage), and a shift's computed cost reflects whichever rules overlap it, splitting and summing by segment where a shift only partially overlaps a rule

---

## 11a. Roles & Competency Matching Module Specification

### 11a.1 Goal

Model the fact that not every employee can safely or legally be put into every position, and stop the suggestion engine from proposing assignments outside what a person is actually qualified to do - while still letting the admin override this manually when they know better than the system does.

### 11a.1a Setup Order

The admin configures the factory's structure in a specific, dependency-driven order, reflected in the Build Order (§24) and enforced loosely by the UI (later screens reference earlier ones):

1. **Lines** (§17.5) - the physical production lines exist first, since almost everything else attaches to one.
2. **Work stations** (`role_kind = station`, §11a.2) - created next, each usually (not always) tied to one line, so line-based cost and headcount reporting (§14) can attribute a station's cost to its line.
3. **Secondary tasks** (`role_kind = secondary_task`, §11a.2) - created after stations, since a secondary task can optionally reference a station, a line, or neither (§11a.2a).
4. **Employees** (§11) - created last among these, since qualifying an employee (§11a.3) requires the stations and secondary tasks to already exist to check boxes against.

### 11a.2 Role Types

A **role** represents a position an employee can be assigned to, and comes in two kinds along two independent axes.

**By scope:**

- **line-independent roles** - apply across the whole factory regardless of which line the shift is on (e.g. "Shift Supervisor" / "Vardiya Şefi")
- **line-specific roles** - apply only to one particular line, and qualification on one line does not imply qualification on another (e.g. "Dough Prep - Line 1" and "Dough Prep - Line 3" are two separate roles, even though they sound like the same task - an employee qualified for one is not automatically qualified for the other, since the user explicitly confirmed a person qualified for dough prep on one line may not be qualified on a different line)

**By kind** (`role_kind`):

- **station** - a physical work position on the line that the shift genuinely cannot run without (e.g. "Dough Prep", "Cutting", "Dipping"). Almost always line-specific in practice (so line-based cost/headcount reporting, §14, can attribute its cost correctly), though the data model does not forbid a line-independent station. A station role is flagged `requires_coverage = true` by default and drives the mandatory-coverage behavior in §11a.3a. An employee can hold exactly one station at any given moment in time within a shift (§11a.3b) - stations are mutually exclusive at the same instant.
- **secondary task** - an additional responsibility layered onto whoever is already working a station during that time (e.g. "Silo Watch," "Sourdough Lead"). A secondary task assignment always requires the employee to already hold a station assignment for the overlapping time window - it cannot exist on its own. Unlike a station, a secondary task's *attachment* is one of three independent modes, set on the role itself via `attachment_type`:
  - **station-attached** (`attachment_type = station`, with `attached_station_role_id` set) - only makes sense alongside one specific station (e.g. a task that only applies while working "Dipping" on Line 3)
  - **line-attached** (`attachment_type = line`, with `line_id` set) - can be layered onto any station on that one line, but not on other lines
  - **unattached** (`attachment_type = none`) - fully independent of line or station, available to be layered onto anyone holding any station anywhere (e.g. "Shift Supervisor")

This replaces the earlier free-text `station` field (previously seen on `shift_assignments`, e.g. `"deeg"`, `"snij"`, `"doop"`, `"draai"`) with a structured, queryable concept: those station labels observed in the factory's paper schedule become actual `roles` records with `role_kind = station`, most of them line-specific.

### 11a.3 Employee-Role Qualification

- an employee can be qualified for any number of roles, via a simple many-to-many mapping (`employee_line_roles`, despite the name covering both line-specific and line-independent roles - see §17)
- the suggestion engine (§12.2) will only ever automatically propose an employee for a role they are qualified for
- an admin can add or remove an employee's qualifications at any time from the employee detail screen (§11.3) or from a dedicated role-management screen

### 11a.3a Mandatory Coverage for Station Roles

- when creating or editing a **station** role, the admin sets `requires_coverage` (default `true` for stations, always `false` for secondary tasks): if enabled, the suggestion engine must place at least one qualified, rule-compliant employee into that station for every shift it is scheduled to run, and **the schedule cannot be approved while a `requires_coverage` station has zero assignments** for a shift it covers - this is a hard block on approval, not a dismissible warning, since the user was explicit that a mandatory station must always have someone assigned
- if `requires_coverage` is on and the suggestion engine's candidate pool for that station/shift/day is empty, the schedule is not silently left incomplete - it surfaces in the unfilled-slots panel (§12.2a) as a blocking item, distinct from a normal (non-blocking) unfilled slot, and the planner must resolve it (via the alternative-candidates list, a manual assignment, or an explicit qualification override) before approval will succeed
- a **secondary task**'s coverage is never mandatory in this sense - `requires_coverage` is not offered as an option when `role_kind = secondary_task`, since a secondary task's presence depends entirely on whether the underlying station assignment exists

### 11a.3b Multiple Roles Per Person & Time-Split Assignments

- a single employee can hold **more than one role within the same shift**, but never more than one **station** at the exact same moment - stations are time-exclusive, secondary tasks are not (an employee can hold one station plus any number of secondary tasks concurrently, since a secondary task rides on top of an existing station assignment rather than competing with it)
- a `shift_assignment` for a station role may cover only part of a shift's full time range: the admin can manually split a shift into consecutive, non-overlapping time segments and assign a different station to each (e.g. Ahmet on "Dough Prep" for the shift's first 2 hours, then "Cutting" for the remaining 6) - see the `starts_at`/`ends_at` fields added to `shift_assignments` in §17.8
- the suggestion engine itself never generates a time-split assignment automatically - splitting a shift into multiple station segments is always a manual, deliberate admin action (via the assignment form or by editing an existing assignment's time range); the engine's automatic suggestions always cover a station role for the assignment's full shift duration
- the application layer must reject any attempt to create two station assignments for the same employee with overlapping time ranges on the same day, while explicitly allowing overlapping **secondary task** assignments (since those don't compete with a station) and allowing a secondary task assignment to overlap its own underlying station assignment (that's the expected, required relationship)
- a station role can have **more than one employee assigned to it at the same time** - `requires_coverage` guarantees a minimum of one via the suggestion engine, but the admin can manually add additional people to the same station/shift/day without limit

### 11a.3c Secondary Task Assignment: Fairness & Line Priority

Once the suggestion engine has filled all station slots for a day/shift (§12.2, step 3), it makes a second pass to auto-assign secondary tasks, governed by two rules working together:

- **Line priority first**: for a **line-attached** secondary task (§11a.2), the candidate pool is restricted, before ranking, to employees who already hold a station assignment on that task's line for the overlapping time window - an employee working a different line that shift is never considered for it. A **station-attached** task is restricted the same way, but further narrowed to employees currently holding that exact station. An **unattached** task's candidate pool is simply everyone qualified and already holding some station that shift (any line).
- **Fairness second**: within whatever candidate pool the priority rule above produces, the employee with the fewest total secondary-task assignments **so far**, counted across all secondary task types combined (not per-task-type - confirmed by the user, since a person who picks up many different small tasks should still be treated as already carrying a fair share), is selected. This reuses the same `fair` scoring concept as station assignment (§12.2) but with a separate counter - a person's number of station shifts and their number of secondary-task assignments are tracked and balanced independently, since one is mandatory coverage and the other is supplemental load.
- Both rules apply regardless of the schedule's `ranking_mode` (§12.2) - fairness for secondary tasks is not affected by choosing `cost` mode for station assignment, since cost-based station selection and fair secondary-task distribution are answering different questions (who's cheapest to staff a mandatory position vs. who's least loaded with extra duties).
- Like stations, this automatic pass only ever assigns **qualified** employees (`employee_line_roles`, §11a.3); if no qualified, line-eligible candidate holds a station that shift, the secondary task slot is left unfilled and appears in the unfilled-slots panel (§12.2a) as advisory (never blocking - only station `requires_coverage` blocks approval, §12.3b).
- the planner can always manually reassign a secondary task afterward, same as any other assignment (§12.3, §12.3a)

### 11a.4 Admin Override

- the suggestion engine's role restriction is a **soft rule for automatic suggestions only** - it is not a hard database constraint on `shift_assignments`
- an admin can still manually assign (via the assignment form, §12.2a, or via drag-and-drop, §12.3a) any employee to any line/role combination they are not qualified for; this is flagged the same way other manual overrides are (visually distinct, logged), but never blocked
- assigning an employee to a role they aren't qualified for does **not** automatically grant them that qualification going forward - qualification is managed explicitly, separately from any single assignment

### 11a.5 Acceptance Criteria

- creating a role can be scoped to one specific line or left line-independent
- creating a role sets its `role_kind` (station or secondary task); `requires_coverage` is only configurable for stations
- the suggestion engine never proposes an employee for a line-specific role they aren't qualified for on that specific line
- an admin can still manually place an unqualified employee into a slot, with the system clearly indicating this is a qualification override
- when no qualified candidate exists for a slot, the system surfaces the ranked alternative-candidate list from §12.2a instead of silently leaving the slot empty
- a schedule with an empty `requires_coverage` station for a scheduled shift cannot be approved until it is resolved
- an employee can never end up with two overlapping station assignments on the same day, but can hold a secondary task at the same time as their station, and can hold a station across a split time range with a different station before/after
- a station can have multiple employees manually assigned to it at once, even though the suggestion engine only guarantees one

---

## 12. Weekly Scheduling Module Specification

### 12.1 Goal

Let a planner generate a rule-aware draft for a given week across 3 lines and 3 shifts, edit it, and approve it - replacing the manual paper/Excel process while keeping the planner as the final decision-maker.

### 12.2 Suggestion Generation Flow

`ScheduleSuggestionService`:

1. Input: `week_start_date`, which lines/shifts/roles to generate for, and a `ranking_mode` chosen by the planner: `fair` or `cost` (this mode governs **station** assignment only - secondary-task assignment always follows the fixed fairness logic in §11a.3c regardless of `ranking_mode`).
2. Build the eligible candidate pool per slot: `employees.is_active = true` AND no approved leave/sick record covering that specific day (`leave_requests.start_date <= work_date <= end_date AND status = 'approved'`), computed independently per day since a person may be eligible some days of the week and not others, **AND qualified for the slot's role** per `employee_line_roles` (§11a.3) - a slot for a line-specific role only considers employees qualified for that exact line+role combination; a slot for a line-independent role considers anyone qualified for that role regardless of line.
3. For each day x line x shift x **station**-role slot: run the `RuleEngine` against each qualified candidate (would assigning this slot exceed this person's `max_weekly_hours`?), drop rule-violating candidates, then rank the remainder according to `ranking_mode` and select exactly one employee for that slot (the engine always aims to satisfy `requires_coverage` with one person per station - a manual step is required afterward to add a second person to the same station, per §11a.3b):
   - `fair` - fewest hours assigned so far this week wins (the original, default fairness score)
   - `cost` - lowest computed cost wins for that specific slot's day/shift: the candidate's base rate (agency `hourly_rate`, or permanent staff's `hourly_rate`/salary-derived effective hourly cost, per §11.2) plus whatever pay rate surcharge rules overlap that slot's time segments (§11.2c) - this is why cost is computed per slot instance rather than looked up as a flat per-employee number; ties broken by the `fair` score so cost mode does not repeatedly overload the single cheapest person
4. Once all station slots for a day/shift are resolved (filled or flagged unfilled), make a second pass to auto-assign **secondary task** slots per the line-priority-then-fairness logic in §11a.3c.
5. Write the selected assignments into `shift_assignments` with `status = proposed`, `source = auto_suggested`, each covering the slot's full shift duration (`starts_at`/`ends_at` left null - see §17.8).
6. The planner reviews the grid, edits freely (any manual change is marked `source = manual`; the `RuleEngine` re-evaluates on every edit and updates the violation panel), then calls "Approve," which transitions `schedules.status = approved` and triggers §12.4 - unless a mandatory-coverage station is still unfilled, per §12.3b.

This is a slot-by-slot ranking choice, not a full-week cost optimizer (no constraint solver) - consistent with the rule-based *suggestion* approach in §5.2, just with a second ranking criterion the planner can pick.

### 12.2a Unfillable Slots & Alternative Candidates

If step 2 above produces zero qualified, rule-compliant candidates for a given day x line x shift x station slot, the suggestion engine does not just leave it silently blank:

- the slot is flagged as **unfilled** in the resulting draft, distinct from an intentionally-unstaffed slot; if the station's `requires_coverage` is `true` (§11a.3a), it is additionally flagged as **blocking** - it will prevent approval per §12.3b until resolved, whereas a non-mandatory unfilled slot is advisory only
- the API response and the schedule grid surface a **ranked alternative-candidates list** for that specific slot: every employee who is *not* qualified for that exact line+role (or who *would* violate a rule, e.g. exceed `max_weekly_hours`), ordered by the same `ranking_mode` the planner chose (fair or cost), each annotated with *why* they were excluded from the automatic pool (e.g. "not qualified for Dough Prep - Line 3", or "would exceed weekly hours by 4")
- this alternative list is advisory only - selecting someone from it and assigning them is a manual action (via the assignment form, §12.2b, or drag-and-drop, §12.3a) and is recorded as `source = manual` like any override
- the same alternative-candidate lookup is available on demand for any already-filled slot too (not just unfilled ones), so the planner can ask "who else, ranked, could go here?" before deciding to move someone via drag-and-drop

### 12.2b Manual Assignment Screen

In addition to editing cells directly in the grid or dragging cards, the admin has a dedicated assignment form (accessible from a grid cell or the alternative-candidates list) to assign a specific employee to a specific day/line/shift/role slot:

- the form defaults to showing only qualified, rule-compliant candidates for the selected slot
- a "show all employees" toggle reveals the full staff list, including unqualified employees, clearly marked as requiring a qualification override (§11a.4)
- submitting with an unqualified employee selected requires an explicit confirmation step (not a silent save), to avoid accidental qualification-bypassing assignments

### 12.3 Manual Editing Rules

- a rule violation blocks nothing - it is a visible warning only
- every edit re-runs the affected employee's rule checks so the violation panel is always current
- an approved schedule can still be edited afterward; each post-approval edit re-triggers the notification flow (§12.4) for the affected employee(s)

### 12.3a Drag-and-Drop Editing

Once a suggestion has been generated (or at any later point while editing an existing schedule), the planner can move a person's assignment visually instead of using a form:

- every assignment card in the `WeeklyScheduleGrid` (§8.3) is draggable
- dropping a card onto a different line/shift/day cell re-assigns that person to the new `line_id`, `shift_pattern_id`, and/or `work_date` - a single drag can change any combination of the three (e.g. moving someone from Lijn2's morning shift on Tuesday to Lijn1's evening shift on Wednesday in one action)
- a drag-and-drop move is a `shift_assignments` update like any other manual edit: it is marked `source = manual`, the `RuleEngine` re-evaluates the moved employee immediately, and the violation panel updates without a page reload (§16.4)
- dropping onto an occupied cell does not silently overwrite the existing assignment - the UI must make clear whether the drop adds a second person to that slot or is rejected, depending on whether the slot still has open headcount (see open item on required headcount per slot, §26)
- dragging a card onto a blank cell ("vrij" for that person that day) creates a new assignment there; dragging a card off the grid entirely (e.g. onto a "remove" drop zone) deletes that assignment, returning the person to "vrij" that day
- dropping a card onto a cell whose line+role the dragged employee is not qualified for (§11a) does not get silently rejected or silently allowed - the drop shows an inline confirmation ("Ahmet is not qualified for Dough Prep - Line 3 - assign anyway?") before committing, so a qualification override via drag-and-drop is always a deliberate, visible decision, consistent with §11a.4
- drag-and-drop is available on the admin desktop grid only - it is not part of the employee-facing PWA view (§15), which is read-only

### 12.3b Approval Blocking for Mandatory Coverage

This is the **one exception** to the "violations are warnings, never blocks" philosophy that governs the rest of this spec (§4.2, §16.4): a `requires_coverage` station role (§11a.3a) with zero assignments for a shift it is scheduled to run makes that schedule **impossible to approve**, full stop - the "Approve" action is disabled and the reason is shown inline, pointing at the specific unfilled mandatory station(s) via the unfilled-slots panel (§12.2a).

This is a deliberate, narrow exception: the user was explicit that a station the shift genuinely cannot run without must always have someone assigned, unlike every other constraint in this system (max weekly hours, role qualification) where the admin's judgment can override the system's. The planner resolves a mandatory-coverage block the same way as any unfilled slot - accept an alternative candidate (§12.2a), manually assign someone including via a qualification override (§11a.4), or reduce the shift's planned coverage requirement - but cannot simply dismiss the warning and proceed.

### 12.4 Notification Trigger

- a notification fires only for changes to an **approved** schedule (including the moment of initial approval), never for draft/proposed edits, to avoid spamming employees with every planning iteration
- the affected employee is the one whose `shift_assignments` row changed; if their `visibility_scope` is `line` or `company`, a change to another employee sharing their line during a week they're scheduled may also warrant a notification (see open items, §26)

### 12.5 Acceptance Criteria

- a planner can generate a full-week draft in under a few seconds for a normal-sized workforce
- the suggestion never proposes a slot for someone with approved leave/sick covering that day
- the suggestion never proposes a slot that would push someone over their personal `max_weekly_hours` (unless the planner manually overrides afterward)
- approving a schedule is a single explicit action, and cannot be un-done silently
- a planner can drag a person's assignment card from one line/shift/day cell to another and see the change persist and re-validate immediately

---

## 13. Leave & Sick Tracking Module Specification

### 13.1 Goal

Track real absences (`Vakantie`, `ziek`) so the suggestion engine automatically excludes affected employees, without turning every day-off ("vrij") into a bureaucratic request.

### 13.2 What Is *Not* a Leave Request

A day where an employee simply has no shift assigned ("vrij" in the current paper schedule) is not a leave request and does not require approval. It may originate from a standard weekly rest day or an informal short personal reason - the system does not need to distinguish these; the absence of a `shift_assignments` row for that employee/day is sufficient.

### 13.3 What *Is* Tracked

- `Vakantie` (annual leave) - a date range, requires approval, must exclude the employee from the suggestion pool for every day in range
- `ziek` (sick leave) - a date range, may be entered retroactively or as it's reported, must exclude the employee from the suggestion pool for every day in range
- an optional "Short Excuse" leave type exists for the personal short-notice absences the user described, but its use is optional - a planner can still just leave a day blank instead

### 13.4 Acceptance Criteria

- an admin (or an employee, if self-service is later enabled - see open items) can record Vakantie/ziek with a date range
- a pending leave request can be approved or rejected
- an approved leave/sick record for a given day always removes that employee from that day's suggestion candidate pool

---

## 13a. Time & Attendance Module Specification

### 13a.1 Goal

Employees take breaks during a shift (e.g. a lunch break) that are unpaid - the planned shift time is not what actually gets paid. This module imports real clock-in/clock-out and break data per employee so that cost calculations (§11.2c, §14) are based on actual worked time, not the planned `shift_assignment` time.

### 13a.2 Relationship to Planned Shifts

Real attendance data is tracked in its own table (`time_clock_entries`), separate from `shift_assignments` (the planned schedule) - the two represent different things (planned vs. actual) and must not be conflated in one table:

- a `time_clock_entries` row is matched to an `employee_id` and a `work_date`, and **optionally** linked to the `shift_assignment` it corresponds to (auto-matched by employee + date at import time, per §13a.4 - if no assignment exists for that employee/date, the entry is still saved, just unlinked)
- cost calculation (§11.2c, §14) uses the actual worked minutes (total clocked time minus total break time) from `time_clock_entries` when a matching entry exists for that employee/date; it falls back to the planned `shift_assignment`/`shift_pattern` duration when no actual attendance data has been imported for that day - the system is never blocked by missing attendance data, it just uses the best information available

### 13a.3 Supported Data Shapes

The source system's export format isn't known in advance, so two shapes are both supported - a real-world PDKS/clock-terminal export may look like either one, and both resolve to the same internal representation (a clock-in, a clock-out, and zero or more break intervals in between):

- **Simple**: one row per employee per day with a clock-in time, a clock-out time, and a single total break-minutes figure (no break start/end detail)
- **Detailed**: a sequence of timestamped events per employee per day - clock-in, break-start, break-end, break-start, break-end, ..., clock-out (any number of break cycles) - each break interval is captured individually, and total break time is their sum

Both shapes produce the same stored result: a clock-in time, a clock-out time, a total break-minutes figure, and (for the detailed shape only) the individual break intervals preserved for reference/audit.

### 13a.4 Import Flow

- the admin uploads a CSV/Excel file from an import screen and explicitly selects which shape it is (**Simple** or **Detailed**) before parsing - the system does not try to auto-detect the format
- each row/event-group is matched to an `employee` by whatever identifier the file carries (e.g. an employee number or email column mapped during import) and to a `work_date`; if a `shift_assignment` exists for that employee on that date, the new `time_clock_entries` row is linked to it automatically
- rows that fail to match any employee, or that carry an obviously invalid time range (e.g. clock-out before clock-in), are reported back to the admin as skipped/errored rather than silently dropped or guessed at
- an admin can review imported entries afterward and manually correct the employee/date match or the times themselves, the same way other manually-corrected data in this system works (§11.2a's manual-linking precedent)

### 13a.5 Acceptance Criteria

- both the Simple and Detailed CSV shapes can be imported and produce equivalent stored attendance data
- an imported entry automatically links to the matching `shift_assignment` when one exists for that employee/date, and is still saved (unlinked) when one doesn't
- a row that can't be matched to an employee, or has an invalid time range, is surfaced to the admin as a per-row import error, not silently skipped
- cost calculations use actual clocked-and-break-adjusted time when available for an employee/date, and fall back to the planned shift duration otherwise
- an admin can manually edit an imported attendance entry after the fact

---

## 14. Reporting Module Specification

### 14.1 Goal

Give management a trustworthy, exportable view of who worked, on what line, and at what cost, split by permanent vs. agency staff.

### 14.2 Must Show

- headcount per line per shift per day, for a selected week or month
- total hours and cost per employee type (vast vs. uitzendkracht), with an agency-level subtotal for uitzendkracht
- cost figures use actual clocked-and-break-adjusted hours from `time_clock_entries` (§13a) where available for an employee/date, falling back to planned `shift_assignment` duration otherwise - so cost reports reflect break-adjusted reality wherever attendance data has been imported, not just the plan
- PDF export (via `barryvdh/laravel-dompdf`) and Excel export (via `maatwebsite/excel`)

### 14.3 Acceptance Criteria

- a report for a past approved week reflects the actual approved `shift_assignments`, not any leftover draft/proposed data
- the cost breakdown correctly separates vast and uitzendkracht hours and cost
- a day with imported attendance data shows break-adjusted actual cost; a day without it shows planned-shift cost, and the report does not error or block on partial attendance data coverage
- exported files open correctly in common PDF viewers and Excel

---

## 15. Employee Schedule View (PWA) & Notifications Module Specification

### 15.1 Goal

Let every employee check their own schedule from their phone without needing to ask the planner, and be proactively notified the moment an approved schedule affecting them changes.

### 15.2 Visibility Scope

Two top-level roles exist:

- **`admin`** - full access to all scheduling, staff, and rule configuration (both per-person rules like `max_weekly_hours`, and company-level rule defaults)
- **`user`** (employee-facing) - cannot edit anything scheduling-related; can only *view* schedules, at one of three visibility levels the admin assigns per user:
  1. **own schedule only** (default for every new user)
  2. **own line's schedule** (everyone assigned to the same line(s) that week)
  3. **entire company schedule** (all lines, all staff)

This is implemented as a `visibility_scope` field (`own` / `line` / `company`, default `own`) rather than as separate Laravel roles per level, since it's a single per-user setting the admin can change at any time (`PUT /v1/users/{user}/visibility-scope`), not a distinct permission set.

### 15.3 PWA Requirements

- installable from the phone's browser (add to home screen) via a web app manifest and service worker (`vite-plugin-pwa`)
- the currently-cached week's schedule remains viewable offline
- default view on open is always the user's own schedule, regardless of their granted scope

### 15.4 Notification Requirements

- triggered only by changes to an **approved** schedule (see §12.4)
- delivered on both channels at once, to the employee's registered **email**, and as a **push notification** to any device where they've installed the PWA and granted notification permission (see §20)
- a `ScheduleChanged` Laravel notification class implements `toMail` and a Web Push channel; both are queued so sending never blocks the approval/edit request
- push delivery requires the employee to have installed the PWA and granted notification permission on at least one device; if no push subscription exists or a send fails (e.g. subscription expired), it must fail independently and log without blocking or failing the email send for the same notification - email is always attempted regardless of push status

### 15.5 Acceptance Criteria

- a new employee, by default, can only see their own shifts
- an admin can change an employee's visibility scope and it takes effect immediately
- an employee with the PWA installed and notifications enabled receives both an email and a push notification when an approved shift affecting them is created, changed, or removed; an employee without the PWA installed still receives the email
- the schedule view can be installed to a phone's home screen and opened without a browser address bar

---

## 16. Rule Engine Specification

### 16.1 Goal

Enforce scheduling constraints in a way that is transparent to the planner and easy to extend as more Dutch ATW (Arbeidstijdenwet) rules and company-specific rules are added over time.

### 16.2 Initial Rule

- **Max Weekly Hours** - each employee has a `max_weekly_hours` value (default 40, but can be set higher or lower per person via `employment_terms`); the suggestion engine will not propose a slot that pushes someone over this limit, and a manual edit that does so is flagged as a violation (not blocked)

Role qualification (§11a) is deliberately **not** implemented as a `SchedulingRule` in this engine, even though it is also a constraint on automatic suggestions: it is a hard filter on the *candidate pool* the suggestion engine builds (§12.2, step 2), not a post-hoc pass/violation check on an already-chosen candidate. The distinction matters for admin overrides - a `RuleResult` violation (like exceeding max hours) is always shown as a warning on an assignment that already exists, while a qualification mismatch is either prevented from being auto-suggested in the first place, or explicitly confirmed as an override at the moment of manual assignment (§11a.4, §12.2b). Both are equally overridable by the admin, just through different UI moments.

### 16.3 Future Rules (Architected, Not Implemented)

These are registered as disabled skeleton classes so they can be activated later without restructuring the engine:

- **Min Rest Between Shifts** - Dutch ATW requires at least 11 consecutive hours of rest between shifts (reducible to 8 hours once per week)
- **Consecutive Night Shifts** - a limit on how many night shifts an employee can work in a row
- **Weekly Mandatory Rest Day** - at least one full rest day (or an equivalent rest block) per week

### 16.4 Rule Result Handling

- every rule evaluation returns a `RuleResult` (pass/violation/warning + message)
- violations are always visible to the planner in the schedule grid's violation panel
- violations never silently block an assignment - the planner has final authority

### 16.5 Acceptance Criteria

- adding a new rule requires only a new rule class + a config entry, with no changes to `RuleEngine` itself
- changing an employee's `max_weekly_hours` immediately affects future suggestion runs and re-evaluates existing draft violations

---

## 17. Data Model Specification

Core tables required:

- `users`
- `agencies`
- `employees`
- `employment_terms`
- `lines`
- `scheduling_roles`
- `employee_scheduling_roles`
- `shift_patterns`
- `pay_rate_surcharge_rules`
- `schedules`
- `shift_assignments`
- `leave_types`
- `leave_requests`
- `time_clock_entries`
- `time_clock_breaks`
- `push_subscriptions`

Optional support tables:

- `notifications` (or Laravel's built-in notifications table)
- `company_rules` (future: DB-backed company-level rule config, not required for the initial build - see §16)

### 17.1 Users

Fields:

- `id`
- `name`
- `email`
- `password`
- `visibility_scope` (enum: `own`, `line`, `company`; default `own`)
- `locale` (enum: `en`, `tr`, `nl`, `es`, `ro`, `uk`; required, chosen at registration, changeable later - see §22a)
- `is_active`
- timestamps

### 17.2 Agencies

Fields:

- `id`
- `name`
- `code` (e.g. `"VZM"`, `"FLEX"`)
- `contact_email`
- `contact_phone`
- `is_active`
- timestamps

### 17.3 Employees

Fields:

- `id`
- `user_id` nullable
- `first_name`
- `last_name`
- `phone`
- `email`
- `employee_type` (enum: `vast`, `uitzendkracht`)
- `agency_id` nullable
- `pay_type` (enum: `hourly`, `monthly`; required for `vast`, always `hourly` for `uitzendkracht`)
- `hourly_rate` nullable decimal (required when `pay_type = hourly`)
- `monthly_salary` nullable decimal (required when `pay_type = monthly`)
- `contracted_hours_per_week` nullable decimal (used only to derive an effective hourly cost from `monthly_salary` for cost-mode ranking, §12.2 - not a payroll figure)
- `default_line_id` nullable
- `is_active`
- timestamps
- soft deletes

### 17.4 Employment Terms

Fields:

- `id`
- `employee_id`
- `max_weekly_hours` (integer, default `40`)
- `effective_from`
- `effective_to` nullable
- timestamps

### 17.5 Lines

Fields:

- `id`
- `name`
- `code`
- `is_active`
- timestamps

### 17.5a Roles

Fields:

- `id`
- `name` (e.g. `"Dough Prep - Line 1"`, `"Shift Supervisor"`)
- `line_id` nullable (set for a line-specific role, whether a station or a line-attached secondary task; null for a line-independent role - see §11a.2)
- `role_kind` (enum: `station`, `secondary_task` - see §11a.2)
- `requires_coverage` boolean (default `true` for `station`, always `false` for `secondary_task` - see §11a.3a)
- `attachment_type` nullable (enum: `station`, `line`, `none`; only meaningful when `role_kind = secondary_task` - see §11a.2)
- `attached_station_role_id` nullable, self-referencing FK to another `roles` row (set only when `attachment_type = station`, identifying which specific station this secondary task rides on)
- `is_active`
- timestamps

### 17.5b Employee Line Roles

Fields:

- `id`
- `employee_id`
- `role_id`
- timestamps

This is the qualification pivot from §11a.3: a row means "this employee is qualified for this role" (and, transitively, for that role's line if it's line-specific).

### 17.5c Pay Rate Surcharge Rules

Fields:

- `id`
- `name` (e.g. `"Weekend"`, `"Night hours"` - admin-facing label, not used in cost logic)
- `days_of_week` (jsonb array of ISO weekday numbers 1-7, e.g. `[6,7]` for Saturday+Sunday)
- `start_time`
- `end_time`
- `crosses_midnight` boolean (same convention as `shift_patterns`, §17.6)
- `surcharge_percentage` (decimal, e.g. `25.00` meaning +25%)
- `is_active`
- timestamps

This is the configurable rule table from §11.2c - applies to both `pay_type = hourly` and `pay_type = monthly` employees, computed per shift instance rather than stored as a static per-employee rate.

### 17.6 Shift Patterns

Fields:

- `id`
- `name`
- `start_time`
- `end_time`
- `crosses_midnight` boolean
- `is_active`
- timestamps

### 17.7 Schedules

Fields:

- `id`
- `week_start_date`
- `status` (enum: `draft`, `proposed`, `approved`)
- `created_by`
- `approved_by` nullable
- `approved_at` nullable
- timestamps

### 17.8 Shift Assignments

Fields:

- `id`
- `schedule_id`
- `employee_id`
- `line_id`
- `shift_pattern_id` nullable
- `work_date`
- `role_id` nullable (the position this assignment fills, per §11a - e.g. `"Dough Prep - Line 1"`; nullable because not every historical/simple assignment needs a role, but the suggestion engine always sets it when generating role-scoped slots)
- `starts_at` nullable time, `ends_at` nullable time (overrides the linked `shift_pattern`'s full time range for this specific assignment, enabling the manual time-split behavior in §11a.3b - e.g. an assignment covering only the first 2 hours of an 8-hour shift; null on both means "the assignment covers the shift pattern's full duration," which is what the suggestion engine always produces)
- `status` (enum: `proposed`, `confirmed`)
- `source` (enum: `auto_suggested`, `manual`)
- `notes` nullable text
- timestamps

### 17.9 Leave Types

Fields:

- `id`
- `name` (e.g. `"Annual Leave - Vakantie"`, `"Sick Leave - Ziek"`, `"Short Excuse"`)
- `requires_approval` boolean
- timestamps

### 17.10 Leave Requests

Fields:

- `id`
- `employee_id`
- `leave_type_id`
- `start_date`
- `end_date`
- `status` (enum: `pending`, `approved`, `rejected`)
- `reason` nullable
- `approved_by` nullable
- timestamps

### 17.10a Time Clock Entries

Fields:

- `id`
- `employee_id`
- `shift_assignment_id` nullable (auto-matched at import time when a planned assignment exists for the same employee/date, §13a.4)
- `work_date`
- `clock_in`
- `clock_out`
- `break_minutes` (integer, total break time - always populated regardless of import shape; for the Detailed shape this is derived by summing `time_clock_breaks` rows, for the Simple shape it's taken directly from the file)
- `source` (enum: `import_simple`, `import_detailed`, `manual` - which import shape produced this row, or that an admin corrected/created it by hand)
- timestamps

### 17.10b Time Clock Breaks

Fields:

- `id`
- `time_clock_entry_id`
- `break_start`
- `break_end`
- timestamps

Only populated for entries imported via the Detailed shape (§13a.3) - a Simple-shape entry has a `break_minutes` total on `time_clock_entries` with no corresponding rows here.

### 17.11 Push Subscriptions

Fields:

- `id`
- `employee_id`
- `endpoint` (the browser-provided push endpoint URL)
- `p256dh_key`, `auth_key` (the subscription's encryption keys, required by the Web Push protocol)
- `user_agent` nullable (helps the admin/employee tell subscriptions apart if ever listed, e.g. "iPhone - Safari")
- `is_active` (set to `false` automatically on a confirmed-expired send, §20.2a, rather than deleted - keeps a record of what existed)
- timestamps

---

## 17A. Database Constraints and Rules

### 17A.1 Users

Constraints:

- `email` must be unique
- `visibility_scope` defaults to `own`
- `locale` required, must be one of the six supported codes (`en`, `tr`, `nl`, `es`, `ro`, `uk`); no default - it is chosen explicitly at registration (§22a)
- `is_active` defaults to `true`

Indexes:

- unique index on `email`

### 17A.2 Agencies

Constraints:

- `name` required
- `code` should be unique
- `is_active` defaults to `true`

Indexes:

- unique index on `code`

### 17A.3 Employees

Constraints:

- `employee_type` required, enum `vast`/`uitzendkracht`
- `agency_id` required (non-null) when `employee_type = uitzendkracht`; must be null when `employee_type = vast` - enforce at the application/validation layer, not a DB CHECK, since Postgres cross-column conditional constraints add migration friction for little benefit here
- `pay_type` required; forced to `hourly` at the application layer when `employee_type = uitzendkracht`
- `hourly_rate` required when `pay_type = hourly`; `monthly_salary` and `contracted_hours_per_week` required when `pay_type = monthly` - enforced at the application/validation layer
- `default_line_id` nullable, never a hard scheduling constraint
- `user_id` nullable, links to at most one `user` (see §11.2a) - not required at creation and never inferred automatically
- `is_active` defaults to `true`

Indexes:

- foreign key on `agency_id` with `nullOnDelete`
- foreign key on `default_line_id` with `nullOnDelete`
- foreign key on `user_id` with `nullOnDelete`
- unique index on `user_id` where not null (a user account can be linked to at most one employee)
- index on `employee_type`
- index on `is_active`

### 17A.4 Employment Terms

Constraints:

- one active row per employee at a time (`effective_to IS NULL` or in the future implies "current")
- `max_weekly_hours` defaults to `40`, must be a positive integer, has no hardcoded upper bound (must support values above 40)

Indexes:

- index on `(employee_id, effective_from)`
- foreign key on `employee_id` with cascade delete

### 17A.5 Lines

Constraints:

- `name` required
- `code` should be unique
- `is_active` defaults to `true`

Indexes:

- unique index on `code`

### 17A.5a Roles

Constraints:

- `name` required
- `line_id` nullable - null means the role is line-independent (e.g. "Shift Supervisor"); set means it only applies to that one line (e.g. "Dough Prep - Line 1") and does not imply qualification on any other line, even for a role with an identical-sounding name
- `role_kind` required, enum `station`/`secondary_task`
- `requires_coverage` boolean; forced to `false` at the application layer when `role_kind = secondary_task` (not user-configurable in that case, per §11a.3a); defaults to `true` when `role_kind = station`
- `attachment_type` required when `role_kind = secondary_task`; forced to `null` at the application layer when `role_kind = station` (a station's own coverage is what matters, not what it's "attached to")
- `attached_station_role_id` required (non-null) when `attachment_type = station`; must reference a row where `role_kind = station`; must be null for any other `attachment_type`; when `attachment_type = station`, this secondary task's own `line_id` must match the referenced station's `line_id` if the station has one - enforced at the application/validation layer
- `is_active` defaults to `true`

Indexes:

- foreign key on `line_id` with cascade delete (a role scoped to a line makes no sense once that line is gone; a line-independent role is unaffected since `line_id` is null)
- foreign key on `attached_station_role_id` referencing `roles.id`, with cascade delete (a secondary task attached to a specific station makes no sense once that station role is removed)
- index on `line_id`
- index on `(role_kind, requires_coverage)` (used when checking mandatory-coverage compliance before approval, §11a.3a)
- index on `attached_station_role_id`

### 17A.5b Employee Line Roles

Constraints:

- a given `(employee_id, role_id)` pair must be unique - an employee is either qualified for a role or not, no duplicate rows

Indexes:

- unique composite index on `(employee_id, role_id)`
- foreign key on `employee_id` with cascade delete
- foreign key on `role_id` with cascade delete
- index on `role_id` (to efficiently look up "who is qualified for this role" when building the suggestion candidate pool, §12.2)

### 17A.5c Pay Rate Surcharge Rules

Constraints:

- `name` required
- `days_of_week` required, non-empty array, each value an integer 1-7
- `start_time` and `end_time` required
- `crosses_midnight` defaults to `false`, must be `true` whenever `end_time < start_time` (same rule as `shift_patterns`, §17A.6)
- `surcharge_percentage` required, must be greater than 0 (a 0% "surcharge" is just the base rate and doesn't need a rule row)
- `is_active` defaults to `true`

Indexes:

- index on `is_active` (used when computing shift cost - only active rules are evaluated)

### 17A.6 Shift Patterns

Constraints:

- `start_time` and `end_time` required
- `crosses_midnight` defaults to `false`, must be `true` whenever `end_time < start_time`
- no restriction on how many shift patterns can exist - the UI must allow adding new start/end time combinations freely, since real data shows far more than 6 fixed shift times

Indexes:

- index on `is_active`

### 17A.7 Schedules

Constraints:

- `week_start_date` should be unique (one schedule per calendar week)
- `status` defaults to `draft`
- `approved_by`/`approved_at` must both be null until `status = approved`

Indexes:

- unique index on `week_start_date`
- foreign key on `created_by` and `approved_by` referencing `users`

### 17A.8 Shift Assignments

Constraints:

- `status` defaults to `proposed`
- `source` required, defaults to `auto_suggested` when created by the suggestion engine
- a given `(employee_id, work_date)` should not have two **station**-role assignments with overlapping effective time ranges (`starts_at`/`ends_at` if set, otherwise the linked `shift_pattern`'s full start/end time) - enforced at the application layer, since overlap depends on resolving each row's effective time range, not a simple column comparison; **secondary_task** assignments are explicitly exempt from this check, including when they overlap their own underlying station assignment (§11a.3b)
- if `role_id` refers to a line-specific role, its `line_id` must match the assignment's own `line_id` - enforced at the application/validation layer, not a DB constraint, since it's a cross-table conditional check
- a **secondary_task** assignment requires at least one overlapping **station** assignment for the same employee and day to already exist - enforced at the application layer at creation time

Indexes:

- foreign key on `schedule_id` with cascade delete
- foreign key on `employee_id` with cascade delete
- foreign key on `line_id` with `restrictOnDelete`
- foreign key on `shift_pattern_id` with `nullOnDelete`
- foreign key on `role_id` with `nullOnDelete`
- index on `(employee_id, work_date)`
- index on `(schedule_id, line_id, work_date)`
- index on `(schedule_id, line_id, role_id, work_date)` (used when checking headcount/candidates for a specific role slot)

### 17A.9 Leave Requests

Constraints:

- `start_date <= end_date`
- `status` defaults to `pending`
- `approved_by` must be null until `status = approved`

Indexes:

- foreign key on `employee_id` with cascade delete
- foreign key on `leave_type_id` with `restrictOnDelete`
- index on `(employee_id, start_date, end_date)`
- index on `status`

### 17A.9b Time Clock Entries

Constraints:

- `clock_out` must be after `clock_in` - a row failing this at import time is rejected as a per-row import error (§13a.4), never silently corrected or dropped
- `break_minutes` defaults to `0`, must be non-negative, and must not exceed the total clock-in-to-clock-out span
- `source` required

Indexes:

- foreign key on `employee_id` with cascade delete
- foreign key on `shift_assignment_id` with `nullOnDelete` (an entry survives its planned assignment being edited/removed - actual attendance history must remain stable)
- index on `(employee_id, work_date)` (used both to look up an employee's actual hours for a given day, and to auto-match new imports against existing `shift_assignments`)

### 17A.9c Time Clock Breaks

Constraints:

- `break_end` must be after `break_start`
- both must fall within the parent entry's `clock_in`/`clock_out` span

Indexes:

- foreign key on `time_clock_entry_id` with cascade delete

### 17A.9a Push Subscriptions

Constraints:

- `endpoint` must be unique (the same browser subscription should never be stored twice)
- `is_active` defaults to `true`

Indexes:

- unique index on `endpoint`
- foreign key on `employee_id` with cascade delete
- index on `(employee_id, is_active)` (used when fetching an employee's active subscriptions to push to, §20.2)

### 17A.10 Referential and Deletion Rules

- deleting an employee must cascade to their own `shift_assignments`, `employment_terms`, `leave_requests`, and `employee_line_roles` (historical schedule data for other employees must remain intact)
- deleting a line must not be allowed while active `shift_assignments` reference it (`restrictOnDelete`) - deactivate instead of deleting; deleting a line does cascade-delete its line-specific `roles` (and, transitively, their `employee_line_roles`), since a role scoped to a deleted line no longer means anything
- deleting a shift pattern should not destroy historical assignment records; prefer `nullOnDelete` so history remains stable
- deleting an agency must not cascade-delete its employees; use `nullOnDelete` on `employees.agency_id` and require the admin to reassign or deactivate those employees explicitly
- deleting a role should not destroy historical assignment records that reference it; prefer `nullOnDelete` on `shift_assignments.role_id` (the assignment's line/date/employee stays meaningful even if the specific role label is later removed)

### 17A.11 Migration Rule

Base schema migrations should contain canonical final table definitions wherever possible for this greenfield build.

Avoid:

- long chains of `add_*` migrations for stable fields in the initial build
- schema drift created by repeated patch-style migrations before the first release

Use later additive migrations only for true versioned evolution after the initial release (e.g. activating a new rule engine field, adding a `company_rules` table).

---

## 18. API Specification

All endpoints are under `Route::prefix('v1')->middleware('auth:sanctum')`, controllers under `App\Http\Controllers\Api\V1\*`, except authentication endpoints.

### 18.1 Authentication

- `POST /v1/auth/login`
- `POST /v1/auth/logout`
- `GET /v1/auth/me`

### 18.2 Staff & Agencies

- `GET|POST /v1/agencies`
- `GET|PUT|DELETE /v1/agencies/{agency}`
- `GET|POST /v1/employees`
- `GET|PUT|DELETE /v1/employees/{employee}`
- `GET|PUT /v1/employees/{employee}/employment-terms`
- `PUT /v1/employees/{employee}/user` - link an existing user account to this employee (§11.2a); body `{ "user_id": 42 }`
- `DELETE /v1/employees/{employee}/user` - unlink the currently linked account, if any
- `GET /v1/users?unlinked=true&search=...` - search users not yet linked to any employee, for the link-account picker (§10A.3)
- `PUT /v1/users/{user}/visibility-scope`

#### Example: `GET /v1/employees`

Query params:

- `employee_type` (`vast` | `uitzendkracht`)
- `agency_id`
- `line_id` (matches `default_line_id`)
- `is_active`
- `has_account` (`true`|`false` - filters by whether `user_id` is set; `false` powers both the staff list badge and the dashboard count from §11.2b)

Response example:

```json
{
  "data": [
    {
      "id": 12,
      "first_name": "Ahmet",
      "last_name": "Yilmaz",
      "employee_type": "uitzendkracht",
      "agency": { "id": 3, "name": "VZM", "code": "VZM" },
      "default_line": { "id": 2, "name": "Lijn 2", "code": "L2" },
      "employment_terms": { "max_weekly_hours": 40 },
      "pay_type": "hourly",
      "hourly_rate": 18.5,
      "is_active": true
    }
  ]
}
```

### 18.3 Lines, Roles & Shift Patterns

- `GET|POST /v1/lines`
- `GET|PUT|DELETE /v1/lines/{line}`
- `GET|POST /v1/roles`
- `GET|PUT|DELETE /v1/roles/{role}`
- `GET|PUT /v1/employees/{employee}/qualified-roles` (list/replace an employee's `employee_line_roles`, §11a.3)
- `GET|POST /v1/shift-patterns`
- `GET|PUT|DELETE /v1/shift-patterns/{shiftPattern}`
- `GET|POST /v1/pay-rate-surcharge-rules` (§11.2c)
- `GET|PUT|DELETE /v1/pay-rate-surcharge-rules/{payRateSurchargeRule}`

#### Example: `POST /v1/pay-rate-surcharge-rules`

Request example:

```json
{
  "name": "Weekend",
  "days_of_week": [6, 7],
  "start_time": "00:00",
  "end_time": "23:59",
  "surcharge_percentage": 25
}
```

A night-hours example spanning midnight:

```json
{
  "name": "Night hours",
  "days_of_week": [1, 2, 3, 4, 5],
  "start_time": "18:00",
  "end_time": "06:00",
  "surcharge_percentage": 50
}
```

#### Example: `POST /v1/roles`

Request example:

```json
{
  "name": "Dough Prep - Line 3",
  "line_id": 3,
  "role_kind": "station",
  "requires_coverage": true
}
```

`requires_coverage` is rejected by validation if `role_kind` is `"secondary_task"` (§17A.5a) - a secondary task is never independently mandatory.

#### Example: `POST /v1/roles` (line-attached secondary task)

Request example:

```json
{
  "name": "Silo Watch - Line 3",
  "line_id": 3,
  "role_kind": "secondary_task",
  "attachment_type": "line"
}
```

A `station`-attached example would instead set `"attachment_type": "station"` and `"attached_station_role_id": 9` (referencing an existing station role, whose `line_id` must match if the station has one); an `unattached` example (e.g. "Shift Supervisor") would omit `line_id` entirely and set `"attachment_type": "none"`.

### 18.4 Leave

- `GET|POST /v1/leave-types`
- `GET|POST /v1/leave-requests`
- `GET|PUT|DELETE /v1/leave-requests/{leaveRequest}`
- `POST /v1/leave-requests/{leaveRequest}/approve`
- `POST /v1/leave-requests/{leaveRequest}/reject`

### 18.4b Time & Attendance

- `POST /v1/time-clock-entries/import` (§13a.4) - multipart file upload; body also carries `shape` (`simple`|`detailed`) and the column-mapping the admin selected
- `GET /v1/time-clock-entries` (filterable by `employee_id`, date range)
- `GET|PUT|DELETE /v1/time-clock-entries/{timeClockEntry}` (manual review/correction, §13a.4)

#### Example: `POST /v1/time-clock-entries/import`

Request example (multipart form, shown as the non-file fields):

```json
{
  "shape": "detailed",
  "column_mapping": {
    "employee_identifier": "Personeelsnummer",
    "date": "Datum",
    "event_time": "Tijd",
    "event_type": "Type"
  }
}
```

Response example:

```json
{
  "data": {
    "imported": 214,
    "skipped": 3,
    "errors": [
      {
        "row": 47,
        "reason": "No employee found matching identifier \"9981\"."
      },
      {
        "row": 112,
        "reason": "clock_out is before clock_in."
      }
    ]
  }
}
```

### 18.4a Push Subscriptions

- `POST /v1/push-subscriptions` - register a subscription for the current employee (§20.2a)
- `DELETE /v1/push-subscriptions/{pushSubscription}` - explicitly unregister one (e.g. from a "disable notifications" control in settings)

#### Example: `POST /v1/push-subscriptions`

Request example (the shape returned by the browser's `PushSubscription.toJSON()`):

```json
{
  "endpoint": "https://fcm.googleapis.com/fcm/send/...",
  "keys": {
    "p256dh": "BN4G...",
    "auth": "k8Jd..."
  }
}
```

### 18.5 Scheduling

- `GET|POST /v1/schedules`
- `GET /v1/schedules/{schedule}`
- `POST /v1/schedules/{schedule}/suggest`
- `POST /v1/schedules/{schedule}/approve`
- `GET|POST /v1/schedules/{schedule}/assignments`
- `PUT|DELETE /v1/shift-assignments/{assignment}`
- `GET /v1/schedules/{schedule}/slots/{line}/{role}/{workDate}/candidates` - ranked alternative-candidates list for one slot (§12.2a), query param `ranking_mode` (`fair`|`cost`)

#### Example: `POST /v1/schedules/{schedule}/suggest`

Request example:

```json
{
  "line_ids": [1, 2, 3],
  "shift_pattern_ids": [1, 2, 3, 4, 5, 6],
  "ranking_mode": "cost"
}
```

`ranking_mode` is `"fair"` or `"cost"` (see §12.2); defaults to `"fair"` if omitted.

Response example:

```json
{
  "data": {
    "schedule_id": 14,
    "status": "proposed",
    "assignments_created": 61,
    "violations": [
      {
        "employee_id": 12,
        "work_date": "2026-08-04",
        "rule": "MaxWeeklyHoursRule",
        "severity": "warning",
        "message": "This assignment would put Ahmet Yilmaz at 44 hours this week (limit: 40)."
      }
    ],
    "unfilled_slots": [
      {
        "line_id": 3,
        "role_id": 9,
        "role_name": "Dough Prep - Line 3",
        "work_date": "2026-08-05",
        "shift_pattern_id": 4,
        "reason": "no_qualified_candidate",
        "blocking": true
      }
    ]
  }
}
```

`blocking: true` means this slot's role has `requires_coverage = true` (§11a.3a) - the schedule cannot be approved (§12.3b) until it is resolved. `blocking: false` is advisory only.

#### Example: `PUT /v1/shift-assignments/{assignment}` (manual time-split)

Request example:

```json
{
  "role_id": 11,
  "starts_at": "08:00",
  "ends_at": "10:00"
}
```

This narrows an existing 08:00-16:00 assignment down to its first two hours, freeing the employee to hold a different station for the remainder of the shift via a second assignment row (§11a.3b) - rejected by validation if it would overlap another station assignment for the same employee and day.

#### Example: `GET /v1/schedules/{schedule}/slots/3/9/2026-08-05/candidates?ranking_mode=cost`

Response example:

```json
{
  "data": [
    {
      "employee_id": 27,
      "name": "Elif Demir",
      "qualified": false,
      "exclusion_reason": "Not qualified for Dough Prep - Line 3",
      "effective_hourly_cost": 17.0,
      "hours_so_far_this_week": 24
    },
    {
      "employee_id": 12,
      "name": "Ahmet Yilmaz",
      "qualified": true,
      "exclusion_reason": "Would exceed max weekly hours (44 > 40)",
      "effective_hourly_cost": 18.5,
      "hours_so_far_this_week": 40
    }
  ]
}
```

Candidates are ranked by the requested `ranking_mode`; `qualified: false` candidates are still listed (per §11a.4, the admin can override), always alongside their exclusion reason so the planner knows exactly what they'd be overriding.

### 18.6 Reports

- `GET /v1/reports/weekly-headcount?week_start=...`
- `GET /v1/reports/cost-breakdown?week_start=...&end=...`
- `GET /v1/reports/export/pdf?...`
- `GET /v1/reports/export/excel?...`

---

## 19. Rule Engine Implementation Specification

### 19.1 Goals

- keep every scheduling constraint isolated and independently testable
- allow future Dutch ATW rules to be added without touching existing rule code
- keep violations visible, never silently blocking

### 19.2 Evaluation Pipeline

The backend should support, per candidate assignment:

1. build an `EmployeeScheduleContext` (the employee, their `employment_terms`, their existing assignments for the week, the candidate new assignment)
2. run every `enabled` rule from `config/scheduling_rules.php` against that context
3. collect all `RuleResult`s
4. surface violations to the planner (schedule grid) and/or the suggestion engine (as a filter)

### 19.3 Provider-Style Abstraction

The product should not hardcode which rules are active in code paths outside config:

- rule interface (`SchedulingRule`)
- concrete rule implementations (one class per rule)
- a config file toggling which are enabled
- the `RuleEngine` orchestrator, which knows nothing about individual rule logic

### 19.4 Frontend Rule Display Rules

- every violation returned by the API must be shown against the specific cell/employee/day it applies to
- the planner must be able to approve a schedule that still has open (acknowledged) violations - this is a deliberate design choice, not an oversight

---

## 20. Notification Specification

### 20.1 Trigger Rules

- fires when a `shift_assignment` belonging to an **approved** schedule is created, edited, or deleted
- fires once at the moment a schedule transitions to `approved` (for every employee with an assignment in it)
- does **not** fire for draft/proposed edits

### 20.2 Channels

- **Email**: Laravel's built-in mail (`Notification` with `toMail`)
- **Push**: Web Push (the standard browser/OS push protocol), via a custom notification channel built on the `web-push` PHP library (VAPID-authenticated, no third-party notification vendor or account required), queued

Both channels fire together for every notification; there is no per-employee channel preference in the initial build. Email always fires if the employee has one on file. Push fires to every active push subscription the employee has registered (potentially more than one, if they installed the PWA on more than one device) - see §20.2a.

### 20.2a Push Subscription Lifecycle

- when an employee installs the PWA and grants notification permission (prompted from within the app, not on first load - see §21.2), the frontend registers a **push subscription** (endpoint + keys) with the backend via `POST /v1/push-subscriptions` and stores it against that employee
- an employee can have multiple active subscriptions (one per installed device/browser); a notification is pushed to all of them
- a subscription that the push service reports as expired or invalid (e.g. the browser returns a 410 Gone on send) is deactivated automatically - no admin action needed, and it does not affect email delivery for that employee
- revoking notification permission in the browser, or uninstalling the PWA, naturally stops that subscription from receiving pushes; the backend lazily deactivates it on the next failed send rather than requiring an explicit "uninstall" signal from the browser (which isn't reliably available)

### 20.3 UX Requirements

- the planner sees a confirmation that notifications were queued after approving/editing an approved schedule
- a failed send on either channel (push or email) must be logged and retryable independently, without blocking or failing the other channel for the same notification
- the PWA prompts for notification permission at a meaningful moment (e.g. right after first successful login, with a plain-language explanation of why), not silently or immediately on page load, per standard Web Push UX guidance

### 20.4 Acceptance Criteria

- approving a new schedule sends one email, plus one push notification per active subscription, per affected employee
- editing a single assignment in an already-approved schedule sends a notification only to the employee(s) whose assignment changed
- notification sending never blocks or slows down the approval/edit HTTP response (queued)
- a push send failure (e.g. expired subscription) does not prevent the email for the same notification from being sent, and does not surface as an error to the planner
- an employee who has never installed the PWA still receives the email side of every notification

---

## 21. PWA Specification

### 21.1 Goals

- installable to a phone home screen directly from the browser
- usable for viewing the current cached week while offline
- feels like a lightweight native app, not a website

### 21.2 Requirements

- `vite-plugin-pwa` integrated into the frontend build
- a web app manifest (name, icons, theme color matching §7.2)
- a service worker caching the employee schedule view and its current week's data
- a service worker `push` event handler that displays a system notification when a `ScheduleChanged` push arrives (§20), and a `notificationclick` handler that opens the app to the affected week
- a notification-permission prompt shown once, after first login, with plain-language copy explaining what it's for (§20.3) - not on page load, and not repeated if the employee dismisses or denies it (a manual "enable notifications" control remains available in settings for someone who wants to opt in later)

### 21.2a Install Prompt at Account Creation

Rather than waiting for the employee to discover installation on their own, the account-creation screen itself (the same screen where they pick their `locale`, §21a.4) prompts them to install the PWA, with platform-specific handling since browsers differ in what they allow:

- **one sentence explaining why**, shown regardless of platform, before either flow below: something like "Install this app on your phone to get notified the moment your schedule changes." (translated per §21a.3, since this is UI copy like any other)
- **Android / Chrome (and other browsers supporting the installation API)**: an "Add to Home Screen" button is shown directly on the account-creation screen. The frontend listens for the browser's `beforeinstallprompt` event; if it has fired (meaning the browser is willing to install this PWA), clicking the button calls the browser's native install flow immediately - the account-creation screen triggers it itself rather than waiting for the employee to find it in a browser menu.
- **iOS / Safari**: Safari does not expose a `beforeinstallprompt` event and does not allow a webpage to trigger installation programmatically - Apple restricts this to a manual, user-driven action. When an iOS Safari user agent is detected, the account-creation screen instead shows a short, visual step-by-step guide in place of the button: "Tap the Share icon, then 'Add to Home Screen'" (with an icon/screenshot illustrating the Safari share icon), since this is the only way iOS supports the install flow.
- neither flow blocks account creation - both are presented alongside the registration form as a recommended next step, and account creation itself succeeds whether or not the employee installs the PWA in that moment; the manual "enable notifications" / install entry point in settings (§21.2) remains available afterward for anyone who skips this step

### 21.3 Acceptance Criteria

- the employee schedule view can be added to a phone's home screen
- the most recently loaded week remains viewable without a network connection
- opening the installed PWA does not show a browser address bar
- granting notification permission registers a push subscription with the backend; denying it leaves the app fully usable, with email as the sole notification channel
- on Android/Chrome, the account-creation screen's install button triggers the native install prompt directly, without the employee needing to find it in a browser menu
- on iOS/Safari, the account-creation screen shows the manual Share → Add to Home Screen instructions instead of a button, and does not attempt to auto-trigger an install
- skipping installation at account creation does not block or interrupt registration

---

## 21a. Internationalization (i18n) Specification

### 21a.1 Goal

Make the entire product usable, for both the `admin` and `user` roles, in any of six languages, chosen once at registration and changeable at any time afterward.

### 21a.2 Supported Languages

- English (`en`)
- Turkish (`tr`)
- Dutch (`nl`)
- Spanish (`es`)
- Romanian (`ro`)
- Ukrainian (`uk`)

This set is chosen to match the factory's actual workforce composition (a mixed permanent and temp-agency staff drawn from multiple nationalities), not just the admin's language.

### 21a.3 Scope of Translation

Everything user-facing must be translatable, not just static labels:

- all frontend UI strings (admin desktop screens and the employee PWA), via `react-i18next` (or equivalent) with one JSON resource file per locale
- backend-generated content that reaches a person directly: rule-violation messages (§16.4), the `ScheduleChanged` notification content across all three channels (§20), and validation error messages returned by the API, via Laravel's built-in localization (`lang/{locale}/*.php`)
- day names, dates, and hour formats must be locale-aware where displayed (e.g. day-of-week headers in the schedule grid)

Not required to be translated: raw data the admin enters themselves (employee names, agency names, notes/free-text fields) - only the product's own generated text.

### 21a.4 Locale Selection Flow

- **at registration**: the account-creation screen requires an explicit language selection before the account can be created - there is no silent default (this applies to both an admin creating their own account and an admin creating an employee's `user` account, or a future self-registration flow if one exists). This same screen also carries the PWA install prompt described in §21.2a, since both are one-time, first-run setup steps that belong together.
- **after registration**: a locale switcher is available from the profile/settings area for both `admin` and `user` roles at any time; changing it takes effect immediately for the current session and is persisted to `users.locale` (§17.1) for all future sessions and all future notifications

### 21a.5 Notification Content Localization

Both notification channels (§20) are plain, freely-authored content controlled entirely by this product - unlike the earlier WhatsApp-based design, there is no third-party template pre-approval process to plan around. The `ScheduleChanged` notification's `toMail` and push-payload content are both rendered from the same Laravel `lang/{locale}/*.php` strings used elsewhere (§21a.3), in the recipient's stored `locale`, with no per-language external approval step or timeline dependency.

### 21a.6 Acceptance Criteria

- a new account cannot be created without explicitly picking one of the six languages
- switching language in settings immediately changes the UI language without requiring logout
- a rule-violation message, and an email/push notification, are generated in the recipient's stored `locale`, not the admin's or the sender's
- adding a UI string without a translation entry for all six locales should be caught in code review/CI (e.g. a lint step comparing locale JSON key sets), not discovered by a user seeing an untranslated key

---

## 22. Non-Functional Requirements

### Performance

- generating a full-week suggestion across 3 lines/3 shifts should complete in a few seconds for a normal-sized workforce
- the admin schedule grid should remain responsive when editing a single cell (no full-grid reload per edit)

### Reliability

- schedule approval must be idempotent - re-approving an already-approved schedule must not re-send duplicate notifications for unchanged assignments
- notification failures must be logged, not silently swallowed

### Accessibility

- the schedule grid must remain usable with keyboard navigation for power users (the planner)
- color-coded statuses (approved/violation/leave/sick) must not be the *only* signal - use icons/labels alongside color

### Maintainability

- backend code organized by domain/service, not by generic CRUD-only controllers
- rule logic isolated from suggestion-generation logic from notification logic
- no hidden scheduling rules embedded directly in controllers or Blade/React views

---

## 23. QA and Acceptance Checklist

### Staff & Agencies

- an employee of either type can be created, edited, deactivated
- an agency can be created and linked to uitzendkracht employees
- `employment_terms.max_weekly_hours` can be changed without losing the prior value's history
- an employee can be created with no linked user account, and remains fully schedulable
- an admin can link an existing user account to an employee, then unlink it, without deleting either record
- a user account already linked to one employee cannot also be linked to a second one
- an admin can create a pay rate surcharge rule (days, time window, percentage) applicable to both hourly and monthly employees
- a shift that only partially overlaps a surcharge rule's time window is costed at the surcharge rate only for the overlapping hours, base rate for the rest
- when two active surcharge rules overlap the same time segment, only the higher percentage applies to that segment - they never stack

### Lines & Shift Patterns

- a new shift pattern with an arbitrary start/end time can be added without a code change
- deactivating a line does not delete historical shift assignments

### Roles & Competency Matching

- a role can be created as line-specific (tied to exactly one line) or line-independent
- a role is created as a station or a secondary task, and `requires_coverage` is only ever configurable for stations
- an employee qualified for a line-specific role on one line is never treated as qualified for the identically-named role on a different line
- an admin can view and edit an employee's qualified roles from the staff detail screen
- an admin can still manually assign an unqualified employee to a slot, with a required, visible confirmation step
- a mandatory-coverage station left unfilled blocks schedule approval, with the blocking slot(s) clearly identified
- an employee can be manually given a time-split across two different stations within one shift, and can hold a secondary task alongside a station, but can never be double-booked on two overlapping stations
- a station can have more than one employee manually assigned to it, even though auto-suggestion only ever fills it with one
- a secondary task can be created as station-attached, line-attached, or fully unattached, and this choice is unavailable when creating a station instead
- suggestion generation only assigns a line-attached secondary task to an employee already working a station on that same line that shift
- suggestion generation distributes secondary tasks by each employee's total secondary-task count across all task types, not per task type
- an admin creating a new line-specific station before any employees exist, and only afterward qualifying employees for it, works without any ordering error in the UI

### Leave & Sick

- an approved Vakantie/ziek record removes the employee from the suggestion pool for every day in range
- a day with no assignment ("vrij") requires no approval workflow

### Time & Attendance

- a Simple-shape and a Detailed-shape file for the same underlying data produce equivalent stored attendance entries
- an entry auto-links to a matching `shift_assignment` when one exists, and imports successfully unlinked when one doesn't
- a row with an unmatched employee or an invalid time range is reported as a specific per-row error, not silently dropped
- an admin can manually edit an imported entry's employee match, date, or times after the fact
- cost calculations use break-adjusted actual hours when attendance data exists for an employee/date, and planned shift duration when it doesn't, without erroring on partial coverage

### Weekly Scheduling

- suggestion generation never proposes a slot for someone on approved leave that day
- suggestion generation never proposes a slot that violates `MaxWeeklyHoursRule` (unless later manually overridden)
- suggestion generation never automatically proposes an employee for a line-specific role they are not qualified for on that exact line
- a slot with no qualified, rule-compliant candidate is flagged as unfilled and surfaces a ranked alternative-candidates list, rather than being silently left blank
- a manual edit re-evaluates rules immediately and updates the violation panel
- approving a schedule is a single explicit, auditable action
- dragging an assignment card to a new line/shift/day cell persists the move and re-validates it immediately
- dragging or manually assigning an unqualified employee into a role slot requires an explicit confirmation before it commits

### Reporting

- headcount and cost reports reflect only approved `shift_assignments`
- cost breakdown correctly separates vast vs. uitzendkracht, with per-agency subtotals
- PDF and Excel exports open correctly

### Employee View & Notifications

- a new employee defaults to `own`-only visibility
- an admin changing visibility scope takes effect immediately for that employee
- an approved-schedule change sends an email, and a push notification to every active subscription, for the affected employee
- an employee who never installed the PWA still receives the email
- the schedule PWA installs to a phone home screen and works offline for the cached week

### Internationalization

- registration cannot be completed without an explicit language choice
- changing language in settings applies immediately, without logout, for both admin and employee roles
- a rule-violation message and a schedule-change notification (email/push) are rendered in the recipient's stored locale, never the sender's
- all six locales have complete, non-empty translation coverage for every UI string and notification template
- on Android/Chrome, the account-creation screen's install button successfully triggers the browser's native PWA install flow
- on iOS/Safari, the account-creation screen shows the manual install instructions instead of a non-functional button
- account creation succeeds whether or not the employee acts on the install prompt

### Backend

- controllers remain thin
- rule engine can be extended with a new rule class + config entry, with zero changes to existing rule classes
- notification sending is queued and does not block the triggering request

---

## 24. Build Order

Recommended implementation order:

1. project skeleton: Laravel API setup, PostgreSQL, Sanctum, `spatie/laravel-permission` + role/visibility-scope seeding, React+Vite+TS skeleton, `react-i18next` + Laravel localization wired in from the start with `locale` on registration (§21a), login. The account-creation screen's layout (identity fields + language selector) is built here, but its install-prompt section (§21.2a, §10A.6a) is a placeholder until step 7 - a working `beforeinstallprompt` listener needs the PWA manifest that doesn't exist yet, so wire it up properly in step 7 rather than half-building it twice.
2. staff & agencies module (employees, employment terms, agencies) - migrations, models, controllers, resources, admin frontend screens
3. lines, roles & shift patterns module - migrations, models, controllers, resources, admin frontend screens, including `roles` (station vs. secondary task, `requires_coverage`) and `employee_line_roles` (§11a), the qualified-roles editor on the staff detail screen, and `pay_rate_surcharge_rules` (§11.2c) with its admin screen
4. leave & sick tracking module - migrations, models, controllers, approval flow, admin frontend screens
5. weekly scheduling core: `schedules`, `shift_assignments` (including `starts_at`/`ends_at` time-split support, §11a.3b), rule engine (`MaxWeeklyHoursRule` + disabled skeletons), `ScheduleSuggestionService` (with `ranking_mode`: fair/cost, and role-qualification filtering per §12.2), suggest/approve endpoints, mandatory-coverage approval blocking (§12.3b), admin schedule grid with drag-and-drop (§12.3a), unfilled-slot flagging and the alternative-candidates lookup (§12.2a, §12.2b)
6. time & attendance module (§13a): `time_clock_entries`/`time_clock_breaks` migrations/models, the Simple and Detailed CSV/Excel import parsers, employee/date auto-matching against `shift_assignments`, the import screen with column mapping and per-row error reporting, and manual-correction editing
7. reporting & export: headcount and cost reports, PDF/Excel export, with cost figures preferring actual `time_clock_entries` data over planned shift duration where available (§14.2)
8. employee schedule view (PWA) + notifications: `visibility_scope`-aware view, `vite-plugin-pwa` setup, VAPID key generation, push-subscription registration (§20.2a), `ScheduleChanged` notification (email + Web Push), all rendered per the recipient's `locale` - this is also where the account-creation screen's install prompt (§21.2a) becomes fully functional, once the manifest and `beforeinstallprompt` plumbing it depends on exist
9. frontend polish: unified visual system across admin and employee views, UX refinement, full translation coverage audit across all six locales

Step 5 depends on steps 2-4 (the suggestion service reads staff/line/role/shift-pattern and leave data). Step 6 depends on step 5 (attendance entries auto-match against `shift_assignments`). Step 7 depends on step 6 (cost reporting reads attendance data). Step 8 depends on step 5 (notifications trigger off `shift_assignments` changes). This ordering must be preserved. Localization (step 1) is foundational, not additive - every screen and notification built in steps 2-8 must use the translation system from the start rather than having strings retrofitted later. Unlike an earlier version of this plan that used WhatsApp/SMS, Web Push requires no third-party account, external approval process, or per-language template review - step 8 has no external dependency to wait on.

---

## 25. Final Success Criteria

The build is successful when:

- a planner can generate a rule-aware weekly draft across 3 lines and 3 shifts in a few clicks, instead of building it by hand in Excel
- every rule violation is visible before approval, and approval remains a deliberate, explicit action
- every employee, by default, can see only their own schedule on their phone, installable as a PWA
- an approved schedule change reliably reaches the affected employee by email, and by push notification on any device where they've installed the PWA
- permanent vs. agency cost and headcount are clearly separated in every report
- the rule engine can grow to cover more Dutch ATW constraints later without a rewrite
- any admin or employee can use the product entirely in their own language, chosen at registration and changeable anytime, across all six supported languages
- nobody is ever automatically scheduled into a line-specific position they aren't qualified for, and every case where the system couldn't find a qualified person is surfaced, ranked, and actionable rather than silently dropped
- a schedule can never be approved with a mandatory work station left completely uncovered, while every other constraint in the system remains an overridable warning

---

## 26. Open Items (Require a Follow-Up Decision, Do Not Block Build Start)

- **Employee self-service leave requests**: whether employees should be able to submit their own Vakantie/short-excuse requests from the PWA, or whether this stays admin-only entry for the initial build.
- **Notification scope for `line`/`company`-visibility employees**: whether a change to a colleague's shift (not the viewing employee's own shift) should also trigger a notification to employees with `line`/`company` visibility, or whether notifications stay strictly "your own assignment changed."
- **Required headcount per line/shift/role/day**: whether this is a fixed default per role slot or a per-week configurable input the planner sets before generating a suggestion.
- **Bulk qualification assignment**: whether roles can only be granted one employee at a time from the staff detail screen, or whether the initial build also needs a "assign this role to multiple employees at once" screen for faster onboarding of the existing workforce into the new role system.
