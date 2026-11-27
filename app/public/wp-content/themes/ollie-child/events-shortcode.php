<?php
/**
 * Events list & admin add form
 * Shortcode: [events_list]
 * Shows events (Name, Date, Location). Administrators see a form to add events.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function shortcode_events_list( $atts ) {
	$atts = shortcode_atts( array( 'per_page' => 50 ), $atts, 'events_list' );
	
	$out = '<div class="events-list-wrap">';

	if ( !is_user_logged_in() ) {
		$out = '<div style="color: green;"><strong>Du måste vara inloggad för att se eventen.</strong></div>';
		return $out;
	}

	$messages = array( 'errors' => array(), 'success-add' => '', 'success-edit' => '', 'success-delete' => '', 'success-cancellation' => '', 'success-registration' => '', );




	// Show one-time success message for admin delete action
	if ( current_user_can( 'manage_options' ) ) {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$delete_key = 'events_success_delete_' . intval( $user_id );
			if ( get_transient( $delete_key ) ) {
				$messages['success-delete'] = 'Event raderat.';
				delete_transient( $delete_key );
			}
		}
	}

	// Show one-time success message for add (admin) using transient
	if ( current_user_can( 'manage_options' ) ) {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$add_key = 'events_success_add_' . intval( $user_id );
			$add_val = get_transient( $add_key );
			if ( $add_val ) {
				$messages['success-add'] = 'Event tillagt.';
				// delete transient after rendering to make it one-time
				delete_transient( $add_key );
			}
		}
	}


	// Handle deletion (admin only)
	if ( isset( $_GET['delete_event'] ) ) {
		if ( current_user_can( 'manage_options' ) ) {
			$del_id = intval( $_GET['delete_event'] );
			$post = get_post( $del_id );
			if ( ! $post || $post->post_type !== 'event' ) {
				$messages['error-delete'][] = 'Event hittades inte.';
			} else {
				wp_delete_post( $del_id, true );
				$user_id = get_current_user_id();
				if ( $user_id ) {
					set_transient( 'events_success_delete_' . intval( $user_id ), 1, 60 );
				}
				$redirect = wp_get_referer();
				if ( ! $redirect ) {
					$redirect = home_url();
				}
				$redirect = remove_query_arg( array( 'delete_event', '_ev_nonce' ), $redirect );
				wp_safe_redirect( $redirect );
				exit;
			}
		}
	}

	// Handle add event
	if ( isset( $_POST['events_add_submit'] ) && current_user_can( 'manage_options' ) ) {
		if ( ! isset( $_POST['events_add_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['events_add_nonce'] ), 'events_add_action' ) ) {
			$messages['errors'][] = 'Invalid form submission.';
		} else {
			$name = isset( $_POST['event_name'] ) ? sanitize_text_field( wp_unslash( $_POST['event_name'] ) ) : '';
			$date = isset( $_POST['event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_date'] ) ) : '';
			$time = isset( $_POST['event_time'] ) ? sanitize_text_field( wp_unslash( $_POST['event_time'] ) ) : '';
			$maxNbrOfParticipants = isset( $_POST['event_nbrOfParticipants'] ) ? sanitize_text_field( wp_unslash( $_POST['event_nbrOfParticipants'] ) ) : '';
			$location = isset( $_POST['event_location'] ) ? sanitize_text_field( wp_unslash( $_POST['event_location'] ) ) : '';
			$status = isset( $_POST['event_status'] ) ? $_POST['event_status'] : '';
			$description = isset( $_POST['event_description'] ) ? sanitize_text_field( wp_unslash( $_POST['event_description'] ) ) : '';
			
			// Handle uploaded picture (from file input event_picture)
			$picture_id = '';

			if ( $name === '' ) 
				$messages['errors'][] = 'Namn är obligatoriskt.';
			if ( $date === '' ) 
				$messages['errors'][] = 'Datum är obligatoriskt.';
			if ( $time === '' ) 
				$messages['errors'][] = 'Tid är obligatoriskt.';

			// Validate date (expect YYYY-MM-DD)
			if ( $date !== '' ) {
				$d = DateTime::createFromFormat( 'Y-m-d', $date );
				if ( ! $d || $d->format( 'Y-m-d' ) !== $date ) {
					$messages['errors'][] = 'Datum måste vara i formatet YYYY-MM-DD.';
				}
			}

			if ( empty( $messages['errors'] ) ) {
				$postarr = array(
					'post_title' => $name,
					'post_type' => 'event',
					'post_status' => 'publish',
				);
				$post_id = wp_insert_post( $postarr );
				
				if ( is_wp_error( $post_id ) || ! $post_id ) {
					$messages['errors'][] = 'Misslyckades att skapa eventet.';
				} else {
					update_post_meta( $post_id, 'event_date', $date );
					update_post_meta( $post_id, 'event_time', $time );
					update_post_meta( $post_id, 'event_nbrOfParticipants', $maxNbrOfParticipants );
					update_post_meta( $post_id, 'event_location', $location );					
					update_post_meta( $post_id, 'event_status', $status );
					// process file upload if provided
					if ( ! empty( $_FILES['event_picture'] ) && ! empty( $_FILES['event_picture']['name'] ) ) {
						// check PHP upload error first
						if ( isset( $_FILES['event_picture']['error'] ) && intval( $_FILES['event_picture']['error'] ) !== 0 ) {
							$messages['errors'][] = 'Bilduppladdning misslyckades (PHP error code: ' . intval( $_FILES['event_picture']['error'] ) . ').';
						} else {
							require_once ABSPATH . 'wp-admin/includes/file.php';
							require_once ABSPATH . 'wp-admin/includes/image.php';
							require_once ABSPATH . 'wp-admin/includes/media.php';
							$file = wp_handle_upload( $_FILES['event_picture'], array( 'test_form' => false ) );
							if ( empty( $file ) || ! isset( $file['file'] ) ) {
								$messages['errors'][] = 'Bilduppladdning misslyckades: okänt fel vid flytt av fil.';
							} elseif ( isset( $file['error'] ) ) {
								$messages['errors'][] = 'Bilduppladdning misslyckades: ' . esc_html( $file['error'] );
							} else {
								// Insert into media library and attach to post
								$wp_filetype = wp_check_filetype( $file['file'], null );
								$attachment = array(
									'post_mime_type' => $wp_filetype['type'],
									'post_title' => sanitize_file_name( basename( $file['file'] ) ),
									'post_content' => '',
									'post_status' => 'inherit'
								);
								$attach_id = wp_insert_attachment( $attachment, $file['file'], $post_id );
								if ( ! is_wp_error( $attach_id ) ) {
									$attach_data = wp_generate_attachment_metadata( $attach_id, $file['file'] );
									wp_update_attachment_metadata( $attach_id, $attach_data );
									$picture_id = $attach_id;
									update_post_meta( $post_id, 'event_picture', $picture_id );
								} else {
									$messages['errors'][] = 'Misslyckades att infoga bilaga i biblioteket.';
								}
							}
						}
					}
					// if no file uploaded, still save empty meta to be consistent
					if ( empty( $picture_id ) ) {
						update_post_meta( $post_id, 'event_picture', '' );
					}
					// set a one-time transient so the success message is shown server-side once
					$user_id = get_current_user_id();
					if ( $user_id ) {
						set_transient( 'events_success_add_' . intval( $user_id ), $post_id, 30 );
					}
					// redirect to the originating page (prefer _wp_http_referer from the form)
					$redirect = '';
					if ( isset( $_POST['_wp_http_referer'] ) ) {
						$redirect = wp_unslash( $_POST['_wp_http_referer'] );
					}
					if ( empty( $redirect ) ) {
						$redirect = wp_get_referer();
					}
					if ( empty( $redirect ) ) {
						$redirect = home_url();
					}
					// clean up form params
					$redirect = remove_query_arg( array( 'events_add_submit', 'events_add_nonce' ), $redirect );
					wp_safe_redirect( esc_url_raw( $redirect ) );
					exit;
				}
			}
		}
	}
	// Handle edit event
	if ( ( isset( $_POST['events_edit_submit'] ) || isset( $_POST['event_id'] ) ) && current_user_can( 'manage_options' ) ) {
		if ( ! isset( $_POST['events_edit_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['events_edit_nonce'] ), 'events_edit_action' ) ) {
			$messages['errors'][] = 'Invalid edit submission.';
		} else {
			$edit_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;
			$name = isset( $_POST['event_name_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['event_name_edit'] ) ) : '';
			$date = isset( $_POST['event_date_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['event_date_edit'] ) ) : '';
			$time = isset( $_POST['event_time_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['event_time_edit'] ) ) : '';
			$maxNbrOfParticipants = isset( $_POST['event_nbrOfParticipants_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['event_nbrOfParticipants_edit'] ) ) : '';
			$location = isset( $_POST['event_location_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['event_location_edit'] ) ) : '';
			$status = isset( $_POST['event_status_edit'] ) ? $_POST['event_status_edit'] : '';
			$description = isset( $_POST['event_description_edit'] ) ? sanitize_text_field( wp_unslash( $_POST['event_description_edit'] ) ) : '';
			// For edit, we'll handle uploaded file via $_FILES; keep incoming value (existing attachment id)
			// Use a dedicated hidden field 'event_picture_existing' which must contain an attachment ID
			$picture = 0;
			if ( isset( $_POST['event_picture_existing'] ) ) {
				$picture = intval( wp_unslash( $_POST['event_picture_existing'] ) );
			}

			// (debug logging uses error_log; do not add debug messages to errors array here)

			if ( $edit_id <= 0 ) {
				$messages['errors'][] = 'Invalid event id.';
			}
			if ( $name === '' ) $messages['errors'][] = 'Namn är obligatoriskt.';
			if ( $date === '' ) $messages['errors'][] = 'Datum är obligatoriskt.';
			if ( $time === '' ) $messages['errors'][] = 'Tid är obligatoriskt.';

			if ( $date !== '' ) {
				$d = DateTime::createFromFormat( 'Y-m-d', $date );
				if ( ! $d || $d->format( 'Y-m-d' ) !== $date ) {
					$messages['errors'][] = 'Datum måste vara i formatet YYYY-MM-DD.';
				}
			}

			if ( empty( $messages['errors'] ) ) {
				$post = get_post( $edit_id );
				if ( ! $post || $post->post_type !== 'event' ) {
					$messages['errors'][] = 'Event not found.';
				} else {
					// Update post title and metas
					wp_update_post( array( 'ID' => $edit_id, 'post_title' => $name ) );
					update_post_meta( $edit_id, 'event_date', $date );
					update_post_meta( $edit_id, 'event_time', $time );
					update_post_meta( $edit_id, 'event_nbrOfParticipants', $maxNbrOfParticipants );
					update_post_meta( $edit_id, 'event_location', $location );
				    delete_post_meta( $edit_id, 'event_status' );
					update_post_meta( $edit_id, 'event_status', $status );
					update_post_meta( $edit_id, 'event_description', $description );
					// handle uploaded picture for edit
				if ( ! empty( $_FILES['event_picture_edit'] ) && ! empty( $_FILES['event_picture_edit']['name'] ) ) {
						require_once ABSPATH . 'wp-admin/includes/file.php';
						require_once ABSPATH . 'wp-admin/includes/image.php';
						require_once ABSPATH . 'wp-admin/includes/media.php';
						$file = wp_handle_upload( $_FILES['event_picture_edit'], array( 'test_form' => false ) );
						if ( isset( $file['error'] ) ) {
							$messages['errors'][] = 'Bilduppladdning misslyckades: ' . esc_html( $file['error'] );
						} else {
							$wp_filetype = wp_check_filetype( $file['file'], null );
							$attachment = array(
								'post_mime_type' => $wp_filetype['type'],
								'post_title' => sanitize_file_name( basename( $file['file'] ) ),
								'post_content' => '',
								'post_status' => 'inherit'
							);
							$attach_id = wp_insert_attachment( $attachment, $file['file'], $edit_id );
							if ( ! is_wp_error( $attach_id ) ) {
								$attach_data = wp_generate_attachment_metadata( $attach_id, $file['file'] );
								wp_update_attachment_metadata( $attach_id, $attach_data );
							// Always save attachment ID
							update_post_meta( $edit_id, 'event_picture', intval( $attach_id ) );
							// ensure $picture reflects saved attachment
							$picture = intval( $attach_id );
							}
						}
					} else {
						// If no new file uploaded, only update meta when we have an existing attachment ID
						if ( $picture > 0 ) {
							update_post_meta( $edit_id, 'event_picture', intval( $picture ) );
						}
					}

					// Production success message
					$messages['success-edit'] = 'Event uppdaterat.';
					
					// preserve saved values to pre-fill the edit form and keep it open
					$edit_saved = array(
						'event_id' => $edit_id,
						'event_name_edit' => $name,
						'event_date_edit' => $date,
						'event_time_edit' => $time,
						'event_location_edit' => $location,
						'event_nbrOfParticipants_edit' => $maxNbrOfParticipants,
						'event_status_edit' => $status,
						'event_description_edit' => $description,
					    'event_picture_edit' => $picture
					);

			        // Leave $edit_saved and $messages['success-edit'] set so the edit form remains open
				}
			}
		}
	}


	// Admin add form
	if ( current_user_can( 'manage_options' ) ) {
		$out .= '<form method="post" enctype="multipart/form-data" class="events-add-form">';
		$out .= wp_nonce_field( 'events_add_action', 'events_add_nonce', true, false );
		$out .= '<div class="form-header">Skapa nytt event:</div>
		        <div style="float:left; margin-right: 20px; clear: both;">
		            <label>Namn:</label>
		            <input name="event_name" required />
				</div>
		        <div style="float:left; margin-right: 20px;">
		          	<label>Datum (YYYY-MM-DD):</label>
					<input name="event_date" type="date" required />
				 </div>
				<div style="float:left; margin-right:  20px;">
		          	<label>Tid</label>
					<input name="event_time" type="time" required />					
				 </div>
				 <div style="float:left; margin-right: 20px;">
					<label>Plats:</label>
					<input name="event_location" />
				</div>
				<div style="float:left; margin-right: 20px;">
		          	<label>Max antal deltagare:</label>
					<input class="event-nbrOfParticipants" name="event_nbrOfParticipants" type="text" />
				</div>
				<div style="float:left; margin: 20px 0 0 0; width: 135px;">				    
					<input type="radio" id="event_status_add_1" name="event_status" value="Foranmalan" />
					<label style="display: inline;" for="event_status_add_1">Föranmälan:</label>
					
					<input style="clear:both;" type="radio" id="event_status_add_2" name="event_status" value="Avanmalan" checked/>
					<label style="display: inline;" for="event_status_add_2">Avanmälan:</label>
				</div>
				<div style="float:left; margin-right: 20px;">
		          	<label>Beskrivning:</label>
					<textarea class="event-description" name="event_description"></textarea>
				 </div>
				<div style="float:left; margin-right: 20px;">
				    <label>Bild:</label>
					<input type="file" name="event_picture" />
				</div>
				<div style="clear:both;">
				<button class="submit-button" type="submit" name="events_add_submit" value="1">Lägg till event</button>';
		
			    if ( $messages['success-add'] ) {
					$out .= '<div class="events-success">' . esc_html( $messages['success-add'] ) . '</div>';
				}			
				$out .= '</div>
			</form>';

			if ( $messages['success-delete'] ) {
				$out .= '<div class="events-success-delete">' . esc_html( $messages['success-delete'] ) . '</div>';
			}
	}

	// Admin edit form 
	if ( current_user_can( 'manage_options' ) ) {
		$edit_display = ( isset( $edit_saved ) && is_array( $edit_saved ) ) ? 'block' : 'none';
		$es = isset( $edit_saved ) && is_array( $edit_saved ) ? $edit_saved : array();

			$out .= '<form method="post" class="events-edit-form" enctype="multipart/form-data" id="events-edit-form" style="display:' . esc_attr( $edit_display ) . '; margin-top:20px;">';
			$out .= wp_nonce_field( 'events_edit_action', 'events_edit_nonce', true, false );
			$out .= '<input type="hidden" name="event_id" value="' . esc_attr( isset( $es['event_id'] ) ? $es['event_id'] : '' ) . '" />
			         <div class="form-header">Ändra event:<button type="button" id="events-edit-close" class="cancel-button">X</button></div>
				 	 <div style="float:left; margin-right: 20px; clear: both;">
						<label>Namn:</label>
						<input name="event_name_edit" required value="' . esc_attr( isset( $es['event_name_edit'] ) ? $es['event_name_edit'] : '' ) . '" />
					 </div>
					 <div style="float:left; margin-right: 20px;">
						<label>Datum (YYYY-MM-DD):</label>
						<input name="event_date_edit" type="date" required value="' . esc_attr( isset( $es['event_date_edit'] ) ? $es['event_date_edit'] : '' ) . '" />
					 </div>
					 <div style="float:left; margin-right:  20px;">
						<label>Tid</label>
						<input name="event_time_edit" type="time" required value="' . esc_attr( isset( $es['event_time_edit'] ) ? $es['event_time_edit'] : '' ) . '" />
					</div>
					<div style="float:left; margin-right: 20px;">
						<label>Plats:</label>
						<input name="event_location_edit" value="' . esc_attr( isset( $es['event_location_edit'] ) ? $es['event_location_edit'] : '' ) . '" />
					</div>
					<div style="float:left; margin-right: 20px;">
						<label>Max antal deltagare:</label>
						<input class="event-nbrOfParticipants" name="event_nbrOfParticipants_edit" type="text" value="' . esc_attr( isset( $es['event_nbrOfParticipants_edit'] ) ? $es['event_nbrOfParticipants_edit'] : '' ) . '" />
					</div>
					<div style="float:left; margin: 20px 0 0 0; width: 135px;">						
						<input type="radio" id="event_status_edit_1" name="event_status_edit" value="Foranmalan" ' . ( isset( $es['event_status_edit'] ) && $es['event_status_edit'] === 'Foranmalan' ? 'checked' : '' ) . ' />
						<label style="display: inline;" for="event_status_edit_1">Föranmälan:</label>						
						<input style="clear:both;" type="radio" id="event_status_edit_2" name="event_status_edit" value="Avanmalan" ' . ( isset( $es['event_status_edit'] ) && $es['event_status_edit'] === 'Avanmalan' ? 'checked' : '' ) . ' />
						<label style="display: inline;" for="event_status_edit_2">Avanmälan:</label>
					</div>
					<div style="float:left; margin-right: 20px;">
						<label>Beskrivning:</label>
						<textarea class="event-description" name="event_description_edit">' . esc_attr( isset( $es['event_description_edit'] ) ? $es['event_description_edit'] : '' ) . '</textarea>
					</div>
					<div style="float:left; margin-right: 20px;">
						<label>Bild:</label>
						<input type="file" name="event_picture_edit" />';
						
			// Show the existing picture filename or attachment URL/ID when editing
			$existing_picture = '';
			if ( isset( $es['event_picture_edit'] ) && $es['event_picture_edit'] !== '' ) {
				$existing_picture = $es['event_picture_edit'];
			} else {
				$stored_picture = get_post_meta( isset( $es['event_id'] ) ? intval( $es['event_id'] ) : 0, 'event_picture', true );
				if ( $stored_picture ) {
					$existing_picture = $stored_picture;
				}
			}
			if ( $existing_picture ) {
				if ( is_numeric( $existing_picture ) ) {
					$url = wp_get_attachment_url( intval( $existing_picture ) );
					if ( $url ) {
						$filename = wp_basename( $url );
					} else {
						$filename = strval( $existing_picture );
					}
				} else {
					if ( filter_var( $existing_picture, FILTER_VALIDATE_URL ) ) {
						$filename = wp_basename( $existing_picture );
					} else {
						$filename = strval( $existing_picture );
					}
				}
				// Preserve existing picture value in a hidden input so it isn't lost when the form is submitted
				// store existing picture in a field that won't conflict with the file input
				$out .= '<input type="hidden" name="event_picture_existing" value="' . esc_attr( $existing_picture ) . '" />';
				// also keep legacy name for compatibility (not the file input)
				$out .= '<input type="hidden" name="event_picture_edit_fallback" value="' . esc_attr( $existing_picture ) . '" />';
				$out .= '<div class="event-picture-preview">Nuvarande bild: <strong>' . esc_html( $filename ) . '</strong></div>';
			}
						
				$out .= '</div>';
				$out .= '<div style="clear:both;">';
				$out .= '<button class="submit-button" type="submit" name="events_edit_submit" value="1">Spara ändringar</button>';

				if ( $messages['success-edit'] ) {
					$out .= '<div id="events-success-edit" class="events-success">' . esc_html( $messages['success-edit'] ) . '</div>';
				}
				$out .= '</div>
			</form>';
	}

	if ( ! empty( $messages['errors'] ) ) {
		$out .= '<div class="events-errors"><ul>';
		foreach ( $messages['errors'] as $e ) {
			$out .= '<li>' . esc_html( $e ) . '</li>';
		}
		$out .= '</ul></div>';
	}

	
	// List events sorted by event_date

	$per_page = intval( $atts['per_page'] );
    $today = date('Y-m-d');

	$args_upcoming = array(
		'post_type'      => 'event',
		'posts_per_page' => -1,
		'meta_key'       => 'event_date',
		'orderby'        => 'meta_value',
		'order'          => 'DESC',
		'meta_query'     => array(
			array(
				'key'     => 'event_date',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		),
	);

	$upcoming_events = new WP_Query($args_upcoming);
	

	$args_past = array(
		'post_type'      => 'event',
		'posts_per_page' => $per_page,
		'meta_key'       => 'event_date',
		'orderby'        => 'meta_value',
		'order'          => 'DESC',
		'meta_query'     => array(
			array(
				'key'     => 'event_date',
				'value'   => $today,
				'compare' => '<',
				'type'    => 'DATE',
			),
		),
	);

	$past_events = new WP_Query($args_past);


	$out .= '<div class="events-list">';
		$out .= '<h4 style="clear:both;">Kommande events:</h4>';
		
		if ( ! $upcoming_events->have_posts() ) {
			$out .= '<p style="clear:both;">Finns inga kommande events.</p>';
		} 
		else {
			while ( $upcoming_events->have_posts() ) {	
				$upcoming_events->the_post();
				$event_id = get_the_ID();
				$name = get_the_title();	
				$event_status = get_post_meta( $event_id, 'event_status', true );
				$date = get_post_meta( $event_id, 'event_date', true );
				$time = get_post_meta( $event_id, 'event_time', true );
				$location = get_post_meta( $event_id, 'event_location', true );					
				$maxNbrOfParticipants = get_post_meta( $event_id, 'event_nbrOfParticipants', true );
				if ( ! $maxNbrOfParticipants ) {
					$maxNbrOfParticipants = '0';
				}
				$description = get_post_meta( $event_id, 'event_description', true );
				// Only store attachment IDs (integer). Reject URLs or other formats.
				$picture = intval( get_post_meta( $event_id, 'event_picture', true ) );
				// Compute a human-friendly picture label (filename) from the attachment, if present
				$picture_label = '';
				if ( $picture > 0 ) {
					$pic_url = wp_get_attachment_url( $picture );
					if ( $pic_url ) {
						$picture_label = wp_basename( $pic_url );
					}
				}
				$status = get_post_meta( $event_id, 'event_status', true );
				
				// Render event item left and right columns
				$out .= '<div class="event-item-outer">';
				$out .= '<div id="event-item-' . esc_attr( $event_id ) . '" class="event-item">';
				// left column
				$out .= '<div class="event-item-left">';
				$out .= '<div style="font-size: 20px;"><strong>' . esc_html( $name ) . '</strong></div>';
				$out .= '<div>' . esc_html( $date ) . ', kl ' . esc_html( $time ) . '</div>';
				$out .= '<div>' . esc_html( $location ) . '</div>';
				if ( $description ) {
					$out .= '<div onclick="ShowDescription(' . "'event-description-" . esc_attr( $event_id ) . "'" . ')" style="text-decoration:underline; cursor:pointer;">Beskrivning:</div>';
					$out .= '<div id="event-description-' . esc_attr( $event_id ) . '" style="text-align:left; margin-top: -5px; margin-bottom:10px; display:none;">' . esc_html( $description ) . '</div>';
				} else {
					$out .= '<div>Beskrivning ej tillgänglig.</div>';
				}
				$out .= '<div>Max ' . esc_html( $maxNbrOfParticipants ) . ' deltagare</div>';

				// Add Avanmälan or Föranmälan link or status text depending on user state
				$user_id = get_current_user_id();
				$cancelled = get_post_meta( $event_id, 'event_cancellations', true );
				if ( ! is_array( $cancelled ) ) 
					$cancelled = array();

				$registered = get_post_meta( $event_id, 'event_registrations', true );
				if ( ! is_array( $registered ) ) 
					$registered = array();

				if ( in_array( $user_id, $cancelled, true ) ) {
					$out .= '<div style="margin-bottom: 10px;">Du har avanmält dig</div>';
				} 
				else if ( in_array( $user_id, $registered, true ) ) {
					$out .= '<div style="margin-bottom: 10px;">Du har anmält dig</div>';
				} 
				else {
					if ( $status === 'Avanmalan' ) {
						$avan_url = get_page_by_path( 'avanmalan' );
						$base = $avan_url ? get_permalink( $avan_url->ID ) : site_url( '/avanmalan/' );
						$avan_url = esc_url( add_query_arg( array( 'event_id' => $event_id ), $base ) );

						$out .= '<div style="margin-bottom: 10px;" >
									<a class="avanmalan-link" href="' . esc_url( $avan_url ) . '">Avanmälan</a>
								</div>';
					}
					if ( $status === 'Foranmalan' ) {
						$foran_url = get_page_by_path( 'foranmalan' );
						$base = $foran_url ? get_permalink( $foran_url->ID ) : site_url( '/foranmalan/' );
						$foran_url = esc_url( add_query_arg( array( 'event_id' => $event_id ), $base ) );
						
						$out .= '<div style="margin-bottom: 10px;" >
									<a class="foranmalan-link" href="' . esc_url( $foran_url ) . '">Föranmälan</a>									
								</div>';
					}
				}
				// Add Anmäl gäst link
				$register_guest_page = get_page_by_path( 'register_guest' );
				$register_guest_base = $register_guest_page ? get_permalink( $register_guest_page->ID ) : site_url( '/register_guest/' );
				$register_guest_link = add_query_arg( array( 'event_id' => $event_id ), $register_guest_base );
				$out .= '<div><a href="' . esc_url( $register_guest_link ) . '">Anmäl en gäst</a></div>';

				// Add "Ladda ner mötesfil" link			
				// Finns det en option_key så finns det en fil att ladda ner
				$option_key = get_option( 'fenix_applications_excel_' . intval( $event_id ) . '_' . sanitize_key( $status ), '' );
				
				if ( $option_key != '' ) {
					$download_url = add_query_arg(
						array(
							'action'       => 'download_applications_excel',
							'event_id'     => intval( $event_id ),
							'event_status' => sanitize_key( $status ),
							'_wpnonce'     => wp_create_nonce( 'download_excel_' . intval( $event_id ) . '_' . sanitize_key( $status ) ),
						),
						admin_url( 'admin-post.php' )
					);

					$out .= '<div>
								<a href="' . esc_url( $download_url ) . '">Ladda ner mötesfil</a>
							</div>';
				}


				$out .= '</div>'; // .event-item-left

				// right column
				$out .= '<div class="event-item-right">';
				if ( $picture ) {
					$img_src = '';
					if ( is_numeric( $picture ) ) {
						$img_src = wp_get_attachment_url( intval( $picture ) );
					} elseif ( filter_var( $picture, FILTER_VALIDATE_URL ) ) {
						$img_src = $picture;
					} else {
						$img_src = wp_get_attachment_url( $picture );
					}
					if ( $img_src ) {
						$out .= '<img src="' . esc_url( $img_src ) . '" alt="Event Picture" />';
					}
				}
				$out .= '</div>'; // .event-item-right

				$out .= '</div>'; // .event-item

				if ( current_user_can( 'manage_options' ) ) {
					// Build action buttons in a single container to avoid stray text nodes between elements
					$buttons = '<span class="event-action-buttons">';
					// include both raw picture value (attachment ID or URL) and a human label
					$buttons .= '<button class="edit-button" data-event-id="' . esc_attr( $event_id ) . '" 
										data-event-name="' . esc_attr( $name ) . '" data-event-date="' . esc_attr( $date ) . '"
										data-event-time="' . esc_attr( $time ) . '" data-event-location="' . esc_attr( $location ) . '" 
										data-event-description="' . esc_attr( $description ) . '" data-event-nbr="' . esc_attr( $maxNbrOfParticipants ) . '" 
										data-event-status="' . esc_attr( $status ) . '" 
										data-event-picture-value="' . esc_attr( $picture ) . '" data-event-picture-label="' . esc_attr( $picture_label ) . '">
									Ändra
								</button>';

					$del_url = add_query_arg( array( 'delete_event' => $event_id, '_ev_nonce' => wp_create_nonce( 'events_delete_action' ) ) );
					$buttons .= '<a href="' . esc_url( $del_url ) . '" onclick="return confirm(\'Vill du radera detta event?\');">
									<button class="delete-button" name="events_delete">Radera</button>
								</a>';

					$anm_page = get_page_by_path( 'anmalningar' );
					$anm_base = $anm_page ? get_permalink( $anm_page->ID ) : site_url( '/anmalningar/' );

					if ( $status === 'Foranmalan' ) {
						$foran_link = add_query_arg( array( 'event_id' => $event_id, 'event_status' => $status  ), $anm_base );
						$buttons .= '<a href="' . esc_url( $foran_link ) . '"><button class="anmalningar-button">Anmälningar</button></a>';
					} elseif ( $status === 'Avanmalan' ) {
						$avan_link = add_query_arg( array( 'event_id' => $event_id, 'event_status' => $status ), $anm_base );
						$buttons .= '<a href="' . esc_url( $avan_link ) . '"><button class="anmalningar-button">Anmälningar</button></a>';
					}

					$buttons .= '</span>';
					
					$out .= $buttons;		
				}
				$out .= '</div>'; // Close the div event-item-outer
			}
	
			$out .= '<hr class="separator" />';

			$out .= '</div>';

		}

	
	  	$out .= '<div class="events-list">';
			$out .= '<h4 style="clear:both;">Gamla events:</h4>';

			if ( ! $past_events->have_posts() ) {
				$out .= '<p style="clear:both;">Finns inga gamla event.</p>';
			} 
			else {

				while ( $past_events->have_posts() ) {	
					$past_events->the_post();
					$event_id = get_the_ID();
					$name = get_the_title();	
					$date = get_post_meta( $event_id, 'event_date', true );
					$time = get_post_meta( $event_id, 'event_time', true );
					$location = get_post_meta( $event_id, 'event_location', true );					
					$maxNbrOfParticipants = get_post_meta( $event_id, 'event_nbrOfParticipants', true );
					if ( ! $maxNbrOfParticipants ) {
						$maxNbrOfParticipants = '0';
					}
					$description = get_post_meta( $event_id, 'event_description', true );
					// Only store attachment IDs (integer). Reject URLs or other formats.
					$picture = intval( get_post_meta( $event_id, 'event_picture', true ) );
					// Compute a human-friendly picture label (filename) from the attachment, if present
					$picture_label = '';
					if ( $picture > 0 ) {
						$pic_url = wp_get_attachment_url( $picture );
						if ( $pic_url ) {
							$picture_label = wp_basename( $pic_url );
						}
					}
					$status = get_post_meta( $event_id, 'event_status', true );
					
					// Render event item left and right columns
					$out .= '<div class="event-item-outer">';
					$out .= '<div id="event-item-' . esc_attr( $event_id ) . '" class="event-item">';
					// left column
					$out .= '<div class="event-item-left">';
					$out .= '<div style="font-size: 20px;"><strong>' . esc_html( $name ) . '</strong></div>';
					$out .= '<div>' . esc_html( $date ) . ', kl ' . esc_html( $time ) . '</div>';
					$out .= '<div>' . esc_html( $location ) . '</div>';
					if ( $description ) {
						$out .= '<div onclick="ShowDescription(' . "'event-description-" . esc_attr( $event_id ) . "'" . ')" style="text-decoration:underline; cursor:pointer;">Beskrivning:</div>';
						$out .= '<div id="event-description-' . esc_attr( $event_id ) . '" style="text-align:left; margin-top: -5px; margin-bottom:10px; display:none;">' . esc_html( $description ) . '</div>';
					} else {
						$out .= '<div>Beskrivning ej tillgänglig.</div>';
					}
					$out .= '<div>Max ' . esc_html( $maxNbrOfParticipants ) . ' deltagare</div>';

					// Add Avanmälan or Föranmälan link or status text depending on user state
					if ( is_user_logged_in() ) {
						$user_id = get_current_user_id();
						$cancelled = get_post_meta( $event_id, 'event_cancellations', true );
						if ( ! is_array( $cancelled ) ) 
							$cancelled = array();

						$registered = get_post_meta( $event_id, 'event_registrations', true );
						if ( ! is_array( $registered ) ) 
							$registered = array();

						if ( in_array( $user_id, $cancelled, true ) ) {
							$out .= '<div style="margin-bottom: 10px;">Du har avanmält dig</div>';
						} 
						else if ( in_array( $user_id, $registered, true ) ) {
							$out .= '<div style="margin-bottom: 10px;">Du har anmält dig</div>';
						} 
						else {
							if ( $status === 'Avanmalan' ) {
								$avan_url = get_page_by_path( 'avanmalan' );
								$base = $avan_url ? get_permalink( $avan_url->ID ) : site_url( '/avanmalan/' );
								$avan_url = esc_url( add_query_arg( array( 'event_id' => $event_id ), $base ) );

								$out .= '<div style="margin-bottom: 10px;" >
											<a class="avanmalan-link" href="' . esc_url( $avan_url ) . '">Avanmälan</a>
										</div>';
							}
							if ( $status === 'Foranmalan' ) {
								$foran_url = get_page_by_path( 'foranmalan' );
								$base = $foran_url ? get_permalink( $foran_url->ID ) : site_url( '/foranmalan/' );
								$foran_url = esc_url( add_query_arg( array( 'event_id' => $event_id ), $base ) );
								
								$out .= '<div style="margin-bottom: 10px;" >
											<a class="foranmalan-link" href="' . esc_url( $foran_url ) . '">Föranmälan</a>									
										</div>';
							}
						}

						// Add "Anmäl en gäst" link		
						$register_guest_page = get_page_by_path( 'register_guest' );
						$register_guest_base = $register_guest_page ? get_permalink( $register_guest_page->ID ) : site_url( '/register_guest/' );
						$register_guest_link = add_query_arg( array( 'event_id' => $event_id ), $register_guest_base );
						$out .= '<div><a href="' . esc_url( $register_guest_link ) . '">Anmäl en gäst</a></div>';

						// Add "Ladda ner mötesfil" link			
						// Finns det en option_key så finns det en fil att ladda ner.
						$option_key = get_option( 'fenix_applications_excel_' . intval( $event_id ) . '_' . sanitize_key( $status ), '' );                        

						if ( $option_key != '' ) {
							$download_url = add_query_arg(
								array(
									'action'       => 'download_applications_excel',
									'event_id'     => intval( $event_id ),
									'event_status' => sanitize_key( $status ),
									'_wpnonce'     => wp_create_nonce( 'download_excel_' . intval( $event_id ) . '_' . sanitize_key( $status ) ),
								),
								admin_url( 'admin-post.php' )
							);

							$out .= '<div>
										<a href="' . esc_url( $download_url ) . '">Ladda ner mötesfil</a>
									</div>';
						}
					} 
				
					$out .= '</div>'; // .event-item-left

					// right column
					$out .= '<div class="event-item-right">';
					if ( $picture ) {
						$img_src = '';
						if ( is_numeric( $picture ) ) {
							$img_src = wp_get_attachment_url( intval( $picture ) );
						} elseif ( filter_var( $picture, FILTER_VALIDATE_URL ) ) {
							$img_src = $picture;
						} else {
							$img_src = wp_get_attachment_url( $picture );
						}
						if ( $img_src ) {
							$out .= '<img src="' . esc_url( $img_src ) . '" alt="Event Picture" />';
						}
					}
					$out .= '</div>'; // .event-item-right

					$out .= '</div>'; // .event-item

					if ( current_user_can( 'manage_options' ) ) {
						// Build action buttons in a single container to avoid stray text nodes between elements
						$buttons = '<span class="event-action-buttons">';
						// include both raw picture value (attachment ID or URL) and a human label
						$buttons .= '<button class="edit-button" data-event-id="' . esc_attr( $event_id ) . '" 
											data-event-name="' . esc_attr( $name ) . '" data-event-date="' . esc_attr( $date ) . '"
											data-event-time="' . esc_attr( $time ) . '" data-event-location="' . esc_attr( $location ) . '" 
											data-event-description="' . esc_attr( $description ) . '" data-event-nbr="' . esc_attr( $maxNbrOfParticipants ) . '" 
											data-event-status="' . esc_attr( $status ) . '" 
											data-event-picture-value="' . esc_attr( $picture ) . '" data-event-picture-label="' . esc_attr( $picture_label ) . '">
										Ändra
									</button>';

						$del_url = add_query_arg( array( 'delete_event' => $event_id, '_ev_nonce' => wp_create_nonce( 'events_delete_action' ) ) );
						$buttons .= '<a href="' . esc_url( $del_url ) . '" onclick="return confirm(\'Vill du radera detta event?\');">
						                 <button class="delete-button" name="events_delete">Radera</button>
								     </a>';

						$anm_page = get_page_by_path( 'anmalningar' );
						$anm_base = $anm_page ? get_permalink( $anm_page->ID ) : site_url( '/anmalningar/' );

						if ( $status === 'Foranmalan' ) {
							$foran_link = add_query_arg( array( 'event_id' => $event_id, 'event_status' => $status ), $anm_base );
							$buttons .= '<a href="' . esc_url( $foran_link ) . '"><button class="anmalningar-button">Anmälningar</button></a>';
						} elseif ( $status === 'Avanmalan' ) {
							$avan_link = add_query_arg( array( 'event_id' => $event_id, 'event_status' => $status ), $anm_base );
							$buttons .= '<a href="' . esc_url( $avan_link ) . '"><button class="anmalningar-button">Anmälningar</button></a>';
						}

						$buttons .= '</span>';
						
						$out .= $buttons;
					}
					$out .= '</div>'; // Close the div event-item-outer
				}
			
            $out .= '</div>';
	}

	wp_reset_postdata(); 


	// Inline script to handle edit button clicks (only for admins)

	// load external JS from theme child folder
	$script_url = get_stylesheet_directory_uri() . '/js/events-edit.js';
	$out .= '<script src="' . esc_url( $script_url ) . '"></script>';

	return $out;
}
add_shortcode( 'events_list', 'shortcode_events_list' );


function download_applications_excel_handler() {
	if ( ! is_user_logged_in() ) {
		wp_die( 'Du måste vara inloggad för att ladda ner filen.' );
	}

	$event_id = isset( $_GET['event_id'] ) ? intval( wp_unslash( $_GET['event_id'] ) ) : 0;
	$event_status = isset( $_GET['event_status'] ) ? sanitize_key( wp_unslash( $_GET['event_status'] ) ) : '';
	$nonce = isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '';

	if ( $event_id <= 0 || ( $event_status !== 'foranmalan' && $event_status !== 'avanmalan' ) ) {
		wp_die( 'Ogiltig nedladdningsförfrågan.' );
	}

	if ( ! wp_verify_nonce( $nonce, 'download_excel_' . $event_id . '_' . $event_status ) ) {
		wp_die( 'Ogiltig formulärförfrågan.' );
	}

	$option_key = 'fenix_applications_excel_' . intval( $event_id ) . '_' . sanitize_key( $event_status );
	$excel_state = get_option( $option_key, array() );

	if ( empty( $excel_state['path'] ) || ! file_exists( $excel_state['path'] ) ) {
		wp_die( 'Filen kunde inte hittas.' );
	}

	$file_path = $excel_state['path'];
	$download_name = basename( $file_path );

	if ( ob_get_length() ) {
		ob_end_clean();
	}

	nocache_headers();
	header( 'Content-Description: File Transfer' );
	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
	header( 'Content-Length: ' . filesize( $file_path ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $file_path );

	exit;
}
add_action( 'admin_post_download_applications_excel', 'download_applications_excel_handler' );

