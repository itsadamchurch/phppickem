# NOTICE
*4/16/2018 - This application is no longer maintained.  Feel free to fork and update this application as needed.*

---

# PHP Pick 'Em

PHP Pick 'Em is a free php web application that allows you to host a weekly NFL pick 'em football pool on your website.

## Minimum Requirements

* PHP 7.4+ (Docker image defaults to PHP 8.x)
* MySQL 8.0+ with mysqli enabled
* Composer (recommended for HTMLPurifier dependency)


## Installation Instructions

1. Extract files
2. Create a MySQL database on your web server, as well as a MySQL user who has all privileges for accessing and modifying it.
3. Edit /includes/config.php and update database connection variables accordingly
4. Upload files to your web server
5. Run installer script at http://www.your-domain.com/phppickem/install. The installer will assist you with the rest and can install Composer dependencies.

## Docker (local dev)

One-command bring up + install (auto-seeds the DB from `install/install.sql`):

```
cp .env.example .env && docker compose up --build -d
```

Then open `http://localhost:8080/` in your browser. Default login is `admin / admin123`.

Composer dependencies are installed automatically when the container starts (if `vendor/` is missing).

Turnkey behavior:
- The app entrypoint waits for the DB to be ready and then removes `install/install.sql` after it confirms the schema exists.
- In non‑dev containers (no `.git`), it removes the entire `install/` folder so the security warning is cleared.

If you’re not using Docker, run Composer after install:

```
composer install
```

To stop:

```
docker compose down
```

### Docker (turnkey image)

Build a shareable image:

```
docker build -t yourname/phppickem:2025 .
```

Run without bind-mounting the repo:

```
docker run -d --name phppickem \
  -p 8080:80 \
  -e DB_HOSTNAME=db \
  -e DB_USERNAME=nflpickem \
  -e DB_PASSWORD=nflpickem \
  -e DB_DATABASE=nflpickem \
  yourname/phppickem:2025
```

If you use the included `docker-compose.yml`, the DB is created automatically from `install/install.sql` and the install file is removed on first successful boot.

### Docker Compose (production)

Use the prebuilt image without bind mounts:

```
docker compose -f docker-compose.prod.yml up -d
```

Edit `docker-compose.prod.yml` to set your image name/tag (default: `yourname/phppickem:2025`).

## Playoffs (import + picks)

Import playoff schedule into the DB (2025, regular postseason rounds):

```
http://localhost:8080/buildPlayoffs.php?year=2025&apply=1
```

If ESPN has not published the playoff schedule yet, the import may return 0 games. Re-run the command once the bracket is released.

Playoff picks entry:

```
http://localhost:8080/entry_form.php?type=playoffs
```

Playoff results:

```
http://localhost:8080/results.php?type=playoffs&round=1
```

## Test data (seed + cleanup)

Seed 3 test users (Bob Loblaw, Sal Goodman, Howie Dewitt) and picks for the full regular season:

```
php tests/seedTestData.php --apply=1
```

Optional: include playoff picks too:

```
php tests/seedTestData.php --apply=1 --playoffs=1
```

Cleanup test users and all their picks (regular + playoffs):

```
php tests/cleanupTestData.php --apply=1
```

## Tests (CLI)

Smoke test (login, core pages, admin visibility):

```
php tests/smokeTest.php --base=http://localhost:8080 --user=bob --pass=test1234 --admin_user=admin --admin_pass=admin --user2=sal --pass2=test1234
```

Data presence test (standings + results show seeded user):

```
php tests/dataPresenceTest.php --base=http://localhost:8080 --user=bob --pass=test1234
```

Data accuracy test (stands + results match computed values up to current week):

```
php tests/dataAccuracyTest.php --base=http://localhost:8080 --user=bob --pass=test1234
```

Picks + winners test (verifies seeded picks exist for each game, reports wins/losses for completed games):

```
php tests/picksWinnerTest.php --user=bob
```

Run all tests (excludes seed/cleanup):

```
php tests/runAll.php --base=http://localhost:8080 --user=bob --pass=test1234 --admin_user=admin --admin_pass=admin --user2=sal --pass2=test1234
```

## Admin: Updating Scores

Regular season (ESPN):

```
http://localhost:8080/updateRegularSeasonScores.php?apply=1&year=2025&week=1
```

Playoffs (ESPN):

```
http://localhost:8080/updatePlayoffScores.php?apply=1&year=2025&round=1
```

CLI examples:

```
php updateRegularSeasonScores.php --apply=1 --year=2025 --week=1
php updatePlayoffScores.php --apply=1 --year=2025 --round=1
```

Debug output (shows ESPN URL used and per-week stats):

```
http://localhost:8080/updateRegularSeasonScores.php?week=1&debug=1
```

Notes:
- These scripts require login (or run inside Docker) and update the `schedule` / `playoff_schedule` tables.
- The “Enter Scores” admin page (`scores.php`) pulls from ESPN via `getHtmlScores.php`.

## Admin: Updating Playoff Schedule (ESPN)

Update a playoff round (writes to `playoff_schedule`):

```
http://localhost:8080/buildPlayoffSchedule.php?round=4&apply=1&year=2025
```

Debug output (shows ESPN URL used + games to insert):

```
http://localhost:8080/buildPlayoffSchedule.php?round=4&debug=1&year=2025
```

## Cron Ideas (future)

Regular season scores (e.g., every Tuesday morning during the season):

```
curl -s "http://localhost:8080/updateRegularSeasonScores.php?apply=1&year=2025"
```

Playoff scores (after each round):

```
curl -s "http://localhost:8080/updatePlayoffScores.php?apply=1&year=2025&round=1"
```

Playoff schedule updates (as brackets advance):

```
curl -s "http://localhost:8080/buildPlayoffSchedule.php?round=4&apply=1&year=2025"
```

Tip: if you want JSON for logging/monitoring, add `&debug=1` to these URLs.

## Composer (autoloading)

If you want Composer autoloading (PSR-4):

```
composer dump-autoload
```

## Logging In

Log in for the first time with admin / admin123.  You may change your password once you are logged in.

## Troubleshooting
For help, please visit: http://www.phppickem.com/
