<?php

/** Log in automatically with your cPanel account
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerCpanel extends Adminer\Plugin {
	/** @var ?array{server: string, username: string, password: string} */
	protected $credentials;

	/** @param ?array{server: string, username: string, password: string} $credentials null to fall back to the login form */
	function __construct($credentials) {
		$this->credentials = $credentials;
	}

	/** Connect with the credentials of the cPanel session, read at the start of this request
	*
	* Not with the ones in Adminer's session, which hold an empty password: the session
	* only records that this user is logged in, the password itself is never put in it.
	*/
	function credentials() {
		if ($this->credentials) {
			return array($this->credentials['server'], $this->credentials['username'], $this->credentials['password']);
		}
	}

	/** Accept the login - cPanel authenticated the account before it ran this page */
	function login($login, $password) {
		if ($this->credentials) {
			return true;
		}
	}

	/** Never store a permanent login - the database user of the cPanel session is
	* dropped with it, so a stored password would be useless by the next visit */
	function permanentLogin($create = false) {
		if ($this->credentials) {
			return '';
		}
	}

	/** Point Adminer at the files build.php wrote next to it
	*
	* cpsrvd sends everything a page of the cPanel interface generates with no-store, so
	* Adminer serving its own stylesheet and scripts means downloading them again on
	* every page. As files in this directory cpsrvd caches them for two months.
	*/
	function assetUrl($file) {
		return "static/$file?version=" . Adminer\VERSION;
	}

	/** Don't check for a new version
	*
	* The account cannot install one - the plugin is updated by whoever runs the server,
	* through install.sh - so an offer to update is noise to the person seeing it.
	*/
	function verifyVersion() {
		return false;
	}

	// the languages Adminer and cPanel have in common; machine translations except cs
	protected $translations = array(
		'ar' => array('' => 'تسجيل دخول تلقائي بحساب cPanel الخاص بك'),
		'cs' => array('' => 'Automatické přihlášení pod vaším cPanel účtem'),
		'da' => array('' => 'Automatisk login med din cPanel-konto'),
		'de' => array('' => 'Automatische Anmeldung mit Ihrem cPanel-Konto'),
		'el' => array('' => 'Αυτόματη σύνδεση με τον λογαριασμό σας cPanel'),
		'es' => array('' => 'Inicio de sesión automático con su cuenta de cPanel'),
		'fi' => array('' => 'Automaattinen kirjautuminen cPanel-tililläsi'),
		'fr' => array('' => 'Connexion automatique avec votre compte cPanel'),
		'he' => array('' => 'התחברות אוטומטית עם חשבון ה-cPanel שלך'),
		'hu' => array('' => 'Automatikus bejelentkezés a cPanel-fiókjával'),
		'id' => array('' => 'Masuk otomatis dengan akun cPanel Anda'),
		'it' => array('' => 'Accesso automatico con il tuo account cPanel'),
		'ja' => array('' => 'cPanel アカウントで自動的にログイン'),
		'ko' => array('' => 'cPanel 계정으로 자동 로그인'),
		'ms' => array('' => 'Log masuk automatik dengan akaun cPanel anda'),
		'nl' => array('' => 'Automatisch inloggen met uw cPanel-account'),
		'no' => array('' => 'Automatisk innlogging med din cPanel-konto'),
		'pl' => array('' => 'Automatyczne logowanie za pomocą konta cPanel'),
		'pt' => array('' => 'Início de sessão automático com a sua conta cPanel'),
		'pt-br' => array('' => 'Login automático com sua conta cPanel'),
		'ro' => array('' => 'Autentificare automată cu contul dumneavoastră cPanel'),
		'ru' => array('' => 'Автоматический вход с вашей учётной записью cPanel'),
		'sv' => array('' => 'Automatisk inloggning med ditt cPanel-konto'),
		'th' => array('' => 'เข้าสู่ระบบอัตโนมัติด้วยบัญชี cPanel ของคุณ'),
		'tr' => array('' => 'cPanel hesabınızla otomatik giriş'),
		'uk' => array('' => 'Автоматичний вхід з вашим обліковим записом cPanel'),
		'vi' => array('' => 'Tự động đăng nhập bằng tài khoản cPanel của bạn'),
		'zh' => array('' => '使用您的 cPanel 帐户自动登录'),
		'zh-tw' => array('' => '使用您的 cPanel 帳戶自動登入'),
	);
}
