<?php

require_once MNCF7_PLUGIN_DIR . '/admin/includes/admin-functions.php';
require_once MNCF7_PLUGIN_DIR . '/admin/includes/help-tabs.php';
require_once MNCF7_PLUGIN_DIR . '/admin/includes/tag-generator.php';
require_once MNCF7_PLUGIN_DIR . '/admin/includes/welcome-panel.php';

add_action( 'admin_init', 'mncf7_admin_init' );

function mncf7_admin_init() {
	do_action( 'mncf7_admin_init' );
}

add_action( 'admin_menu', 'mncf7_admin_menu', 9 );

function mncf7_admin_menu() {
	global $_mn_last_object_menu;

	$_mn_last_object_menu++;

	add_menu_page( __( 'Contact Form 7', 'contact-form-7' ),
		__( 'Contact', 'contact-form-7' ),
		'mncf7_read_contact_forms', 'mncf7',
		'mncf7_admin_management_page', 'dashicons-email',
		$_mn_last_object_menu );

	$edit = add_submenu_page( 'mncf7',
		__( 'Edit Contact Form', 'contact-form-7' ),
		__( 'Contact Forms', 'contact-form-7' ),
		'mncf7_read_contact_forms', 'mncf7',
		'mncf7_admin_management_page' );

	add_action( 'load-' . $edit, 'mncf7_load_contact_form_admin' );

	$addnew = add_submenu_page( 'mncf7',
		__( 'Add New Contact Form', 'contact-form-7' ),
		__( 'Add New', 'contact-form-7' ),
		'mncf7_edit_contact_forms', 'mncf7-new',
		'mncf7_admin_add_new_page' );

	add_action( 'load-' . $addnew, 'mncf7_load_contact_form_admin' );

	$integration = MNCF7_Integration::get_instance();

	if ( $integration->service_exists() ) {
		$integration = add_submenu_page( 'mncf7',
			__( 'Integration with Other Services', 'contact-form-7' ),
			__( 'Integration', 'contact-form-7' ),
			'mncf7_manage_integration', 'mncf7-integration',
			'mncf7_admin_integration_page' );

		add_action( 'load-' . $integration, 'mncf7_load_integration_page' );
	}
}

add_filter( 'set-screen-option', 'mncf7_set_screen_options', 10, 3 );

function mncf7_set_screen_options( $result, $option, $value ) {
	$mncf7_screens = array(
		'cfseven_contact_forms_per_page' );

	if ( in_array( $option, $mncf7_screens ) )
		$result = $value;

	return $result;
}

function mncf7_load_contact_form_admin() {
	global $plugin_page;

	$action = mncf7_current_action();

	if ( 'save' == $action ) {
		$id = $_POST['post_ID'];
		check_admin_referer( 'mncf7-save-contact-form_' . $id );

		if ( ! current_user_can( 'mncf7_edit_contact_form', $id ) )
			mn_die( __( 'You are not allowed to edit this item.', 'contact-form-7' ) );

		$id = mncf7_save_contact_form( $id );

		$query = array(
			'message' => ( -1 == $_POST['post_ID'] ) ? 'created' : 'saved',
			'post' => $id,
			'active-tab' => isset( $_POST['active-tab'] ) ? (int) $_POST['active-tab'] : 0 );

		$redirect_to = add_query_arg( $query, menu_page_url( 'mncf7', false ) );
		mn_safe_redirect( $redirect_to );
		exit();
	}

	if ( 'copy' == $action ) {
		$id = empty( $_POST['post_ID'] )
			? absint( $_REQUEST['post'] )
			: absint( $_POST['post_ID'] );

		check_admin_referer( 'mncf7-copy-contact-form_' . $id );

		if ( ! current_user_can( 'mncf7_edit_contact_form', $id ) )
			mn_die( __( 'You are not allowed to edit this item.', 'contact-form-7' ) );

		$query = array();

		if ( $contact_form = mncf7_contact_form( $id ) ) {
			$new_contact_form = $contact_form->copy();
			$new_contact_form->save();

			$query['post'] = $new_contact_form->id();
			$query['message'] = 'created';
		}

		$redirect_to = add_query_arg( $query, menu_page_url( 'mncf7', false ) );

		mn_safe_redirect( $redirect_to );
		exit();
	}

	if ( 'delete' == $action ) {
		if ( ! empty( $_POST['post_ID'] ) )
			check_admin_referer( 'mncf7-delete-contact-form_' . $_POST['post_ID'] );
		elseif ( ! is_array( $_REQUEST['post'] ) )
			check_admin_referer( 'mncf7-delete-contact-form_' . $_REQUEST['post'] );
		else
			check_admin_referer( 'bulk-posts' );

		$posts = empty( $_POST['post_ID'] )
			? (array) $_REQUEST['post']
			: (array) $_POST['post_ID'];

		$deleted = 0;

		foreach ( $posts as $post ) {
			$post = MNCF7_ContactForm::get_instance( $post );

			if ( empty( $post ) )
				continue;

			if ( ! current_user_can( 'mncf7_delete_contact_form', $post->id() ) )
				mn_die( __( 'You are not allowed to delete this item.', 'contact-form-7' ) );

			if ( ! $post->delete() )
				mn_die( __( 'Error in deleting.', 'contact-form-7' ) );

			$deleted += 1;
		}

		$query = array();

		if ( ! empty( $deleted ) )
			$query['message'] = 'deleted';

		$redirect_to = add_query_arg( $query, menu_page_url( 'mncf7', false ) );

		mn_safe_redirect( $redirect_to );
		exit();
	}

	if ( 'validate' == $action && mncf7_validate_configuration() ) {
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer( 'mncf7-bulk-validate' );

			if ( ! current_user_can( 'mncf7_edit_contact_forms' ) ) {
				mn_die( __( "You are not allowed to validate configuration.", 'contact-form-7' ) );
			}

			$contact_forms = MNCF7_ContactForm::find();
			$result = array(
				'timestamp' => current_time( 'timestamp' ),
				'version' => MNCF7_VERSION,
				'count_valid' => 0,
				'count_invalid' => 0 );

			foreach ( $contact_forms as $contact_form ) {
				$config_validator = new MNCF7_ConfigValidator( $contact_form );
				$config_validator->validate();

				if ( $config_validator->is_valid() ) {
					$result['count_valid'] += 1;
				} else {
					$result['count_invalid'] += 1;
				}
			}

			MNCF7::update_option( 'bulk_validate', $result );

			$query = array(
				'message' => 'validated' );

			$redirect_to = add_query_arg( $query, menu_page_url( 'mncf7', false ) );
			mn_safe_redirect( $redirect_to );
			exit();
		}
	}

	$_GET['post'] = isset( $_GET['post'] ) ? $_GET['post'] : '';

	$post = null;

	if ( 'mncf7-new' == $plugin_page ) {
		$post = MNCF7_ContactForm::get_template( array(
			'locale' => isset( $_GET['locale'] ) ? $_GET['locale'] : null ) );
	} elseif ( ! empty( $_GET['post'] ) ) {
		$post = MNCF7_ContactForm::get_instance( $_GET['post'] );
	}

	$current_screen = get_current_screen();

	$help_tabs = new MNCF7_Help_Tabs( $current_screen );

	if ( $post && current_user_can( 'mncf7_edit_contact_form', $post->id() ) ) {
		$help_tabs->set_help_tabs( 'edit' );
	} else {
		$help_tabs->set_help_tabs( 'list' );

		if ( ! class_exists( 'MNCF7_Contact_Form_List_Table' ) ) {
			require_once MNCF7_PLUGIN_DIR . '/admin/includes/class-contact-forms-list-table.php';
		}

		add_filter( 'manage_' . $current_screen->id . '_columns',
			array( 'MNCF7_Contact_Form_List_Table', 'define_columns' ) );

		add_screen_option( 'per_page', array(
			'default' => 20,
			'option' => 'cfseven_contact_forms_per_page' ) );
	}
}

add_action( 'admin_enqueue_scripts', 'mncf7_admin_enqueue_scripts' );

function mncf7_admin_enqueue_scripts( $hook_suffix ) {
	if ( false === strpos( $hook_suffix, 'mncf7' ) ) {
		return;
	}

	mn_enqueue_style( 'contact-form-7-admin',
		mncf7_plugin_url( 'admin/css/styles.css' ),
		array(), MNCF7_VERSION, 'all' );

	if ( mncf7_is_rtl() ) {
		mn_enqueue_style( 'contact-form-7-admin-rtl',
			mncf7_plugin_url( 'admin/css/styles-rtl.css' ),
			array(), MNCF7_VERSION, 'all' );
	}

	mn_enqueue_script( 'mncf7-admin',
		mncf7_plugin_url( 'admin/js/scripts.js' ),
		array( 'jquery', 'jquery-ui-tabs' ),
		MNCF7_VERSION, true );

	$args = array(
		'pluginUrl' => mncf7_plugin_url(),
		'saveAlert' => __(
			"The changes you made will be lost if you navigate away from this page.",
			'contact-form-7' ),
		'activeTab' => isset( $_GET['active-tab'] )
			? (int) $_GET['active-tab'] : 0,
		'howToCorrectLink' => __( "How to correct this?", 'contact-form-7' ),
		'configErrors' => array() );

	if ( ( $post = mncf7_get_current_contact_form() )
	&& current_user_can( 'mncf7_edit_contact_form', $post->id() )
	&& mncf7_validate_configuration() ) {
		$config_validator = new MNCF7_ConfigValidator( $post );
		$error_messages = $config_validator->collect_error_messages();

		foreach ( $error_messages as $section => $errors ) {
			$args['configErrors'][$section] = array();

			foreach ( $errors as $error ) {
				$args['configErrors'][$section][] = array(
					'message' => esc_html( $error['message'] ),
					'link' => esc_url( $error['link'] ) );
			}
		}
	}

	mn_localize_script( 'mncf7-admin', '_mncf7', $args );

	add_thickbox();

	mn_enqueue_script( 'mncf7-admin-taggenerator',
		mncf7_plugin_url( 'admin/js/tag-generator.js' ),
		array( 'jquery', 'thickbox', 'mncf7-admin' ), MNCF7_VERSION, true );
}

function mncf7_admin_management_page() {
	if ( $post = mncf7_get_current_contact_form() ) {
		$post_id = $post->initial() ? -1 : $post->id();

		require_once MNCF7_PLUGIN_DIR . '/admin/includes/editor.php';
		require_once MNCF7_PLUGIN_DIR . '/admin/edit-contact-form.php';
		return;
	}

	if ( 'validate' == mncf7_current_action()
	&& mncf7_validate_configuration()
	&& current_user_can( 'mncf7_edit_contact_forms' ) ) {
		mncf7_admin_bulk_validate_page();
		return;
	}

	$list_table = new MNCF7_Contact_Form_List_Table();
	$list_table->prepare_items();

?>
<div class="wrap">

<h1><?php
	echo esc_html( __( 'Contact Forms', 'contact-form-7' ) );

	if ( current_user_can( 'mncf7_edit_contact_forms' ) ) {
		echo ' <a href="' . esc_url( menu_page_url( 'mncf7-new', false ) ) . '" class="add-new-h2">' . esc_html( __( 'Add New', 'contact-form-7' ) ) . '</a>';
	}

	if ( ! empty( $_REQUEST['s'] ) ) {
		echo sprintf( '<span class="subtitle">'
			. __( 'Search results for &#8220;%s&#8221;', 'contact-form-7' )
			. '</span>', esc_html( $_REQUEST['s'] ) );
	}
?></h1>

<?php do_action( 'mncf7_admin_warnings' ); ?>
<?php mncf7_welcome_panel(); ?>
<?php do_action( 'mncf7_admin_notices' ); ?>

<form method="get" action="">
	<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
	<?php $list_table->search_box( __( 'Search Contact Forms', 'contact-form-7' ), 'mncf7-contact' ); ?>
	<?php $list_table->display(); ?>
</form>

</div>
<?php
}

function mncf7_admin_bulk_validate_page() {
	$contact_forms = MNCF7_ContactForm::find();
	$count = MNCF7_ContactForm::count();

	$submit_text = sprintf(
		_n(
			"Validate %s Contact Form Now",
			"Validate %s Contact Forms Now",
			$count, 'contact-form-7' ),
		number_format_i18n( $count ) );

?>
<div class="wrap">

<h1><?php echo esc_html( __( 'Validate Configuration', 'contact-form-7' ) ); ?></h1>

<form method="post" action="">
	<input type="hidden" name="action" value="validate" />
	<?php mn_nonce_field( 'mncf7-bulk-validate' ); ?>
	<p><input type="submit" class="button" value="<?php echo esc_attr( $submit_text ); ?>" /></p>
</form>

<?php echo mncf7_link( __( 'http://contactform7.com/configuration-validator-faq/', 'contact-form-7' ), __( 'FAQ about Configuration Validator', 'contact-form-7' ) ); ?>

</div>
<?php
}

function mncf7_admin_add_new_page() {
	$post = mncf7_get_current_contact_form();

	if ( ! $post ) {
		$post = MNCF7_ContactForm::get_template();
	}

	$post_id = -1;

	require_once MNCF7_PLUGIN_DIR . '/admin/includes/editor.php';
	require_once MNCF7_PLUGIN_DIR . '/admin/edit-contact-form.php';
}

function mncf7_load_integration_page() {
	$integration = MNCF7_Integration::get_instance();

	if ( isset( $_REQUEST['service'] )
	&& $integration->service_exists( $_REQUEST['service'] ) ) {
		$service = $integration->get_service( $_REQUEST['service'] );
		$service->load( mncf7_current_action() );
	}

	$help_tabs = new MNCF7_Help_Tabs( get_current_screen() );
	$help_tabs->set_help_tabs( 'integration' );
}

function mncf7_admin_integration_page() {
	$integration = MNCF7_Integration::get_instance();

?>
<div class="wrap">

<h1><?php echo esc_html( __( 'Integration with Other Services', 'contact-form-7' ) ); ?></h1>

<?php do_action( 'mncf7_admin_warnings' ); ?>
<?php do_action( 'mncf7_admin_notices' ); ?>

<?php
	if ( isset( $_REQUEST['service'] )
	&& $service = $integration->get_service( $_REQUEST['service'] ) ) {
		$message = isset( $_REQUEST['message'] ) ? $_REQUEST['message'] : '';
		$service->admin_notice( $message );
		$integration->list_services( array( 'include' => $_REQUEST['service'] ) );
	} else {
		$integration->list_services();
	}
?>

</div>
<?php
}

/* Misc */

add_action( 'mncf7_admin_notices', 'mncf7_admin_updated_message' );

function mncf7_admin_updated_message() {
	if ( empty( $_REQUEST['message'] ) ) {
		return;
	}

	if ( 'created' == $_REQUEST['message'] ) {
		$updated_message = __( "Contact form created.", 'contact-form-7' );
	} elseif ( 'saved' == $_REQUEST['message'] ) {
		$updated_message = __( "Contact form saved.", 'contact-form-7' );
	} elseif ( 'deleted' == $_REQUEST['message'] ) {
		$updated_message = __( "Contact form deleted.", 'contact-form-7' );
	}

	if ( ! empty( $updated_message ) ) {
		echo sprintf( '<div id="message" class="updated notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $updated_message ) );
		return;
	}

	if ( 'validated' == $_REQUEST['message'] ) {
		$bulk_validate = MNCF7::get_option( 'bulk_validate', array() );
		$count_invalid = isset( $bulk_validate['count_invalid'] )
			? absint( $bulk_validate['count_invalid'] ) : 0;

		if ( $count_invalid ) {
			$updated_message = sprintf(
				_n(
					"Configuration validation completed. An invalid contact form was found.",
					"Configuration validation completed. %s invalid contact forms were found.",
					$count_invalid, 'contact-form-7' ),
				number_format_i18n( $count_invalid ) );

			echo sprintf( '<div id="message" class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( $updated_message ) );
		} else {
			$updated_message = __( "Configuration validation completed. No invalid contact form was found.", 'contact-form-7' );

			echo sprintf( '<div id="message" class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $updated_message ) );
		}

		return;
	}
}

add_filter( 'plugin_action_links', 'mncf7_plugin_action_links', 10, 2 );

function mncf7_plugin_action_links( $links, $file ) {
	if ( $file != MNCF7_PLUGIN_BASENAME )
		return $links;

	$settings_link = '<a href="' . menu_page_url( 'mncf7', false ) . '">'
		. esc_html( __( 'Settings', 'contact-form-7' ) ) . '</a>';

	array_unshift( $links, $settings_link );

	return $links;
}

add_action( 'mncf7_admin_warnings', 'mncf7_old_mn_version_error' );

function mncf7_old_mn_version_error() {
	$mn_version = get_bloginfo( 'version' );

	if ( ! version_compare( $mn_version, MNCF7_REQUIRED_MN_VERSION, '<' ) ) {
		return;
	}

?>
<div class="notice notice-warning">
<p><?php echo sprintf( __( '<strong>Contact Form 7 %1$s requires Mtaandao %2$s or higher.</strong> Please <a href="%3$s">update Mtaandao</a> first.', 'contact-form-7' ), MNCF7_VERSION, MNCF7_REQUIRED_MN_VERSION, admin_url( 'update-core.php' ) ); ?></p>
</div>
<?php
}

add_action( 'mncf7_admin_warnings', 'mncf7_not_allowed_to_edit' );

function mncf7_not_allowed_to_edit() {
	if ( ! $contact_form = mncf7_get_current_contact_form() ) {
		return;
	}

	$post_id = $contact_form->id();

	if ( current_user_can( 'mncf7_edit_contact_form', $post_id ) ) {
		return;
	}

	$message = __( "You are not allowed to edit this contact form.",
		'contact-form-7' );

	echo sprintf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html( $message ) );
}

add_action( 'mncf7_admin_misc_pub_section', 'mncf7_notice_config_errors' );

function mncf7_notice_config_errors() {
	if ( ! $contact_form = mncf7_get_current_contact_form() ) {
		return;
	}

	if ( ! mncf7_validate_configuration()
	|| ! current_user_can( 'mncf7_edit_contact_form', $contact_form->id() ) ) {
		return;
	}

	$config_validator = new MNCF7_ConfigValidator( $contact_form );

	if ( $count_errors = $config_validator->count_errors() ) {
		$message = sprintf(
			_n(
				'%s configuration error found',
				'%s configuration errors found',
				$count_errors, 'contact-form-7' ),
			number_format_i18n( $count_errors ) );

		$link = mncf7_link(
			__( 'http://contactform7.com/configuration-validator-faq/',
				'contact-form-7' ),
			__( "What's this?", 'contact-form-7' ),
			array( 'class' => 'external' ) );

		echo sprintf(
			'<div class="misc-pub-section warning">%1$s<br />%2$s</div>',
			$message, $link );
	}
}

add_action( 'mncf7_admin_warnings', 'mncf7_notice_bulk_validate_config', 5 );

function mncf7_notice_bulk_validate_config() {
	if ( ! mncf7_validate_configuration()
	|| ! current_user_can( 'mncf7_edit_contact_forms' ) ) {
		return;
	}

	if ( isset( $_GET['page'] ) && 'mncf7' == $_GET['page']
	&& isset( $_GET['action'] ) && 'validate' == $_GET['action'] ) {
		return;
	}

	if ( MNCF7::get_option( 'bulk_validate' ) ) { // already done.
		return;
	}

	$link = add_query_arg(
		array( 'action' => 'validate' ),
		menu_page_url( 'mncf7', false ) );

	$link = sprintf( '<a href="%s">%s</a>', $link, esc_html( __( 'Validate Contact Form 7 Configuration', 'contact-form-7' ) ) );

	$message = __( "Misconfiguration leads to mail delivery failure or other troubles. Validate your contact forms now.", 'contact-form-7' );

	echo sprintf( '<div class="notice notice-warning"><p>%s &raquo; %s</p></div>',
		esc_html( $message ), $link );
}
