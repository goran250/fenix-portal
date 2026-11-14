<?php

function shortcode_tfa_intro( $atts )
{
    if ( ! is_user_logged_in() ) {
		$out = '<div style="color: green;"><strong>Du måste vara inloggad för att se dina TFA-uppgifter</strong></div>';
		return $out;
	}

    $currentUser = wp_get_current_user();


    $tfa_posts_sent = get_posts( 
        array(
            'post_type' => 'tfa',
            'author' => $currentUser->ID,
            'posts_per_page' => -1,
        ) 
    );

    $tfa_sent_amount = 0;

    foreach ($tfa_posts_sent as $tfa_post) {            
        $tfa_sent_amount += intval( get_post_meta( $tfa_post->ID, 'tfa-amount', true ) );;
    }


    $tfa_posts_received = get_posts( array(
        'post_type'      => 'tfa',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'meta_query'     => array(
            array(
                'key'     => 'tfa-receiver',
                'value'   => $currentUser->ID,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
    ));

    $tfa_received_amount = 0;
    
    foreach ($tfa_posts_received as $tfa_post) {            
        $tfa_received_amount += intval( get_post_meta( $tfa_post->ID, 'tfa-amount', true ) );;
    }


    $tfa_posts_all = get_posts( 
        array(
            'post_type' => 'tfa',            
            'posts_per_page' => -1,
        ) 
    );

    $tfa_total_amount = 0;

    foreach ($tfa_posts_all as $tfa_post) {            
        $tfa_total_amount += intval( get_post_meta( $tfa_post->ID, 'tfa-amount', true ) );;
    }


    $out = '<div class="tfa-intro-box">
                <div>
                    <p>Mina skickade tfa:er:</p>
                    <p><strong>' .  number_format( $tfa_sent_amount, 0, ',', ' ' ) . ' kr</strong></p>
                </div>
    
                <div>
                    <p>Mina mottagna tfa:er:</p>
                    <p><strong>' .  number_format( $tfa_received_amount, 0, ',', ' ' ) . ' kr</strong></p>
                </div>

                <div>
                    <p>Totalt belopp för alla tfa:er:</p>
                    <p><strong>' .  number_format( $tfa_total_amount, 0, ',', ' ' ) . ' kr</strong></p>
                </div>
            </div>';
   
    return $out;
}
    
add_shortcode( 'tfa_intro', 'shortcode_tfa_intro' );  

function shortcode_tfa_form( $atts )
{
    if ( ! is_user_logged_in() ) {
		return '';
	}

    $currentUser = wp_get_current_user();

    $messages = array( 'errors' => array(), 'success' => '' );
    $reset_form = false;
    $current_tfa_post_id = isset( $_POST['tfa_post_id'] ) ? intval( wp_unslash( $_POST['tfa_post_id'] ) ) : 0;

    // Edit mode via query parameter: ? tfa_edit_id = post_id>
    if ( $current_tfa_post_id <= 0 && isset( $_GET['tfa_edit_id'] ) ) {
        $tfa_edit_id = intval( wp_unslash( $_GET['tfa_edit_id'] ) );
        if ( $tfa_edit_id > 0 ) {
            $edit_post = get_post( $tfa_edit_id );
            if ( $edit_post && intval( $edit_post->post_author ) === intval( $currentUser->ID ) ) {
                $current_tfa_post_id = $tfa_edit_id;
            }
        }
    }

    // Delete one attached file from current TFA
    if ( isset( $_POST['tfa_delete_attach_id'] ) ) {
        if ( ! isset( $_POST['tfa_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['tfa_nonce'] ), 'tfa_action' ) ) {
            $messages['errors'][] = 'Ogiltig formulärförfrågan.';
        } else {
            $delete_attach_id = intval( wp_unslash( $_POST['tfa_delete_attach_id'] ) );
            $delete_post_id = isset( $_POST['tfa_post_id'] ) ? intval( wp_unslash( $_POST['tfa_post_id'] ) ) : 0;

            if ( $delete_attach_id > 0 && $delete_post_id > 0 ) {
                $delete_post = get_post( $delete_post_id );
                if ( $delete_post && intval( $delete_post->post_author ) === intval( $currentUser->ID ) ) {
                    $attached_ids = get_post_meta( $delete_post_id, 'tfa-file', false );
                    if ( in_array( $delete_attach_id, array_map( 'intval', $attached_ids ), true ) ) {
                        delete_post_meta( $delete_post_id, 'tfa-file', $delete_attach_id );
                        wp_delete_attachment( $delete_attach_id, true );
                        $messages['success'] = 'Filen är borttagen.';
                    }
                }
                $current_tfa_post_id = $delete_post_id;
            }
        }
    }


    if ( isset( $_POST['tfa_new_submit'] ) ) {
        if ( ! isset( $_POST['tfa_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['tfa_nonce'] ), 'tfa_action' ) ) {
            $messages['errors'][] = 'Ogiltig formulärförfrågan.';
             $out = '<div style="color: red;"><strong>' . 'Ogiltig formulärförfrågan.</strong></div>';
            return $out;
        } 
        else {
            // Reset form state and clear edit query parameter to avoid updating previous TFA
            $redirect_url = remove_query_arg( array( 'tfa_edit_id', 'tfa_post_to_show_id' ));
            $redirect_url = add_query_arg( 'tfa_reset', '1', $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    if ( isset( $_GET['tfa_reset'] ) && !isset( $_GET['tfa_edit_id'] ) ) {
        $reset_form = true;
        $current_tfa_post_id = 0;
        $messages['success'] = 'Formuläret är återställt. Du kan registrera en ny TFA.';
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

            if ( empty( $tfa_title ) || strlen( $tfa_title ) > 40 ) {
                $messages['errors'][] = 'Ange en kort beskrivning';
            }

            // Update post in db.  
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

            }
            
            $current_tfa_post_id = $post_id;

            HandleUploadedFiles($current_tfa_post_id, $currentUser );

            // update post_meta. Posts and Uploaded files is already handled.   
            if ( empty( $messages['errors'] ) ) {
                if ( !is_wp_error( $post_id ) && $post_id > 0) {
                    // update_post_meta handles both inserting and updating post meta values
                    update_post_meta( $post_id, 'tfa-sender', $currentUser->ID );
                    update_post_meta( $post_id, 'tfa-receiver', $tfa_receiver_id );
                    update_post_meta( $post_id, 'tfa-amount', $tfa_amount );
                    update_post_meta( $post_id, 'tfa-description', $tfa_description );
                    $current_tfa_post_id = intval( $post_id );

                                     
                    $messages['success'] = 'TFA-informationen har sparats.';
                } 
                elseif ( is_wp_error( $post_id ) ) {
                    $messages['errors'][] = $post_id->get_error_message();
                }
			}
        }
    }

    
    $args = array('orderby' => 'registered', 'order' => 'DESC', 'fields' => 'all', ); 
    
    $q = new WP_User_Query( $args ); 
    $users = $q->get_results(); 

    if ( empty( $users ) ) 
    { 
        error_log( 'all_members shortcode: no members. Query vars: ' . print_r( $q->query_vars, true ) . ' total:' . $q->get_total() ); 
        
        return '<p>Finns inga medlemmar i databasen.</p>'; 
    }

    // Prepare form values (load existing TFA if editing)
    $form_receiver = 0;
    $form_title = '';
    $form_description = '';
    $form_amount = '';

    if ( $current_tfa_post_id > 0 && !isset( $_POST['tfa_submit'] ) ) {  // Load existing TFA for editing
        $form_receiver = intval( get_post_meta( $current_tfa_post_id, 'tfa-receiver', true ) );
        $form_title = get_the_title( $current_tfa_post_id );
        $form_description = (string) get_post_meta( $current_tfa_post_id, 'tfa-description', true );
        $saved_amount = get_post_meta( $current_tfa_post_id, 'tfa-amount', true );
        $form_amount = ( $saved_amount !== '' ) ? (string) $saved_amount : '';
    }

    if ( isset( $_POST['tfa_submit'] ) || isset( $_POST['tfa_new_submit'] ) ) {
        $form_receiver = $reset_form ? 0 : ( isset( $_POST['tfa_receiver'] ) ? intval( wp_unslash( $_POST['tfa_receiver'] ) ) : $form_receiver );
        $form_title = $reset_form ? '' : ( isset( $_POST['tfa_title'] ) ? (string) wp_unslash( $_POST['tfa_title'] ) : $form_title );
        $form_description = $reset_form ? '' : ( isset( $_POST['tfa_description'] ) ? (string) wp_unslash( $_POST['tfa_description'] ) : $form_description );
        $form_amount = $reset_form ? '' : ( isset( $_POST['tfa_amount'] ) ? (string) wp_unslash( $_POST['tfa_amount'] ) : $form_amount );
    }
    
    // Handle output to screen  
    $out = '';

    if ( ! empty( $messages['errors'] ) ) {
        $out .= '<div class="tfa-errors"><ul>';
        foreach ( $messages['errors'] as $e ) {
            $out .= '<li>' . esc_html( $e ) . '</li>';
        }
        $out .= '</ul></div>';
    }
    if ( $messages['success'] ) {
        $out .= '<div class="tfa-success">' . esc_html( $messages['success'] ) . '</div>';
    }

    $out .= '<div style="width: 100%; float: left;">
                <p style="clear; both;">Här kan du lägga in tfa-er (Tack för affären) som du gett andra medlemmar.</p>
             </div>';
    $form_action = esc_url( remove_query_arg( array( 'tfa_edit_id', 'tfa_reset', 'tfa_post_to_show_id' ) ) );
    $out .= '<form method="post" action="' . $form_action . '" enctype="multipart/form-data" class="tfa-form">';
    $out .= wp_nonce_field( 'tfa_action', 'tfa_nonce', true, false );
    $out .= '<input type="hidden" name="tfa_post_id" value="' . esc_attr( $current_tfa_post_id ) . '" />';

    $out .=  '<div class="from-member-wrap">
                 <p style="margin-top: 0;">Ditt namn och företag!</p>
                 <p><strong>' . esc_html( $currentUser->display_name . ' - ' . $currentUser->company ) . '</strong></p>
              </div>
              <hr class="tfa-separator" />';

    $out .= '<div class="to-member-wrap"> 
               <label>Vem har du fått affären från?</label>               
               <select name="tfa_receiver" required >';
                  $posted_sender = intval( $form_receiver );
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
                <label>Titel: En kort beskrivning av affären. Max 30 tecken</label>
                <input type="text" name="tfa_title" required maxlength="30" value="' . esc_attr( $form_title ) . '">
             </div>
             <hr class="tfa-separator" />'; 

    $out .= '<div class="description-wrap"> 
                <label>Lång beskrivning av affären.</label>
                <textarea name="tfa_description" rows="4" required>' . esc_textarea( $form_description ) . '</textarea>
             </div>
             <hr class="tfa-separator" />'; 
  

    $file_atts = get_post_meta( $current_tfa_post_id, 'tfa-file', false ); // all values
    $file_items = array();
    
    if ( !empty( $file_atts ) ) {
        // Prepare file labels for existing attachments
        foreach ( $file_atts as $att_id ) {
            $att_id = intval( $att_id );
            if ( $att_id <= 0 ) continue;

            $file_url = wp_get_attachment_url( $att_id );
            if ( $file_url ) {
                $file_items[] = array(
                    'id'    => $att_id,
                    'label' => wp_basename( $file_url ),
                );
            }
        }
    }

    $out .= '<div class="file-upload-wrap"> 
                <p>Här kan du ladda upp filer relaterade till affären.</p>
			    <p style="font-size: 15px; margin-bottom: -10px;">Tillåtna filtyper: doc, docx, xls, xlsx, ppt, pptx, pptm, rtf, txt, jpg, jpeg, png, webp, bmp, gif, pdf.</p>           
                <p style="font-size: 15px;">Max filstorlek: 5 MB</p>
                <label>Välj filer att ladda upp (flera filer är tillåtet):</label>
                <input type="file" name="tfa_files[]" multiple />';
                if ( $current_tfa_post_id > 0 && ! empty( $file_items ) ) {
                    $out .= '<div class="file-preview">Nuvarande filer:</div>';
                    foreach ( $file_items as $item ) {
                        $out .= '<div class="file-preview">
                                    <a href="' . esc_url( wp_get_attachment_url( $item['id'] ) ) . '" target="_blank"><strong> ' . esc_html( $item['label'] ) . '</strong></a>
                                    <button type="submit" name="tfa_delete_attach_id" value="' . esc_attr( $item['id'] ) . '" class="file-delete-button" title="Ta bort bort denna fil" formnovalidate onclick="return confirm(\'Ta bort bort denna fil?\');">
                                      X
                                    </button>
                                 </div>';
                    }
                }
    $out .= '</div>
             <hr class="tfa-separator" />';

    $out .= '<div class="amount-wrap">
                <label>Belopp (använd endast siffror utan mellanslag)</label>
                <input type="number" name="tfa_amount" min="1" step="1" value="' . esc_attr( $form_amount ) . '" required />
             </div>
             <hr class="tfa-separator" />';

    $out .=   '<p>
                  <button type="submit" name="tfa_submit" class="submit-button">Spara TFA</button> 
                  <button type="submit" name="tfa_new_submit" class="submit-button" formnovalidate>Ny TFA</button>
               </p>';
    $out .= '</form>';
    
    return $out; 
} 

add_shortcode( 'tfa_form', 'shortcode_tfa_form' );  



// Handle file uploads for TFA. Updates db too.
function HandleUploadedFiles( $current_tfa_post_id, $currentUser ) {
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
        } 
        else {
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

            $fullFilePath = $moved['file'];
            $filetype = wp_check_filetype( basename( $fullFilePath ), null );

            $attachment = array(
                'post_mime_type' => $filetype['type'],
                'post_title'     => sanitize_file_name( basename( $fullFilePath ) ),
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_author'    => $currentUser->ID,
                'post_parent'    => $current_tfa_post_id,
            );

            // Insert the file attachment into the database
            $attach_id = wp_insert_attachment( $attachment, $fullFilePath );
            
            if ( is_wp_error( $attach_id ) || ! $attach_id ) {
                $messages['errors'][] = sprintf( 'Misslyckades att infoga bilaga för %s.', esc_html( $file['name'] ) );
                // remove file on disk
                @unlink( $fullFilePath );
                continue;
            }

            $attach_data = wp_generate_attachment_metadata( $attach_id, $fullFilePath );
            wp_update_attachment_metadata( $attach_id, $attach_data );

            // add reference to tfa post_meta for the file
            add_post_meta( $current_tfa_post_id, 'tfa-file', $attach_id );                    
        }  
    }
}



function shortcode_tfa_list( $atts )
{
    if ( ! is_user_logged_in() ) {
		return '';
	}

    $currentUser = wp_get_current_user();

    $tfa_posts_sent = get_posts( 
        array(
            'post_type' => 'tfa',
            'author' => $currentUser->ID,
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
        ) 
    );

    $tfa_posts_received = get_posts(array(
        'post_type'      => 'tfa',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'meta_query'     => array(
            array(
                'key'     => 'tfa-receiver',
                'value'   => $currentUser->ID,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        ),
    ));

    $out = '';

    if ( !empty( $tfa_posts_sent ) ) {
        $out .= '<div style="float: left;">Här är dina tidigare skickade TFA:er:</div>';

        $out .= '<div class="tfa-list-box">';

        foreach ( $tfa_posts_sent as $post ) {
            $tfa_amount = get_post_meta( $post->ID, 'tfa-amount', true ); 
            $tfa_receiver_id = get_post_meta( $post->ID, 'tfa-receiver', true );
            $tfa_receiver = get_userdata( $tfa_receiver_id );
            $edit_url = esc_url( add_query_arg( 'tfa_edit_id', intval( $post->ID ), remove_query_arg( array( 'tfa_reset', 'tfa_post_to_show_id' ) ) ) );
            $out .= '<div>
                        <div>Mottagare: ' . esc_html( $tfa_receiver->display_name ) . '</div>
                        <div>Titel: <a href="' . $edit_url . '" name="tfa_edit">' .  esc_html( get_the_title( $post->ID ) ) . '</a></div>
                        <div>Belopp: <strong>' . esc_html( number_format( $tfa_amount, 0, ',', ' ' ))  . ' kr</strong></div>
                     </div>';            
        } 

        $out .= '</div>';       
    } 
    else {
        $out .= '<p style="float: left;">Inga skickade TFA:er hittades.</p>';
    }



    if ( !empty( $tfa_posts_received ) ) {
        $out .= '<p style="float: left; clear: both">Här är dina mottagna TFA:er:</p>';
        
        $out .= '<div class="tfa-list-box">';
        foreach ( $tfa_posts_received as $post ) {
            $tfa_amount = get_post_meta( $post->ID, 'tfa-amount', true ); 
            $tfa_sender_id = get_post_meta( $post->ID, 'tfa-sender', true );
            $tfa_sender = get_userdata( $tfa_sender_id );
            $post_to_show_url = esc_url( add_query_arg( 'tfa_post_to_show_id', intval( $post->ID ) ) );
          
            $out .= '<div>
                        <div>Skickad av: ' . esc_html( $tfa_sender->display_name ) . '</div>
                        <div>Titel: <a href="' . $post_to_show_url . '" name="tfa_post_to_show">' . esc_html( get_the_title( $post->ID ) ) . '</a></div>
                        <div>Belopp: <strong>' . esc_html( number_format( $tfa_amount, 0, ',', ' ' ))  . ' kr</strong></div>                    
                     </div>';                     
        }
    
        $out .= '</div>';
    } 
    else {
        $out .= '<p style="float: left;">Inga mottagna TFA:er hittades.</p>';
    }


    if ( isset( $_GET['tfa_post_to_show_id'] ) ) {
        $post_to_show_id = intval( wp_unslash( $_GET['tfa_post_to_show_id'] ) );

        if ( $post_to_show_id > 0 ) {
            $post_to_show = get_post( $post_to_show_id );
            $tfa_sender_id = intval( get_post_meta( $post_to_show_id, 'tfa-sender', true ) );
            $tfa_receiver_id = intval( get_post_meta( $post_to_show_id, 'tfa-receiver', true ) );

            // Access control: only sender (author) or receiver can view details
            if ( ! $post_to_show || ( intval( $post_to_show->post_author ) !== intval( $currentUser->ID ) && $tfa_receiver_id !== intval( $currentUser->ID ) ) ) {
                return $out;
            }

            $tfa_sender = get_userdata( $tfa_sender_id );

            $out .='<div class="tfa-post-to-show-box">
                        <div>Skickad av: <strong>' . esc_html( $tfa_sender->display_name ) . '</strong></div>
                        <hr class="tfa-separator" />
                        <div>Titel: <strong>' .  esc_html( $post_to_show->post_title ) . '</strong></div>
                        <hr class="tfa-separator" />
                        <label>Lång beskrivning av affären.</label>
                        <div><strong>' . esc_html( get_post_meta( $post_to_show_id, 'tfa-description', true ) ) . '</strong></div>
                        <hr class="tfa-separator" />
                        <label>Uppladdade filer.</label>';

            $file_atts = get_post_meta( $post_to_show_id, 'tfa-file', false ); // all values
            $file_items = array();
    
            if ( !empty( $file_atts ) ) {
                // Prepare file labels uploaded files
                foreach ( $file_atts as $att_id ) {
                    $att_id = intval( $att_id );
                    if ( $att_id <= 0 ) 
                        continue;

                    $file_url = wp_get_attachment_url( $att_id );
                    if ( $file_url ) {
                        $file_items[] = array(
                            'id'    => $att_id,
                            'label' => wp_basename( $file_url ),
                        );
                    }
                }

                if ( ! empty( $file_items ) ) {
                    foreach ( $file_items as $item ) {
                        $out .= '<div class="file-preview">
                                    <a href="' . esc_url( wp_get_attachment_url( $item['id'] ) ) . '" target="_blank"><strong> ' . esc_html( $item['label'] ) . '</strong></a>                                         
                                </div>';
                    }
                }
            }

            $out .=    '<hr class="tfa-separator" />
                        <div>Belopp: <strong>' . esc_html( number_format( get_post_meta( $post_to_show_id, 'tfa-amount', true ), 0, ',', ' ' ))  . ' kr</strong></div>
                    </div>'; // Closing the tfa-post-to-show                        -box
            $out .= '<script>window.scrollTo({ top: document.documentElement.scrollHeight, behavior: "smooth" });</script>';

            echo "<script>history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/([?&])tfa_post_to_show_id=[^&]*&?/, '$1').replace(/[?&]$/, ''));</script>";
        }       
    }   



    return $out;
}

add_shortcode( 'tfa_list', 'shortcode_tfa_list' );  