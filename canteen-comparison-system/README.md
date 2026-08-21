# College Canteen Comparison System

A multi-role website where students and faculty rate the college's 3 canteens
(Bappa, IMDR, Media) across 5 criteria — price, quality, cleanliness, speed,
and hygiene — and a **TOPSIS** ranking engine combines them into a single,
defensible "best canteen" answer that adapts based on what the user actually
needs it for (hangout, quick bite, budget-conscious, or custom weights).

Built on top of real data from a prior field project (Google Form survey of
BAPPA/IMDR/Media canteens), upgraded into a full PHP + PostgreSQL web app.

## Features

- Role-based login: student, faculty, canteen admin, super admin
- Per-canteen menu management (canteen admins only manage their own canteen —
  enforced at both the application layer and the database layer)
- 5-criteria canteen ratings + free-text feedback with like/dislike voting
- Dish-level ratings ("which canteen has the best Vada Pav")
- Crowd check-ins ("I'm here now") aggregated into hour-of-day crowd patterns
- **TOPSIS** multi-criteria ranking engine with 4 preset weight profiles
  (overall / hangout / quick bite / budget) plus custom weights
- **Bayesian-adjusted averages** to correct for sampling bias — canteens with
  few ratings get pulled toward the overall mean instead of being fully
  trusted, so a thin sample can't distort the ranking
- Analytics dashboard (price, ratings, crowd patterns) via Chart.js
- CSRF protection on every form; bcrypt password hashing; prepared statements
  throughout; canteen admins blocked from rating/reviewing their own canteen

## Tech stack

PHP 8 + PostgreSQL, deployed on CentOS via Apache. No frameworks, no ML.

## Setup

```bash
# 1. Create the database and run the SQL files in order
createdb canteen_comparison
psql -d canteen_comparison -f database/schema.sql
psql -d canteen_comparison -f database/triggers.sql
psql -d canteen_comparison -f database/views.sql
psql -d canteen_comparison -f database/seed-data.sql

# 2. Configure the app
cp config/config.example.php config/config.php
# edit config/config.php with your DB credentials

# 3. Run locally
cd public
php -S localhost:8080
```

Then open `http://localhost:8080/login.php`.

Seed login (all seed users share the password `password123`):
`aastha@college.edu` (student), `admin.bappa@college.edu` (canteen admin), etc.

## Project structure

```
database/     schema, triggers, views, seed data (SQL)
includes/     shared PHP: db connection, auth, TOPSIS engine
public/       all web-facing pages
config/       DB config (config.php is gitignored — never commit real credentials)
```

## The algorithmic core

`includes/topsis.php` implements TOPSIS (Technique for Order of Preference by
Similarity to Ideal Solution) — normalizes each criterion, applies weights,
finds the ideal-best and ideal-worst canteen on paper, then ranks real
canteens by how close they are to the ideal and how far from the worst.
Combined with a Bayesian shrinkage adjustment (`canteen_criteria_bayesian()`
in `database/views.sql`) that corrects for uneven sample sizes across
canteens before the ranking runs.
