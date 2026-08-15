<?php
// Assemble the installable archive in dist/. Usage:
//
//   php build.php [VERSION]
//
// Adminer is downloaded from adminer.org, the current release unless a version is
// given, so nothing but PHP and tar is needed to build.

$root = __DIR__;
list($url, $version) = adminer_release($argv[1] ?? '');
$name = "adminer-cpanel-$version";
// the directory inside the archive carries no version, so the install command in
// README.md never has to be updated for a release
$dir = 'adminer-cpanel';
$dist = "$root/dist";
$target = "$dist/$dir";

echo "Building $name from $url\n";
$adminer = download($url);
remove_recursive($target);
copy_recursive("$root/src", $target);
write_file("$target/adminer/adminer.php", $adminer);
copy_file("$target/adminer/adminer.svg", "$target/adminer.svg"); // install.json reads the icon next to itself
copy_file("$root/README.md", "$target/README.md");
copy_file("$root/LICENSE", "$target/LICENSE");

// tar is used instead of PharData because the archive must keep the executable bit
$archive = "$dist/$name.tar.gz";
@unlink($archive);
// run from $dist with relative names: GNU tar on Windows reads the C: of an absolute
// path as a remote host
chdir($dist);
run("tar -czf " . escapeshellarg("$name.tar.gz") . " " . escapeshellarg($dir));

printf("%s (%d kB)\n", $archive, round(filesize($archive) / 1024));

/** Find the Adminer to bundle
*
* adminer.org/latest.php redirects to the current release, which is also where its
* version number comes from - there is none stored here to keep up to date.
*
* @param string $version '' for the current release
* @return array{string, string} URL and version
*/
function adminer_release(string $version): array {
	if ($version !== '') {
		return array("https://www.adminer.org/static/download/$version/adminer-$version.php", $version);
	}
	$context = stream_context_create(array('http' => array(
		'method' => 'HEAD',
		'follow_location' => 0, // the redirect is the answer, not something to follow
		'ignore_errors' => true,
	)));
	@file_get_contents('https://www.adminer.org/latest.php', false, $context);
	$location = '';
	foreach (($http_response_header ?? array()) as $header) {
		if (preg_match('~^Location:\s*(\S+)~i', $header, $match)) {
			$location = $match[1];
		}
	}
	if (!preg_match('~adminer-([\w.-]+)\.php$~', $location, $match)) {
		fail("adminer.org did not say which release is current. Pass a version as an argument.");
	}
	// the redirect is relative to the site root
	return array('https://www.adminer.org/' . ltrim($location, '/'), $match[1]);
}

/** Download a file, failing on anything which is not the expected PHP */
function download(string $url): string {
	$file = @file_get_contents($url);
	if ($file === false || substr($file, 0, 5) !== '<?php') {
		fail("Cannot download $url.");
	}
	return $file;
}

function write_file(string $filename, string $content): void {
	if (file_put_contents($filename, $content) === false) {
		fail("Cannot write $filename.");
	}
	chmod($filename, 0644);
}

function copy_recursive(string $source, string $target): void {
	if (!mkdir($target, 0755, true)) {
		fail("Cannot create $target.");
	}
	foreach (scandir($source) as $file) {
		if ($file === '.' || $file === '..') {
			continue;
		}
		if (is_dir("$source/$file")) {
			copy_recursive("$source/$file", "$target/$file");
		} else {
			copy_file("$source/$file", "$target/$file");
		}
	}
}

function copy_file(string $source, string $target): void {
	if (!copy($source, $target)) {
		fail("Cannot copy $source to $target.");
	}
	// the install scripts are run with sh, so the bit is a convenience, not a requirement
	chmod($target, (substr($target, -3) === '.sh' ? 0755 : 0644));
}

function remove_recursive(string $path): void {
	if (!file_exists($path)) {
		return;
	}
	foreach (scandir($path) as $file) {
		if ($file === '.' || $file === '..') {
			continue;
		}
		$full = "$path/$file";
		is_dir($full) ? remove_recursive($full) : unlink($full);
	}
	rmdir($path);
}

function run(string $command): void {
	exec("$command 2>&1", $output, $status);
	if ($status) {
		fail("$command failed:\n" . implode("\n", $output));
	}
}

/** @return never */
function fail(string $message) {
	fwrite(STDERR, "$message\n");
	exit(1);
}
