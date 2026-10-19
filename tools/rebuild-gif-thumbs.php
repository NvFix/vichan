<?php
/*
 *  rebuild-gif-thumbs.php
 *  Regenerates static/PNG GIF thumbnails into animated GIF thumbnails for all existing GIF posts.
 */

require_once file_exists('tools/inc/cli.php') ? 'tools/inc/cli.php' : (file_exists('/code/tools/inc/cli.php') ? '/code/tools/inc/cli.php' : (dirname(__FILE__) . '/inc/cli.php'));
require_once file_exists('inc/image.php') ? 'inc/image.php' : '/code/inc/image.php';

$boards = listBoards();
$total_rebuilt = 0;

echo "=== Rebuilding GIF Thumbnails ===\n";

foreach ($boards as $b_item) {
	$uri = $b_item['uri'];
	echo "Processing board /{$uri}/...\n";
	openBoard($uri);

	$query = query(sprintf("SELECT id, files FROM ``posts_%s`` WHERE files LIKE '%%.gif%%'", $uri));
	$posts = $query->fetchAll(PDO::FETCH_ASSOC);

	$board_rebuilt = 0;
	foreach ($posts as $p) {
		$files = json_decode($p['files'], true);
		if (!is_array($files)) continue;

		$modified = false;
		foreach ($files as &$file) {
			if (!isset($file['extension']) || strtolower($file['extension']) !== 'gif') {
				continue;
			}

			$src_path = $board['dir'] . $config['dir']['img'] . $file['file'];
			if (!file_exists($src_path)) {
				echo "  [Post #{$p['id']}] Source file not found: {$src_path}\n";
				continue;
			}

			$target_thumb_name = $file['file_id'] . '.gif';
			$target_thumb_path = $board['dir'] . $config['dir']['thumb'] . $target_thumb_name;
			$old_thumb_path = isset($file['thumb']) ? $board['dir'] . $config['dir']['thumb'] . $file['thumb'] : null;

			try {
				$img = new Image($src_path, 'gif');
				$thumb = $img->resize('gif', $config['thumb_width'], $config['thumb_height']);
				$thumb->to($target_thumb_path);
				$thumb_width = $thumb->width;
				$thumb_height = $thumb->height;
				$thumb->_destroy();

				// Clean up old non-gif thumbnail if filename differed
				if ($old_thumb_path && $old_thumb_path !== $target_thumb_path && file_exists($old_thumb_path)) {
					@unlink($old_thumb_path);
				}

				$file['thumb'] = $target_thumb_name;
				$file['thumb_path'] = $target_thumb_path;
				$file['thumbwidth'] = $thumb_width;
				$file['thumbheight'] = $thumb_height;
				$modified = true;
				$board_rebuilt++;
				$total_rebuilt++;
				echo "  [Post #{$p['id']}] Rebuilt animated thumbnail: {$target_thumb_name} ({$thumb_width}x{$thumb_height})\n";
			} catch (Exception $e) {
				echo "  [Post #{$p['id']}] Error regenerating thumbnail: " . $e->getMessage() . "\n";
			}
		}

		if ($modified) {
			$stmt = prepare(sprintf("UPDATE ``posts_%s`` SET files = :files WHERE id = :id", $uri));
			$stmt->bindValue(':files', json_encode($files));
			$stmt->bindValue(':id', $p['id'], PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	if ($board_rebuilt > 0) {
		echo "Rebuilding pages for /{$uri}/...\n";
		buildIndex();
	}
}

echo "=== Done! Total GIF thumbnails rebuilt: {$total_rebuilt} ===\n";
