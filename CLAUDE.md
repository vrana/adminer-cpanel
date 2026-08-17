# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A cPanel plugin which puts Adminer in the Databases group of cPanel and logs the user
in without a password prompt. It is packaging, not a fork: `src/adminer/adminer.php`
is the untouched compiled release, dropped in by `build.php`, and everything specific
to cPanel lives in the three small files around it.

Written to make the ask of a hosting provider small — "install this on one package"
instead of "replace phpMyAdmin". `README.md` carries that pitch and is the page a
provider is meant to land on.

## Layout

| Path | What it is |
| --- | --- |
| `src/install.json` | The cPanel icon: `databases` group, `adminer` feature, points at `adminer/index.live.php`. |
| `src/install.sh`, `src/uninstall.sh` | Run as root on the server; copy the app into every theme under `/usr/local/cpanel/base/frontend/` and call `install_plugin`. |
| `src/adminer/index.live.php` | Entry point. `.live.php` is what makes cpsrvd execute it inside the authenticated session. |
| `src/adminer/cpanel.inc.php` | Everything that talks to cPanel. Global namespace, `cpanel_` prefix, because it is included before Adminer. |
| `src/adminer/plugin.php` | `AdminerCpanel`, an ordinary Adminer plugin. Included *inside* `adminer_object()` — `Adminer\Plugin` does not exist before that. |
| `src/adminer/cpanel.js` | Unregisters the service workers of the previous cPanel sessions. A file so cpsrvd caches it; `AdminerCpanel::head()` links it. |
| `build.php` | Assembles `dist/adminer-cpanel-<version>.tar.gz`. |

`src/adminer/adminer.php` is not in the repository; `build.php` downloads it from
adminer.org, following the redirect of `latest.php` to learn which release is current.
Neither is `src/adminer/adminer.css`: that is Adminer's bundled `cpanel` design, which
lives in the repository rather than on adminer.org, so it comes from raw.githubusercontent.com
— from `main` until 6.0.2 is released, because 6.0.1 predates the design.

## How the login works

The credentials are the database user of the cPanel session. UAPI
`Session::create_temp_user` has cPanel create it and returns its name; the password is
`SESSION_TEMP_PASS` from the environment. `~/.my.cnf` is the fallback when that call is
unavailable, and Adminer's own login form the fallback after that.

**The call returns the name only the first time.** Once the user exists it answers
`created => 0` with no name at all, which is not a failure — so `cpanel_temp_user()`
builds the name itself the way `Cpanel::Session::Temp::full_username_from_temp_user()`
does: `cpses_` + the first two letters of the account + `SESSION_TEMP_USER`. Testing
this by hand is misleading: the first call of a session succeeds and every later one
looks broken.

The three URL states, which is what makes logout work:

| URL | What happens |
| --- | --- |
| `index.live.php?username=` | what the icon links to; an empty username means "log in as this account", so it is filled in from `.my.cnf` and redirected |
| `index.live.php?username=<user>` | the session is seeded and Adminer connects |
| `index.live.php` | login form — and where Adminer's logout lands, since it drops `username=` from the URL |

Adminer connects only once `$_GET["username"]` is set *and* a password is in its
session (`adminer/include/auth.inc.php`). `adminer_object()` puts one there with
`set_password()` — the same trick demo.adminer.org logs its visitors in with. Faking
`$_POST["auth"]` instead also logs in, but
Adminer answers it with a redirect of its own and **that redirect never arrives
through cpsrvd** — it was the cause of a long hunt, so do not go back to it.

Two details inside `adminer_object()` that are not obvious:

- `DRIVER` / `SERVER` / `DB` are defined *after* it returns, so the driver is the
  literal `'server'` and the rest comes from `$_GET`.
- the seeding is keyed by the exact user and only runs when the URL asks for that
  user, so a session left over from another login does not lock us out and the bare
  page does not seed a session it will not use.

The password in the session is empty; the real one comes from
`AdminerCpanel::credentials()` on every request. Returning `null` from any hook falls
through to Adminer's default (`Plugins::__call`), which is what gives the login form
fallback for free.

## What the cPanel environment does and does not carry

Established on a live server, and worth not re-deriving:

- **`SESSION_TEMP_USER` is not a database user** — it is the *input* to
  `Session::create_temp_user`, which turns it into one. `SESSION_TEMP_PASS` beside it
  *is* that user's password. Both are in `$_ENV` for a plugin page.
- `REMOTE_USER` is the account name. `REMOTE_PASSWORD` arrives empty, and
  `REMOTE_DBOWNER` is absent: that pair is how the bundled phpMyAdmin authenticates
  (`AuthenticationCpanel::readCredentials()`), and cpsrvd fills it in only for its own
  applications under `base/3rdparty/`, reached through `Cgi::phpmyadminlink`. Chasing
  that route wastes a day — `Session::create_temp_user` is the supported way in.
- `bin/cpses_tool` is `rwx------ root`, so nothing there is callable by the account.
- Sessions work: `session.save_path` is empty but `sys_get_temp_dir()` is the
  account's `~/tmp` and is writable. `cpanel_session_path()` sets it explicitly anyway.
- cpsrvd appends *"Child failed to make LIVEAPI connection to cPanel"* to the body of
  any `.live.php` which never opens the LiveAPI connection, which is one reason
  `cpanel_liveapi()` opens it once per request and closes it on shutdown.
- `install.json` does pass `feature` through to `dynamicui`, and does accept a query
  string in `uri`.
- Icons are drawn in a 48×48 box, and Jupiter honours neither the `<svg>` element's
  own `viewBox` nor `<g>` wrappers — hence the flat, pre-scaled `adminer.svg`.

Direct requests to `src/adminer/adminer.php` land on a bare Adminer login form. That
is inside the authenticated cPanel session and grants nothing the account holder could
not get by uploading the same file to their own hosting, so it is left alone.

## Tested on

cPanel & WHM 11.136, Ubuntu 24.04, Jupiter theme. No other theme has been tried. A
throwaway VPS with a cPanel trial licence is the only way to test this; the installer
needs `perl` and `gnupg`, which a lean Ubuntu image may lack.

## Rules

- **Never edit `adminer.php`.** It is a compiled release. Anything Adminer needs to do
  differently belongs in `plugin.php`.
- **Never `git push`** — the rule across this workspace.
- Match Adminer's style: tabs, `.editorconfig`, doc comments in its voice.
- There is no test suite and no linter here. `php -l` on the four PHP files and a
  `php build.php` are the whole check.
