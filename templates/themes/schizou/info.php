<?php
	$theme = Array();
	
	// Theme name
	$theme['name'] = 'Schizou';
	// Description (you can use Tinyboard markup here)
	$theme['description'] = 'Schizou pastel purple portal theme with board navigator header, logo, latest news, site welcome box, 2-column latest posts & hot threads, 5x4 spoiler-free images grid, daily (IRST) & all-time stats, and containerized footer.';
	$theme['version'] = 'v1.0';
	
	// Theme configuration	
	$theme['config'] = Array();
	
	$theme['config'][] = Array(
		'title' => 'Site Title',
		'name' => 'title',
		'type' => 'text',
		'default' => 'Schizou',
		'size' => 50
	);
	
	$theme['config'][] = Array(
		'title' => 'Logo Path/URL',
		'name' => 'logo',
		'type' => 'text',
		'default' => 'schizou_logo.png',
		'size' => 50,
		'comment' => '(relative to webroot or full URL)'
	);
	
	$theme['config'][] = Array(
		'title' => 'Detail Box Title',
		'name' => 'detail_title',
		'type' => 'text',
		'default' => 'SchizoÜ',
		'size' => 50
	);
	
	$theme['config'][] = Array(
		'title' => 'Detail Box Description',
		'name' => 'detail_body',
		'type' => 'textarea',
		'default' => "Sometimes I fantasize about Misato coming home drunk and beating me until I feel numb. She kicks me in the ribs until I can hardly breathe. Then she starts to cry and apologizes, begging me to forgive her. She holds me all night as I gently cry into her t-shirt. Please help is there any hope for me"
	);
	
	$theme['config'][] = Array(
		'title' => '# of Latest News Entries',
		'name' => 'no_news',
		'type' => 'text',
		'default' => 5,
		'size' => 4,
		'comment' => '(number of recent news entries to display; 0 is infinite)'
	);
	
	$theme['config'][] = Array(
		'title' => '# of Latest Posts',
		'name' => 'no_latest_posts',
		'type' => 'text',
		'default' => 5,
		'size' => 4,
		'comment' => '(maximum recent posts to display)'
	);
	
	$theme['config'][] = Array(
		'title' => '# of Hot Threads',
		'name' => 'no_hot_threads',
		'type' => 'text',
		'default' => 5,
		'size' => 4,
		'comment' => '(threads with the most replies)'
	);
	
	$theme['config'][] = Array(
		'title' => '# of Latest Images',
		'name' => 'limit_images',
		'type' => 'text',
		'default' => 20,
		'size' => 4,
		'comment' => '(maximum images to display in the 5x4 grid)'
	);
	
	$theme['config'][] = Array(
		'title' => 'Excluded boards',
		'name' => 'exclude',
		'type' => 'text',
		'default' => '',
		'comment' => '(space separated board URIs, e.g. "mod secret")'
	);
	
	$theme['config'][] = Array(
		'title' => 'HTML file',
		'name' => 'html',
		'type' => 'text',
		'default' => 'index.html',
		'comment' => '(eg. "index.html")'
	);
	
	$theme['config'][] = Array(
		'title' => 'CSS file',
		'name' => 'css',
		'type' => 'text',
		'default' => 'schizou.css',
		'comment' => '(eg. "schizou.css")'
	);

	$theme['config'][] = Array(
		'title' => 'CSS stylesheet source',
		'name' => 'basecss',
		'type' => 'text',
		'default' => 'schizou.css',
		'comment' => '(source template CSS file in templates/themes/schizou/)'
	);
	
	// Unique function name for building everything
	$theme['build_function'] = 'schizou_build';
	$theme['install_callback'] = 'schizou_install';

	if (!function_exists('schizou_install')) {
		function schizou_install($settings) {
			if (!is_numeric($settings['limit_images']) || $settings['limit_images'] < 0)
				return Array(false, '<strong>' . utf8tohtml($settings['limit_images']) . '</strong> is not a non-negative integer.');
			if (!is_numeric($settings['no_latest_posts']) || $settings['no_latest_posts'] < 0)
				return Array(false, '<strong>' . utf8tohtml($settings['no_latest_posts']) . '</strong> is not a non-negative integer.');
			if (!is_numeric($settings['no_hot_threads']) || $settings['no_hot_threads'] < 0)
				return Array(false, '<strong>' . utf8tohtml($settings['no_hot_threads']) . '</strong> is not a non-negative integer.');
			if (!is_numeric($settings['no_news']) || $settings['no_news'] < 0)
				return Array(false, '<strong>' . utf8tohtml($settings['no_news']) . '</strong> is not a non-negative integer.');
		}
	}
