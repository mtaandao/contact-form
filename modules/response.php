<?php
/**
** A base module for [response]
**/

/* Shortcode handler */

mncf7_add_shortcode( 'response', 'mncf7_response_shortcode_handler' );

function mncf7_response_shortcode_handler( $tag ) {
	if ( $contact_form = mncf7_get_current_contact_form() ) {
		return $contact_form->form_response_output();
	}
}

?>