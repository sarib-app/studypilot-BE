# StudyPilot — Phase 2 Database Schema

Backend: Laravel + Sanctum (API token auth). Local development runs on SQLite for convenience; production will run on MySQL/MariaDB per the agreed stack — the schema below is identical either way, Laravel manages both through the same migrations.

This covers the tables built for Phase 2 (Application Foundation): accounts, authentication, profile setup, and subject selection. Planning, sessions, quizzes, badges, and other Phase 3/4 tables aren't built yet, per the phased scope.

---

## `users`
The student account. Created at signup (email/password or Google), filled in progressively through onboarding.

| Column | Type | Notes |
|---|---|---|
| id | integer | Primary key |
| name | string | First name |
| email | string | Unique |
| email_verified_at | datetime, nullable | Set once email verification is built (Phase 3) |
| password | string, hashed | Randomized (unusable) for Google/Apple-only accounts |
| dob | date, nullable | Required for email/password signup (13+ gate enforced here); collected later in Profile Setup for Google/Apple accounts, which don't share it |
| grade_year | string, nullable | e.g. "Grade 11" |
| curriculum | string, nullable | e.g. "American Curriculum", "Cambridge" |
| city | string, nullable | |
| goal_type | string, nullable | Weekly study goal type chosen in onboarding |
| weekly_study_goal_hours | integer, nullable | |
| google_id | string, nullable, unique | Set once a Google account is linked |
| apple_id | string, nullable, unique | Reserved for Apple Sign-In (not yet built) |
| organization_id | integer, nullable | Reserved for a future school/organization tier — always null in MVP |
| created_at / updated_at | datetime | |

## `subjects`
Admin-managed subject list (e.g. Calculus, Biology) plus each student's own custom "Other" entries. There's no admin content-management screen yet (that's Phase 4) — the initial list is seeded directly.

| Column | Type | Notes |
|---|---|---|
| id | integer | Primary key |
| name | string | |
| color_key | string, nullable | Which accent color the subject uses in the app |
| user_id | integer, nullable, FK → users | Null = admin-managed, global; set = a student's own custom subject, never shown to other students |
| created_at / updated_at | datetime | |

## `subject_user`
Which subjects each student selected during onboarding (many-to-many link between `users` and `subjects`).

| Column | Type | Notes |
|---|---|---|
| user_id | integer, FK → users | |
| subject_id | integer, FK → subjects | |
| created_at / updated_at | datetime | |

---

## Framework tables

These are standard Laravel/Sanctum infrastructure, not app-specific data:

- **personal_access_tokens** — API login sessions (Sanctum)
- **password_reset_tokens** — temporary tokens for the "forgot password" flow
- **cache, jobs, failed_jobs, job_batches, cache_locks, sessions** — Laravel's internal queue/cache machinery, present by default, unused by app logic so far
