<?php

add_filter( 'map_meta_cap', 'mncf7_map_meta_cap', 10, 4 );

function mncf7_map_meta_cap( $caps, $cap, $user_id, $args ) {
	$meta_caps = array(
		'mncf7_edit_contact_form' => MNCF7_ADMIN_READ_WRITE_CAPABILITY,
		'mncf7_edit_contact_forms' => MNCF7_ADMIN_READ_WRITE_CAPABILITY,
		'mncf7_read_contact_forms' => MNCF7_ADMIN_READ_CAPABILITY,
		'mncf7_delete_contact_form' => MNCF7_ADMIN_READ_WRITE_CAPABILITY,
		'mncf7_manage_integration' => 'manage_options' );

	$meta_caps = apply_filters( 'mncf7_map_meta_cap', $meta_caps );

	$caps = array_diff( $caps, array_keys( $meta_caps ) );

	if ( isset( $meta_caps[$cap] ) ) {
		$caps[] = $meta_caps[$cap];
	}

	return $caps;
}
