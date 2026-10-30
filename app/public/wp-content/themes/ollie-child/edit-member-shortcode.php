<?php
/**
 * Edit member shortcode implementation.
 * Shortcode: [Edit_member]
 * Allows administrators and the user themselves to edit a user's profile (front-end).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function shortcode_Edit_member( $atts ) {
	if ( ! is_user_logged_in() ) {
		$out = '<div style="color: green;"><strong>Du måste vara inloggad för att ändra din profil.</strong></div>';
		return $out;
	}

	$current = wp_get_current_user();

	// Determine target user: admins may pass ?user_id=; otherwise edit current user
	$target_user_id = 0;
	if ( current_user_can( 'manage_options' ) && isset( $_GET['user_id'] ) ) {
		$target_user_id = intval( $_GET['user_id'] );
	} else {
		$target_user_id = get_current_user_id();
	}

	if ( ! $target_user_id ) {
		return '<p>No user specified.</p>';
	}

	// Permission check: admins or the user themselves
	if ( ! ( current_user_can( 'manage_options' ) || ( get_current_user_id() === $target_user_id ) ) ) {
		// Not allowed to edit this profile — do not emit a blocking message on pages.
		return '';
	}

	if (get_current_user_id() != $target_user_id ) {
		echo '<script> 
		        
				document.addEventListener("DOMContentLoaded", function () {
					var header_title = document.querySelector(".wp-block-post-title");
					if (header_title) {
						header_title.innerHTML = "Ändra medlem";
					}
				});
			</script>';
	}
	
	$user = get_user_by( 'id', $target_user_id );
	if ( ! $user ) {
		return '<p>User not found.</p>';
	}

	$messages = array( 'errors' => array(), 'success' => '' );

	if ( isset( $_POST['delete_member_submit'] ) ) {
		if ( ! isset( $_POST['edit_member_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['edit_member_nonce'] ), 'edit_member_action' ) ) {
			$messages['errors'][] = 'Invalid form submission.';
		} 
		elseif ( ! current_user_can( 'manage_options' ) ) {
			$messages['errors'][] = 'Du har inte behörighet att ta bort medlemmar.';
		} 
		elseif ( get_current_user_id() === $target_user_id ) {
			$messages['errors'][] = 'Du kan inte ta bort ditt eget konto.';
		} 
		else {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			$deleted = wp_delete_user( $target_user_id );
			if ( $deleted ) {
				return '<div class="member-success">Medlemmen har tagits bort.</div>';
			}
			$messages['errors'][] = 'Kunde inte ta bort medlemmen.';
		}
	}

	if ( isset( $_POST['edit_member_submit'] ) ) {
		if ( ! isset( $_POST['edit_member_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['edit_member_nonce'] ), 'edit_member_action' ) ) {
			$messages['errors'][] = 'Invalid form submission.';
		} else {
			$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
			$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
			$company    = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
			$phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
			$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$linkedin   = isset( $_POST['linkedin'] ) ? esc_url_raw( wp_unslash( $_POST['linkedin'] ) ) : '';
			$website    = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';
			$isGoodAt = isset( $_POST['isGoodAt'] ) ? wp_kses_post( wp_unslash( $_POST['isGoodAt'] ) ) : '';
			$biography  = isset( $_POST['biography'] ) ? wp_kses_post( wp_unslash( $_POST['biography'] ) ) : '';
			$password   = isset( $_POST['password'] ) ? $_POST['password'] : '';
			$password_confirm = isset( $_POST['password_confirm'] ) ? $_POST['password_confirm'] : '';
			// Role only editable by admins
			$role = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';

			// Basic validations
			if ( $email && ! is_email( $email ) ) {
				$messages['errors'][] = 'Ogiltig epostaddress.';
			}

			// If email changed, ensure not taken by another user
			if ( $email && $email !== $user->user_email ) {
				if ( email_exists( $email ) ) {
					$messages['errors'][] = 'Epostadressen finns redan.';
				}
			}

			if ( $password !== '' ) {
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
			}

			// Allowed roles
			$allowed_roles = array( 'administrator', 'subscriber' );
			if ( $role && ! in_array( $role, $allowed_roles, true ) ) {
				$role = '';
			}

			if ( empty( $messages['errors'] ) ) {
				$userdata = array( 'ID' => $target_user_id );
				if ( $email !== '' ) $userdata['user_email'] = $email;
				$userdata['first_name'] = $first_name;
				$userdata['last_name']  = $last_name;
				$userdata['user_url']   = $website;
				$userdata['display_name'] = trim( $first_name . ' ' . $last_name );

				if ( $password !== '' ) {
					$userdata['user_pass'] = $password;
				}

				$updated = wp_update_user( $userdata );
				if ( is_wp_error( $updated ) ) {
					$messages['errors'][] = $updated->get_error_message();
				} 
				else {
					// Update meta fields
					if ( $company !== '' ) {
						update_user_meta( $target_user_id, 'company', $company );
					} 
					else {
						delete_user_meta( $target_user_id, 'company' );
					}

					if ( $phone !== '' ) {
						update_user_meta( $target_user_id, 'phone', $phone );
					} 
					else {
						delete_user_meta( $target_user_id, 'phone' );
					}

					if ( $linkedin !== '' ) {
						update_user_meta( $target_user_id, 'linkedin', $linkedin );
					} 
					else {
						delete_user_meta( $target_user_id, 'linkedin' );
					}

					if ( $isGoodAt !== '' ) {
						update_user_meta( $target_user_id, 'isGoodAt', $isGoodAt );
					} 
					else {
						delete_user_meta( $target_user_id, 'isGoodAt' );
					}

					if ( $biography !== '' ) {
						update_user_meta( $target_user_id, 'description', $biography );
					}
					else {
						delete_user_meta( $target_user_id, 'description' );
					}

					// Role change by admin
					if ( current_user_can( 'manage_options' ) && $role ) {
						$wp_user = new WP_User( $target_user_id );
						$wp_user->set_role( $role );
					}

					// Handle uploads
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/image.php';
					require_once ABSPATH . 'wp-admin/includes/media.php';

					// Profile picture
					if ( ! empty( $_FILES['profile_picture_file']['name'] ) ) {
						$file = $_FILES['profile_picture_file'];
						$max_size = 2 * 1024 * 1024; // 2MB
						$allowed_mimes = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
						if ( $file['size'] > $max_size ) {
							$messages['errors'][] = 'Profilbilden är för stor. Max 2MB.';
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

								// If new uploaded, save attach id and remove old
								$old = get_user_meta( $target_user_id, 'profile_picture', true );
								update_user_meta( $target_user_id, 'profile_picture', intval( $attach_id ) );
								if ( $old && intval( $old ) !== intval( $attach_id ) ) {
									wp_delete_attachment( intval( $old ), true );
								}
								}
							}
						}
					}

					// Cover photo
					if ( ! empty( $_FILES['cover_photo_file']['name'] ) ) {
						$file = $_FILES['cover_photo_file'];
						$max_size = 3 * 1024 * 1024; // 3MB
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

									$old = get_user_meta( $target_user_id, 'profile_cover', true );
									update_user_meta( $target_user_id, 'profile_cover', intval( $attach_id ) );
									if ( $old && intval( $old ) !== intval( $attach_id ) ) {
										wp_delete_attachment( intval( $old ), true );
									}
								}
							}
						}
					}

					// Preserve existing attachments when no new upload was submitted
					if ( empty( $_FILES['profile_picture_file']['name'] ) && isset( $_POST['profile_picture_existing'] ) ) {
						$existing = intval( $_POST['profile_picture_existing'] );
						if ( $existing > 0 ) {
							update_user_meta( $target_user_id, 'profile_picture', $existing );
						}
					}
					if ( empty( $_FILES['cover_photo_file']['name'] ) && isset( $_POST['cover_photo_existing'] ) ) {
						$existing = intval( $_POST['cover_photo_existing'] );
						if ( $existing > 0 ) {
							update_user_meta( $target_user_id, 'profile_cover', $existing );
						}
					}
					
					if (get_current_user_id() === $target_user_id ) {
						$messages['success'] = 'Din profil är uppdaterad.';
					}
					else {
						$messages['success'] = 'Medlemmen är uppdaterad.';
					}
				}
			}
		}
	}

	// Prepare form values
	$vals = array(
		'username'   => $user->user_login,
		'first_name' => get_user_meta( $target_user_id, 'first_name', true ) ?: $user->first_name,
		'last_name'  => get_user_meta( $target_user_id, 'last_name', true ) ?: $user->last_name,
		'company'    => get_user_meta( $target_user_id, 'company', true ),
		'phone'      => get_user_meta( $target_user_id, 'phone', true ),
		'email'      => $user->user_email,
		'linkedin'   => get_user_meta( $target_user_id, 'linkedin', true ),
		'website'    => $user->user_url,
		'isGoodAt'   => get_user_meta( $target_user_id, 'isGoodAt', true ),
		'biography'  => get_user_meta( $target_user_id, 'description', true ),
		'role'       => implode( ',', (array) $user->roles ),
	);

	// If POST values exist (on error) override
	$fields = array( 'first_name','last_name','company','phone','email', 'linkedin', 'website','isGoodAt','biography','role' );
	foreach ( $fields as $f ) {
		if ( isset( $_POST[ $f ] ) ) {
			$vals[ $f ] = esc_attr( wp_unslash( $_POST[ $f ] ) );
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
	}

	$out .= '<form method="post" enctype="multipart/form-data" class="edit-member-form">';
	$out .= wp_nonce_field( 'edit_member_action', 'edit_member_nonce', true, false );
	$out .= '<input type="hidden" name="target_user_id" value="' . esc_attr( $target_user_id ) . '" />';
	$out .= '<table class="form-table">';
	$out .= '<tr><th><label>Användarnamn:</label></th><td><input name="username" value="' . esc_attr( $vals['username'] ) . '" readonly /></td></tr>';

	// Role selector for admins
	if ( current_user_can( 'manage_options' ) ) {
		$out .= '<tr><th><label>Roll:</label></th><td><select name="role">';
		$out .= '<option value="subscriber"' . ( $vals['role'] === 'subscriber' ? ' selected' : '' ) . '>Subscriber</option>';
		$out .= '<option value="administrator"' . ( strpos( $vals['role'], 'administrator' ) !== false ? ' selected' : '' ) . '>Administrator</option>';
		$out .= '</select></td></tr>';
	}

	$out .= '<tr><th><label>Förnamn:</label></th><td><input name="first_name" value="' . esc_attr( $vals['first_name'] ) . '" /></td></tr>';
	$out .= '<tr><th><label>Efternamn:</label></th><td><input name="last_name" value="' . esc_attr( $vals['last_name'] ) . '" /></td></tr>';
	$out .= '<tr><th><label>Företag:</label></th><td><input name="company" value="' . esc_attr( $vals['company'] ) . '" /></td></tr>';
	$out .= '<tr><th><label>Telefon:</label></th><td><input name="phone" value="' . esc_attr( $vals['phone'] ) . '" /></td></tr>';
	$out .= '<tr><th><label>Epost:</label></th><td><input type="email" name="email" value="' . esc_attr( $vals['email'] ) . '" /></td></tr>';
	$out .= '<tr><th><label>Nytt lösenord (valfritt):</label></th><td><input type="password" name="password" /></td></tr>';
	$out .= '<tr><th><label>Bekräfta lösenord:</label></th><td><input type="password" name="password_confirm" /></td></tr>';
	$out .= '<tr><th><label>LinkedIn</label></th><td><input type="url" name="linkedin" value="' . $vals['linkedin'] . '" /></td></tr>';
	$out .= '<tr><th><label>Webbplats:</label></th><td><input name="website" value="' . esc_attr( $vals['website'] ) . '" /></td></tr>';
	
	if ( get_current_user_id() == $target_user_id ) {
		$out .= '<tr><th><label>Något du är bra på?</label></th><td><textarea name="isGoodAt" maxlength="80" class="isGoodAt">' . $vals['isGoodAt'] . '</textarea></td></tr>';
	} 
	else {
		$out .= '<tr><th><label>Något medlemmen är bra på?</label></th><td><textarea name="isGoodAt" maxlength="80" class="isGoodAt">' . $vals['isGoodAt'] . '</textarea></td></tr>';
	}
	
	$out .= '<tr><th><label>Biografi:</label></th><td><textarea name="biography" class="biography">' . esc_textarea( $vals['biography'] ) . '</textarea></td></tr>';

	// Profile picture: show current filename (attachment ID only) and include hidden existing field
	$profile_att = intval( get_user_meta( $target_user_id, 'profile_picture', true ) );
	$profile_label = '';
	if ( $profile_att > 0 ) {
		$profile_url = wp_get_attachment_url( $profile_att );
		if ( $profile_url ) {
			$profile_label = wp_basename( $profile_url );
		}
	}
	$out .= '<tr><th><label>Profilbild:</label></th><td>';
	$out .= '<input type="file" name="profile_picture_file" />';
	if ( $profile_att > 0 ) {
		$out .= '<input type="hidden" name="profile_picture_existing" value="' . esc_attr( $profile_att ) . '" />';
		$out .= '<div class="member-picture-preview">Nuvarande bild: <strong>' . esc_html( $profile_label ) . '</strong></div>';
	}
	$out .= '</td></tr>';

	// Cover photo (profile cover)
	$cover_att = intval( get_user_meta( $target_user_id, 'profile_cover', true ) );
	$cover_label = '';
	if ( $cover_att > 0 ) {
		$cover_url = wp_get_attachment_url( $cover_att );
		if ( $cover_url ) {
			$cover_label = wp_basename( $cover_url );
		}
	}
	$out .= '<tr><th><label>Logotyp:</label></th><td>';
	$out .= '<input type="file" name="cover_photo_file" />';
	if ( $cover_att > 0 ) {
		$out .= '<input type="hidden" name="cover_photo_existing" value="' . esc_attr( $cover_att ) . '" />';
		$out .= '<div class="member-cover-preview">Nuvarande bild: <strong>' . esc_html( $cover_label ) . '</strong></div>';
	}
	$out .= '</td></tr>';

	$out .= '</table>';
	$out .= '<p>';
	$out .= '<button type="submit" name="edit_member_submit" class="submit-button">Spara</button>';
	if ( current_user_can( 'manage_options' ) && get_current_user_id() !== $target_user_id ) {
		$out .= ' <button type="submit" name="delete_member_submit" class="submit-button delete-button" onclick="return confirm(\'Är du säker på att du vill ta bort medlemmen?\');">Ta bort medlem</button>';
	}
	$out .= '</p>';
	$out .= '</form>';

	return $out;
}
add_shortcode( 'Edit_member', 'shortcode_Edit_member' );
