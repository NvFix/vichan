<?php
require_once __DIR__ . '/info.php';


function global_header_build($action, $settings, $board = false) {
	if ($action == 'all' || $action == 'boards') {
		global_header_rebuild_all();
	}
}

function global_header_rebuild_all() {
	global $config;

	if (class_exists('Cache')) {
		\Cache::flush();
	}

	if (\Vichan\Functions\Theme\is_theme_installed('schizou')) {
		\Vichan\Functions\Theme\rebuild_theme('schizou', 'all');
	}

	$boards = listBoards();
	foreach ($boards as $b) {
		openBoard($b['uri']);
		$config['try_smarter'] = false;
		buildIndex();

		$query = query(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL", $b['uri']));
		while ($post = $query->fetch(\PDO::FETCH_ASSOC)) {
			buildThread($post['id']);
		}
	}

	if (\Vichan\Functions\Theme\is_theme_installed('catalog')) {
		\Vichan\Functions\Theme\rebuild_theme('catalog', 'all');
	}
}
