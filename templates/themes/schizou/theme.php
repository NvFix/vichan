<?php
	require_once 'info.php';
	
	function schizou_build($action, $settings, $board = false) {
		// Possible values for $action:
		//	- all (rebuild everything, initialization)
		//	- news (news has been updated)
		//	- boards (board list changed)
		//	- post (a post has been made)
		//	- post-thread (a thread has been made)
		//	- post-delete (a post has been deleted)
		
		$theme_instance = new SchizouTheme();
		$theme_instance->build($action, $settings);
	}
	
	class SchizouTheme {
		private $excluded = [];

		public function build($action, $settings) {
			global $config;
			
			$this->excluded = !empty($settings['exclude']) ? explode(' ', $settings['exclude']) : [];

			// Ensure CSS and logo assets are copied to webroot
			$copy_asset = function($src, $dst) use ($action) {
				if (file_exists($src) && (!file_exists($dst) || filemtime($src) > filemtime($dst) || $action == 'all')) {
					if (file_exists($dst) && !is_writable($dst)) {
						@unlink($dst);
					}
					@copy($src, $dst);
					@chmod($dst, 0666);
				}
			};

			$src_css = 'templates/themes/schizou/' . (!empty($settings['basecss']) ? $settings['basecss'] : 'schizou.css');
			$dst_css = $config['dir']['home'] . (!empty($settings['css']) ? $settings['css'] : 'schizou.css');
			$copy_asset($src_css, $dst_css);

			$src_logo = 'templates/themes/schizou/schizou_logo.png';
			$dst_logo = $config['dir']['home'] . 'schizou_logo.png';
			$copy_asset($src_logo, $dst_logo);

			$src_favicon = 'templates/themes/schizou/SchizoFavicon.png';
			$dst_favicon = $config['dir']['home'] . 'favicon.png';
			$copy_asset($src_favicon, $dst_favicon);

			$src_ico = 'templates/themes/schizou/favicon.ico';
			$dst_ico = $config['dir']['home'] . 'favicon.ico';
			$copy_asset($src_ico, $dst_ico);

			$target_html = $config['dir']['home'] . (!empty($settings['html']) ? $settings['html'] : 'index.html');

			// Always rebuild and write the fresh homepage immediately
			file_write($target_html, $this->homepage($settings));
		}

		public function homepage($settings) {
			global $config, $board, $twig;

			// Ensure Twig automatically detects modified template files
			$config['twig_auto_reload'] = true;
			if ($twig) {
				$twig->enableAutoReload();
			}

			if (!empty($settings['detail_body'])) {
				$settings['detail_body'] = $this->sanitize_detail_body($settings['detail_body']);
			}

			$all_boards = listBoards();
			$active_boards = [];
			foreach ($all_boards as $b) {
				if (!in_array($b['uri'], $this->excluded)) {
					$active_boards[] = $b;
				}
			}

			// 1. Logo URL and cache-busting timestamp
			$logo_file = !empty($settings['logo']) ? $settings['logo'] : 'schizou_logo.png';
			$logo_path_on_disk = $config['dir']['home'] . basename($logo_file);
			$logo_mtime = file_exists($logo_path_on_disk) ? filemtime($logo_path_on_disk) : time();

			if (!preg_match('#^https?://#', $logo_file) && !preg_match('#^/#', $logo_file)) {
				$logo_url = $config['root'] . $logo_file;
			} else {
				$logo_url = $logo_file;
			}

			// CSS cache-busting timestamp
			$css_file = !empty($settings['css']) ? $settings['css'] : 'schizou.css';
			$css_path_on_disk = $config['dir']['home'] . basename($css_file);
			$css_mtime = file_exists($css_path_on_disk) ? filemtime($css_path_on_disk) : time();

			// 2. Latest News
			$news_limit = isset($settings['no_news']) ? (int)$settings['no_news'] : 5;
			$news_sql = "SELECT * FROM ``news`` ORDER BY `time` DESC" . ($news_limit > 0 ? " LIMIT " . $news_limit : "");
			$news_query = query($news_sql) or error(db_error());
			$news = [];
			while ($item = $news_query->fetch(PDO::FETCH_ASSOC)) {
				$item['date_formatted'] = date('d/m/Y, g:i:s a', $item['time']);
				$item['snippet'] = pm_snippet(strip_tags($item['body']), 65);
				$news[] = $item;
			}

			// 3. Latest Posts
			$limit_posts = isset($settings['no_latest_posts']) ? (int)$settings['no_latest_posts'] : 5;
			$recent_posts = [];
			if (!empty($active_boards)) {
				$parts = [];
				foreach ($active_boards as $b) {
					$parts[] = sprintf("SELECT `id`, `thread`, `subject`, `name`, `body`, `time`, '%s' AS `board` FROM ``posts_%s``", $b['uri'], $b['uri']);
				}
				$sql = implode(' UNION ALL ', $parts) . " ORDER BY `time` DESC LIMIT " . (int)$limit_posts;
				$res = query($sql) or error(db_error());
				while ($post = $res->fetch(PDO::FETCH_ASSOC)) {
					openBoard($post['board']);
					$post['link'] = $config['root'] . $board['dir'] . $config['dir']['res'] . link_for($post) . '#' . $post['id'];
					$formatted = $this->format_snippet($post['body'], '', 60);
					$post['snippet'] = $formatted['snippet'];
					$post['snippet_full'] = $formatted['full'];
					$post['date_formatted'] = date('d/m/Y, g:i:s a', $post['time']);
					$recent_posts[] = $post;
				}
			}

			// 4. Hot Threads (threads with the most replies)
			$limit_hot = isset($settings['no_hot_threads']) ? (int)$settings['no_hot_threads'] : 5;
			$hot_threads = [];
			if (!empty($active_boards)) {
				$parts = [];
				foreach ($active_boards as $b) {
					$parts[] = sprintf("SELECT `id`, `subject`, `body`, `time`, `bump`, '%s' AS `board`, (SELECT COUNT(*) FROM ``posts_%s`` WHERE `thread` = `p`.`id`) AS `reply_count` FROM ``posts_%s`` AS `p` WHERE `thread` IS NULL", $b['uri'], $b['uri'], $b['uri']);
				}
				$sql = implode(' UNION ALL ', $parts) . " ORDER BY `reply_count` DESC, `id` DESC LIMIT " . (int)$limit_hot;
				$res = query($sql) or error(db_error());
				while ($thread = $res->fetch(PDO::FETCH_ASSOC)) {
					openBoard($thread['board']);
					$thread['link'] = $config['root'] . $board['dir'] . $config['dir']['res'] . link_for($thread);
					$formatted = $this->format_snippet($thread['body'], $thread['subject'] ?? '', 70);
					$thread['title'] = $formatted['snippet'];
					$thread['title_full'] = $formatted['full'];
					$thread['date_formatted'] = date('d/m/Y, g:i:s a', $thread['time']);
					$hot_threads[] = $thread;
				}
			}

			// 5. Latest Images (5x4 grid = 20 images, spoilers strictly excluded)
			$limit_images = isset($settings['limit_images']) ? (int)$settings['limit_images'] : 20;
			$recent_images = [];
			if (!empty($active_boards)) {
				$parts = [];
				foreach ($active_boards as $b) {
					$parts[] = sprintf("SELECT `id`, `thread`, `subject`, `time`, `files`, '%s' AS `board` FROM ``posts_%s`` WHERE `files` IS NOT NULL AND `num_files` > 0", $b['uri'], $b['uri']);
				}
				$fetch_limit = max(200, $limit_images * 8);
				$sql = implode(' UNION ALL ', $parts) . " ORDER BY `time` DESC LIMIT " . (int)$fetch_limit;
				$res = query($sql) or error(db_error());
				while ($post = $res->fetch(PDO::FETCH_ASSOC)) {
					if (empty($post['files'])) continue;
					$files = json_decode($post['files']);
					if (!$files) continue;
					if (is_object($files)) {
						$files = [$files];
					}
					if (!is_array($files)) continue;

					openBoard($post['board']);

					foreach ($files as $file) {
						if (empty($file)) continue;

						$thumb = is_array($file) ? ($file['thumb'] ?? '') : ($file->thumb ?? '');
						$file_name = is_array($file) ? ($file['file'] ?? '') : ($file->file ?? '');
						$is_spoiler = is_array($file) ? (!empty($file['spoiler'])) : (!empty($file->spoiler));
						$thumb_path = is_array($file) ? ($file['thumb_path'] ?? '') : ($file->thumb_path ?? '');

						if ($file_name === 'deleted' || $thumb === 'file' || empty($thumb)) continue;

						// Only image formats allowed (including gif)
						$ext = strtolower(is_array($file) ? ($file['extension'] ?? '') : ($file->extension ?? ''));
						if (empty($ext) && !empty($file_name)) {
							$ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
						}
						$allowed_image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
						if (!in_array($ext, $allowed_image_exts, true)) {
							continue;
						}

						// Spoilers must strictly NOT appear on the homepage's latest images
						if ($is_spoiler || $thumb === 'spoiler' || $thumb_path === 'spoiler' ||
							stripos($thumb, 'spoiler') !== false || stripos($thumb_path, 'spoiler') !== false) {
							continue;
						}

						$img_entry = $post;
						$img_entry['link'] = $config['root'] . $board['dir'] . $config['dir']['res'] . link_for($post) . '#' . $post['id'];
						$img_entry['src'] = $config['uri_thumb'] . $thumb;
						$img_entry['thumbwidth'] = is_array($file) ? ($file['thumbwidth'] ?? 100) : ($file->thumbwidth ?? 100);
						$img_entry['thumbheight'] = is_array($file) ? ($file['thumbheight'] ?? 100) : ($file->thumbheight ?? 100);
						$recent_images[] = $img_entry;

						if (count($recent_images) >= $limit_images) {
							break 2;
						}
					}
				}
			}

			// 6. Site Stats (posts today resetting at midnight Iran Standard Time, total posts, total files, total size)
			$stats = [
				'posts_today' => 0,
				'total_posts' => 0,
				'total_files' => 0,
				'total_size' => '0 B'
			];

			if (!empty($active_boards)) {
				// Calculate midnight Iran Standard Time (Asia/Tehran)
				try {
					$tz_iran = new DateTimeZone('Asia/Tehran');
					$dt_iran = new DateTime('today midnight', $tz_iran);
					$midnight_iran_ts = $dt_iran->getTimestamp();
				} catch (Exception $e) {
					$now = time();
					$offset = 12600; // 3.5 hours
					$midnight_iran_ts = ($now + $offset) - (($now + $offset) % 86400) - $offset;
				}

				$q_counts = [];
				$q_files = [];
				foreach ($active_boards as $b) {
					$q_counts[] = sprintf("SELECT SUM(CASE WHEN `time` >= %d THEN 1 ELSE 0 END) AS `today`, COUNT(*) AS `total` FROM ``posts_%s``", $midnight_iran_ts, $b['uri']);
					$q_files[] = sprintf("SELECT `files` FROM ``posts_%s`` WHERE `num_files` > 0", $b['uri']);
				}

				// Combined post counts
				$res_counts = query("SELECT SUM(`today`), SUM(`total`) FROM (" . implode(' UNION ALL ', $q_counts) . ") AS `t`") or error(db_error());
				$counts = $res_counts->fetch(PDO::FETCH_NUM);
				$raw_today = (int)($counts[0] ?? 0);
				$raw_total = (int)($counts[1] ?? 0);
				$stats['posts_today'] = number_format($raw_today);
				$stats['total_posts'] = number_format($raw_total);
				$stats['raw_total_posts'] = $raw_total;

				// Files & sizes
				$res_files = query(implode(' UNION ALL ', $q_files)) or error(db_error());
				$raw_size = 0;
				$raw_file_count = 0;
				while ($row = $res_files->fetch(PDO::FETCH_NUM)) {
					if (!empty($row[0])) {
						preg_match_all('/"size":\s*([0-9]+)/', $row[0], $matches);
						if (!empty($matches[1])) {
							$raw_size += array_sum($matches[1]);
							$raw_file_count += count($matches[1]);
						}
					}
				}
				$stats['total_files'] = number_format($raw_file_count);

				// Format size
				$units = ['B', 'KB', 'MB', 'GB', 'TB'];
				$bytes = max($raw_size, 0);
				$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
				$pow = min((int)$pow, count($units) - 1);
				$bytes /= pow(1024, $pow);
				$stats['total_size'] = round($bytes, ($pow > 1 ? 1 : 0)) . ' ' . $units[$pow];
			}

			return Element('themes/schizou/schizou.html', [
				'settings' => $settings,
				'config' => $config,
				'boardlist' => createBoardlist(),
				'boards' => $active_boards,
				'logo_url' => $logo_url,
				'logo_mtime' => $logo_mtime,
				'css_mtime' => $css_mtime,
				'news' => $news,
				'recent_posts' => $recent_posts,
				'hot_threads' => $hot_threads,
				'recent_images' => $recent_images,
				'stats' => $stats
			]);
		}

		private function format_snippet(string $body, string $subject = '', int $limit = 60): array {
			$prefix = '';
			if ($subject !== '') {
				$clean_sub = trim(preg_replace('/\s+/u', ' ', strip_tags($subject)));
				if ($clean_sub !== '') {
					$prefix = $clean_sub . ' - ';
				}
			}

			// Convert line breaks and blocks to spaces
			$text = preg_replace('/<br\s*\/?>/i', ' ', $body);
			$text = preg_replace('/<\/?(?:p|div|li|blockquote|h[1-6])[^>]*>/i', ' ', $text);

			// Protect kaomoji spans from strip_tags
			$kaos = [];
			$text = preg_replace_callback('/<span class="kao[^"]*">(.*?)<\/span>/is', function($m) use (&$kaos) {
				$id = '___KAO_' . count($kaos) . '___';
				$kaos[$id] = '<span class="kao font-family-aa-2ch">' . $m[1] . '</span>';
				return $id;
			}, $text);

			// Also support raw [kao]...[/kao] or [ascii]...[/ascii]
			$text = preg_replace_callback('/\[(?:kao|ascii)\](.*?)\[\/(?:kao|ascii)\]/is', function($m) use (&$kaos) {
				$id = '___KAO_' . count($kaos) . '___';
				$kaos[$id] = '<span class="kao font-family-aa-2ch">' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</span>';
				return $id;
			}, $text);

			// Strip all other HTML tags
			$clean = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
			if ($prefix === '' && $clean === '') {
				return [
					'snippet' => '<em>' . _('(no comment)') . '</em>',
					'full' => '(no comment)'
				];
			}

			$combined = $prefix . $clean;

			// Plain text for title attribute
			$full_plain = $combined;
			foreach ($kaos as $id => $html) {
				$full_plain = str_replace($id, strip_tags($html), $full_plain);
			}

			// Truncate based on character count
			$len = mb_strlen($combined);
			if ($len > $limit) {
				$snippet = mb_substr($combined, 0, $limit) . '&hellip;';
			} else {
				$snippet = $combined;
			}

			// Restore kaomoji spans
			foreach ($kaos as $id => $html) {
				$snippet = str_replace($id, $html, $snippet);
			}
			$snippet = preg_replace('/___KAO_\d*___?/i', '', $snippet);

			return [
				'snippet' => '<em>' . $snippet . '</em>',
				'full' => htmlspecialchars($full_plain, ENT_QUOTES, 'UTF-8')
			];
		}

		private function sanitize_detail_body(string $html): string {
			if (trim($html) === '') {
				return $html;
			}
			// Fix unclosed attribute quotes right before '>' e.g. <a href="... >
			$html = preg_replace('/(\b[a-zA-Z0-9_-]+)=([\'"])([^\'">\s]+)>/', '$1=$2$3$2>', $html);
			// Fix unclosed attribute quotes before next attribute e.g. <a href="... target="..." >
			$html = preg_replace('/(\b[a-zA-Z0-9_-]+)=([\'"])([^\'">\s]+)\s+([a-zA-Z0-9_-]+=)/', '$1=$2$3$2 $4=', $html);

			// Balance any unclosed HTML tags so layout never breaks
			if (class_exists('DOMDocument')) {
				$doc = new DOMDocument();
				libxml_use_internal_errors(true);
				$doc->loadHTML('<?xml encoding="utf-8" ?><div id="__wrap__">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
				libxml_clear_errors();
				$node = $doc->getElementById('__wrap__');
				if ($node) {
					$inner = '';
					foreach ($node->childNodes as $child) {
						$inner .= $doc->saveHTML($child);
					}
					return $inner;
				}
			}

			return $html;
		}
	}
