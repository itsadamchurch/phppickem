# NOTICE
*4/16/2018 - This application is no longer maintained.  Feel free to fork and update this application as needed.*

---

# PHP Pick 'Em

PHP Pick 'Em is a free php web application that allows you to host a weekly NFL pick 'em football pool on your website.

## Minimum Requirements

* PHP version 5.2 or greater
* MySQL version 5.0 or greater with mysqli enabled
* Mcrypt module for password encryption


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

If you’re not using Docker, run Composer after install:

```
composer install
```

To stop:

```
docker compose down
```

## Playoffs (import + picks)

Import playoff schedule into the DB (2025, regular postseason rounds):

```
http://localhost:8080/buildPlayoffs.php?year=2025&apply=1
```

If ESPN has not published the playoff schedule yet, the import may return 0 games. Re-run the command once the bracket is released.

Playoff picks entry:

```
http://localhost:8080/playoff_entry.php
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

## Composer (autoloading)

If you want Composer autoloading (PSR-4):

```
composer dump-autoload
```

## Logging In

Log in for the first time with admin / admin123.  You may change your password once you are logged in.

## Troubleshooting
For help, please visit: http://www.phppickem.com/
