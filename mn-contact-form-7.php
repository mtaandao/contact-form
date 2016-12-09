<?php
/*
Plugin Name: Contact Form 7
Plugin URI: http://contactform7.com/
Description: Just another contact form plugin. Simple but flexible.
Author: Takayuki Miyoshi
Author URI: http://ideasilo.wordpress.com/
Text Domain: contact-form-7
Domain Path: /languages/
Version: 4.5.1
*/

define( 'MNCF7_VERSION', '4.5.1' );

define( 'MNCF7_REQUIRED_MN_VERSION', '4.4' );

define( 'MNCF7_PLUGIN', __FILE__ );

define( 'MNCF7_PLUGIN_BASENAME', plugin_basename( MNCF7_PLUGIN ) );

define( 'MNCF7_PLUGIN_NAME', trim( dirname( MNCF7_PLUGIN_BASENAME ), '/' ) );

define( 'MNCF7_PLUGIN_DIR', untrailingslashit( dirname( MNCF7_PLUGIN ) ) );

define( 'MNCF7_PLUGIN_MODULES_DIR', MNCF7_PLUGIN_DIR . '/modules' );

if ( ! defined( 'MNCF7_LOAD_JS' ) ) {
	define( 'MNCF7_LOAD_JS', true );
}

if ( ! defined( 'MNCF7_LOAD_CSS' ) ) {
	define( 'MNCF7_LOAD_CSS', true );
}

if ( ! defined( 'MNCF7_AUTOP' ) ) {
	define( 'MNCF7_AUTOP', true );
}

if ( ! defined( 'MNCF7_USE_PIPE' ) ) {
	define( 'MNCF7_USE_PIPE', true );
}

if ( ! defined( 'MNCF7_ADMIN_READ_CAPABILITY' ) ) {
	define( 'MNCF7_ADMIN_READ_CAPABILITY', 'edit_posts' );
}

if ( ! defined( 'MNCF7_ADMIN_READ_WRITE_CAPABILITY' ) ) {
	define( 'MNCF7_ADMIN_READ_WRITE_CAPABILITY', 'publish_pages' );
}

if ( ! defined( 'MNCF7_VERIFY_NONCE' ) ) {
	define( 'MNCF7_VERIFY_NONCE', true );
}

if ( ! defined( 'MNCF7_USE_REALLY_SIMPLE_CAPTCHA' ) ) {
	define( 'MNCF7_USE_REALLY_SIMPLE_CAPTCHA', false );
}

if ( ! defined( 'MNCF7_VALIDATE_CONFIGURATION' ) ) {
	define( 'MNCF7_VALIDATE_CONFIGURATION', true );
}

// Deprecated, not used in the plugin core. Use mncf7_plugin_url() instead.
define( 'MNCF7_PLUGIN_URL', untrailingslashit( plugins_url( '', MNCF7_PLUGIN ) ) );

require_once MNCF7_PLUGIN_DIR . '/settings.php';
