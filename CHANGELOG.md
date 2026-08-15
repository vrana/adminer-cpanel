# Changelog

## 6.0.1

Adminer 6.0.1 in the Databases group of cPanel, opening on the list of databases
without asking for a password.

The account is logged in as the database user of its cPanel session, which UAPI
`Session::create_temp_user` creates on request and cPanel drops when the session ends.
`~/.my.cnf` is used when that call is unavailable, and Adminer's login form after that.

Adminer's version check is off, since only the server owner can install a new version.

Tested on cPanel & WHM 11.136, Ubuntu 24.04, Jupiter theme.
