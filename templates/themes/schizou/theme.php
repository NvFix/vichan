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
			$src_css = 'templates/themes/schizou/' . (!empty($settings['basecss']) ? $settings['basecss'] : 'schizou.css');
			$dst_css = $config['dir']['home'] . (!empty($settings['css']) ? $settings['css'] : 'schizou.css');
			if (file_exists($src_css) && (!file_exists($dst_css) || filemtime($src_css) > filemtime($dst_css) || $action == 'all')) {
				copy($src_css, $dst_css);
			}

			$src_logo = 'templates/themes/schizou/schizou_logo.png';
			$dst_logo = $config['dir']['home'] . 'schizou_logo.png';
			if (file_exists($src_logo) && (!file_exists($dst_logo) || filemtime($src_logo) > filemtime($dst_logo) || $action == 'all')) {
				copy($src_logo, $dst_logo);
			}

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
			$logo_src_in_theme = 'templates/themes/schizou/' . basename($logo_file);
			if (file_exists($logo_src_in_theme) && (!file_exists($logo_path_on_disk) || filemtime($logo_src_in_theme) > filemtime($logo_path_on_disk))) {
				copy($logo_src_in_theme, $logo_path_on_disk);
			}
			$logo_mtime = file_exists($logo_path_on_disk) ? filemtime($logo_path_on_disk) : time();

			if (!preg_match('#^https?://#', $logo_file) && !preg_match('#^/#', $logo_file)) {
				$logo_url = $config['root'] . $logo_file;
			} else {
				$logo_url = $logo_file;
			}

			// CSS cache-busting timestamp
			$css_file = !empty($settings['css']) ? $settings['css'] : 'schizou.css';
			$css_path_on_disk = $config['dir']['home'] . basename($css_file);
			$css_src_in_theme = 'templates/themes/schizou/' . (!empty($settings['basecss']) ? $settings['basecss'] : 'schizou.css');
			if (file_exists($css_src_in_theme) && (!file_exists($css_path_on_disk) || filemtime($css_src_in_theme) > filemtime($css_path_on_disk))) {
				copy($css_src_in_theme, $css_path_on_disk);
			}
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
					$clean_body = trim(strip_tags($post['body']));
					if ($clean_body !== '') {
						$post['snippet'] = pm_snippet($clean_body, 60);
						$post['snippet_full'] = htmlspecialchars($clean_body);
					} else {
						$post['snippet'] = '<em>' . _('(no comment)') . '</em>';
						$post['snippet_full'] = '(no comment)';
					}
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
					$clean_body = trim(strip_tags($thread['body']));
					$clean_subject = !empty($thread['subject']) ? trim($thread['subject']) : '';

					if ($clean_subject !== '' && $clean_body !== '') {
						$combined = $clean_subject . ' - ' . $clean_body;
						$thread['title'] = pm_snippet($combined, 70);
						$thread['title_full'] = htmlspecialchars($combined);
					} elseif ($clean_subject !== '') {
						$thread['title'] = pm_snippet($clean_subject, 70);
						$thread['title_full'] = htmlspecialchars($clean_subject);
					} elseif ($clean_body !== '') {
						$thread['title'] = pm_snippet($clean_body, 70);
						$thread['title_full'] = htmlspecialchars($clean_body);
					} else {
						$thread['title'] = 'Thread #' . $thread['id'];
						$thread['title_full'] = 'Thread #' . $thread['id'];
					}
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
				$fetch_limit = max(100, $limit_images * 4);
				$sql = implode(' UNION ALL ', $parts) . " ORDER BY `time` DESC LIMIT " . (int)$fetch_limit;
				$res = query($sql) or error(db_error());
				while ($post = $res->fetch(PDO::FETCH_ASSOC)) {
					if (empty($post['files'])) continue;
					$files = json_decode($post['files']);
					if (!$files || empty($files[0])) continue;
					$file = $files[0];
					if ($file->file == 'deleted' || $file->thumb == 'file') continue;
					
					// Spoilers must NOT show up here
					if ((isset($file->thumb) && $file->thumb == 'spoiler') || (isset($file->spoiler) && $file->spoiler)) {
						continue;
					}

					openBoard($post['board']);
					$post['link'] = $config['root'] . $board['dir'] . $config['dir']['res'] . link_for($post) . '#' . $post['id'];
					$post['src'] = $config['uri_thumb'] . $file->thumb;
					$post['thumbwidth'] = $file->thumbwidth ?? 100;
					$post['thumbheight'] = $file->thumbheight ?? 100;
					$recent_images[] = $post;

					if (count($recent_images) >= $limit_images) {
						break;
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

				$q_today = [];
				$q_total = [];
				$q_files = [];
				foreach ($active_boards as $b) {
					$q_today[] = sprintf("SELECT COUNT(*) AS `c` FROM ``posts_%s`` WHERE `time` >= %d", $b['uri'], $midnight_iran_ts);
					$q_total[] = sprintf("SELECT COUNT(*) AS `c` FROM ``posts_%s``", $b['uri']);
					$q_files[] = sprintf("SELECT `files` FROM ``posts_%s`` WHERE `num_files` > 0", $b['uri']);
				}

				// Posts today (Iran Time)
				$res_today = query("SELECT SUM(`c`) FROM (" . implode(' UNION ALL ', $q_today) . ") AS `t`") or error(db_error());
				$stats['posts_today'] = number_format((int)$res_today->fetchColumn());

				// Total posts
				$res_total = query("SELECT SUM(`c`) FROM (" . implode(' UNION ALL ', $q_total) . ") AS `t`") or error(db_error());
				$stats['total_posts'] = number_format((int)$res_total->fetchColumn());

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
	}
