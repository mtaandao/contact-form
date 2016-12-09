<?php

class MNCF7_Mail {

	private static $current = null;

	private $name = '';
	private $template = array();

	public static function send( $template, $name = '' ) {
		$instance = new self;
		$instance->name = trim( $name );
		$instance->setup_template( $template );

		self::$current = $instance;

		return $instance->compose();
	}

	private function __construct() {}

	public function name() {
		return $this->name;
	}

	public static function get_current() {
		return self::$current;
	}

	private function setup_template( $template ) {
		$defaults = array(
			'subject' => '', 'sender' => '', 'body' => '',
			'recipient' => '', 'additional_headers' => '',
			'attachments' => '', 'use_html' => false,
			'exclude_blank' => false );

		$this->template = mn_parse_args( $template, $defaults );
	}

	private function compose( $send = true ) {
		$template = $this->template;
		$use_html = (bool) $template['use_html'];

		$subject = $this->replace_tags( $template['subject'] );
		$sender = $this->replace_tags( $template['sender'] );
		$recipient = $this->replace_tags( $template['recipient'] );
		$additional_headers = $this->replace_tags( $template['additional_headers'] );

		if ( $use_html ) {
			$body = $this->replace_tags( $template['body'], true );
			$body = mnautop( $body );
		} else {
			$body = $this->replace_tags( $template['body'] );
		}

		$attachments = $this->attachments( $template['attachments'] );

		$components = compact( 'subject', 'sender', 'body',
			'recipient', 'additional_headers', 'attachments' );

		$components = apply_filters( 'mncf7_mail_components',
			$components, mncf7_get_current_contact_form(), $this );

		$subject = mncf7_strip_newline( $components['subject'] );
		$sender = mncf7_strip_newline( $components['sender'] );
		$recipient = mncf7_strip_newline( $components['recipient'] );
		$body = $components['body'];
		$additional_headers = trim( $components['additional_headers'] );
		$attachments = $components['attachments'];

		$headers = "From: $sender\n";

		if ( $use_html ) {
			$headers .= "Content-Type: text/html\n";
			$headers .= "X-MNCF7-Content-Type: text/html\n";
		} else {
			$headers .= "X-MNCF7-Content-Type: text/plain\n";
		}

		if ( $additional_headers ) {
			$headers .= $additional_headers . "\n";
		}

		if ( $send ) {
			return mn_mail( $recipient, $subject, $body, $headers, $attachments );
		}

		$components = compact( 'subject', 'sender', 'body',
			'recipient', 'headers', 'attachments' );

		return $components;
	}

	public function replace_tags( $content, $html = false ) {
		$args = array(
			'html' => $html,
			'exclude_blank' => $this->template['exclude_blank'] );

		return mncf7_mail_replace_tags( $content, $args );
	}

	private function attachments( $template ) {
		$attachments = array();

		if ( $submission = MNCF7_Submission::get_instance() ) {
			$uploaded_files = $submission->uploaded_files();

			foreach ( (array) $uploaded_files as $name => $path ) {
				if ( false !== strpos( $template, "[${name}]" )
				&& ! empty( $path ) ) {
					$attachments[] = $path;
				}
			}
		}

		foreach ( explode( "\n", $template ) as $line ) {
			$line = trim( $line );

			if ( '[' == substr( $line, 0, 1 ) ) {
				continue;
			}

			$path = path_join( MAIN, $line );

			if ( @is_readable( $path ) && @is_file( $path ) ) {
				$attachments[] = $path;
			}
		}

		return $attachments;
	}
}

function mncf7_mail_replace_tags( $content, $args = '' ) {
	$args = mn_parse_args( $args, array(
		'html' => false,
		'exclude_blank' => false ) );

	if ( is_array( $content ) ) {
		foreach ( $content as $key => $value ) {
			$content[$key] = mncf7_mail_replace_tags( $value, $args );
		}

		return $content;
	}

	$content = explode( "\n", $content );

	foreach ( $content as $num => $line ) {
		$line = new MNCF7_MailTaggedText( $line, $args );
		$replaced = $line->replace_tags();

		if ( $args['exclude_blank'] ) {
			$replaced_tags = $line->get_replaced_tags();

			if ( empty( $replaced_tags ) || array_filter( $replaced_tags ) ) {
				$content[$num] = $replaced;
			} else {
				unset( $content[$num] ); // Remove a line.
			}
		} else {
			$content[$num] = $replaced;
		}
	}

	$content = implode( "\n", $content );

	return $content;
}

add_action( 'phpmailer_init', 'mncf7_phpmailer_init' );

function mncf7_phpmailer_init( $phpmailer ) {
	$mncf7_content_type = false;

	foreach ( (array) $phpmailer->getCustomHeaders() as $custom_header ) {
		if ( 'X-MNCF7-Content-Type' == $custom_header[0] ) {
			$mncf7_content_type = trim( $custom_header[1] );
			break;
		}
	}

	if ( 'text/html' == $mncf7_content_type ) {
		$phpmailer->msgHTML( $phpmailer->Body );
	}
}

class MNCF7_MailTaggedText {

	private $html = false;
	private $callback = null;
	private $content = '';
	private $replaced_tags = array();

	public function __construct( $content, $args = '' ) {
		$args = mn_parse_args( $args, array(
			'html' => false,
			'callback' => null ) );

		$this->html = (bool) $args['html'];

		if ( null !== $args['callback'] && is_callable( $args['callback'] ) ) {
			$this->callback = $args['callback'];
		} elseif ( $this->html ) {
			$this->callback = array( $this, 'replace_tags_callback_html' );
		} else {
			$this->callback = array( $this, 'replace_tags_callback' );
		}

		$this->content = $content;
	}

	public function get_replaced_tags() {
		return $this->replaced_tags;
	}

	public function replace_tags() {
		$regex = '/(\[?)\[[\t ]*'
			. '([a-zA-Z_][0-9a-zA-Z:._-]*)' // [2] = name
			. '((?:[\t ]+"[^"]*"|[\t ]+\'[^\']*\')*)' // [3] = values
			. '[\t ]*\](\]?)/';

		return preg_replace_callback( $regex, $this->callback, $this->content );
	}

	private function replace_tags_callback_html( $matches ) {
		return $this->replace_tags_callback( $matches, true );
	}

	private function replace_tags_callback( $matches, $html = false ) {
		// allow [[foo]] syntax for escaping a tag
		if ( $matches[1] == '[' && $matches[4] == ']' ) {
			return substr( $matches[0], 1, -1 );
		}

		$tag = $matches[0];
		$tagname = $matches[2];
		$values = $matches[3];

		if ( ! empty( $values ) ) {
			preg_match_all( '/"[^"]*"|\'[^\']*\'/', $values, $matches );
			$values = mncf7_strip_quote_deep( $matches[0] );
		}

		$do_not_heat = false;

		if ( preg_match( '/^_raw_(.+)$/', $tagname, $matches ) ) {
			$tagname = trim( $matches[1] );
			$do_not_heat = true;
		}

		$format = '';

		if ( preg_match( '/^_format_(.+)$/', $tagname, $matches ) ) {
			$tagname = trim( $matches[1] );
			$format = $values[0];
		}

		$submission = MNCF7_Submission::get_instance();
		$submitted = $submission ? $submission->get_posted_data( $tagname ) : null;

		if ( null !== $submitted ) {

			if ( $do_not_heat ) {
				$submitted = isset( $_POST[$tagname] ) ? $_POST[$tagname] : '';
			}

			$replaced = $submitted;

			if ( ! empty( $format ) ) {
				$replaced = $this->format( $replaced, $format );
			}

			$replaced = mncf7_flat_join( $replaced );

			if ( $html ) {
				$replaced = esc_html( $replaced );
				$replaced = mntexturize( $replaced );
			}

			$replaced = apply_filters( 'mncf7_mail_tag_replaced',
				$replaced, $submitted, $html );

			$replaced = mn_unslash( trim( $replaced ) );

			$this->replaced_tags[$tag] = $replaced;
			return $replaced;
		}

		$special = apply_filters( 'mncf7_special_mail_tags', '', $tagname, $html );

		if ( ! empty( $special ) ) {
			$this->replaced_tags[$tag] = $special;
			return $special;
		}

		return $tag;
	}

	public function format( $original, $format ) {
		$original = (array) $original;

		foreach ( $original as $key => $value ) {
			if ( preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value ) ) {
				$original[$key] = mysql2date( $format, $value );
			}
		}

		return $original;
	}
}

/* Special Mail Tags */

add_filter( 'mncf7_special_mail_tags', 'mncf7_special_mail_tag', 10, 3 );

function mncf7_special_mail_tag( $output, $name, $html ) {
	$name = preg_replace( '/^mncf7\./', '_', $name ); // for back-compat

	$submission = MNCF7_Submission::get_instance();

	if ( ! $submission ) {
		return $output;
	}

	if ( '_remote_ip' == $name ) {
		if ( $remote_ip = $submission->get_meta( 'remote_ip' ) ) {
			return $remote_ip;
		} else {
			return '';
		}
	}

	if ( '_user_agent' == $name ) {
		if ( $user_agent = $submission->get_meta( 'user_agent' ) ) {
			return $html ? esc_html( $user_agent ) : $user_agent;
		} else {
			return '';
		}
	}

	if ( '_url' == $name ) {
		if ( $url = $submission->get_meta( 'url' ) ) {
			return esc_url( $url );
		} else {
			return '';
		}
	}

	if ( '_date' == $name || '_time' == $name ) {
		if ( $timestamp = $submission->get_meta( 'timestamp' ) ) {
			if ( '_date' == $name ) {
				return date_i18n( get_option( 'date_format' ), $timestamp );
			}

			if ( '_time' == $name ) {
				return date_i18n( get_option( 'time_format' ), $timestamp );
			}
		}

		return '';
	}

	if ( '_post_' == substr( $name, 0, 6 ) ) {
		$unit_tag = $submission->get_meta( 'unit_tag' );

		if ( $unit_tag
		&& preg_match( '/^mncf7-f(\d+)-p(\d+)-o(\d+)$/', $unit_tag, $matches ) ) {
			$post_id = absint( $matches[2] );

			if ( $post = get_post( $post_id ) ) {
				if ( '_post_id' == $name ) {
					return (string) $post->ID;
				}

				if ( '_post_name' == $name ) {
					return $post->post_name;
				}

				if ( '_post_title' == $name ) {
					return $html ? esc_html( $post->post_title ) : $post->post_title;
				}

				if ( '_post_url' == $name ) {
					return get_permalink( $post->ID );
				}

				$user = new MN_User( $post->post_author );

				if ( '_post_author' == $name ) {
					return $user->display_name;
				}

				if ( '_post_author_email' == $name ) {
					return $user->user_email;
				}
			}
		}

		return '';
	}

	return $output;
}

?>
