<?php

function shortcode_foranmalan( $atts )
{
    if ( ! is_user_logged_in() ) {
		$out = '<div style="color: green;"><strong>Du måste vara inloggad för att kunna anmäla dig.</strong></div>';
		return $out;
	}
    else {

        $messages = array( 'errors' => array(), 'success' => '');

        $event_id = intval( $_GET['event_id'] );
		$event = get_post( $event_id );
        $event_date = get_post_meta( $event_id, 'event_date', true );
        $out = '<div ">Vill du anmäla dig till eventet <strong>' . esc_html( $event->post_title ) . '</strong> den ' . esc_html( date_i18n( 'j F Y', strtotime( $event_date ) )  ) . '?</div>';        
    
        if (  isset( $_POST['foranmalan_submit'] ) ) {

            // verify nonce to avoid CSRF
            if ( ! isset( $_POST['foranmalan_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['foranmalan_nonce'] ), 'foranmalning_action' ) ) {
                $messages['errors'][] = 'Ogiltig formulärförfrågan.';
            } 
          
            $user_id = get_current_user_id();
        
            // store cancellations as an array of user IDs in post meta 'event_cancellations'
            $registrations = get_post_meta( $event_id, 'event_registrations', true );
            if ( ! is_array( $registrations ) ) {
                $registrations = array();
            }

            if ( ! in_array( $user_id, $registrations, true ) ) {
                $registrations[] = $user_id;
                update_post_meta( $event_id, 'event_registrations', $registrations );
            }

            // optionally also record on user meta for quick lookup
            $user_registered = get_user_meta( $user_id, 'registered_events', true );
            if ( ! is_array( $user_registered ) ) {
                $user_registered = array();
            }

            if ( ! in_array( $event_id, $user_registered, true ) ) {
                $user_registered[] = $event_id;
                update_user_meta( $user_id, 'registered_events', $user_registered );
            }

          
            // Insert replacer information in wp_posts
            if ( (isset( $_POST['replacer_name'] ) && !isset( $_POST['replacer_email'] )) || (!isset( $_POST['replacer_name'] ) && isset( $_POST['replacer_email'] )) ) {
                $messages['errors'][] = 'Om du vill registrera en ersättare måste både namn och epostadress anges.';
            }
            else if ( isset( $_POST['replacer_name'] ) && isset( $_POST['replacer_email'] ) ) {
                $replacer_name  = sanitize_text_field( $_POST['replacer_name'] );
                $replacer_email = sanitize_email( $_POST['replacer_email'] );

                wp_insert_post(
                    array(
                        'post_title'  => $replacer_email,
                        'post_content' => $replacer_name,
                        'post_type'   => 'replacer',
                        'post_status' => 'publish',
                        'post_author' => $currentUser->ID,
                        'post_parent' => $event_id,
                    ),
                    true
                );               
            }

            if ( empty( $messages['errors'] ) ) {
                wp_safe_redirect( "/events/" );
                exit;
            }
            else {
                $out .= '<div class="register-guest-errors"><ul>';
    
                foreach ( $messages['errors'] as $errors ) {
                    $out .= '<li>' . esc_html( $errors ) . '</li>';
                }
                
                $out .= '</ul></div>';
            }
        }

        // handle output for the form
        $out .= '<form method="post" class="foranmalan-form">';
        $out .=     wp_nonce_field( 'foranmalning_action', 'foranmalan_nonce', true, false );

        $out .=     '<div style="padding: 10px 0 20px;"> 
                        <label>Kommer någon att ersätta dig?</label>               
                        <input type="text" name="replacer_name" placeholder="Ange namnet på ersättaren" value="">
                    </div>';


        $out .=     '<div style="padding: 10px 0 20px;"> 
                        <label>Ersättaren epostadress?</label>               
                        <input type="text" name="replacer_email" placeholder="Ange ersättarens epostadress." value="">
                    </div>
                    <hr class="separator" />';
            
        $out .=     '<p>
                        <button type="submit" name="foranmalan_submit" class="submit-button">Spara</button>                                       
                    </p>
                </form>';

        return $out;
    }
}
add_shortcode( 'foranmalan', 'shortcode_foranmalan' );
