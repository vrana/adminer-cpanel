## Adminer for cPanel 6.0.2
- Log in automatically also when the account logs in to cPanel directly, which creates no temporary database user
- Look like cPanel by bundling Adminer's cpanel design
- Unregister the service workers left behind by the previous cPanel sessions

## Adminer for cPanel 6.0.1
- Add Adminer to the Databases group of cPanel, installed and removed by one command as root
- Bind the icon to a cPanel feature so that it can be enabled for a single package
- Log in as the database user of the cPanel session, created by UAPI Session::create_temp_user and dropped with the session
- Log in with the credentials from ~/.my.cnf when that call is unavailable, ask for them when neither works
- Start in the language the account reads cPanel in
- Do not check for a new Adminer version, only the owner of the server can install one
