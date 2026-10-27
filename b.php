<?php

$files = @scandir('static/banners/', SCANDIR_SORT_NONE);
if (!is_array($files)) {
	$files = [];
}
$files = array_values(array_filter($files, function($file) {
	return isset($file[0]) && $file[0] !== '.' && is_file('static/banners/' . $file);
}));

// Exclude default placeholder banner if custom banners exist
$custom_files = array_values(array_filter($files, function($file) {
	return $file !== 'defaultbanner.png';
}));
if (!empty($custom_files)) {
	$files = $custom_files;
}

if (empty($files)) {
	header('HTTP/1.0 404 Not Found');
	exit;
}

$name = $files[array_rand($files)];
header("Location: /static/banners/$name", true, 307);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

