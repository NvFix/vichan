<?php

$files = scandir('static/banners/', SCANDIR_SORT_NONE);
$files = array_values(array_filter($files, function($file) {
	return !str_starts_with($file, '.') && is_file('static/banners/' . $file);
}));

if (empty($files)) {
	header('HTTP/1.0 404 Not Found');
	exit;
}

$name = $files[array_rand($files)];
header("Location: /static/banners/$name", true, 307);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

