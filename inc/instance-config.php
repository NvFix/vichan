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
		'Schizou' => 'schizou.css',
		'Yotsuba' => 'yotsuba.css'
	];

	// The default stylesheet to use on board pages.
	$config['default_stylesheet'] = array('Schizou', $config['stylesheets']['Schizou']);

	// Website favicon
	$config['url_favicon'] = '/favicon.png';

	// Database stuff
	$config['db']['type']		= 'mysql';
	$config['db']['server']		= 'localhost';
	$config['db']['user']		= '';
	$config['db']['password']	= '';
	$config['db']['database']	= '';
	
	//$config['root']				= '/';
	
	@include('inc/secrets.php');
?>
