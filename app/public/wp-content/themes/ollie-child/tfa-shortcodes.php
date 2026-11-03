<?php

function shortcode_tfa_form( $atts )
{
    if ( ! is_user_logged_in() ) {
		$out = '<div style="color: green;"><strong>Du måste vara inloggad för att se dina TFA-uppgifter</strong></div>';
		return $out;
	}

    $currentUser = wp_get_current_user();

    $messages = array( 'errors' => array(), 'success' => '' );
    $reset_form = false;
    $current_tfa_post_id = isset( $_POST['tfa_post_id'] ) ? intval( wp_unslash( $_POST['tfa_post_id'] ) ) : 0;

    if ( isset( $_POST['tfa_new_submit'] ) ) {
        if ( ! isset( $_POST['tfa_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['tfa_nonce'] ), 'tfa_action' ) ) {
            $messages['errors'][] = 'Ogiltig formulärförfrågan.';
        } 
        else {
            $reset_form = true;
            $current_tfa_post_id = 0;
            $messages['success'] = 'Formuläret är återställt. Du kan registrera en ny TFA.';
        }
    }
    
    if ( isset( $_POST['tfa_submit'] ) && !$reset_form ) {
       
        if ( ! isset( $_POST['tfa_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['tfa_nonce'] ), 'tfa_action' ) ) {
            $messages['errors'][] = 'Ogiltig formulärförfrågan.';
        } 
        else {
            $tfa_receiver_id = isset( $_POST['tfa_receiver'] ) ? intval( wp_unslash( $_POST['tfa_receiver'] ) ) : 0;
            $tfa_amount = isset( $_POST['tfa_amount'] ) ? floatval( wp_unslash( $_POST['tfa_amount'] ) ) : 0;
            $tfa_description = isset( $_POST['tfa_description'] ) ? wp_unslash( $_POST['tfa_description'] ) : '';
            $tfa_title = isset( $_POST['tfa_title'] ) ? wp_unslash( $_POST['tfa_title'] ) : '';

            if ( $tfa_receiver_id <= 0 || ! get_user_by( 'id', $tfa_receiver_id ) ) {
                $messages['errors'][] = 'Välj en giltig medlem.';
            }

            if ( $tfa_amount <= 0 ) {
                $messages['errors'][] = 'Ange ett giltigt belopp som är större än 0.';
            }

            if ( empty( $tfa_title ) || strlen( $tfa_title ) > 80 ) {
                $messages['errors'][] = 'Ange en kort beskrivning';
            }

            // Handle file uploads for TFA
            // Allowed mime types
            $allowed_mimes = array(
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // PowerPoint (ppt, pptx, pptm)
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
                'text/plain',
                'application/rtf',
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/bmp',
                'image/gif',
                'application/pdf',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

            // Allowed extensions (fallback)
            $allowed_exts = array( 'doc','docx','xls','xlsx','ppt','pptx','pptm','txt','rtf','jpg','jpeg','png','webp','bmp','gif','pdf' );

            // Extension -> mime mapping for fallback when detection fails
            $text_to_mime = array(
                'ppt'  => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'pptm' => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
                'doc'  => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls'  => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'pdf'  => 'application/pdf',
                'txt'  => 'text/plain',
                'rtf'  => 'application/rtf',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'webp' => 'image/webp',
                'bmp'  => 'image/bmp',
                'gif'  => 'image/gif'
            );

            // Max file size 5 MB
	        $max_size = 5 * 1024 * 1024;
            
            $has_tfa_files = false;
            if ( isset( $_FILES['tfa_files']['name'] ) ) {
                if ( is_array( $_FILES['tfa_files']['name'] ) ) {
                    $has_tfa_files = ! empty( array_filter( $_FILES['tfa_files']['name'] ) );
                } else {
                    $has_tfa_files = ! empty( $_FILES['tfa_files']['name'] );
                }
            }

            $files = array();

            if ( $has_tfa_files ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
                
				// Normalize multiple uploads
				
				if ( is_array( $_FILES['tfa_files']['name'] ) ) {
					
					for ( $i = 0; $i < count( $_FILES['tfa_files']['name'] ); $i++ ) {
						if ( empty( $_FILES['tfa_files']['name'][ $i ] ) ) 
                            continue;
						$files[] = array(
							'name'     => $_FILES['tfa_files']['name'][ $i ],
							'type'     => $_FILES['tfa_files']['type'][ $i ],
							'tmp_name' => $_FILES['tfa_files']['tmp_name'][ $i ],
							'error'    => $_FILES['tfa_files']['error'][ $i ],
							'size'     => $_FILES['tfa_files']['size'][ $i ],
						);
					}
				} 
                else { // single file
					$files[] = $_FILES['tfa_files'];
				}

				foreach ( $files as $file ) {
                    if ( intval( $file['error'] ) !== 0 ) {
                        $messages['errors'][] = sprintf( 'Upppladdningsfel för %s (code %d).', esc_html( $file['name'] ), intval( $file['error'] ) );
                        continue;
                    }
                    if ( intval( $file['size'] ) > $max_size ) {
                        $messages['errors'][] = sprintf( '%s är för stor (max %d MB).', esc_html( $file['name'] ), intval( $max_size / (1024*1024) ) );
                        continue;
                    }

                    // Validate file type and extension using WP helper which attempts content-based checks
                    $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
                    $detected_type = isset( $check['type'] ) ? $check['type'] : '';
                    $detected_ext = isset( $check['ext'] ) ? $check['ext'] : '';

                    if ( empty( $detected_ext ) || empty( $detected_type ) ) {
                        $messages['errors'][] = sprintf( '%s har en ej stödd eller okänd filtyp.', esc_html( $file['name'] ) );
                        continue;
                    }

                    if ( ! in_array( $detected_type, $allowed_mimes, true ) ) {
                        $messages['errors'][] = sprintf( '%s har en filtyp som inte stöds (%s).', esc_html( $file['name'] ), esc_html( $detected_type ) );
                        continue;
                    }

                    // Move files to uploads
                    $overrides = array( 'test_form' => false );
                    $moved = wp_handle_upload( $file, $overrides );
                    if ( isset( $moved['error'] ) ) {
                        $messages['errors'][] = sprintf( 'Misslyckades att flytta %s: %s', esc_html( $file['name'] ), esc_html( $moved['error'] ) );
                        continue;
                    }

                    $filename = $moved['file'];
                    $filetype = wp_check_filetype( basename( $filename ), null );
                    $attachment = array(
                        'post_mime_type' => $filetype['type'],
                        'post_title'     => sanitize_file_name( basename( $filename ) ),
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                        'post_author'    => $currentUser->ID,
                        'post_parent'    => $current_tfa_post_id,
                    );
                }
            }

            if ( empty( $messages['errors'] ) ) {
                if ( $current_tfa_post_id > 0 ) {
                    $existing_post = get_post( $current_tfa_post_id );
                    if ( isset($existing_post) && intval( $existing_post->post_author ) == $currentUser->ID ) {
                        $post_id = wp_update_post(
                            array(
                                'ID' => $current_tfa_post_id,
                                'post_title' => $tfa_title
                            ),
                            true
                        );
                    } 
                    else {
                        $messages['errors'][] = 'Kunde inte uppdatera TFA-posten.';
                        $post_id = 0;
                    }
                }
                else {
                    $post_id = wp_insert_post(
                        array(
                            'post_title'  => $tfa_title,
                            'post_type'   => 'tfa',
                            'post_status' => 'publish',
                            'post_author' => $currentUser->ID,
                        ),
                        true
                    );
                }

                if ( !is_wp_error( $post_id ) && $post_id > 0) {
                    // update_post_meta handles both inserting and updating post meta values
                    update_post_meta( $post_id, 'tfa-sender', $currentUser->ID );
                    update_post_meta( $post_id, 'tfa-receiver', $tfa_receiver_id );
                    update_post_meta( $post_id, 'tfa-amount', $tfa_amount );
                    update_post_meta( $post_id, 'tfa-description', $tfa_description );
                    $current_tfa_post_id = intval( $post_id );

                    foreach ( $files as $file ) {
                        $attach_id = wp_insert_attachment( $attachment, $filename );
                                            
                        if ( is_wp_error( $attach_id ) || ! $attach_id ) {
                            $messages['errors'][] = sprintf( 'Misslyckades att infoga bilaga för %s.', esc_html( $file['name'] ) );
                            // remove file on disk
                            @unlink( $filename );
                            continue;
                        }

                        $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
                        wp_update_attachment_metadata( $attach_id, $attach_data );

                        // add reference to tfa post_meta for the file
                        add_post_meta( $current_tfa_post_id, 'tfa-file', $attach_id );
                    }

                    $messages['success'] = 'TFA-informationen har sparats.';
                } 
                elseif ( is_wp_error( $post_id ) ) {
                    $messages['errors'][] = $post_id->get_error_message();
                }
			}
        }
    }

    $current_company = get_user_meta( $currentUser->ID, 'company', true );
    $args = array('orderby' => 'registered', 'order'   => 'DESC', 'fields'  => 'all', ); 
    
    $q = new WP_User_Query( $args ); 
    $users = $q->get_results(); 

    if ( empty( $users ) ) 
    { 
        error_log( 'all_members shortcode: no members. Query vars: ' . print_r( $q->query_vars, true ) . ' total:' . $q->get_total() ); 
        
        return '<p>Finns inga medlemmar i databasen.</p>'; 
    }
    
    // Handle output to screen  
    $out = '';

    if ( ! empty( $messages['errors'] ) ) {
        $out .= '<div class="member-errors"><ul>';
        foreach ( $messages['errors'] as $e ) {
            $out .= '<li>' . esc_html( $e ) . '</li>';
        }
        $out .= '</ul></div>';
    }
    if ( $messages['success'] ) {
        $out .= '<div class="member-success">' . esc_html( $messages['success'] ) . '</div>';
    }

    $out .= '<p>Här kan du lägga in tfa-er (Tack för affären) som du gett andra medlemmar.</p>';
    $out .= '<form method="post" enctype="multipart/form-data" class="tfa-form">';
    $out .= wp_nonce_field( 'tfa_action', 'tfa_nonce', true, false );
    $out .= '<input type="hidden" name="tfa_post_id" value="' . esc_attr( $current_tfa_post_id ) . '" />';

    $out .=  '<div class="to-member-wrap">
                 <p style="margin-top: 0;">Ditt namn och företag!</p>
                 <p><strong>' . esc_html( $currentUser->display_name . ' - ' . $current_company ) . '</strong></p>
              </div>
              <hr class="tfa-separator" />';

    $out .= '<div class="from-member-wrap"> 
               <label>Vem har du fått affären från?</label>               
               <select name="tfa_receiver" required>';
                  $posted_sender = $reset_form ? 0 : ( isset( $_POST['tfa_receiver'] ) ? intval( wp_unslash( $_POST['tfa_receiver'] ) ) : 0 );
                  $out .= '<option value="">Välj medlem</option>';
                  foreach ( $users as $u ) {       
                     $sender_company = get_user_meta( $u->ID, 'company', true );
                     $selected = ( $posted_sender === intval( $u->ID ) ) ? ' selected' : '';
                     $out .= '<option value="' . esc_attr( $u->ID ) . '"' . $selected . '>' . esc_html( $u->display_name . ' - ' . $sender_company ) . '</option>';
                  }
	$out .=    '</select>
            </div>
            <hr class="tfa-separator" />';

    $out .= '<div class="title-wrap"> 
                <label>Kort beskrivning av affären. Max 40 tecken</label>
                <input type="text" name="tfa_title" required maxlength="40" value="' . esc_html( $reset_form ? '' : ( isset( $_POST['tfa_title'] ) ? wp_unslash( $_POST['tfa_title'] ) : '' ) ) . '">
             </div>
             <hr class="tfa-separator" />'; 

    $out .= '<div class="description-wrap"> 
                <label>Lång beskrivning av affären.</label>
                <textarea name="tfa_description" rows="4" required>' . esc_html( $reset_form ? '' : ( isset( $_POST['tfa_description'] ) ? wp_unslash( $_POST['tfa_description'] ) : '' ) ) . '</textarea>
             </div>
             <hr class="tfa-separator" />'; 
  

    $file_atts = get_post_meta( $current_tfa_post_id, 'tfa-file', false ); // all values
    $file_labels = array();
    
    if ( !empty( $file_atts ) ) {
        // Prepare file labels for existing attachments
        foreach ( $file_atts as $att_id ) {
            $att_id = intval( $att_id );
            if ( $att_id <= 0 ) continue;

            $file_url = wp_get_attachment_url( $att_id );
            if ( $file_url ) {
                $file_labels[] = wp_basename( $file_url );
            }
        }
    }

    $out .= '<div class="file-upload-wrap"> 
                <p>Här kan du ladda upp filer relaterade till affären.</p>
			    <p style="font-size: 15px; margin-bottom: -10px;">Tillåtna filtyper: doc, docx, xls, xlsx, ppt, pptx, pptm, rtf, txt, jpg, jpeg, png, webp, bmp, gif, pdf.</p>           
                <p style="font-size: 15px;">Max filstorlek: 5 MB</p>
                <label>Välj filer att ladda upp (flera filer är tillåtet):</label>
                <input type="file" name="tfa_files[]" multiple />';
                if ( isset($file_labels[0]) ) {
                    $out .= '<div class="file-preview">Nuvarande filer: <strong>' . esc_html( $file_labels[0] ) . '</strong></div>';                    
                    for ($i = 1; $i < count($file_labels); $i++ ) {                        
                        $out .= '<div class="file-preview" style="margin-left: 115px;"><strong>' . esc_html( $file_labels[$i] ) . '</strong></div>';
                    }
                }
    $out .= '</div>
             <hr class="tfa-separator" />';

    $out .= '<div class="amount-wrap">
                <label>Belopp (använd endast siffror utan mellanslag)</label>
                <input type="number" name="tfa_amount" min="1" step="1" value="' . esc_attr( $reset_form ? '' : ( isset( $_POST['tfa_amount'] ) ? wp_unslash( $_POST['tfa_amount'] ) : '' ) ) . '" required />
             </div>
             <hr class="tfa-separator" />';

    $out .=   '<p>
                  <button type="submit" name="tfa_submit" class="submit-button">Spara TFA</button> 
                  <button type="submit" name="tfa_new_submit" class="submit-button">Ny TFA</button>
               </p>';
    $out .= '</form>';
    
    return $out; 
} 

add_shortcode( 'tfa_form', 'shortcode_tfa_form' );  


function shortcode_tfa_list( $atts )
{

    $out = '<p>Nedanför kommer en lista över dina tidigare TFA:er (både skickade och mottagna).</p>';

    $currentUser = wp_get_current_user();

    $tfa_posts = get_posts( 
        array(
            'post_type' => 'tfa',
            'author' => $currentUser->ID,
            'posts_per_page' => -1
        ) 
    );

    if ( !empty( $tfa_posts ) ) {
        $out .= '<div>Här är dina tidigare skickade TFA:er:</div>';
        $out .= '<ul class="user-tfa-list">';
        foreach ( $tfa_posts as $post ) {
            $tfa_amount= get_post_meta( $post->ID, 'tfa-amount', true ); 
            $out .= '<li>' . esc_html( get_the_title( $post->ID ) ) . ' - Belopp: ' . esc_html( $tfa_amount ) . '</li>';
        }
        $out .= '</ul>';
    } else {
        $out .= '<p>Inga skickade TFA:er hittades.</p>';
    }

    return $out;
}

add_shortcode( 'tfa_list', 'shortcode_tfa_list' );  