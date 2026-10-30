<?php
/**
 * Password setup shortcode implementation.
 * Shortcode: [password_setup]
 * Lets newly registered members create their password via secure token link.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function shortcode_password_setup( $atts ) {
	$messages = array( 'errors' => array(), 'success' => '' );

	$uid = isset( $_REQUEST['uid'] ) ? intval( wp_unslash( $_REQUEST['uid'] ) ) : 0;
	$token = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';

	if ( isset( $_POST['password_setup_submit'] ) ) {
		$uid = isset( $_POST['uid'] ) ? intval( wp_unslash( $_POST['uid'] ) ) : 0;
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

		if ( ! isset( $_POST['password_setup_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['password_setup_nonce'] ), 'password_setup_action' ) ) {
			$messages['errors'][] = 'Invalid form submission.';
		} else {
			$password = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';
			$password_confirm = isset( $_POST['password_confirm'] ) ? (string) $_POST['password_confirm'] : '';

			$user = $uid ? get_user_by( 'id', $uid ) : false;
			if ( ! $user ) {
				$messages['errors'][] = 'Ogiltig länk.';
			} 
			else {
				$stored_hash = get_user_meta( $uid, 'password_setup_token_hash', true );
				$stored_expires = intval( get_user_meta( $uid, 'password_setup_token_expires', true ) );
				$incoming_hash = hash( 'sha256', $token );

				if ( empty( $token ) || empty( $stored_hash ) || ! hash_equals( (string) $stored_hash, (string) $incoming_hash ) ) {
					$messages['errors'][] = 'Ogiltig eller redan använd länk.';
				} 
				elseif ( $stored_expires <= time() ) {
					$messages['errors'][] = 'Länken har gått ut. Be om en ny länk.';
				} 
				else {
					if ( $password === '' || $password_confirm === '' ) {
						$messages['errors'][] = 'Lösenord och bekräftelse är obligatoriska.';
					}
					if ( $password !== $password_confirm ) {
						$messages['errors'][] = 'Lösenorden matchar inte.';
					}
					if ( strlen( $password ) < 10 ) {
						$messages['errors'][] = 'Lösenordet måste vara minst 10 tecken.';
					}
					if ( ! preg_match( '/[A-ZÅÄÖ]/u', $password ) ) {
						$messages['errors'][] = 'Lösenordet måste innehålla minst en versal.';
					}
					if ( ! preg_match( '/[a-zåäö]/u', $password ) ) {
						$messages['errors'][] = 'Lösenordet måste innehålla minst en gemen.';
					}
					if ( ! preg_match( '/\d/', $password ) ) {
						$messages['errors'][] = 'Lösenordet måste innehålla minst en siffra.';
					}
					if ( ! preg_match( '/[^A-Za-z0-9]/', $password ) ) {
						$messages['errors'][] = 'Lösenordet måste innehålla minst ett specialtecken.';
					}

					if ( empty( $messages['errors'] ) ) {
						wp_set_password( $password, $uid );
						delete_user_meta( $uid, 'password_setup_token_hash' );
						delete_user_meta( $uid, 'password_setup_token_expires' );
						$messages['success'] = 'Lösenordet är sparat. Du kan nu logga in.';
					}
				}
			}
		}
	}

	$out = '';
	if ( ! empty( $messages['errors'] ) ) {
		$out .= '<div class="member-errors"><ul>';
		foreach ( $messages['errors'] as $e ) {
			$out .= '<li>' . esc_html( $e ) . '</li>';
		}
		$out .= '</ul></div>';
	}
	if ( $messages['success'] && empty( $messages['errors'] ) ) {
		$out .= '<div class="member-success">' . esc_html( $messages['success'] ) . '</div>';
		return $out;
	}

	$user = $uid ? get_user_by( 'id', $uid ) : false;
	$stored_hash = $uid ? get_user_meta( $uid, 'password_setup_token_hash', true ) : '';
	$stored_expires = $uid ? intval( get_user_meta( $uid, 'password_setup_token_expires', true ) ) : 0;
	$incoming_hash = $token ? hash( 'sha256', $token ) : '';
	$is_valid_link = $user && $token && $stored_hash && hash_equals( (string) $stored_hash, (string) $incoming_hash ) && $stored_expires > time();

	if ( ! $is_valid_link ) {
		$out .= '<div class="member-errors"><ul><li>Ogiltig eller utgången länk för lösenordsskapande.</li></ul></div>';
		return $out;
	}

	$out .= '<form method="post" class="password-setup-form">';
	$out .= wp_nonce_field( 'password_setup_action', 'password_setup_nonce', true, false );
	$out .= '<input type="hidden" name="uid" value="' . esc_attr( $uid ) . '" />';
	$out .= '<input type="hidden" name="token" value="' . esc_attr( $token ) . '" />';
	$out .= '<table class="form-table">';
	$out .= '<tr><th><label>Lösenord: </label></th><td><input type="password" name="password" required /></td></tr>';
	$out .= '<tr><th><label>Bekräfta lösenord: </label></th><td><input type="password" name="password_confirm" required /></td></tr>';
	$out .= '</table>';
	$out .= '<p><button type="submit" name="password_setup_submit" class="submit-button">Spara lösenord</button></p>';
	$out .= '</form>';

	return $out;
}
add_shortcode( 'password_setup', 'shortcode_password_setup' );
