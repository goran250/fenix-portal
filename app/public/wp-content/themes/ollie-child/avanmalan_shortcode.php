<?php

function shortcode_avanmalan( $atts )
{
    if ( ! is_user_logged_in() ) {
		$out = '<div style="color: green;"><strong>Du måste vara inloggad för att kunna avanmäla dig.</strong></div>';
		return $out;
	}
    else {

        $messages = array( 'errors' => array(), 'success' => '');

        $event_id = intval( $_GET['event_id'] );
		$event = get_post( $event_id );
        $event_date = get_post_meta( $event_id, 'event_date', true );
        $out = '<div ">Vill du avanmäla dig från eventet <strong>' . esc_html( $event->post_title ) . '</strong> den ' . esc_html( date_i18n( 'j F Y', strtotime( $event_date ) )  ) . '?</div>';        
    
        if (  isset( $_POST['avanmalan_submit'] ) ) {

            // verify nonce to avoid CSRF
            if ( ! isset( $_POST['avanmalan_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['avanmalan_nonce'] ), 'avanmalning_action' ) ) {
                $messages['errors'][] = 'Ogiltig formulärförfrågan.';
            } 
          
            $user_id = get_current_user_id();
        
            // store cancellations as an array of user IDs in post meta 'event_cancellations'
            $cancellations = get_post_meta( $event_id, 'event_cancellations', true );
            if ( ! is_array( $cancellations ) ) {
                $cancellations = array();
            }

            if ( ! in_array( $user_id, $cancellations, true ) ) {
                $cancellations[] = $user_id;
                update_post_meta( $event_id, 'event_cancellations', $cancellations );
            }

            // optionally also record on user meta for quick lookup
            $user_cancelled = get_user_meta( $user_id, 'cancelled_events', true );
            if ( ! is_array( $user_cancelled ) ) {
                $user_cancelled = array();
            }

            if ( ! in_array( $event_id, $user_cancelled, true ) ) {
                $user_cancelled[] = $event_id;
                update_user_meta( $user_id, 'cancelled_events', $user_cancelled );
            }

            // store absence reason in wp_posts         
            $absence_reason = sanitize_text_field( $_POST['absence_reason'] );
            wp_insert_post(
                array(
                    'post_title'  => $absence_reason,
                    'post_content' => "",
                    'post_type'   => 'absence_reason',
                    'post_status' => 'publish',
                    'post_author' => $currentUser->ID,
                    'post_parent' => $event_id,
                ),
                true
            );
         
            // Insert replacer information in wp_posts
            if ( isset( $_POST['replacer_name'] ) && !isset( $_POST['replacer_email'] ) || !isset( $_POST['replacer_name'] ) && isset( $_POST['replacer_email'] ) ) {
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
        $out .= '<form method="post" class="avanmalan-form">';
        $out .=     wp_nonce_field( 'avanmalning_action', 'avanmalan_nonce', true, false );
        $out .=    '<div> 
                        <label>Orsak till frånvaro?</label>               
                        <div style="padding: 10px 0 20px;">
                            <input id="absence_reason_F" type="radio" name="absence_reason" value="F" />
                            <label for="absence_reason_F" style="display: inline;  margin-right: 20px;">Vill inte ange!</label>                        
                            <input id="absence_reason_FL" type="radio" name="absence_reason" value="FL" checked/>
                            <label for="absence_reason_FL" style="display: inline;">Föräldraledighet</label>
                        </div>
                    </div>
                    <hr class="separator" />'; 

        $out .=     '<div style="padding: 10px 0 20px;"> 
                        <label>Kommer någon att ersätta dig?</label>               
                        <input type="text" name="replacer_name" required placeholder="Ange namnet på ersättaren" value="">
                    </div>';


        $out .=     '<div style="padding: 10px 0 20px;"> 
                        <label>Ersättaren epostadress?</label>               
                        <input type="text" name="replacer_email" required placeholder="Ange ersättarens epostadress." value="">
                    </div>
                    <hr class="separator" />';
            
        $out .=     '<p>
                        <button type="submit" name="avanmalan_submit" class="submit-button">Spara</button>                                       
                    </p>
                </form>';

        return $out;
    }
}
add_shortcode( 'avanmalan', 'shortcode_avanmalan' );


/**
 * Handle Avanmalning (user cancellation) requests.
 
    */