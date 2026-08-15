<?php
// Entry point of Adminer for cPanel, served by cpsrvd at
// https://<host>:2083/cpsess<token>/frontend/<theme>/adminer/index.live.php
//
// The .live.php extension is what makes cPanel run the file as PHP inside the
// authenticated session instead of serving it.

require_once __DIR__ . '/cpanel.inc.php';

cpanel_session_path(); // before Adminer starts the session

// Start in the language of the cPanel interface. Adminer looks at this cookie first,
// so this only fills in what the browser did not send: once the user picks a language
// in Adminer itself, that is what arrives here and this is skipped.
if (!isset($_COOKIE['adminer_lang'])) {
	$cpanel_language = cpanel_language();
	if ($cpanel_language !== '') {
		$_COOKIE['adminer_lang'] = $cpanel_language;
	}
}

// only when the URL asks for a login: resolving them has cPanel create the session's
// database user, which the bare login form has no use for
$cpanel_credentials = (isset($_GET['username']) ? cpanel_credentials() : null);

// The cPanel icon links to index.live.php?username= - an empty username means "log in
// as this account". Filling it in and redirecting puts the real name in the address bar,
// so every later link carries it and the session seeded in adminer_object() below is
// found again. A faked $_POST["auth"] would do both at once but Adminer answers it with
// a redirect of its own, and that one never arrives through cpsrvd.
//
// Without the parameter at all Adminer shows its login form, which is what makes
// logging out stick: its logout drops username= from the URL.
if (isset($_GET['username']) && $_GET['username'] === '') {
	if ($cpanel_credentials) {
		$_GET['username'] = $cpanel_credentials['username'];
		header('Location: ' . preg_replace('~\?.*~', '', $_SERVER['REQUEST_URI']) . '?' . http_build_query($_GET));
		exit;
	}
	unset($_GET['username']); // no credentials to use, ask rather than try to connect as nobody
}

/** Register the plugin; called by Adminer once its session is open and its own classes exist */
function adminer_object() {
	global $cpanel_credentials;

	// "server" is Adminer's id of the MySQL driver; the DRIVER constant is only
	// defined after this function returns, so it cannot be used here
	$driver = 'server';
	$server = (string) ($_GET[$driver] ?? '');
	// Log in only when the URL asks for our user - not on the bare page, which has to
	// keep showing the login form after a logout. Once, too: a repeated login would
	// regenerate the session on every request. Keyed by this exact user so that a
	// session left over from another login does not keep us out; ?? because
	// $_SESSION["pwds"] is null until the first login and indexing null is an error.
	$asked = ($cpanel_credentials && ($_GET['username'] ?? null) === $cpanel_credentials['username']);
	$stored = ($asked ? $_SESSION['pwds'][$driver][$server][$cpanel_credentials['username']] ?? null : null);
	if ($asked && $stored === null) {
		session_regenerate_id(); // defense against session fixation, the same as in auth.inc.php
		// the real password comes from credentials(), so an empty one is enough here
		Adminer\set_password($driver, $server, $cpanel_credentials['username'], '');
		$_SESSION['db'][$driver][$server][$cpanel_credentials['username']][(string) ($_GET['db'] ?? '')] = true;
	}

	include_once __DIR__ . '/plugin.php'; // Adminer\Plugin is only defined by the include below
	return new Adminer\Plugins(array(
		new AdminerCpanel($cpanel_credentials),
	));
}

include __DIR__ . '/adminer.php';
