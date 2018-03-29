<?php
/**
 * Plugin Name: Front End Editor Example
 * Plugin URI:  https://gist.github.com/JPry/4b91773fef173eaefb2b
 * Description: Make a front-end editor with CMB2
 * Version:     1.0
 * Author:      Jeremy Pry
 * Author URI:  http://jeremypry.com/
 * License:     GPL2
 *
 * @package JPry_Front_End_Editor
 */

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	die( "You can't do anything by accessing this file directly." );
}

define( 'JPRY_FRONT_END_EDITOR', __FILE__ );

require_once( __DIR__ . '/class-jpry-front-end-editor.php' );

$front_end_editor = JPry_Front_End_Editor::instance();
add_action( 'plugins_loaded', array( $front_end_editor, 'do_hooks' ) );
