<?php

if ( ! defined( 'MN_UNINSTALL_PLUGIN' ) )
	exit();

function mncf7_delete_plugin() {
	global $mndb;

	delete_option( 'mncf7' );

	$posts = get_posts( array(
		'numberposts' => -1,
		'post_type' => 'mncf7_contact_form',
		'post_status' => 'any' ) );

	foreach ( $posts as $post )
		mn_delete_post( $post->ID, true );

	$table_name = $mndb->prefix . "contact_form_7";

	$mndb->query( "DROP TABLE IF EXISTS $table_name" );
}

mncf7_delete_plugin();

?>