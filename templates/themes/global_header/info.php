<?php
$theme = array();

// Theme name
$theme['name'] = 'Global Header';
// Description
$theme['description'] = 'Site-wide top navigation header with board navigator, home link, and live theme/stylesheet changer.';
$theme['version'] = 'v1.0';

// Theme configuration
$theme['config'] = array();

$theme['config'][] = array(
	'title' => 'Show Home button',
	'name' => 'show_home',
	'type' => 'checkbox',
	'default' => true,
	'comment' => 'Display [ home ] link on the left side of the header'
);

$theme['config'][] = array(
	'title' => 'Home Label',
	'name' => 'home_label',
	'type' => 'text',
	'default' => 'home',
	'size' => 20,
	'comment' => '(text to display inside brackets, e.g. "home")'
);

$theme['config'][] = array(
	'title' => 'Home URL',
	'name' => 'home_url',
	'type' => 'text',
	'default' => '/',
	'size' => 40,
	'comment' => '(target link for home button)'
);

$theme['config'][] = array(
	'title' => 'Show Board Navigator',
	'name' => 'show_boards',
	'type' => 'checkbox',
	'default' => true,
	'comment' => 'Display [ b / meta ] board links'
);

$theme['config'][] = array(
	'title' => 'Excluded Boards',
	'name' => 'exclude_boards',
	'type' => 'text',
	'default' => '',
	'comment' => '(space-separated board URIs to omit from the navigator, e.g. "secret mod")'
);

$theme['config'][] = array(
	'title' => 'Show Theme Changer',
	'name' => 'show_theme_changer',
	'type' => 'checkbox',
	'default' => true,
	'comment' => 'Display live stylesheet changer on the right side'
);

$theme['config'][] = array(
	'title' => 'Fixed Header at Top',
	'name' => 'fixed_top',
	'type' => 'checkbox',
	'default' => true,
	'comment' => 'Keep the header fixed flush to the top edge when scrolling'
);

$theme['config'][] = array(
	'title' => 'Header Height (px)',
	'name' => 'header_height',
	'type' => 'text',
	'default' => '36',
	'size' => 6,
	'comment' => '(height of the header bar in pixels)'
);

$theme['config'][] = array(
	'title' => 'Custom CSS',
	'name' => 'custom_css',
	'type' => 'textarea',
	'default' => '',
	'comment' => '(optional custom CSS rules injected with the header)'
);

$theme['build_function'] = 'global_header_build';
$theme['install_callback'] = 'global_header_install';

if (!function_exists('global_header_install')) {
	function global_header_install($settings) {
		if (isset($settings['header_height']) && (!is_numeric($settings['header_height']) || $settings['header_height'] < 0)) {
			return array(false, 'Header height must be a non-negative number.');
		}
		return array(true, 'Global Header theme configured successfully.');
	}
}

