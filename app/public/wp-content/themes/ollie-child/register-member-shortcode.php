<?php
/**
 * Register member shortcode implementation.
 * Shortcode: [Register_member]
 * Admin-only form to create a subscriber with profile and cover uploads.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function shortcode_Register_member( $atts ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		// Do not emit any output for unauthorized visitors so pages containing the shortcode remain visible.
		return '';
	}

	$messages = array( 'errors' => array(), 'success' => '' );
	$new_member_id = 0;
	$password_setup_url = home_url( '/set-password/' );
	$mail_debug_notice = '';

	if ( isset( $_POST['send_new_member_email_submit'] ) ) {
		if ( ! isset( $_POST['send_new_member_email_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['send_new_member_email_nonce'] ), 'send_new_member_email_action' ) ) {
			$messages['errors'][] = 'Invalid form submission.';
		} else {
			$target_user_id = isset( $_POST['new_member_user_id'] ) ? intval( $_POST['new_member_user_id'] ) : 0;
			$new_member_id = $target_user_id;
			$target_user = $target_user_id ? get_user_by( 'id', $target_user_id ) : false;
			if ( ! $target_user || empty( $target_user->user_email ) ) {
				$messages['errors'][] = 'Kunde inte hitta medlemmens e-postadress.';
			} else {
				$subject = 'Välkommen - välj ditt lösenord';
				$message = "Hej!\n\nVälkommen till nätverket Fenix medlemsportal.Du är nu antagen som medlem.\n\nVälj ett lösenord via länken nedan:\n" . $password_setup_url;
				$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
				$mail_error_message = '';
				$wp_mail_failed_handler = function( $wp_error ) use ( &$mail_error_message ) {
					if ( is_wp_error( $wp_error ) ) {
						$mail_error_message = $wp_error->get_error_message();
					}
				};
				add_action( 'wp_mail_failed', $wp_mail_failed_handler );
				$sent = wp_mail( $target_user->user_email, $subject, $message, $headers );
				remove_action( 'wp_mail_failed', $wp_mail_failed_handler );
				$mail_debug_notice = 'Mail debug: wp_mail returned ' . ( $sent ? 'true' : 'false' ) . '.';
				if ( ! $sent && $mail_error_message ) {
					$mail_debug_notice .= ' Fel: ' . $mail_error_message;
				}
				if ( $sent ) {
					$messages['success'] = 'Meddelandet har skickats till medlemmen.';
				} else {
					$messages['errors'][] = 'Kunde inte skicka meddelandet.';
				}
			}
		}
	}

	if ( isset( $_POST['register_member_submit'] ) ) {
		if ( ! isset( $_POST['register_member_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['register_member_nonce'] ), 'register_member_action' ) ) {
			$messages['errors'][] = 'Invalid form submission.';
		} else {
			$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
			$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
			$last_name = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
			// Role selection (only allow specific roles)
			$role = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'subscriber';
			$company = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
			$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$password = isset( $_POST['password'] ) ? $_POST['password'] : '';
			$password_confirm = isset( $_POST['password_confirm'] ) ? $_POST['password_confirm'] : '';
			$website = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';
			$biography = isset( $_POST['biography'] ) ? wp_kses_post( wp_unslash( $_POST['biography'] ) ) : '';

			$required = array(
				'username' => $username,
				'first_name' => $first_name,
				'last_name' => $last_name,
				'company' => $company,
				'email' => $email,
				'password' => $password,
				'password_confirm' => $password_confirm,
			);
			foreach ( $required as $key => $val ) {
				if ( empty( $val ) ) {
					$messages['errors'][] = sprintf( '%s is required.', esc_html( $key ) );
				}
			}

			if ( $password !== $password_confirm ) {
				$messages['errors'][] = 'Lösenorden matchar inte.';
			}
			if ( $email && ! is_email( $email ) ) {
				$messages['errors'][] = 'Ogiltig epostaddress.';
			}
			if ( $username && username_exists( $username ) ) {
				$messages['errors'][] = 'Användarnamnet finns redan.';
			}
			if ( $email && email_exists( $email ) ) {
				$messages['errors'][] = 'Epostadressen finns redan.';
			}

			// Allowed roles for this form
			$allowed_roles = array( 'administrator', 'editor', 'subscriber' );
			if ( ! in_array( $role, $allowed_roles, true ) ) {
				$role = 'subscriber';
			}

			if ( empty( $messages['errors'] ) ) {
				$userdata = array(
					'user_login'   => $username,
					'user_pass'    => $password,
					'user_email'   => $email,
					'display_name' => trim( $first_name . ' ' . $last_name ),
					'first_name'   => $first_name,
					'last_name'    => $last_name,
					'user_url'     => $website,
					'role'         => $role,
				);

				$user_id = wp_insert_user( $userdata );
				if ( is_wp_error( $user_id ) ) {
					$messages['errors'][] = $user_id->get_error_message();
				} else {
					$new_member_id = intval( $user_id );
					if ( $company !== '' ) update_user_meta( $user_id, 'company', $company );
					if ( $phone !== '' ) update_user_meta( $user_id, 'phone', $phone );
					if ( $biography !== '' ) update_user_meta( $user_id, 'description', $biography );

					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/image.php';
					require_once ABSPATH . 'wp-admin/includes/media.php';

					// Profile picture validation + upload
					if ( ! empty( $_FILES['profile_picture_file']['name'] ) ) {
						$file = $_FILES['profile_picture_file'];
						$max_size = 2 * 1024 * 1024; // 2MB
						$allowed_mimes = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
						if ( $file['size'] > $max_size ) {
							$messages['errors'][] = 'Profilpbilden är för stor. Max 3MB.';
						} else {
							$finfo_mime = false;
							if ( function_exists( 'finfo_open' ) ) {
								$finfo = finfo_open( FILEINFO_MIME_TYPE );
								$finfo_mime = finfo_file( $finfo, $file['tmp_name'] );
								finfo_close( $finfo );
							}
							$mime = $finfo_mime ? $finfo_mime : ( isset( $file['type'] ) ? $file['type'] : '' );
							if ( ! in_array( $mime, $allowed_mimes, true ) ) {
								$messages['errors'][] = 'Profilbilden måste vara en JPG, PNG, GIF eller WEBP.';
							} else {
								$overrides = array( 'test_form' => false );
								$movefile = wp_handle_upload( $file, $overrides );
								if ( isset( $movefile['error'] ) ) {
									$messages['errors'][] = 'Profilbilds uppladdning misslyckades: ' . esc_html( $movefile['error'] );
								} elseif ( isset( $movefile['file'] ) ) {
									$filename = $movefile['file'];
									$filetype = wp_check_filetype( basename( $filename ), null );
									$attachment = array(
										'post_mime_type' => $filetype['type'],
										'post_title'     => sanitize_file_name( basename( $filename ) ),
										'post_content'   => '',
										'post_status'    => 'inherit',
									);
									$attach_id = wp_insert_attachment( $attachment, $filename );
									$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
									wp_update_attachment_metadata( $attach_id, $attach_data );
									update_user_meta( $user_id, 'profile_picture', intval( $attach_id ) );
								}
							}
						}
					}

					// Cover photo validation + upload
					if ( ! empty( $_FILES['cover_photo_file']['name'] ) ) {
						$file = $_FILES['cover_photo_file'];
						$max_size = 3 * 1024 * 1024; // 3MB for cover
						$allowed_mimes = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
						if ( $file['size'] > $max_size ) {
							$messages['errors'][] = 'Logotypbilden är för stor. Max 3MB.';
						} else {
							$finfo_mime = false;
							if ( function_exists( 'finfo_open' ) ) {
								$finfo = finfo_open( FILEINFO_MIME_TYPE );
								$finfo_mime = finfo_file( $finfo, $file['tmp_name'] );
								finfo_close( $finfo );
							}
							$mime = $finfo_mime ? $finfo_mime : ( isset( $file['type'] ) ? $file['type'] : '' );
							if ( ! in_array( $mime, $allowed_mimes, true ) ) {
								$messages['errors'][] = 'Logotypbilden måste vara en JPG, PNG, GIF eller WEBP.';
							} else {
								$overrides = array( 'test_form' => false );
								$movefile = wp_handle_upload( $file, $overrides );
								if ( isset( $movefile['error'] ) ) {
									$messages['errors'][] = 'Logotypbilds uppladdning misslyckades: ' . esc_html( $movefile['error'] );
								} elseif ( isset( $movefile['file'] ) ) {
									$filename = $movefile['file'];
									$filetype = wp_check_filetype( basename( $filename ), null );
									$attachment = array(
										'post_mime_type' => $filetype['type'],
										'post_title'     => sanitize_file_name( basename( $filename ) ),
										'post_content'   => '',
										'post_status'    => 'inherit',
									);
									$attach_id = wp_insert_attachment( $attachment, $filename );
									$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
									wp_update_attachment_metadata( $attach_id, $attach_data );
									update_user_meta( $user_id, 'profile_cover', intval( $attach_id ) );
								}
							}
						}
					}

					$messages['success'] = 'Medlemmen är registerad.';
				}
			}
		}
	}

	$vals = array();
	$fields = array( 'username','first_name','last_name','company','phone','email','website','biography','role' );
	foreach ( $fields as $f ) {
		$vals[ $f ] = isset( $_POST[ $f ] ) ? esc_attr( wp_unslash( $_POST[ $f ] ) ) : '';
	}
	// default role to subscriber when not set
	if ( empty( $vals['role'] ) ) {
		$vals['role'] = 'subscriber';
	}

	$out = '';
	
	if ( ! empty( $messages['errors'] ) ) {
		$out .= '<div class="member-errors"><ul>';
		foreach ( $messages['errors'] as $e ) {
			$out .= '<li>' . esc_html( $e ) . '</li>';
		}
		$out .= '</ul></div>';
	}
	if ( $messages['success']  && empty( $messages['errors'] )) {
		$out .= '<div class="member-success">' . esc_html( $messages['success'] ) . '</div>';
	}
	if ( $mail_debug_notice !== '' ) {
		$out .= '<div class="member-success">' . esc_html( $mail_debug_notice ) . '</div>';
	}

	$out .= '<form method="post" enctype="multipart/form-data" class="register-member-form">';
	$out .= wp_nonce_field( 'register_member_action', 'register_member_nonce', true, false );
	$out .= '<table class="form-table">';
	$out .= '<tr><th><label>Användarnamn&nbsp;*</label></th><td><input name="username" value="' . $vals['username'] . '" required /></td></tr>';
	// Role selector
	$out .= '<tr><th><label>Role</label></th><td><select name="role">';
	$out .= '<option value="subscriber"' . ( $vals['role'] === 'subscriber' ? ' selected' : '' ) . '>Subscriber</option>';
	$out .= '<option value="editor"' . ( $vals['role'] === 'editor' ? ' selected' : '' ) . '>Editor</option>';
	$out .= '<option value="administrator"' . ( $vals['role'] === 'administrator' ? ' selected' : '' ) . '>Administrator</option>';
	$out .= '</select></td></tr>';
	$out .= '<tr><th><label>Förnamn *</label></th><td><input name="first_name" value="' . $vals['first_name'] . '" required /></td></tr>';
	$out .= '<tr><th><label>Efternamn *</label></th><td><input name="last_name" value="' . $vals['last_name'] . '" required /></td></tr>';
	$out .= '<tr><th><label>Företag *</label></th><td><input name="company" value="' . $vals['company'] . '" required /></td></tr>';
	$out .= '<tr><th><label>Telefon</label></th><td><input name="phone" value="' . $vals['phone'] . '" /></td></tr>';
	$out .= '<tr><th><label>Epost *</label></th><td><input type="email" name="email" value="' . $vals['email'] . '" required /></td></tr>';
	$out .= '<tr><th><label>Lösenord *</label></th><td><input type="password" name="password" required /></td></tr>';
	$out .= '<tr><th><label style="padding-left: 25px;">Bekräfta lösenord *</label></th><td><input type="password" name="password_confirm" required /></td></tr>';
	$out .= '<tr><th><label>Webbplats</label></th><td><input name="website" value="' . $vals['website'] . '" /></td></tr>';
	$out .= '<tr><th><label>Biografi</label></th><td><textarea name="biography">' . $vals['biography'] . '</textarea></td></tr>';
	$out .= '<tr><th><label>Profilbild</label></th><td><input type="file" name="profile_picture_file" /></td></tr>';
	$out .= '<tr><th><label>Logotyp</label></th><td><input type="file" name="cover_photo_file" /></td></tr>';
	$out .= '</table>';
	$out .= '<p><button type="submit" name="register_member_submit" class="submit-button">Skapa medlem</button></p>';
	$out .= '</form>';

	if ( $new_member_id > 0 && empty( $messages['errors'] ) ) {
		$out .= '<form method="post" class="send-member-email-form">';
		$out .= wp_nonce_field( 'send_new_member_email_action', 'send_new_member_email_nonce', true, false );
		$out .= '<input type="hidden" name="new_member_user_id" value="' . esc_attr( $new_member_id ) . '" />';
		$out .= '<p><button type="submit" name="send_new_member_email_submit" class="submit-button">Skicka meddelande till ny medlem</button></p>';
		$out .= '</form>';
	}

	

	return $out;
}
add_shortcode( 'Register_member', 'shortcode_Register_member' );
