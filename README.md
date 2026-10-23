# Campus Lost & Found Portal

A campus platform where students report lost and found belongings, search the board,
and claim items. Every claim is verified in person by the Lost & Found desk (admin).

**Stack:** HTML5 · CSS3 · JavaScript · PHP (PDO) · MySQL · PHP sessions · Docker · Git

## Features

- Registration (bcrypt-hashed passwords) and login/logout with PHP sessions
- 30-minute idle timeout, session-ID regeneration on login, CSRF tokens on every form
- Report a lost or found item (the photo is optional), edit and delete your own reports
- Search by name, place or description; filter by category and type
- "Possible matches": a transparent points-based comparison of lost and found reports
- Claim a found item; the first claim locks it so two people can never claim the same item
- Admin panel: review claims (approve or reject with a note), mark items handed over,
  close or delete listings
- Automatic cleanup: resolved items are deleted after 10 days, never-claimed items after 30 days
- Slide-in account menu (three-line button, top-left)

## Item status lifecycle

```
active --claim submitted--> matched --admin approves--> claimed --handed over--> resolved
  ^                            |                                                    |
  +--------admin rejects-------+                                                    v
active --owner/admin resolves--> resolved                              deleted after 10 days
active --admin closes----------> closed ----------------------------> deleted after 10 days
active --nobody responds for 30 days-------------------------------> deleted
```

## Run it with XAMPP

1. Put this folder in `C:\xampp\htdocs\` (e.g. `htdocs\PROJECT`).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. In MySQL Workbench (or phpMyAdmin) run `schema.sql` for a fresh install.
   Already have the database from earlier? Run `schema-update.sql` instead.
4. Open `http://localhost/PROJECT/` and register an account.
5. Make that account an admin (run in Workbench, with your own email):
   ```sql
   UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
   ```
   Log out and back in; you will land on the admin panel.

## Run it with Docker

```
docker compose up --build
```
Then open `http://localhost:8080`. The database tables are created automatically on first
start. Create an admin the same way as above, using
`docker compose exec db mysql -uroot -prootpass campus_lost_found`.

## Folder layout

```
*.php            pages and form handlers (one file = one job)
helpers.php      shared functions: sessions, CSRF, flash messages, uploads, cleanup, matching
db.php           the single PDO connection
auth-check.php   login guard + idle timeout      admin-check.php   admin-only guard
header.php / footer.php   shared page top and bottom (navigation, slide-in menu)
css/ js/         style.css + extras.css, main.js + menu.js + validation.js
schema.sql       full database          schema-update.sql   upgrade for the earlier database
cleanup.php      optional cron version of the automatic cleanup (command line only)
uploads/         created automatically; user photos (not tracked by Git)
```
