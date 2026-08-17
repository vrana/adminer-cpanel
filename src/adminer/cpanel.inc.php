<?php
// Access to the cPanel environment. Included before Adminer, so it lives in the global
// namespace and prefixes everything with cpanel_ instead.

/** Read a variable from the environment cPanel prepares for a page of its interface */
function cpanel_env(string $name): string {
	if (isset($_ENV[$name])) {
		return (string) $_ENV[$name];
	}
	$value = getenv($name);
	return ($value === false ? '' : (string) $value);
}

/** Point PHP at a writable directory for its session files
*
* cpsrvd runs the page with an empty session.save_path. Adminer keeps the password of
* the current login in the session, so if the session cannot be written it asks for
* the credentials again on every request - which looks exactly like no login at all.
*/
function cpanel_session_path(): void {
	if (ini_get('session.save_path') !== '') {
		return;
	}
	$dir = cpanel_env('TMPDIR');
	if ($dir === '') {
		$home = cpanel_env('HOME');
		$dir = ($home !== '' ? "$home/tmp" : '');
	}
	if ($dir === '' || (!is_dir($dir) && !@mkdir($dir, 0700, true))) {
		return; // let PHP try its own default rather than break the page
	}
	session_save_path($dir);
}

/** Get the database credentials of the logged in account
*
* Three ways, in this order:
*
* 1. The database user of the cPanel session. UAPI Session::create_temp_user has the
*    account's own temporary user created and returns its name (cpses_ + the first two
*    letters of the account + SESSION_TEMP_USER); the password is SESSION_TEMP_PASS
*    from the environment. It is granted the account's databases and dropped with the
*    cPanel session, so nothing outlives the session and no password is stored.
* 2. The account itself, through REMOTE_PASSWORD - the same pair the bundled phpMyAdmin
*    logs in with. cPanel creates the temporary user of step 1 only for a session whose
*    password it does not know, which means one opened from WHM or by SSO; a user
*    logging in to cPanel directly, as nearly all of them do, gets none and this is the
*    only way in. The owner is REMOTE_DBOWNER where cPanel provides it - only under
*    base/3rdparty/, not for a plugin - and the account name otherwise; the two differ
*    just where a database was moved between accounts.
* 3. The [client] section of ~/.my.cnf. cPanel does not write that file, but a host can,
*    and a plugin page runs as the account so the file is ours to read.
*
* Not SESSION_TEMP_USER on its own - that is the input to the call in step 1, not a
* database user.
*
* @return ?array{server: string, username: string, password: string} null when
*     no way worked and Adminer should ask for the credentials itself
*/
function cpanel_credentials(): ?array {
	$password = cpanel_env('SESSION_TEMP_PASS');
	$username = ($password !== '' ? cpanel_temp_user() : '');
	if ($username === '') {
		$password = cpanel_env('REMOTE_PASSWORD');
		$username = ($password !== '' ? (cpanel_env('REMOTE_DBOWNER') ?: cpanel_env('REMOTE_USER')) : '');
	}
	if ($username === '') {
		$home = cpanel_env('HOME');
		$found = ($home !== '' ? cpanel_my_cnf("$home/.my.cnf") : null);
		if (!$found) {
			return null;
		}
		list($username, $password) = $found;
	}
	return array(
		'server' => cpanel_mysql_host(),
		'username' => $username,
		'password' => $password,
	);
}

/** Get the language of the cPanel interface, named the way Adminer names it
*
* cPanel writes the region with an underscore and calls a few locales something else.
* Anything Adminer does not have it ignores, falling back to the browser's
* Accept-Language, so an unknown name here costs nothing.
*
* @return string '' when cPanel doesn't say
*/
function cpanel_language(): string {
	$locale = strtolower(str_replace('_', '-', cpanel_uapi('Locale', 'get_attributes', 'locale')));
	$differing = array('es-419' => 'es', 'es-es' => 'es', 'nb' => 'no');
	return ($differing[$locale] ?? $locale);
}

/** Get the name of the database user of this cPanel session, having it created first
*
* UAPI Session::create_temp_user asks cPanel to create the user and returns its name -
* but only the first time. Once it exists the call answers created => 0 and no name at
* all, so the name is built here the way Cpanel::Session::Temp does it: the cpses_
* prefix, the first two letters of the account, and SESSION_TEMP_USER.
*
* @return string '' when this is not a cPanel session
*/
function cpanel_temp_user(): string {
	$temp = cpanel_env('SESSION_TEMP_USER');
	$account = cpanel_env('REMOTE_USER');
	if ($temp === '' || $account === '') {
		return '';
	}
	$username = cpanel_uapi('Session', 'create_temp_user', 'session_temp_user');
	return ($username !== '' ? $username : 'cpses_' . substr($account, 0, 2) . $temp);
}

/** Read the credentials out of a MySQL option file
*
* Only user and password are of interest, so this doesn't parse sections - the files
* cPanel writes hold nothing else.
*
* @return ?array{string, string} null if the file has no complete pair
*/
function cpanel_my_cnf(string $filename): ?array {
	if (!is_readable($filename)) {
		return null;
	}
	$username = '';
	$password = '';
	foreach (file($filename) as $line) {
		if (!preg_match('~^\s*(user(?:name)?|pass(?:word)?)\s*=\s*(.*)$~i', $line, $match)) {
			continue;
		}
		// the value may be quoted, and a password may well contain a # or a space
		$value = trim($match[2]);
		if (preg_match('~^"(.*)"$~', $value, $quoted) || preg_match("~^'(.*)'$~", $value, $quoted)) {
			$value = $quoted[1];
		}
		if (stripos($match[1], 'user') === 0) {
			$username = $value;
		} else {
			$password = $value;
		}
	}
	return ($username !== '' && $password !== '' ? array($username, $password) : null);
}

/** Get the MySQL host this account uses
*
* Most servers keep the database local, but a host can put the account's databases on
* a remote server, in which case cPanel knows the address and we do not. Asking is
* best effort: any failure falls back to the local server, which is the common case.
*/
function cpanel_mysql_host(): string {
	$host = cpanel_uapi('Mysql', 'get_server_information', 'host');
	return ($host !== '' ? $host : 'localhost');
}

/** Call a UAPI function through the LiveAPI PHP class and dig one string out of its result
*
* The class only exists when the script runs inside cPanel, and a cPanel upgrade can
* change or drop a function, so every failure returns '' rather than breaking the page.
*
* @param literal-string $module
* @param literal-string $function
* @param literal-string $key key of the returned data to read
*/
function cpanel_uapi(string $module, string $function, string $key): string {
	$cpanel = cpanel_liveapi();
	if (!$cpanel) {
		return '';
	}
	try {
		$result = $cpanel->uapi($module, $function);
		$data = cpanel_dig($result, array('cpanelresult', 'result', 'data'));
		$value = (is_array($data) ? cpanel_dig($data, array($key)) : null);
		return (is_string($value) || is_int($value) ? (string) $value : '');
	} catch (Throwable $e) {
		return '';
	}
}

/** Open the LiveAPI connection, once for the request
*
* cpsrvd expects a page of its interface to make this connection and complains into
* the response body when it doesn't, so it is also worth opening for its own sake.
*
* @return ?object the CPANEL instance, null when not running inside cPanel
*/
function cpanel_liveapi() {
	static $cpanel = false;
	if ($cpanel !== false) {
		return $cpanel;
	}
	$cpanel = null;
	$file = '/usr/local/cpanel/php/cpanel.php';
	if (is_readable($file)) {
		require_once $file;
		if (class_exists('CPANEL')) {
			try {
				$cpanel = new CPANEL();
				// Adminer exits by itself, so the socket is closed on shutdown
				register_shutdown_function(function () use ($cpanel) {
					try {
						$cpanel->end();
					} catch (Throwable $e) {
						// nothing left to do about it at this point
					}
				});
			} catch (Throwable $e) {
				$cpanel = null;
			}
		}
	}
	return $cpanel;
}

/** Walk a nested array without tripping over a level which is not an array
* @param mixed $value
* @param list<string> $keys
* @return mixed null if any level is missing
*/
function cpanel_dig($value, array $keys) {
	foreach ($keys as $key) {
		if (!is_array($value) || !array_key_exists($key, $value)) {
			return null;
		}
		$value = $value[$key];
	}
	return $value;
}
