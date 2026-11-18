<?php

    /**
     * Shows list of members for a given event who are either cancelled (avanmälda)
     * or registered (föranmälda) depending on querystring parameters.
     * Expected querystring: ?event_id=123&status=cancelled  OR  ?event_id=123&status=registered
     * Swedish synonyms are accepted for status: 'avan', 'foran', 'avanmalda', 'foranmalda', etc.
     */

    function shortcode_applications( $atts ) {
        // Only allow administrators to view the signups list
        if ( ! current_user_can( 'manage_options' ) ) {
            return '<div style="color: green;"><strong>Du måste vara administratör för att se anmälningar.</strong></div>';
        }

        $event_id = isset( $_GET['event_id'] ) ? intval( wp_unslash( $_GET['event_id'] ) ) : 0;
        $status_raw = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
        $status = strtolower( trim( $status_raw ) );

        // normalize Swedish/English synonyms
        $cancel_values = array( 'cancelled', 'cancel', 'avan', 'avanmalda', 'avanmald', 'avanmälda', 'avanmäld' );
        $reg_values = array( 'registered', 'reg', 'foran', 'foranmalda', 'foranmald', 'föranmälda', 'föranmäld' );

        if ( in_array( $status, $cancel_values, true ) ) {
            $whichType = 'cancelled';
        } 
        else if ( in_array( $status, $reg_values, true ) ) {
            $whichType = 'registered';
        } 
        else {
            return '<div>Ogiltigt statusvärde.</div>';
        }

        if ( $event_id <= 0 ) {
            return '<div>Event-id saknas eller ogiltigt.</div>';
        }

        $post = get_post( $event_id );
        if ( ! $post || $post->post_type !== 'event' ) {
            return '<div>Eventet hittades inte.</div>';
        }

        if ( $whichType === 'cancelled' ) {
            $user_ids = get_post_meta( $event_id, 'event_cancellations', true );
            $heading = 'Avanmälningar';
        } else {
            $user_ids = get_post_meta( $event_id, 'event_registrations', true );
            $heading = 'Föranmälningar';
        }

        if ( ! is_array( $user_ids ) ) {
            $user_ids = array();
        }
        $date = get_post_meta( $event_id, 'event_date', true );

        $out = '<div class="applications-list">';

        if ( !empty( $user_ids ) ) {
            $out .= '<div>';        
            $out .= '   <h4 style="margin-bottom: 5px;">' . 
                            esc_html( $heading ) . ' för ' . esc_html( get_the_title( $event_id ) ) . ' den ' . esc_html( date_i18n( 'j F Y', strtotime( $date ) ) ) . 
                        '</h4>';
            
            foreach ( $user_ids as $uid ) {
                $user = get_userdata( intval( $uid ) );
                if ( ! $user ) 
                    continue;

                $out .= '<div class="member-info">' . esc_html($user->display_name) . '&nbsp; - &nbsp;' . esc_html(get_user_meta( $user->ID, 'company', true )) . '</div>';
            }
            $out .= '</div>'; 
        }
        else {
            $out .= '<div>'; 
                if ( $whichType === 'cancelled' ) { 
                    $out .= '<h4><strong>Inga avanmälda medlemmar hittades.</strong></h4>';
                } else {
                    $out .= '<h4><strong>Inga föranmälda medlemmar hittades.</strong></h4>';
                }
            $out .= '</div>';
        } 
        
        $invited_guests = get_posts(array(
            'post_type'  => 'guest',
            'post_status' => 'publish',
            'post_parent' => $event_id,   // exact title match (WP support varies by version)
        ));

        $membership_guests = array();        

        if ( ! empty( $invited_guests ) ) {
            // Flytta membership-guests till $membership_guests array.
            for ( $i = 0; $i < count($invited_guests); $i++ ) {
                $guest = $invited_guests[$i];
                $is_membership_guest = get_post_meta($guest->ID, 'is_membership_guest', true);
                if ($is_membership_guest == "Yes") {                   
                    array_splice($invited_guests, $i, 1);
                    array_push($membership_guests, $guest);
                    $i--; // Adjust index after removal to avoid skipping the next element
                }
            }

            if ( ! empty( $invited_guests ) ) {
                $out .= '<div class="invited-guests">';
                $out .=     '<h4 style="margin-bottom: 5px;">Inbjudna gäster</h4>';
                foreach ( $invited_guests as $guest ) {
                    $guest_company = get_post_meta($guest->ID, 'guest_company', true);
                    $guest_author_name = get_the_author_meta( 'display_name', $guest->post_author );
                    $out .= '<div class="invited-guest">' . esc_html($guest_author_name) . '&nbsp; - &nbsp;' . esc_html( $guest->post_content ) . '&nbsp; - &nbsp;' . esc_html( $guest_company ) . '</div>';
                }
            }           

            if ( ! empty( $membership_guests ) ) {
                $out .= '<div class="invited-guests">';
                $out .=     '<h4 style="margin-bottom: 5px;">Gäster som sökt medlemskap</h4>';
                foreach ( $membership_guests as $guest ) {
                
                    $guest_company = get_post_meta($guest->ID, 'guest_company', true);
                    $guest_author_name = get_the_author_meta( 'display_name', $guest->post_author );
                    $out .= '<div class="invited-guest">' . esc_html($guest_author_name) . '&nbsp; - &nbsp;' . esc_html( $guest->post_content ) . '&nbsp; - &nbsp;' . esc_html( $guest_company ) . '</div>';
                }
            }
      
            $out .= '</div>'; // invited-guests
        }

        if ( empty( $invited_guests ) ) {
            $out .= '<div class="invited-guests">
                        <h4 class="no-invited-guests">Inga inbjudna gäster hittades.</h4>
                     </div>';
        }

        if ( empty( $membership_guests ) ) { 
            $out .= '<div class="invited-guests">    
                        <h4 class="no-invited-guests">Inga gäster som sökt medlemskap hittades.</h4>
                     </div>';
        }
       



        $out .= '</div>'; // applications-list

        return $out;
    }
    add_shortcode( 'applications', 'shortcode_applications' );