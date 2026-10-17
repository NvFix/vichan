<?php

/*
 *  This script will look for Tinyboard in the following places (in order):
 *    - $TINYBOARD_PATH environment varaible
 *    - ./
 *    - ./Tinyboard/
 *    - ../
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);
umask(0000);

register_shutdown_function(function() {
	if (function_exists('posix_getuid') && posix_getuid() === 0) {
		@exec('chown -R www-data:www-data /var/cache/template-cache /var/tmp/vichan /var/cache/gen-cache /var/www/templates/cache 2>/dev/null');
		@exec('chmod -R 777 /var/cache/template-cache /var/tmp/vichan /var/cache/gen-cache 2>/dev/null');
	}
});

$shell_path = getcwd();

if (php_sapi_name() != 'cli') {
	die("This script is executable only from Command Line Interface.");
}

if(getenv('TINYBOARD_PATH') !== false)
	$dir = getenv('TINYBOARD_PATH');
elseif(file_exists('inc/functions.php'))
	$dir = false;
elseif(file_exists('Tinyboard') && is_dir('Tinyboard') && file_exists('Tinyboard/inc/functions.php'))
	$dir = 'Tinyboard';
elseif(file_exists('../inc/functions.php'))
	$dir = '..';
else
	die("Could not locate Tinyboard directory!\n");

if($dir && !chdir($dir))
	die("Could not change directory to {$dir}\n");

if(!getenv('TINYBOARD_PATH')) {
	// follow symlink
	chdir(realpath('inc') . '/..');
}

putenv('TINYBOARD_PATH=' . getcwd());

require 'inc/bootstrap.php';

$mod = Array(
	'id' => -1,
	'type' => ADMIN,
	'username' => '?',
	'boards' => Array('*')
);


