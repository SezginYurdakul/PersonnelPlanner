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
- email and SMS (MessageBird) notifications on approved-schedule changes

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

The product is organized into six product domains:

1. `Staff & Agencies` - employees, employment terms, temp agencies
2. `Lines & Shift Patterns` - production lines, shift time definitions
3. `Leave & Sick Tracking` - leave types, leave requests, approval
4. `Weekly Scheduling` - schedule generation, rule engine, suggestion, manual edit, approval
5. `Reporting & Export` - headcount and cost reports, PDF/Excel export
6. `Employee Schedule View` - visibility-scoped schedule display (PWA) and notifications

There is also one internal support domain:

7. `Rule Engine & Notification Infrastructure`

---

## 7. Visual and Thematic Design

### 7.1 Design Theme

The visual direction should be:

- `operational clarity`
- `calm industrial`
- `readable at a glance from across a factory office`

The app should not look like a consumer social app, and it should not look like a raw admin scaffold (e.g. default Laravel Nova/Filament styling left unstyled).

### 7.2 Color Direction

Primary:

- steel blue / slate

Secondary:

- warm neutral grays
- wheat/amber accent (nods to the bakery domain without being literal/childish)

State colors:

- approved/confirmed: muted green
- proposed/draft: neutral amber
- rule violation/warning: controlled red
- leave (Vakantie): distinct cool tone, consistent with the color-coding already used in the factory's paper schedule
- sick (ziek): distinct from leave, e.g. a warmer red-adjacent tone

Rules:

- keep the same color meaning consistent across the calendar grid, reports, and notifications
- avoid a rainbow-per-employee scheme - color should encode *status/type* (leave, sick, proposed, confirmed), not identity

### 7.3 Typography Direction

- strong distinction between a date/day header and cell content
- tabular figures for shift times and hour totals so columns of numbers align
- compact but legible at small sizes, since the core view is a dense weekly grid (3 lines x 3 shifts x 7 days)

### 7.4 Layout Direction

Rules:

- the admin's weekly schedule view is desktop-first and grid-dense (rows = employees grouped by line, columns = days)
- the employee's schedule view is mobile-first and simplified (a single week, one's own or one's line's shifts only, by default)
- avoid forcing the admin grid layout onto the employee PWA view - they are different information densities for different audiences

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
- vite-plugin-pwa (installable PWA, service worker, manifest)

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
- WeeklyScheduleGrid (admin, dense)
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
- SMS/email sending must be queueable and must not block the HTTP request that triggered it

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
- `NotificationDispatchService` - wraps Laravel's Notification system for email + MessageBird SMS, queued
- `ScheduleExportService` - PDF/Excel generation for reports

---

## 10. Page Map

Required routes (admin-facing, desktop):

- `/login`
- `/dashboard` - admin landing
- `/staff` - employee list/CRUD
- `/staff/:id` - employee detail incl. employment terms
- `/agencies` - temp agency list/CRUD
- `/lines` - production line list/CRUD
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
2. quick counts: active employees, pending leave requests, active rule violations in the current draft
3. shortcuts to staff, lines, shift patterns
4. recent reports

Layout rules:

- the current week's schedule status must be the single most prominent element
- pending leave requests must be visible without navigating away, since they block accurate suggestion generation

### 10A.2 Weekly Schedule Grid (Admin)

Desktop order from top to bottom:

1. week selector and schedule status (draft/proposed/approved) with an approve action
2. "Generate Suggestion" action, scoped to selectable lines/shifts
3. the grid itself: rows grouped by line (plus a leadership/unassigned group), columns = the 7 days of the week; each cell shows an assignment (shift time, optional in-line task/station label) or is blank ("vrij")
4. a rule-violation panel/banner that lists any current violations with a jump-to-cell link
5. per-employee weekly hour totals in a summary column/row

Layout rules:

- the grid must clearly distinguish `auto_suggested` vs `manual` assignments (e.g. a small icon or border style) so the planner can see what they changed
- a cell in Vakantie or ziek state must be visually distinct from a normal shift cell and from a blank "vrij" cell
- editing a cell must show any new rule violation inline, immediately, without a page reload

### 10A.3 Staff List / Detail (Admin)

Desktop order from top to bottom (list):

1. filter bar: employee type (vast/uitzendkracht), agency, line, active/inactive
2. table: name, type, agency (if any), default line, active status

Desktop order from top to bottom (detail):

1. identity block (name, contact, phone, email)
2. employment block (type, agency if applicable, hourly rate, default line)
3. employment terms block (max weekly hours, effective dates)
4. recent assignment history

Layout rules:

- employment terms must be visibly editable independent of the base identity fields, since they change on a different cadence

### 10A.4 Leave Requests (Admin)

Desktop order from top to bottom:

1. filter by status (pending/approved/rejected) and by type (Vakantie/ziek/other)
2. table: employee, type, date range, status, approve/reject actions

Layout rules:

- pending requests must be sorted to the top by default, since they are the actionable ones

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

---

## 11. Staff & Agencies Module Specification

### 11.1 Goal

Maintain an accurate, typed record of every person who can be scheduled, including which temp agency they come through if any, and what their personal scheduling limits are.

### 11.2 Required Behavior

- an employee is either `vast` (permanent) or `uitzendkracht` (temp-agency); this is a single enum field, not a role hierarchy, because scheduling rules apply identically to both types (confirmed with the user) - the only difference is cost and agency attribution
- `flex` and `VZM`, observed as informal labels in the factory's current paper schedule, are both temp-agency categories and are represented as rows in the `agencies` table, not as special-cased employee types
- an agency-linked employee must have an `agency_id` and should have an `hourly_rate` distinct from permanent-staff cost handling
- a `default_line_id` on an employee is a weighting/default hint only for the suggestion engine - it never hard-restricts which line an employee can be assigned to, since the real schedules show staff moving between lines within the same week
- leadership/management staff (e.g. "Leiding" in the current paper schedule) are modeled as employees with `default_line_id = null`

### 11.3 Acceptance Criteria

- an admin can create, edit, and deactivate an employee of either type
- an admin can create and edit agencies, and link/unlink an employee to one
- an admin can set and later change an employee's `max_weekly_hours` without losing the history of the previous value (see §17, `employment_terms`)

---

## 12. Weekly Scheduling Module Specification

### 12.1 Goal

Let a planner generate a rule-aware draft for a given week across 3 lines and 3 shifts, edit it, and approve it - replacing the manual paper/Excel process while keeping the planner as the final decision-maker.

### 12.2 Suggestion Generation Flow

`ScheduleSuggestionService`:

1. Input: `week_start_date`, plus which lines/shifts to generate for.
2. Build the eligible candidate pool: `employees.is_active = true` AND no approved leave/sick record covering that specific day (`leave_requests.start_date <= work_date <= end_date AND status = 'approved'`), computed independently per day since a person may be eligible some days of the week and not others.
3. For each day x line x shift slot: run the `RuleEngine` against each candidate (would assigning this slot exceed this person's `max_weekly_hours`?), drop rule-violating candidates, rank the remainder by a simple fairness score (fewest hours assigned so far this week wins), and select the required headcount for that slot.
4. Write the selected assignments into `shift_assignments` with `status = proposed`, `source = auto_suggested`.
5. The planner reviews the grid, edits freely (any manual change is marked `source = manual`; the `RuleEngine` re-evaluates on every edit and updates the violation panel), then calls "Approve," which transitions `schedules.status = approved` and triggers §12.4.

### 12.3 Manual Editing Rules

- a rule violation blocks nothing - it is a visible warning only
- every edit re-runs the affected employee's rule checks so the violation panel is always current
- an approved schedule can still be edited afterward; each post-approval edit re-triggers the notification flow (§12.4) for the affected employee(s)

### 12.4 Notification Trigger

- a notification fires only for changes to an **approved** schedule (including the moment of initial approval), never for draft/proposed edits, to avoid spamming employees with every planning iteration
- the affected employee is the one whose `shift_assignments` row changed; if their `visibility_scope` is `line` or `company`, a change to another employee sharing their line during a week they're scheduled may also warrant a notification (see open items, §26)

### 12.5 Acceptance Criteria

- a planner can generate a full-week draft in under a few seconds for a normal-sized workforce
- the suggestion never proposes a slot for someone with approved leave/sick covering that day
- the suggestion never proposes a slot that would push someone over their personal `max_weekly_hours` (unless the planner manually overrides afterward)
- approving a schedule is a single explicit action, and cannot be un-done silently

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

## 14. Reporting Module Specification

### 14.1 Goal

Give management a trustworthy, exportable view of who worked, on what line, and at what cost, split by permanent vs. agency staff.

### 14.2 Must Show

- headcount per line per shift per day, for a selected week or month
- total hours and cost per employee type (vast vs. uitzendkracht), with an agency-level subtotal for uitzendkracht
- PDF export (via `barryvdh/laravel-dompdf`) and Excel export (via `maatwebsite/excel`)

### 14.3 Acceptance Criteria

- a report for a past approved week reflects the actual approved `shift_assignments`, not any leftover draft/proposed data
- the cost breakdown correctly separates vast and uitzendkracht hours and cost
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
- delivered to both the employee's registered **email** and **phone number** (SMS via MessageBird)
- a `ScheduleChanged` Laravel notification class implements `toMail` and a MessageBird-backed SMS channel; both are queued so sending never blocks the approval/edit request

### 15.5 Acceptance Criteria

- a new employee, by default, can only see their own shifts
- an admin can change an employee's visibility scope and it takes effect immediately
- an employee receives both an email and an SMS when an approved shift affecting them is created, changed, or removed
- the schedule view can be installed to a phone's home screen and opened without a browser address bar

---

## 16. Rule Engine Specification

### 16.1 Goal

Enforce scheduling constraints in a way that is transparent to the planner and easy to extend as more Dutch ATW (Arbeidstijdenwet) rules and company-specific rules are added over time.

### 16.2 Initial Rule

- **Max Weekly Hours** - each employee has a `max_weekly_hours` value (default 40, but can be set higher or lower per person via `employment_terms`); the suggestion engine will not propose a slot that pushes someone over this limit, and a manual edit that does so is flagged as a violation (not blocked)

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
- `shift_patterns`
- `schedules`
- `shift_assignments`
- `leave_types`
- `leave_requests`

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
- `hourly_rate` nullable decimal
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
- `station` nullable string (e.g. `"deeg"`, `"snij"`, `"doop"`, `"draai"` - in-line task/role observed in the factory's current paper schedule)
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

---

## 17A. Database Constraints and Rules

### 17A.1 Users

Constraints:

- `email` must be unique
- `visibility_scope` defaults to `own`
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
- `hourly_rate` nullable, but expected to be set for `uitzendkracht`
- `default_line_id` nullable, never a hard scheduling constraint
- `is_active` defaults to `true`

Indexes:

- foreign key on `agency_id` with `nullOnDelete`
- foreign key on `default_line_id` with `nullOnDelete`
- foreign key on `user_id` with `nullOnDelete`
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
- a given `(employee_id, work_date)` should not have overlapping shift times across two rows - enforced at the application layer when creating/editing an assignment, since overlap depends on the linked `shift_pattern`'s start/end time, not a simple column comparison

Indexes:

- foreign key on `schedule_id` with cascade delete
- foreign key on `employee_id` with cascade delete
- foreign key on `line_id` with `restrictOnDelete`
- foreign key on `shift_pattern_id` with `nullOnDelete`
- index on `(employee_id, work_date)`
- index on `(schedule_id, line_id, work_date)`

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

### 17A.10 Referential and Deletion Rules

- deleting an employee must cascade to their own `shift_assignments`, `employment_terms`, and `leave_requests` (historical schedule data for other employees must remain intact)
- deleting a line must not be allowed while active `shift_assignments` reference it (`restrictOnDelete`) - deactivate instead of deleting
- deleting a shift pattern should not destroy historical assignment records; prefer `nullOnDelete` so history remains stable
- deleting an agency must not cascade-delete its employees; use `nullOnDelete` on `employees.agency_id` and require the admin to reassign or deactivate those employees explicitly

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
- `PUT /v1/users/{user}/visibility-scope`

#### Example: `GET /v1/employees`

Query params:

- `employee_type` (`vast` | `uitzendkracht`)
- `agency_id`
- `line_id` (matches `default_line_id`)
- `is_active`

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
      "is_active": true
    }
  ]
}
```

### 18.3 Lines & Shift Patterns

- `GET|POST /v1/lines`
- `GET|PUT|DELETE /v1/lines/{line}`
- `GET|POST /v1/shift-patterns`
- `GET|PUT|DELETE /v1/shift-patterns/{shiftPattern}`

### 18.4 Leave

- `GET|POST /v1/leave-types`
- `GET|POST /v1/leave-requests`
- `GET|PUT|DELETE /v1/leave-requests/{leaveRequest}`
- `POST /v1/leave-requests/{leaveRequest}/approve`
- `POST /v1/leave-requests/{leaveRequest}/reject`

### 18.5 Scheduling

- `GET|POST /v1/schedules`
- `GET /v1/schedules/{schedule}`
- `POST /v1/schedules/{schedule}/suggest`
- `POST /v1/schedules/{schedule}/approve`
- `GET|POST /v1/schedules/{schedule}/assignments`
- `PUT|DELETE /v1/shift-assignments/{assignment}`

#### Example: `POST /v1/schedules/{schedule}/suggest`

Request example:

```json
{
  "line_ids": [1, 2, 3],
  "shift_pattern_ids": [1, 2, 3, 4, 5, 6]
}
```

Response example:

```json
{
  "data": {
    "schedule_id": 14,
    "status": "proposed",
    "assignments_created": 63,
    "violations": [
      {
        "employee_id": 12,
        "work_date": "2026-08-04",
        "rule": "MaxWeeklyHoursRule",
        "severity": "warning",
        "message": "This assignment would put Ahmet Yilmaz at 44 hours this week (limit: 40)."
      }
    ]
  }
}
```

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
- **SMS**: MessageBird (chosen by the user - Netherlands-based provider, natural fit for this Dutch-labor-context factory), via a custom notification channel, queued

### 20.3 UX Requirements

- the planner sees a confirmation that notifications were queued after approving/editing an approved schedule
- a failed SMS/email send must be logged and retryable, not silently dropped

### 20.4 Acceptance Criteria

- approving a new schedule sends one email + one SMS per affected employee
- editing a single assignment in an already-approved schedule sends a notification only to the employee(s) whose assignment changed
- notification sending never blocks or slows down the approval/edit HTTP response (queued)

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

### 21.3 Acceptance Criteria

- the employee schedule view can be added to a phone's home screen
- the most recently loaded week remains viewable without a network connection
- opening the installed PWA does not show a browser address bar

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

### Lines & Shift Patterns

- a new shift pattern with an arbitrary start/end time can be added without a code change
- deactivating a line does not delete historical shift assignments

### Leave & Sick

- an approved Vakantie/ziek record removes the employee from the suggestion pool for every day in range
- a day with no assignment ("vrij") requires no approval workflow

### Weekly Scheduling

- suggestion generation never proposes a slot for someone on approved leave that day
- suggestion generation never proposes a slot that violates `MaxWeeklyHoursRule` (unless later manually overridden)
- a manual edit re-evaluates rules immediately and updates the violation panel
- approving a schedule is a single explicit, auditable action

### Reporting

- headcount and cost reports reflect only approved `shift_assignments`
- cost breakdown correctly separates vast vs. uitzendkracht, with per-agency subtotals
- PDF and Excel exports open correctly

### Employee View & Notifications

- a new employee defaults to `own`-only visibility
- an admin changing visibility scope takes effect immediately for that employee
- an approved-schedule change sends both email and SMS to the affected employee
- the schedule PWA installs to a phone home screen and works offline for the cached week

### Backend

- controllers remain thin
- rule engine can be extended with a new rule class + config entry, with zero changes to existing rule classes
- notification sending is queued and does not block the triggering request

---

## 24. Build Order

Recommended implementation order:

1. project skeleton: Laravel API setup, PostgreSQL, Sanctum, `spatie/laravel-permission` + role/visibility-scope seeding, React+Vite+TS skeleton, login
2. staff & agencies module (employees, employment terms, agencies) - migrations, models, controllers, resources, admin frontend screens
3. lines & shift patterns module - migrations, models, controllers, resources, admin frontend screens
4. leave & sick tracking module - migrations, models, controllers, approval flow, admin frontend screens
5. weekly scheduling core: `schedules`, `shift_assignments`, rule engine (`MaxWeeklyHoursRule` + disabled skeletons), `ScheduleSuggestionService`, suggest/approve endpoints, admin schedule grid
6. reporting & export: headcount and cost reports, PDF/Excel export
7. employee schedule view (PWA) + notifications: `visibility_scope`-aware view, `vite-plugin-pwa` setup, `ScheduleChanged` notification (email + MessageBird SMS)
8. frontend polish: unified visual system across admin and employee views, UX refinement

Step 5 depends on steps 2-4 (the suggestion service reads staff/line/shift-pattern and leave data). Step 7 depends on step 5 (notifications trigger off `shift_assignments` changes). This ordering must be preserved.

---

## 25. Final Success Criteria

The build is successful when:

- a planner can generate a rule-aware weekly draft across 3 lines and 3 shifts in a few clicks, instead of building it by hand in Excel
- every rule violation is visible before approval, and approval remains a deliberate, explicit action
- every employee, by default, can see only their own schedule on their phone, installable as a PWA
- an approved schedule change reliably reaches the affected employee by both email and SMS
- permanent vs. agency cost and headcount are clearly separated in every report
- the rule engine can grow to cover more Dutch ATW constraints later without a rewrite

---

## 26. Open Items (Require a Follow-Up Decision, Do Not Block Build Start)

- **MessageBird account/API credentials**: needed before Phase 7 (notifications) can be implemented and tested end-to-end; not needed for phases 1-6.
- **Employee self-service leave requests**: whether employees should be able to submit their own Vakantie/short-excuse requests from the PWA, or whether this stays admin-only entry for the initial build.
- **Notification scope for `line`/`company`-visibility employees**: whether a change to a colleague's shift (not the viewing employee's own shift) should also trigger a notification to employees with `line`/`company` visibility, or whether notifications stay strictly "your own assignment changed."
- **Required headcount per line/shift/day**: whether this is a fixed default per slot or a per-week configurable input the planner sets before generating a suggestion.
