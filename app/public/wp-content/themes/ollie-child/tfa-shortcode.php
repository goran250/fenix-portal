<?php

function shortcode_tfa_select_members( $atts )
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
            $tfa_sender_id = isset( $_POST['tfa_sender'] ) ? intval( wp_unslash( $_POST['tfa_sender'] ) ) : 0;
            $tfa_amount = isset( $_POST['tfa_amount'] ) ? floatval( wp_unslash( $_POST['tfa_amount'] ) ) : 0;
            $tfa_description = isset( $_POST['tfa_description'] ) ? wp_unslash( $_POST['tfa_description'] ) : '';

            if ( $tfa_sender_id <= 0 || ! get_user_by( 'id', $tfa_sender_id ) ) {
                $messages['errors'][] = 'Välj en giltig medlem.';
            }

            if ( $tfa_amount <= 0 ) {
                $messages['errors'][] = 'Ange ett giltigt belopp som är större än 0.';
            }

            if ( empty( $messages['errors'] ) ) {
                if ( $current_tfa_post_id > 0 ) {
                    $existing_post = get_post( $current_tfa_post_id );
                    if ( ! $existing_post || intval( $existing_post->post_author ) !== $currentUser->ID ) {
                        $messages['errors'][] = 'Kunde inte uppdatera TFA-posten.';
                        $post_id = 0;
                    } else {
                        $post_id = wp_update_post(
                            array(
                                'ID' => $current_tfa_post_id,
                                'post_title' => 'TFA',
                            ),
                            true
                        );
                    }
                } else {
                    $post_id = wp_insert_post(
                        array(
                            'post_title'  => 'TFA',
                            'post_type'   => 'post',
                            'post_status' => 'publish',
                            'post_author' => $currentUser->ID,
                        ),
                        true
                    );
                }

                if ( is_wp_error( $post_id ) ) {
                    $messages['errors'][] = $post_id->get_error_message();
                } elseif ( $post_id > 0 ) {
                    update_post_meta( $post_id, 'tfa-reciever', $currentUser->ID );
                    update_post_meta( $post_id, 'tfa-sender', $tfa_sender_id );
                    update_post_meta( $post_id, 'tfa-amount', $tfa_amount );
                    update_post_meta( $post_id, 'tfa-description', $tfa_description );
                    $current_tfa_post_id = intval( $post_id );
                    $messages['success'] = 'TFA-informationen har sparats.';
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

    $out .= '<form method="post" class="tfa-form">';
    $out .= wp_nonce_field( 'tfa_action', 'tfa_nonce', true, false );
    $out .= '<input type="hidden" name="tfa_post_id" value="' . esc_attr( $current_tfa_post_id ) . '" />';

    $out .=  '<div class="to-member-wrap">
                 <p style="margin-top: 0;">Ditt namn och företag!</p>
                 <p><strong>' . esc_html( $currentUser->display_name . ' - ' . $current_company ) . '</strong></p>
              </div>
              <hr class="tfa-separator" />';

    $out .= '<div class="from-member-wrap"> 
               <label>Vem har du fått affären från?</label>               
               <select name="tfa_sender" required>';
                  $posted_sender = $reset_form ? 0 : ( isset( $_POST['tfa_sender'] ) ? intval( wp_unslash( $_POST['tfa_sender'] ) ) : 0 );
                  $out .= '<option value="">Välj medlem</option>';
                  foreach ( $users as $u ) {       
                     $sender_company = get_user_meta( $u->ID, 'company', true );
                     $selected = ( $posted_sender === intval( $u->ID ) ) ? ' selected' : '';
                     $out .= '<option value="' . esc_attr( $u->ID ) . '"' . $selected . '>' . esc_html( $u->display_name . ' - ' . $sender_company ) . '</option>';
                  }
	$out .=    '</select>
            </div>
            <hr class="tfa-separator" />';

    $out .= '<div class="description-wrap"> 
                <label>Beskrivning av affären</label>
                <textarea name="tfa_description" rows="4" required>' . esc_html( $reset_form ? '' : ( isset( $_POST['tfa_description'] ) ? wp_unslash( $_POST['tfa_description'] ) : '' ) ) . '</textarea>
             </div>
             <hr class="tfa-separator" />';

    $out .= '<div class="file-upload-wrap"> 
                <p>Här kan du ladda upp filer relaterade till affären.</p>
                <label>Välj filer att ladda upp (flera filer är tillåtet):</label>
                <input type="file" name="tfa_files[]" multiple />
             </div>
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

add_shortcode( 'tfa_select_members', 'shortcode_tfa_select_members' );  