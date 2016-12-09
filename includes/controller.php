<?php

add_action( 'mn_loaded', 'mncf7_control_init' );

function mncf7_control_init() {
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
		return;
	}

	if ( 'GET' == $_SERVER['REQUEST_METHOD'] ) {
		if ( isset( $_GET['_mncf7_is_ajax_call'] ) ) {
			mncf7_ajax_onload();
		}
	}

	if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
		if ( isset( $_POST['_mncf7_is_ajax_call'] ) ) {
			mncf7_ajax_json_echo();
		}

		mncf7_submit_nonajax();
	}
}

function mncf7_ajax_onload() {
	$echo = '';
	$items = array();

	if ( isset( $_GET['_mncf7'] )
	&& $contact_form = mncf7_contact_form( (int) $_GET['_mncf7'] ) ) {
		$items = apply_filters( 'mncf7_ajax_onload', $items );
	}

	$echo = mn_json_encode( $items );

	if ( mncf7_is_xhr() ) {
		@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo $echo;
	}

	exit();
}

function mncf7_ajax_json_echo() {
	$echo = '';

	if ( isset( $_POST['_mncf7'] ) ) {
		$id = (int) $_POST['_mncf7'];
		$unit_tag = mncf7_sanitize_unit_tag( $_POST['_mncf7_unit_tag'] );

		if ( $contact_form = mncf7_contact_form( $id ) ) {
			$items = array(
				'mailSent' => false,
				'into' => '#' . $unit_tag,
				'captcha' => null );

			$result = $contact_form->submit( true );

			if ( ! empty( $result['message'] ) ) {
				$items['message'] = $result['message'];
			}

			if ( 'mail_sent' == $result['status'] ) {
				$items['mailSent'] = true;
			}

			if ( 'validation_failed' == $result['status'] ) {
				$invalids = array();

				foreach ( $result['invalid_fields'] as $name => $field ) {
					$invalids[] = array(
						'into' => 'span.mncf7-form-control-wrap.'
							. sanitize_html_class( $name ),
						'message' => $field['reason'],
						'idref' => $field['idref'] );
				}

				$items['invalids'] = $invalids;
			}

			if ( 'spam' == $result['status'] ) {
				$items['spam'] = true;
			}

			if ( ! empty( $result['scripts_on_sent_ok'] ) ) {
				$items['onSentOk'] = $result['scripts_on_sent_ok'];
			}

			if ( ! empty( $result['scripts_on_submit'] ) ) {
				$items['onSubmit'] = $result['scripts_on_submit'];
			}

			$items = apply_filters( 'mncf7_ajax_json_echo', $items, $result );
		}
	}

	$echo = mn_json_encode( $items );

	if ( mncf7_is_xhr() ) {
		@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo $echo;
	} else {
		@header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		echo '<textarea>' . $echo . '</textarea>';
	}

	exit();
}

function mncf7_is_xhr() {
	if ( ! isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) )
		return false;

	return $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
}

function mncf7_submit_nonajax() {
	if ( ! isset( $_POST['_mncf7'] ) )
		return;

	if ( $contact_form = mncf7_contact_form( (int) $_POST['_mncf7'] ) ) {
		$contact_form->submit();
	}
}

add_filter( 'widget_text', 'mncf7_widget_text_filter', 9 );

function mncf7_widget_text_filter( $content ) {
	if ( ! preg_match( '/\[[\r\n\t ]*contact-form(-7)?[\r\n\t ].*?\]/', $content ) )
		return $content;

	$content = do_shortcode( $content );

	return $content;
}

add_action( 'mn_enqueue_scripts', 'mncf7_do_enqueue_scripts' );

function mncf7_do_enqueue_scripts() {
	if ( mncf7_load_js() ) {
		mncf7_enqueue_scripts();
	}

	if ( mncf7_load_css() ) {
		mncf7_enqueue_styles();
	}
}

function mncf7_enqueue_scripts() {
	// jquery.form.js originally bundled with Mtaandao is out of date and deprecated
	// so we need to deregister it and re-register the latest one
	mn_deregister_script( 'jquery-form' );
	mn_register_script( 'jquery-form',
		mncf7_plugin_url( 'includes/js/jquery.form.min.js' ),
		array( 'jquery' ), '3.51.0-2014.06.20', true );

	$in_footer = true;

	if ( 'header' === mncf7_load_js() ) {
		$in_footer = false;
	}

	mn_enqueue_script( 'contact-form-7',
		mncf7_plugin_url( 'includes/js/scripts.js' ),
		array( 'jquery', 'jquery-form' ), MNCF7_VERSION, $in_footer );

	$_mncf7 = array(
		'loaderUrl' => mncf7_ajax_loader(),
		'recaptcha' => array(
			'messages' => array(
				'empty' => __( 'Please verify that you are not a robot.',
					'contact-form-7' ) ) ),
		'sending' => __( 'Sending ...', 'contact-form-7' ) );

	if ( defined( 'MN_CACHE' ) && MN_CACHE ) {
		$_mncf7['cached'] = 1;
	}

	if ( mncf7_support_html5_fallback() ) {
		$_mncf7['jqueryUi'] = 1;
	}

	mn_localize_script( 'contact-form-7', '_mncf7', $_mncf7 );

	do_action( 'mncf7_enqueue_scripts' );
}

function mncf7_script_is() {
	return mn_script_is( 'contact-form-7' );
}

function mncf7_enqueue_styles() {
	mn_enqueue_style( 'contact-form-7',
		mncf7_plugin_url( 'includes/css/styles.css' ),
		array(), MNCF7_VERSION, 'all' );

	if ( mncf7_is_rtl() ) {
		mn_enqueue_style( 'contact-form-7-rtl',
			mncf7_plugin_url( 'includes/css/styles-rtl.css' ),
			array(), MNCF7_VERSION, 'all' );
	}

	do_action( 'mncf7_enqueue_styles' );
}

function mncf7_style_is() {
	return mn_style_is( 'contact-form-7' );
}

/* HTML5 Fallback */

add_action( 'mn_enqueue_scripts', 'mncf7_html5_fallback', 20 );

function mncf7_html5_fallback() {
	if ( ! mncf7_support_html5_fallback() ) {
		return;
	}

	if ( mncf7_script_is() ) {
		mn_enqueue_script( 'jquery-ui-datepicker' );
		mn_enqueue_script( 'jquery-ui-spinner' );
	}

	if ( mncf7_style_is() ) {
		mn_enqueue_style( 'jquery-ui-smoothness',
			mncf7_plugin_url( 'includes/js/jquery-ui/themes/smoothness/jquery-ui.min.css' ), array(), '1.10.3', 'screen' );
	}
}
