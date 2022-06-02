<?php
/*
Plugin Name: Easy Digital Downloads Multilingual
Plugin URI: https://wordpress.org/plugins/edd-multilingual/
Description: A plugin to enable seamless integration between Easy Digital Downloads and WPML
Version: 1.4.0
Author: OnTheGoSystems
Author URI: http://www.onthegosystems.com/
Text Domain: edd_multilingual
*/

if ( defined( 'EDD_MULTILINGUAL_VERSION' ) ) {
	return;
}

define( 'EDD_MULTILINGUAL_VERSION', '1.4.0' );
define( 'EDD_MULTILINGUAL_PATH', dirname( __FILE__ ) );

require EDD_MULTILINGUAL_PATH . '/class-edd-multilingual.php';

$edd_multilingual = new EDD_Multilingual();
