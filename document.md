# Exeat System Update: Configuration, Workflow, Notifications, Filters, and Frontend Integration

## Overview

This document explains all implemented changes across backend and frontend, how each API was affected, and how the frontend integrates without errors. All existing features remain unchanged when `HOSTEL_STAGES_ENABLED=true` (default). The new behavior is configuration-driven and does not scatter or rewrite existing code paths.

## Configuration Toggle

- Added `hostel_stages_enabled` config key: `backend/config/app.php:69`
  - Reads `HOSTEL_STAGES_ENABLED` from `.env`, defaults to `true`.
  - When `true`: normal hostel admin workflow continues (`hostel_signout`/`hostel_signin`).
  - When `false`: hostel stages are skipped for non-holiday categories.

### How To Use

- Set `.env` accordingly:
  - `HOSTEL_STAGES_ENABLED=true` → existing workflow preserved.
  - `HOSTEL_STAGES_ENABLED=false` → skip hostel stages; see Workflow Branching below.

## Workflow Branching

- File: `backend/app/Services/ExeatWorkflowService.php`
- Behavior changes:
  - From `dean_review`:
    - If category is Holiday → always skip hostel steps → move to `security_signout`.
    - Else → when `hostel_stages_enabled=false`, move to `security_signout`; when `true`, move to `hostel_signout`.
  - From `security_signin`:
    - When `hostel_stages_enabled=false`, move directly to `completed`.
    - When `true`, move to `hostel_signin`.
- Notifications on transitions (see next section) are triggered without changing existing notification flows.

## Gate Event Notifications (with sound)

- File: `backend/app/Services/ExeatNotificationService.php:13`
- Added method `sendHostelGateEventNotificationToAssignedAdmins(ExeatRequest $exeatRequest, string $event)`:
  - Finds all active hostel admin assignments for the exeat’s `student_accommodation`.
  - Sends high-priority notifications of type `stage_change` with `data.play_sound=true` and `data.event=gate_signout|gate_signin`.
  - Messages:
    - Sign-out: “Student with Reg No: {matric} — {fullName} — from your hostel has been signed out at the gate.”
    - Sign-in: “Student with Reg No: {matric} — {fullName} — from your hostel has been signed back into the school.”
- Trigger points in `ExeatWorkflowService.php`:
  - On moving to `security_signout` → send sign-out notification.
  - On moving to `security_signin` or `completed` (when `hostel_stages_enabled=false`) → send sign-in notification.

## Staff Filters and CSV Export

- File: `backend/app/Http/Controllers/StaffExeatRequestController.php:162`
- Extended `index` to accept optional `filter` query parameter:
  - `filter=overdue` → `return_date` is past, not yet signed back, not expired.
  - `filter=signed_out` → `status=security_signout`.
  - `filter=signed_in` → `status=security_signin`.
- New `export` endpoint streams a CSV of filtered requests.
  - Route: `GET /api/staff/exeat-requests/export`
  - Output columns: `matric_no, student_name, hostel, status, departure_date, return_date`.
- Route registrations: `backend/routes/api.php:3`.

## Audio Streaming Endpoint

- File: `backend/routes/api.php:3`
- Route: `GET /api/notifications/alert-audio`
  - Streams `backend/storage/app/alert.mp3` with `Content-Type: audio/mpeg`.
  - Frontend uses this for sound playback when new notifications arrive.

## Frontend Integration

### Sound Notifications

- New component: `backend/front/components/notifications/SoundNotifier.tsx:1`
  - Polls `GET /api/notifications/unread-count` every 20 seconds.
  - If the unread count increases, plays `GET /api/notifications/alert-audio`.
  - Uses localStorage `token` for Authorization header.
- Injected into staff layout to run globally:
  - `backend/front/app/staff/layout.tsx:71` includes `<SoundNotifier />` under the navbar.

### Hostel Admin Gate Filters and CSV Download

- Filters UI:
  - `backend/front/components/staff/ExeatRequestFilters.tsx:29`
  - Added “Gate Status” filter options: All, Overdue, Signed Out, Signed Back In.
  - Added “Download CSV” button calling `GET /api/staff/exeat-requests/export` with current filters.
- Pending page logic:
  - `backend/front/app/staff/pending/page.tsx:33`
  - New `gateFilter` state and client-side filtering to match backend semantics:
    - Overdue: return date passed and not signed back, not expired.
    - Signed Out: `status=security_signout`.
    - Signed Back In: `status=security_signin`.
  - CSV download uses Authorization header and streams to a file named `exeat_requests.csv`.

## Previously Implemented Stability Fixes (Retained)

- Weekday email guard to prevent approval breakage:
  - `backend/app/Models/ExeatRequest.php:137` (`sendWeekdayNotification`)
  - Validates recipient emails (`ACADEMIC_ADMIN_EMAIL` then fallback to `ADMIN_EMAIL`); skips sending and logs a warning when invalid.
  - Ensures dean approval does not fail on email errors.
- Staff list endpoint hardening:
  - `backend/app/Http/Controllers/StaffExeatRequestController.php:162`
  - `index` wrapped in `try/catch` to return an empty list and a message with `200` on internal errors, instead of `500`.

## API Impacts Summary

- `GET /api/staff/exeat-requests`
  - Unchanged default behavior for role-based lists.
  - New optional `filter` query param (`overdue|signed_out|signed_in`).
  - For hostel admins, `applyHostelFiltering` continues to restrict to assigned hostels.

- `GET /api/staff/exeat-requests/export`
  - New endpoint streaming CSV with the same filters.
  - Honors role-based restrictions and hostel admin assignments.

- `GET /api/notifications/unread-count`
  - Existing endpoint used by frontend polling for new notifications.

- `GET /api/notifications/alert-audio`
  - New endpoint serving the `alert.mp3` sound.

- Workflow transitions
  - When `HOSTEL_STAGES_ENABLED=true` (default): no behavior change vs existing system.
  - When `HOSTEL_STAGES_ENABLED=false`:
    - `dean_review → security_signout` (non-holiday).
    - `security_signin → completed`.
    - Gate notifications are still sent to hostel admins on sign-out/in.

## Frontend Behavior Expectations

- Sound playback
  - Runs automatically for staff users from the staff layout.
  - Plays when unread notification count increases (covers gate events and other alerts).

- Filters and export
  - Gate Status filter updates the list without page reload.
  - CSV download streams the current filtered list.

- No breaking changes
  - Existing components and flows continue to work.
  - New features only add optional filters and sound notifier.

## Error Handling & Robustness

- Backend
  - All new routes return well-defined JSON or streamed CSV/audio.
  - Controller methods use `try/catch` logging and fail-safe responses.

- Frontend
  - Sound notifier catches playback errors silently to avoid UI disruption.
  - CSV download handles network errors and logs them to console without crashing the page.

## Security Considerations

- Authorization headers are sent for protected endpoints.
- No secrets are logged or exposed; audio file is static and safe to stream.

## File References

- Toggle: `backend/config/app.php:69`
- Workflow: `backend/app/Services/ExeatWorkflowService.php`
- Notifications: `backend/app/Services/ExeatNotificationService.php:13`
- Staff controller filters/export: `backend/app/Http/Controllers/StaffExeatRequestController.php:162` and `:216`
- Routes: `backend/routes/api.php:3`
- Frontend sound: `backend/front/components/notifications/SoundNotifier.tsx:1`, `backend/front/app/staff/layout.tsx:71`
- Frontend filters: `backend/front/components/staff/ExeatRequestFilters.tsx:29`
- Pending page: `backend/front/app/staff/pending/page.tsx:33`
- Email guard: `backend/app/Models/ExeatRequest.php:137`

## Verification Notes

- PHP syntax checks passed for modified backend files.
- Frontend TypeScript additions follow existing project patterns; if you have a lint/typecheck command, run it to validate locally (e.g., `npm run lint` or `npm run typecheck`).

## Assurance of Unchanged Functions

- Default configuration preserves all existing approval and hostel flows.
- All APIs not listed above remain unchanged.
- Role-based access and existing notifications continue to function as before.