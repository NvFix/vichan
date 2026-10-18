<?php

/*
 *  Instance Configuration
 *  ----------------------
 *  Edit this file and not config.php for imageboard configuration.
 *
 *  You can copy values from config.php (defaults) and paste them here.
 */



	// Enable automatic template reloading so edits to templates are picked up immediately
	$config['twig_auto_reload'] = true;

	// Custom stylesheets available for the user to choose.
	$config['stylesheets'] = [
		'Deluded Lightly' => 'deluded_lightly.css',
		'Deluded Nightly' => 'deluded_nightly.css',
		'Yotsuba' => 'yotsuba.css'
	];

	// The default stylesheet to use on board pages.
	$config['default_stylesheet'] = array('Deluded Lightly', $config['stylesheets']['Deluded Lightly']);

	// Website favicon
	$config['url_favicon'] = '/favicon.png';

	// Enable spoiler images
	$config['spoiler_images'] = true;

	// Resource cache buster version ('auto' derives from asset modification time)
	$config['resource_version'] = 'auto';

	// Allowed downloadable / media file extensions
	$config['allowed_ext_files'] = [
		'mp3',
		'mkv',
		'mp4',
		'ogg',
		'webm',
		'pdf',
		'txt'
	];

	// Video / FFmpeg settings
	$config['webm']['use_ffmpeg'] = true;
	$config['webm']['allow_audio'] = true;
	$config['webm']['ffmpeg_path'] = 'ffmpeg';
	$config['webm']['ffprobe_path'] = 'ffprobe';

	// Thumbnail icons for non-image file uploads
	$config['file_icons']['webm'] = 'video.png';
	$config['file_icons']['mp4']  = 'video.png';
	$config['file_icons']['mkv']  = 'video.png';
	$config['file_icons']['mp3']  = 'video.png';
	$config['file_icons']['ogg']  = 'video.png';
	$config['file_icons']['pdf']  = 'file.png';
	$config['file_icons']['txt']  = 'file.png';

	// Database stuff
	$config['db']['type']		= 'mysql';

	$config['db']['server']		= 'localhost';
	$config['db']['user']		= '';
	$config['db']['password']	= '';
	$config['db']['database']	= '';
	
	//$config['root']				= '/';
	
	@include('inc/secrets.php');

	// Thumbnail generation
	$config['thumb_method'] = 'convert+gifsicle';
	$config['thumb_ext'] = ''; // Inherit extension: .gif for GIFs, .jpg for JPGs, .png for PNGs
	$config['thumb_keep_animation_frames'] = 100; // Preserve animation frames for GIFs
?>
