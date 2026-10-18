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

	// Resource cache buster version
	$config['resource_version'] = 40;

	// Database stuff
	$config['db']['type']		= 'mysql';
	$config['db']['server']		= 'localhost';
	$config['db']['user']		= '';
	$config['db']['password']	= '';
	$config['db']['database']	= '';
	
	//$config['root']				= '/';
	
	@include('inc/secrets.php');
?>
