<?php

function shortcode_register_guest( $atts )
{
    if ( ! is_user_logged_in() ) {
		$out = '<div style="color: green;"><strong>Du måste vara inloggad för att kunna anmäla gäster.</strong></div>';
		return $out;
	}

    $currentUser = wp_get_current_user();
   
    $event_id = isset( $_REQUEST['event_id'] ) ? intval( wp_unslash( $_REQUEST['event_id'] ) ) : 0;

    $event_name = get_the_title( $event_id );
    $event_notification = get_post_meta( $event_id, 'event_notification', true );
    $event_date = get_post_meta( $event_id, 'event_date', true );
    $event_location = get_post_meta( $event_id, 'event_location', true );

    $guests = get_post_meta( $event_id, 'event_guests', true );
    
    if ( ! is_array( $guests ) ) {
        $guests = array();
    }

    $messages = array( 'errors' => array(), 'errors_delete_guest' => array(), 'success' => '', 'success_delete_guest' => '' );

    $guest_id = isset($_POST['guest_id']) ? intval(wp_unslash($_POST['guest_id'])) : 0;


    if ( isset( $_POST['register_new_guest_submit'] ) ) {
        if ( ! isset( $_POST['register_guest_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['register_guest_nonce'] ), 'register_guest_action' ) ) {
            $messages['errors'][] = 'Ogiltig formulärförfrågan.';
             $out = '<div style="color: red;"><strong>' . 'Ogiltig formulärförfrågan.</strong></div>';
            return $out;
        } 
        else {
            // Reset form state and clear any previous guest data.
            unset($_POST['guest_name'], $_POST['guest_company'], $_POST['guest_email'], $_POST['guest_phone'], $_GET['edit_guest_id'] );   
           
            $guest_id = 0;
            $messages['success'] = 'Formuläret är återställt. Du kan nu anmäla en ny gäst.';
            $redirect_url = remove_query_arg( array( 'edit_guest_id' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }


    // Edit mode via query parameter: ? tfa_edit_id = post_id>
    if ( isset( $_GET['edit_guest_id'] ) ) {
        $edit_guest_id = intval( wp_unslash( $_GET['edit_guest_id'] ) );

        if ( $edit_guest_id > 0 ) {
            $edit_guest_post = get_post( $edit_guest_id );
            if ( $edit_guest_post && ((intval( $edit_guest_post->post_author ) === intval( $currentUser->ID )) ||  current_user_can('manage_options')) ) {
                $guest_id = $edit_guest_id;
            }
        }
    }


    if ( isset( $_POST['register_guest_submit'] )) {
       
        if ( ! isset( $_POST['register_guest_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['register_guest_nonce'] ), 'register_guest_action' ) ) {
            $messages['errors'][] = 'Ogiltig formulärförfrågan.';
        } 
        else {
            $guest_name = isset( $_POST['guest_name'] ) ? wp_unslash( $_POST['guest_name'] ) : '';
            $guest_company = isset( $_POST['guest_company'] ) ? wp_unslash( $_POST['guest_company'] ) : '';
            $guest_email = strtolower( sanitize_email( wp_unslash( $_POST['guest_email'] ?? '' ) ) );
            $guest_phone = isset( $_POST['guest_phone'] ) ? wp_unslash( $_POST['guest_phone'] ) : '';           

            if ( empty( $guest_name )) {
                $messages['errors'][] = 'Ange ett namn för gästen!';
            }

            if ( empty( $guest_company )) {
                $messages['errors'][] = 'Ange företaget som gästen representerar!';
            }

            if ( empty( $guest_email )) {
                $out .= 'Ingen epostadress angiven';
                $messages['errors'][] = 'Ange en giltig epostadress för gästen!';
            }

            // Check if the email is already registered for this event
            if ( !empty( $guest_email ) && !isset( $_GET['edit_guest_id'] ) ) {
                $guests = get_posts(array(
                    'post_type'  => 'guest',
                    'post_status' => 'publish',
                    'post_parent' => $event_id, 
                    'title' => $guest_email,
                    'fields' => 'ids' 
                ));

                if (count($guests) > 0) {
                    $messages['errors'][] = 'En gäst med denna epostadress är redan registrerad för detta event.';
                }
            }
    
             // ensure post exists and is an event
            $event_post = get_post( $event_id );
            if ( ! $event_post || $event_post->post_type !== 'event' ) {
                wp_safe_redirect( site_url( '/foranmalan/?status=invalid' ) );
                exit;
            }

            // insert or update a guest post
            if ( empty( $messages['errors'] ) ) {

                $guest = get_posts(array(
                    'post_type'      => 'guest',
                    'post_status'    => 'publish',
                    'title'          => $guest_email,   // exact title match (WP support varies by version)
                    'posts_per_page' => 1,
                    'post_parent'   => $event_id,
                ));
                
                if (count($guest) == 0  ) {
                    $guest_id = wp_insert_post(
                        array(
                            'post_title'  => $guest_email,
                            'post_content' => $guest_name,
                            'post_type'   => 'guest',
                            'post_status' => 'publish',
                            'post_author' => $currentUser->ID,
                            'post_parent' => $event_id,
                        ),
                        true
                    );
                }
                else {
                    $guest_id = wp_update_post(
                        array(
                            'ID'         => $guest[0]->ID,
                            'post_content' => $guest_name,
                            'post_type'   => 'guest',
                            'post_status' => 'publish',
                        ),
                        true
                    );
                }
        
                // insert or update guest post_meta
                if ( empty( $messages['errors'] ) ) {
                    if ( !is_wp_error( $guest_id ) && $guest_id > 0) {
                        // update_post_meta handles both inserting and updating post meta values
                        update_post_meta( $guest_id, 'guest_company', $guest_company );
                        update_post_meta( $guest_id, 'guest_phone', $guest_phone );
                        $messages['success'] = '<strong>Gästen har sparats.</strong><br/><span style="font-size: 14px;">Formuläret är återställt och du kan anmäla en ny gäst.</span>';
                    } 
                    else if ( is_wp_error( $guest_id ) ) {
                        $messages['errors'][] = $guest_id->get_error_message();
                    }
                }

                // store guests as an array of guest IDs in post meta 'event_guests'
                $event_guests = get_post_meta( $event_id, 'event_guests', true );
                if ( !is_array( $event_guests ) ) {
                    $event_guests = array();
                }

                if ( ! in_array( $guest_id, $event_guests, true ) ) {
                    $event_guests[] = $guest_id;
                    update_post_meta( $event_id, 'event_guests', $event_guests );                   
                }
            }
        } 

        if ( isset( $_GET['edit_guest_id'] ) ) {
            unset($_GET['edit_guest_id']);
        }
    }


    // Delete a guest.
    if ( isset( $_POST['delete_guest'] ) ) {
        if ( ! isset( $_POST['delete_guest_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['delete_guest_nonce'] ), 'delete_guest_action' ) ) {
            $messages['errors'][] = 'Ogiltig formulärförfrågan.';
        } else {
            $guest_id = intval( wp_unslash( $_POST['delete_guest'] ) );   
            $event_id = isset($_POST['event_id']) ? intval( wp_unslash($_POST['event_id']) ) : 0;           
    
            if ( $guest_id > 0) {
                $guest_post = get_post($guest_id );
                              
                if ( $guest_post && ((intval( $guest_post->post_author) === intval( $currentUser->ID )) || current_user_can('manage_options') ) ) {
                    $deleted = wp_delete_post( $guest_id, true );
                    $event_guests = get_post_meta( $event_id, 'event_guests', true );
                    $event_guests = is_array( $event_guests ) ? array_map( 'intval', $event_guests ) : array();

                    // remove deleted guest id from the event_guests array
                    $event_guests = array_values(
                        array_filter( $event_guests, function( $id ) use ( $guest_id ) {
                            return intval($id) !== $guest_id;
                        })
                    );

                    update_post_meta( $event_id, 'event_guests', $event_guests );

                    if ( $deleted ) {
                        $messages['success_delete_guest'] = 'Gästen är borttagen.';
                    } 
                    else {
                        $messages['errors'][] = 'Gästen kunde inte tas bort.';
                    }
                }
            }
        }
    }



    // Handle output to screen  

    $out = '';    

    $guest_name = '';
    $guest_company = '';
    $guest_email = '';
    $guest_phone = '';  

    // Load a guest for editing
    if ( $guest_id > 0 && !isset( $_POST['register_guest_submit']) && isset( $_GET['edit_guest_id'] ) ) {  
        $guest = get_post( $guest_id );
        $guest_name = $guest->post_content;
        $guest_company = get_post_meta( $guest_id, 'guest_company', true );
        $guest_email =  $guest->post_title;
        $guest_phone = get_post_meta( $guest_id, 'guest_phone', true );
    }

    $out .= '<div class="register-guest-text">
                Här kan du anmäla gäster till eventet <strong>' . esc_html( $event_name ) . '</strong> den ' . 
                esc_html( date_i18n( 'j F Y', strtotime( $event_date ) )  ) . ' på ' . esc_html( $event_location ) . '.
             </div>';
            

    $out .= '<form method="post" action="" class="register-guest-form">';
    $out .= wp_nonce_field( 'register_guest_action', 'register_guest_nonce', true, false );
    $out .= '<input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '" />';
    
    if(current_user_can('manage_options') && isset( $_GET['edit_guest_id'] ) ) {
        $author_name = get_userdata($guest->post_author)->display_name;
        $author_company = get_userdata($guest->post_author)->company;
        $out .= '<div>
                    <p style="margin-top: 0;">Anmälarens namn och företag!</p>
                    <p><strong>' . esc_html( $author_name . ' - ' . $author_company ) . '</strong></p>
                </div>
                <hr class="separator" />';
    }
    else {
         $out .= '<div>
                    <p style="margin-top: 0;">Anmälarens namn och företag!</p>
                    <p><strong>' . esc_html( $currentUser->display_name . ' - ' . $currentUser->company ) . '</strong></p>
                </div>
                <hr class="separator" />';
    }

    $out .= '<div> 
                <label>Vem vill du anmäla som gäst?</label>               
                <input type="text" name="guest_name" required placeholder="Namnet på gästen" value="' . esc_attr($guest_name) . '">
             </div>
             <hr class="separator" />';

    $out .= '<div> 
                <label>Företaget som gästen representerar?</label>               
                <input type="text" name="guest_company" required placeholder="Namnet på företaget" value="' . esc_attr($guest_company) . '">
             </div>
             <hr class="separator" />'; 

    $out .= '<div> 
                <label>Gästens epostadress?</label>';
                if ( isset($_GET['edit_guest_id']) ) {
                   $out .= '<input type="email" name="guest_email" required placeholder="Ange gästens epostadress" value="' . esc_attr($guest_email) . '" readonly >';
                }
                else {
                   $out .= '<input type="email" name="guest_email" required placeholder="Ange gästens epostadress" value="' . esc_attr($guest_email) . '">';
                }
    $out .= '</div>
             <hr class="separator" />'; 
    
    $out .= '<div> 
                <label>Gästens telefonnr?</label>               
                <input type="text" name="guest_phone" placeholder="Ange gästens telefonnr" value="' . esc_attr($guest_phone) . '">
             </div>
             <hr class="separator" />';

    $out .= '<p>
                <button type="submit" name="register_guest_submit" class="submit-button">Spara</button>                 
                    <button type="submit" name="register_new_guest_submit" class="submit-button" formnovalidate data-tooltip="Nollställ formuläret och lägg till fler gäster">Lägg till fler gäster</button>
                </span>
             </p>';
            
    $out .= '</form>';


    if ( ! empty( $messages['errors'] ) ) {
        $out .= '<div class="register-guest-errors"><ul>';
        
        foreach ( $messages['errors'] as $errors ) {
            $out .= '<li>' . esc_html( $errors ) . '</li>';
        }
        
        $out .= '</ul></div>';
    }

    if ( $messages['success'] ) {
        $out .= '<div class="register-guest-success">' .wp_kses_post(  $messages['success'] ). '</div>';
    }

    if ( isset( $_POST['register_guest_submit'] ) ) {
        $out .= '<script>window.scrollTo({ top: document.documentElement.scrollHeight, behavior: "smooth" });</script>';
    }

    /*** Hämtar inbjudna gäster för evenemanget. Om current user är administratör hämtas alla gäster, annars bara de gäster som skapats av current user ***/
    $invited_guests = array();
   
    if ( current_user_can( 'manage_options' ) ) {   // Kontrollera om curentUser är administratör
        $invited_guests = get_posts(array(
            'post_type'  => 'guest',
            'post_status' => 'publish',
            'post_parent' => $event_id,   // exact title match (WP support varies by version)
        ));   

        $invited_guests_text = 'Alla registrerade gäster för detta event';
    }
    else {
        $invited_guests = get_posts(array(
                        'post_type'  => 'guest',
                        'post_status' => 'publish',
                        'post_parent' => $event_id,  
                        'author' => $currentUser->ID,
        ));

        $invited_guests_text = 'Mina registrerade gäster för detta event';
    }


    $out .= '<form method="post" action="" class="my-guest-form">';
    $out .= wp_nonce_field( 'delete_guest_action', 'delete_guest_nonce', true, false );
    $out .= '<input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '" />';
    $out .=    '<p style="clear: both;"><strong>' . esc_html( $invited_guests_text ) . '</strong></p>';
    
    if ( !empty($invited_guests) ) {
        foreach ( $invited_guests as $guest ) {
            $guest_company = get_post_meta($guest->ID, 'guest_company', true);
            if ( current_user_can( 'manage_options' ) ) {
                $edit_url = esc_url( add_query_arg( 'edit_guest_id', intval( $guest->ID )) );
                $author_name = get_userdata($guest->post_author)->display_name;
                $out .= '<div class="guest-div">
                            <a href="' . $edit_url . '" name="edit_guest" data-tooltip="Ändra denna gäst">' . esc_html($author_name) . ' - ' . esc_html( $guest->post_content ) . ' - ' . esc_html( $guest_company ) . '</a>' .
                            '<button type="submit" name="delete_guest" value="' . esc_attr( $guest->ID ) . '" class="guest-delete-button" data-tooltip="Ta bort bort denna gäst" formnovalidate onclick="return confirm(\'Vill du ta bort bort denna gäst?\');">' .
                                'X' .
                            '</button>' .
                        '</div>';
            }
            else{
                $edit_url = esc_url( add_query_arg( 'edit_guest_id', intval( $guest->ID )) );
                $out .= '<div class="guest-div">
                            <a href="' . $edit_url . '" name="edit_guest" data-tooltip="Ändra denna gäst">' . esc_html( $guest->post_content ) . ' - ' . esc_html( $guest_company ) . '</a>' .
                            '<button type="submit" name="delete_guest" value="' . esc_attr( $guest->ID ) . '" class="guest-delete-button" data-tooltip="Ta bort bort denna gäst" formnovalidate onclick="return confirm(\'Vill du ta bort bort denna gäst?\');">' .
                                'X' .
                            '</button>' .
                        '</div>';
            }
        } 
    } 
    else {
        $out .= 'Inga gäster registrerade för detta event.';
    }

    if ( $messages['success_delete_guest'] ) {
        $out .= '<div class="register-guest-success">' . esc_html( $messages['success_delete_guest'] ) . '</div>';
    }    
    
    if ( !empty($messages['errors_delete_guest']) ) {
        $out .= '<div class="register-guest-errors"><ul>';
        
        foreach ( $messages['errors_delete_guest'] as $error ) {
            $out .= '<li>' . esc_html( $error ) . '</li>';
        }
            
        $out .= '</ul></div>';
    }
    if ( isset( $_POST['delete_guest'] ) ) {
        $out .= '<script>window.scrollTo({ top: document.documentElement.scrollHeight, behavior: "smooth" });</script>';
    }

    $out .= '</form>';

    return $out;
} 
add_shortcode( 'register_guest', 'shortcode_register_guest' );  