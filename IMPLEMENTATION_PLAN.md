# Gym Management System — Implementation Plan

Based on `gym mvp.pdf` (SRD/SRS/UI-UX spec) and the database schema design agreed in conversation. This is a planning document only — no code has been written against this plan yet.

Stack confirmed from the existing repo: Laravel 12, PHP 8.2, MySQL, Vite + Tailwind 4, no auth package or frontend framework installed yet.

## Open decisions (confirm before Phase 1 / Phase 5)

| Decision | Recommendation | Why |
|---|---|---|
| Frontend approach | **Livewire** (Blade components, no separate API/SPA) | The spec is modal-heavy, table-heavy, and wants real-time dashboard updates without full page reloads (§13, §16–17) — Livewire gets this natively inside Blade using the broadcasting layer already decided, without standing up a separate REST API + Vue/React SPA (Inertia) or hand-wiring Alpine.js for every modal |
| Auth scaffolding | **Laravel Breeze**, Livewire stack | Lightweight, unopinionated, gives login/password-reset/profile scaffolding matching §8 and §19.2 directly, and its Livewire stack is consistent with the frontend choice above — Jetstream's teams/2FA are unneeded overhead here |
| Broadcast driver | **Laravel Reverb** (self-hosted) over Pusher | First-party, free, self-hosted — no recurring third-party SaaS cost for a single gym's internal tool |

## Phase 1 — Foundation

- Install Breeze (Livewire stack), Reverb, `giggsey/libphonenumber-for-php`.
- Switch `.env`: `BROADCAST_CONNECTION=reverb`, keep `QUEUE_CONNECTION=database` (already correct — events/notifications need a real queue, not `sync`).
- Confirm `SESSION_DRIVER=database` is fine as-is (already set).

## Phase 2 — Database layer

Migrations in FK-dependency order (each depends only on tables above it):

1. `users` (extend default migration: add `role` enum, `profile_image`, `deleted_at`, `email_active` generated column + unique index)
2. `application_settings` (singleton: `id` fixed, `CHECK (id = 1)`)
3. `plans` (`deleted_at`, `plan_name_active` generated column + unique index, CHECK constraints)
4. `members` (`created_by` FK, `deleted_at`, `phone_active` generated column + unique index, `phone_number` regex CHECK, `status` enum restricted to active/expired)
5. `subscriptions` (FKs to members/plans, all CHECK constraints: date ordering, price/balance non-negativity, `balance = plan_price - amount_paid`)
6. `payments` (FKs to members/subscriptions/users, `amount > 0` CHECK) — plus a raw-SQL migration for the `BEFORE INSERT` trigger enforcing the overpayment rule, since Laravel's schema builder can't express triggers; this one migration will contain `DB::unprepared()` SQL
7. `notifications` (FK to users, type enum, composite index)
8. `audit_logs` (FK to users, composite index, no `updated_at`)

Seeders: `application_settings` singleton row, at least one Admin user, the three example plans (Monthly/Weekly/Daily) as the initial catalog per §6 example pricing.

## Phase 3 — Models & relationships

- `User`, `Member`, `Plan`, `Subscription`, `Payment`, `Notification` (custom, not Laravel's built-in notifications, since we designed our own table), `AuditLog`, `ApplicationSetting`.
- `SoftDeletes` trait on `User`, `Member`, `Plan` only.
- Eloquent relationships matching the ERD: `Member::subscriptions()`, `Member::payments()`, `Subscription::payments()`, `Subscription::plan()`, `Subscription::member()`, `User::createdMembers()`, `User::receivedPayments()`.
- Query scopes: `Member::active()`, `Member::expired()`, `Member::archived()` (the last one is `onlyTrashed()`, not a `status` filter — matching the decision that "archived" is derived, not stored).
- `ApplicationSetting` accessed via a small cached singleton accessor (e.g. `Setting::current()`), not a repeated query per request — settings are read on nearly every page (sidebar branding, theme).

## Phase 4 — Authorization

- Laravel Policies: `MemberPolicy`, `PlanPolicy`, `PaymentPolicy`, `UserPolicy`, `SettingPolicy` — encode the Admin vs Receptionist matrix from §3.1/§3.2 exactly (Receptionist: no delete/plans/pricing/users/settings/financial-reports).
- Gate/middleware combination on route groups so an entire route group (e.g. `/settings/*`) is Admin-only at the routing layer, not just hidden in the UI — since the spec explicitly requires server-side exclusion (Receptionist "shall not see" revenue, not just have it visually hidden).

## Phase 5 — Core domain services (the business-rule-critical layer)

Plain service classes, not fat controllers — this is where Rule 5 (atomic transaction) and the overpayment rule actually get orchestrated:

- `MemberRegistrationService::register()` — wraps member+subscription+payment creation in a single `DB::transaction()`, per Rule 5. Rolls back entirely if payment validation fails (Rule 4).
- `MembershipRenewalService::renew()` — creates a *new* subscription row (never mutates the old one), per the Renewal Rules — old subscriptions/payments stay untouched.
- `PaymentService::record()` — records a payment against an existing subscription, updates `subscriptions.amount_paid`/`balance` in the same transaction (maintaining the denormalized aggregate), relies on the DB trigger as the hard backstop against overpayment but should also pre-check and return a friendly validation error before ever hitting the DB.
- Phone canonicalization: a small `PhoneNumberService` wrapping libphonenumber, called from the Member creation/edit Form Requests before anything touches the database.

## Phase 6 — Scheduled jobs

- `app/Console/Commands/ExpireMemberships.php` — the cron-driven expiry job. Query: `subscriptions WHERE status = 'active' AND expiry_date < today` (this is exactly why that composite index exists), flip `subscriptions.status` and the corresponding `members.status`, then dispatch a `MembershipExpired` event per member (not a direct notification write — see Phase 7).
- Registered in `routes/console.php` via `Schedule::command(...)->daily()` (or hourly, depending on how promptly expiry should be reflected — worth deciding when we get there).

## Phase 7 — Events, listeners, notifications (event-driven)

- Events: `MemberRegistered`, `MembershipRenewed`, `MembershipExpired` — dispatched from the Phase 5 services and the Phase 6 cron job, not created inline.
- Listeners: one per event, each resolves the recipient set (all Admin users, at minimum) and fan-out-inserts one `notifications` row per recipient.
- Listeners implement `ShouldQueue` (queue is already `database`-backed) so notification writes don't block the request/cron cycle.
- A separate listener on the same events broadcasts to the WebSocket channel for live dashboard/notification-badge updates (Phase 8) — kept distinct from the DB-write listener, so a broadcasting failure never blocks the notification actually being persisted.

## Phase 8 — Real-time (WebSockets)

- Reverb server config + a private channel per user (`private-App.Models.User.{id}`) for personal notifications, plus a shared channel (e.g. `dashboard`) broadcasting aggregate stat changes (Total/Active/Expired Members, Revenue figures) on Payment/Member/Renewal/Expiry events, per §13.1's event-to-stat mapping table in the spec.
- Frontend: Laravel Echo + `laravel-echo`/`pusher-js` (works with Reverb) wired into the Livewire components so stat cards and the notification bell update without a page refresh.

## Phase 9 — Audit logging

- A model `Observer` (not scattered manual calls) on `Member`, `Plan`, `Payment`, `ApplicationSetting`, and auth events (login/logout) — writes to `audit_logs` automatically on create/update/delete, capturing the acting user via `Auth::id()` and the request IP.
- Centralizing this in observers avoids the risk of a controller forgetting to log an action — matches §19.1's list of "must log" actions.

## Phase 10 — Controllers & routes

- Resource controllers per module: `MemberController`, `PlanController`, `PaymentController`, `SubscriptionController` (renewal-specific actions), `SettingController`, `UserController`.
- Route groups mirroring the two sidebars (§5–6): an `admin` middleware group with everything, a shared group both roles can hit (Dashboard, Members, Profile).
- Every index route (`/members`, `/plans`, `/payments`, `/users`) supports the search+pagination+filter combination from §14 — implemented as query scopes composed in the controller/service, not duplicated per controller.

## Phase 11 — Frontend (Livewire components)

- Layout: sidebar (role-conditional per §5/§6) + top nav (theme toggle, notification bell, profile menu) as a shared Blade layout.
- Livewire components: `MemberIndex` (search/filter/paginate), `MemberForm` (create/edit modal), `RenewalModal`, `PaymentForm`, `AdminDashboard` (stat cards + charts, wired to the broadcast channel), `ReceptionistDashboard` (stat cards only, no revenue), `SettingsPage`, `NotificationPanel`.
- Charts: a lightweight JS charting lib (Chart.js is the common Livewire pairing) fed by controller/service-computed data, not raw SQL in Blade.
- Theme toggle: persisted per-user preference (a column on `users` or a cookie — worth deciding when we get there; not in the current schema, would need a small addition).

## Phase 12 — Testing

- Feature tests per business rule, since these are the parts most likely to regress: atomic rollback on failed payment (Rule 4), renewal preserving old subscriptions, overpayment rejection (both the app-level check and the DB trigger, tested independently), soft-delete hiding archived members from default queries, Receptionist getting a 403 on Admin-only routes/data.
- A dedicated test for the MySQL generated-column uniqueness behavior (soft-deleted member freeing up their phone number for reuse, active-duplicate rejection) — this is DB-specific behavior that's easy to assume works without checking.

## Phase 13 — Ops/deployment considerations

- `composer.json`'s existing `dev` script already runs `queue:listen` alongside `serve`/`vite` — will need a Reverb process added to that concurrently-run list for local dev.
- Production: queue worker (Supervisor or equivalent) + Reverb server + the scheduler's cron entry (`* * * * * php artisan schedule:run`) — three long-running/periodic processes beyond the web server itself, a step up from a purely request/response app.
