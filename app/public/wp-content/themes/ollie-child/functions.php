<?php

add_action( 'wp_enqueue_scripts', 'ollie_child_enqueue_styles' );

/**
 * Enqueue Ollie child theme styles and scripts.
 *
 * @return void
 */
function ollie_child_enqueue_styles(): void {
	wp_enqueue_style( 'ollie-child-style', get_stylesheet_uri(), array( 'ollie' ), wp_get_theme()->get( 'Version' ) );

	// Responsive style – laddas sist så den vinner i specificitet
	wp_enqueue_style( 'ollie-child-responsive-style', get_stylesheet_directory_uri() . '/responsive-style.css', array( 'ollie-child-style' ), wp_get_theme()->get( 'Version' ) );

	// Header scroll effect
	wp_enqueue_script( 'header-scroll', get_stylesheet_directory_uri() . '/header-scroll.js', array(), wp_get_theme()->get( 'Version' ), true );
}

/**
 * Handle Avanmalning (user cancellation) requests.
 * Accepts POST or GET with event_id and records the current user as cancelled for that event.
 * Accessible via admin-post.php?action=avanmalning
 */
function avanmalning_handler() {
    // verify nonce to avoid CSRF
    if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'avanmalning_action' ) ) {
        wp_safe_redirect( site_url( '/avanmalan/?status=nonce' ) );
        exit;
    }

    // require logged in user
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( site_url( '/avanmalan/' ) ) );
        exit;
    }

    $event_id = 0;
    if ( isset( $_REQUEST['event_id'] ) ) {
        $event_id = intval( wp_unslash( $_REQUEST['event_id'] ) );
    }

    if ( $event_id <= 0 ) {
        wp_safe_redirect( site_url( '/avanmalan/' ) );
        exit;
    }

    $user_id = get_current_user_id();

    // ensure post exists and is an event
    $post = get_post( $event_id );
    if ( ! $post || $post->post_type !== 'event' ) {
        wp_safe_redirect( site_url( '/avanmalan/?status=invalid' ) );
        exit;
    }

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

    // set a short-lived transient to indicate success for this user+event (one-time display)
    if ( $user_id && $event_id ) {
        set_transient( 'events_success_avan_' . intval( $user_id ) . '_' . intval( $event_id ), 1, 30 );
    }

    // Redirect back to referring page (clean URL). Transient contains one-time success flag.
    $redirect = wp_get_referer();
    if ( ! $redirect ) {
        $redirect = site_url( '/avanmalan/' );
    }
    // remove any success/query parameters that may have been present in the referrer
    $parts = wp_parse_url( $redirect );
    $clean_url = $redirect;
    if ( ! empty( $parts['query'] ) ) {
        wp_parse_str( $parts['query'], $q );
        // keys to remove
        $remove_keys = array( 'avanmald', 'foranmald', 'event_id', 'delete_event', '_ev_nonce', '_wpnonce' );
        foreach ( $remove_keys as $k ) {
            if ( isset( $q[ $k ] ) ) {
                unset( $q[ $k ] );
            }
        }
        $clean_query = ! empty( $q ) ? '?' . build_query( $q ) : '';
        $clean_url = $parts['scheme'] . '://' . $parts['host'] . ( ! empty( $parts['path'] ) ? $parts['path'] : '' ) . $clean_query;
        if ( ! empty( $parts['port'] ) ) {
            $clean_url = $parts['scheme'] . '://' . $parts['host'] . ':' . $parts['port'] . ( ! empty( $parts['path'] ) ? $parts['path'] : '' ) . $clean_query;
        }
    }
    wp_safe_redirect( $clean_url );

    exit;
}
add_action( 'admin_post_avanmalning', 'avanmalning_handler' );
add_action( 'admin_post_nopriv_avanmalning', 'avanmalning_handler' );

/**
 * Handle Foranmalning (user registration) requests.
 * Accepts POST or GET with event_id and records the current user as registered for that event.
 * Accessible via admin-post.php?action=foranmalning
 */
function foranmalning_handler() {
    // verify nonce to avoid CSRF
    if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'foranmalning_action' ) ) {
        wp_safe_redirect( site_url( '/foranmalan/?status=nonce' ) );
        exit;
    }

    // require logged in user
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( site_url( '/foranmalan/' ) ) );
        exit;
    }

    $event_id = 0;
    if ( isset( $_REQUEST['event_id'] ) ) {
        $event_id = intval( wp_unslash( $_REQUEST['event_id'] ) );
    }

    if ( $event_id <= 0 ) {
        wp_safe_redirect( site_url( '/foranmalan/' ) );
        exit;
    }

    $user_id = get_current_user_id();

    // ensure post exists and is an event
    $post = get_post( $event_id );
    if ( ! $post || $post->post_type !== 'event' ) {
        wp_safe_redirect( site_url( '/foranmalan/?status=invalid' ) );
        exit;
    }

    // store registrations as an array of user IDs in post meta 'event_registrations'
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

    // set a short-lived transient to indicate success for this user+event (one-time display)
    if ( $user_id && $event_id ) {
        set_transient( 'events_success_foran_' . intval( $user_id ) . '_' . intval( $event_id ), 1, 30 );
    }

    // Redirect back to referring page or Foranmalan with success flag
    $redirect = wp_get_referer();
    if ( ! $redirect ) {
        $redirect = site_url( '/foranmalan/' );
    }
    // include event_id in redirect so the shortcode can show a message under that event
    $redirect = add_query_arg( array( 'foranmald' => '1', 'event_id' => $event_id ), $redirect );
    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_foranmalning', 'foranmalning_handler' );
add_action( 'admin_post_nopriv_foranmalning', 'foranmalning_handler' );

/**
 * Show Company field on user profile and edit screens (editable) and save it to user meta.
 */
function ollie_user_company_profile_field( $user ) {
    if ( ! current_user_can( 'edit_users' ) ) {
        return;
    }

    $company = get_user_meta( $user->ID, 'company', true );

    wp_nonce_field( 'ollie_save_company', 'ollie_company_nonce' );
    ?>
    <h2><?php esc_html_e( 'Company', 'ollie-child' ); ?></h2>
    <table class="form-table">
        <tr>
            <th><label for="company"><?php esc_html_e( 'Company', 'ollie-child' ); ?></label></th>
            <td>
                <input type="text" name="company" id="company" value="<?php echo esc_attr( $company ); ?>" class="regular-text" />
                <p class="description"><?php esc_html_e( 'Company name for this user.', 'ollie-child' ); ?></p>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'ollie_user_company_profile_field' );
add_action( 'edit_user_profile', 'ollie_user_company_profile_field' );

function ollie_save_user_company_profile( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    if ( empty( $_POST ) ) {
        return false;
    }

    if ( ! isset( $_POST['ollie_company_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ollie_company_nonce'] ), 'ollie_save_company' ) ) {
        return false;
    }

    if ( isset( $_POST['company'] ) ) {
        $company = sanitize_text_field( wp_unslash( $_POST['company'] ) );
        if ( $company === '' ) {
            delete_user_meta( $user_id, 'company' );
        } else {
            update_user_meta( $user_id, 'company', $company );
        }
    }
}
add_action( 'personal_options_update', 'ollie_save_user_company_profile' );
add_action( 'edit_user_profile_update', 'ollie_save_user_company_profile' );

/**
 * Remove or hide User Registration "User Extra Information" company field to avoid duplication.
 * Filters the profile meta fields shown by the User Registration plugin and removes any field
 * whose label or field_key looks like a company/företag field.
 */
add_filter( 'user_registration_profile_meta_fields', 'ollie_filter_ur_profile_meta_fields', 10, 1 );
function ollie_filter_ur_profile_meta_fields( $fields ) {
    if ( empty( $fields ) || ! is_array( $fields ) ) {
        return $fields;
    }

    foreach ( $fields as $group_key => $group ) {
        if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
            continue;
        }

        foreach ( $group['fields'] as $key => $field ) {
            $label = isset( $field['label'] ) ? $field['label'] : '';
            $field_key = isset( $field['field_key'] ) ? $field['field_key'] : $key;

            // Candidate identifiers for company field
            $company_identifiers = array( 'billing_company', 'business_name', 'company', 'company_name', 'företag', 'foretag' );

            if ( $label && ( stripos( $label, 'företag' ) !== false || stripos( $label, 'foretag' ) !== false ) ) {
                unset( $fields[ $group_key ]['fields'][ $key ] );
                continue;
            }

            if ( in_array( strtolower( $field_key ), $company_identifiers, true ) ) {
                unset( $fields[ $group_key ]['fields'][ $key ] );
                continue;
            }
        }
    }

    return $fields;
}


// Registrera Custom Post Type - Medlemmar
function fenix_register_member_cpt() {
    $labels = array(
        'name'               => 'Medlemmar',
        'singular_name'      => 'Medlem',
        'add_new'            => 'Lägg till ny',
        'add_new_item'       => 'Lägg till ny medlem',
        'edit_item'          => 'Redigera medlem',
        'new_item'           => 'Ny medlem',
        'view_item'          => 'Visa medlem',
        'search_items'       => 'Sök medlemmar',
        'not_found'          => 'Inga medlemmar hittades',
        'not_found_in_trash' => 'Inga medlemmar i papperskorgen',
        'menu_name'          => 'Medlemmar',
    );

    $args = array(
        'labels'       => $labels,
        'public'       => true,
        'has_archive'  => false,
        'show_in_rest' => true,
        'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
        'menu_icon'    => 'dashicons-groups',
        'rewrite'      => array( 'slug' => 'medlemmar' ),
    );

    register_post_type( 'member', $args );
}
add_action( 'init', 'fenix_register_member_cpt' );


// Registrera metafält för Medlemmar
function fenix_register_member_meta() {
    $string_fields = array( 'fenix_name', 'fenix_title', 'fenix_email', 'fenix_phone', 'fenix_address', 'fenix_website' );

    foreach ( $string_fields as $field ) {
        register_post_meta( 'member', $field, array(
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'auth_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
        ));
    }
}



// Show admin notice on user profile/edit pages with saved company
add_action( 'admin_notices', 'ollie_show_company_admin_notice' );
function ollie_show_company_admin_notice() {
    if ( ! is_admin() ) {
        return;
    }

    // Only show on user edit/profile pages
    if ( ! in_array( basename( filter_input( INPUT_SERVER, 'PHP_SELF', FILTER_SANITIZE_STRING ) ), array( 'user-edit.php', 'profile.php' ), true ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_users' ) ) {
        return;
    }

    $user_id = 0;
    if ( isset( $_GET['user_id'] ) ) {
        $user_id = intval( $_GET['user_id'] );
    } else {
        $user_id = get_current_user_id();
    }

    if ( ! $user_id ) {
        return;
    }

    $company = get_user_meta( $user_id, 'company', true );
    if ( empty( $company ) ) {
        // try billing_company
        $company = get_user_meta( $user_id, 'billing_company', true );
    }

    if ( empty( $company ) ) {
        return;
    }

    printf(
        '<div class="notice notice-info is-dismissible"><p><strong>%s</strong> %s</p></div>',
        esc_html__( 'Company:', 'ollie-child' ),
        esc_html( $company )
    );
}
add_action( 'init', 'fenix_register_member_meta' );

add_shortcode( 'fenix_website_link', function() {
    $url = get_post_meta( get_the_ID(), 'fenix_website', true );
    if ( ! $url ) return '';
    return '<a href="' . esc_url( $url ) . '" target="_blank" class="fenix-website-link">' . esc_html( $url ) . '</a>';
});



// Shortcode för klickbar e-postlänk
add_shortcode( 'fenix_email_link', function() {
    $email = get_post_meta( get_the_ID(), 'fenix_email', true );
    if ( ! $email ) return '';
    return '<a href="mailto:' . antispambot( $email ) . '" class="fenix-email-link">' . antispambot( $email ) . '</a>';
});

// Shortcode för klickbar telefonlänk
add_shortcode( 'fenix_phone_link', function() {
    $phone = get_post_meta( get_the_ID(), 'fenix_phone', true );
    if ( ! $phone ) return '';
    // Rensa bort mellanslag etc för tel:-länken men visa originalet
    $tel = preg_replace( '/[^+0-9]/', '', $phone );
    return '<a href="tel:' . esc_attr( $tel ) . '" class="fenix-phone-link">' . esc_html( $phone ) . '</a>';
});


// Hide Navigation block items with class "admin-only" from non-admins (for FSE / Navigation block)
add_filter( 'render_block', 'fp_filter_navigation_block_admin_only', 10, 2 );
function fp_filter_navigation_block_admin_only( $block_content, $block ) {
    if ( empty( $block['blockName'] ) || $block['blockName'] !== 'core/navigation' ) {
        return $block_content;
    }
    // Don't modify HTML structure here — hide admin-only items with CSS instead so nested
    // submenu markup remains intact. This avoids removing nodes and breaking submenus.
    return $block_content;
}

// Output CSS in the head to hide elements that have the admin-only class for non-admins.
add_action( 'wp_head', function() {
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }
    echo "<style>/* hide admin-only navigation items for non-admins */\n",
         ".wp-block-navigation .admin-only, .wp-block-navigation li.admin-only, .wp-block-navigation .wp-block-navigation-item.admin-only { display: none !important; }\n",
         ".wp-block-navigation__submenu-container .admin-only { display: none !important; }\n",
         "</style>";
} );


function shortcode_all_members( $atts )
{
    // Public members listing — Visibility restricted to logged-in users. Capability checks are handled
    // where actions require them (edit links, admin-only controls).
    if ( ! is_user_logged_in() ) {
        return '<p style="color: green;"><strong>Du måste vara inloggad för att se medlemmar.</strong></p>';
    }

    $atts = shortcode_atts( array( 'per_page' => 20 ), $atts, 'all_members' );
    $per_page = intval( $atts['per_page'] );
    $paged = max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) ); 
    $offset = ( $paged - 1 ) * $per_page; 
    $args = array( 'number'  => $per_page, 'offset'  => $offset, 'orderby' => 'registered', 'order'   => 'DESC', 'fields'  => 'all', ); 
    
    $q = new WP_User_Query( $args ); 
    $users = $q->get_results(); 
    
    if ( empty( $users ) ) 
    { 
        error_log( 'all_members shortcode: no members. Query vars: ' . print_r( $q->query_vars, true ) . ' total:' . $q->get_total() ); 
        
        return '<p>No members found in db.</p>'; 
    }

    $out = '<div class="all-members-wrap">
            
                <div class="all-members-inner-wrap">' .
              
    $i = ""; // Ta inte bort denna variabel, då kraschar sidan.
    
    foreach ( $users as $u ) {                     
        $profilePhoto = '';
        $att_id = get_user_meta($u->ID, 'profile_picture', true );

        if ( $att_id ) {
            $profilePhoto = wp_get_attachment_image( $att_id, array( 64, 64 ), false, array( 'class' => 'avatar' ) );
        } 
        else {
            $profilePhoto = get_avatar($u->ID, 64 );
        }

        // Resolve cover image URL (if present) and build inline style only when available
        $cover_url = '';
        if ( function_exists( 'ollie_get_user_cover_url' ) ) {
            $cover_url = ollie_get_user_cover_url( $u->ID, 'full' );
        } else {
            $cover_att = get_user_meta( $u->ID, 'profile_cover', true );
            if ( $cover_att ) {
                $cover_url = wp_get_attachment_image_url( $cover_att, 'full' );
            }
        }

        $cover_style = 'background-image:url(' . esc_url( $cover_url ) . ')';
        
        $email = isset( $u->user_email ) ? $u->user_email : '';
        $website = isset( $u->user_url ) ? $u->user_url : get_user_meta( $u->ID, 'user_url', true );
        $company =  get_user_meta( $u->ID, 'company', true );
        $biography= isset( $u->description ) ? $u->description : get_user_meta( $u->ID, 'description', true );
        
        $out .= '<div class="member-wrap">';
        $out .= '<div class="member-cover"' . ( $cover_style ? ' style="' . $cover_style . '"' : '' ) . '></div>';
        // Resolve the edit-member page permalink by path if it exists, fallback to the slug URL.
        $edit_page = get_page_by_path( 'andra-medlem' );
        $base = $edit_page ? get_permalink( $edit_page->ID ) : site_url( '/andra-medlem/' );
        $edit_link = esc_url( add_query_arg( array( 'user_id' => $u->ID ), $base ) );
        

        $out .= '<div class="member-card">';
    
        if ( current_user_can( 'manage_options' ) ) {
            $out .= '<div class="member-photo"><a href="' . $edit_link . '">' . $profilePhoto . '</a></div>';
            $out .= '<div class="member profile-display-name"><a href="' . $edit_link . '">' . esc_html( $u->display_name ) . '</a></div>';
        } else {
            $out .= '<div class="member-photo">' . $profilePhoto . '</div>';
            $out .= '<div class="member profile-display-name">' . esc_html( $u->display_name ) . '</div>';
        }

        $out .= '<div class="member-item-wrap">' . esc_html( $u->user_login ) . '</div>';
        $out .= '<div class="member-item-wrap"><span class="member-profile-item-title">Epost: </span>' . esc_html( $email ) . '</div>';
        $out .= '<div class="member-item-wrap"><span class="member-profile-item-title">Företag: </span>' . esc_html( $company ) . '</div>';
        $out .= '<div class="member-item-wrap"><span class="member-profile-item-title">Webbplats: </span><a href="' . esc_url( $website ) . '" target="_blank" rel="noopener">' . esc_html( $website ) . '</a></div>';
        $out .= '<div class="member-item-wrap profile-bio">' . wp_kses_post( wpautop( $biography ) ) . '</div>';
        $out .= '</div>'; // member-card
        $out .= '</div>'; // member-wrap
        
    } 
    $out .= '</div>
                </div>'; 
    
    return $out; 
} 
   
/**
 * Shows list of members for a given event who are either cancelled (avanmälda)
 * or registered (föranmälda) depending on querystring parameters.
 * Expected querystring: ?event_id=123&status=cancelled  OR  ?event_id=123&status=registered
 * Swedish synonyms are accepted for status: 'avan', 'foran', 'avanmalda', 'foranmalda', etc.
 */
function shortcode_anmalningar( $atts ) {
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
        $which = 'cancelled';
    } 
    else if ( in_array( $status, $reg_values, true ) ) {
        $which = 'registered';
    } 
    else {
        return '<div>Ogiltigt statusvärde. Ange status=cancelled eller status=registered i querystring.</div>';
    }

    if ( $event_id <= 0 ) {
        return '<div>Event-id saknas eller ogiltigt. Ange event_id i querystring.</div>';
    }

    $post = get_post( $event_id );
    if ( ! $post || $post->post_type !== 'event' ) {
        return '<div>Eventet hittades inte.</div>';
    }

    if ( $which === 'cancelled' ) {
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

    $out = '<div class="anmalningar-list">';
    $out .= '<h4 style="margin-bottom: 40px;">' . esc_html( $heading ) . ' för "' . esc_html( get_the_title( $event_id ) ) . '" ' . esc_html( $date ) . '</h4>';

    if ( empty( $user_ids ) ) {
        $out .= '<div>Inga medlemmar hittades.</div>';
        $out .= '</div>';
        return $out;
    }

    $out .= '<div class="anmalningar-members">';
    foreach ( $user_ids as $uid ) {
        $u = get_userdata( intval( $uid ) );
        if ( ! $u ) continue;

        // avatar / profile picture if available
        $profilePhoto = '';
        $att_id = get_user_meta( $u->ID, 'profile_picture', true );
        if ( $att_id ) {
            $profilePhoto = wp_get_attachment_image( $att_id, array( 64, 64 ), false, array( 'class' => 'avatar' ) );
        } else {
            $profilePhoto = get_avatar( $u->ID, 64 );
        }

    
        $out .= '<div class="member-wrap">';
        $out .= '<div class="member-photo">' . $profilePhoto . '</div>';
        $out .= '<div class="member-info">';
        $out .= '<div class="member-name">' . esc_html( $u->display_name ) . '</div>';                
        $out .= '<div class="member-email">' . esc_html( $u->user_email ) . '</div>';
        $out .= '<div class="member-company">' . esc_html( get_user_meta( $u->ID, 'company', true ) ) . '</div>';
        $out .= '</div>'; // member-info
        $out .= '</div>'; // member-wrap
    }
    $out .= '</div>'; // anmalningar-members
    $out .= '</div>'; // anmalningar-list

    return $out;
}
add_shortcode( 'anmalningar_list', 'shortcode_anmalningar' );

    
    add_shortcode( 'all_members', 'shortcode_all_members' );

// Save company/business custom field from User Registration forms to user meta
add_action( 'user_registration_after_register_user_action', 'ollie_save_company_from_ur', 10, 3 );
function ollie_save_company_from_ur( $valid_form_data, $form_id, $user_id ) {
    if ( empty( $valid_form_data ) || empty( $user_id ) ) {
        return;
    }

    // Candidate field keys that may be used for "Företag" in different forms
    $company_keys = array( 'billing_company', 'shipping_company', 'business_name', 'company', 'company_name', 'företag', 'foretag' );

    foreach ( $company_keys as $key ) {
        if ( isset( $valid_form_data[ $key ] ) ) {
            $value = is_object( $valid_form_data[ $key ] ) && isset( $valid_form_data[ $key ]->value ) ? $valid_form_data[ $key ]->value : ( is_scalar( $valid_form_data[ $key ] ) ? $valid_form_data[ $key ] : '' );
            if ( $value !== '' ) {
                update_user_meta( $user_id, 'company', sanitize_text_field( $value ) );
                return; // stop after first match
            }
        }
    }

    // Fallback: some forms (User Registration plugin) store extra fields under meta keys prefixed
    // with 'user_registration_'. If we didn't find a company key in $valid_form_data, inspect
    // usermeta for likely company values and copy one to 'company'. This avoids duplicate form
    // fields being shown while ensuring company is available on the user record.
    $all_meta = get_user_meta( $user_id );
    if ( ! empty( $all_meta ) && is_array( $all_meta ) ) {
        foreach ( $all_meta as $meta_key => $meta_values ) {
            // skip already stored company
            if ( $meta_key === 'company' ) {
                continue;
            }
            // look for plugin-stored keys
            if ( strpos( $meta_key, 'user_registration_' ) === 0 || stripos( $meta_key, 'business' ) !== false || stripos( $meta_key, 'company' ) !== false || stripos( $meta_key, 'företag' ) !== false || stripos( $meta_key, 'foretag' ) !== false ) {
                $val = is_array( $meta_values ) ? $meta_values[0] : $meta_values;
                if ( is_array( $val ) ) {
                    $val = maybe_unserialize( $val );
                    if ( is_array( $val ) ) {
                        $val = reset( $val );
                    }
                }
                $val = is_scalar( $val ) ? trim( (string) $val ) : '';
                if ( $val !== '' ) {
                    update_user_meta( $user_id, 'company', sanitize_text_field( $val ) );
                    return;
                }
            }
        }
    }
}

// Also migrate UR extra-information to 'company' when a user profile is updated in admin
add_action( 'personal_options_update', 'ollie_migrate_ur_company_on_update', 20 );
add_action( 'edit_user_profile_update', 'ollie_migrate_ur_company_on_update', 20 );
function ollie_migrate_ur_company_on_update( $user_id ) {
    if ( ! $user_id ) {
        return;
    }
    // If company already set, nothing to do
    $existing = get_user_meta( $user_id, 'company', true );
    if ( ! empty( $existing ) ) {
        return;
    }

    $all_meta = get_user_meta( $user_id );
    if ( empty( $all_meta ) || ! is_array( $all_meta ) ) {
        return;
    }

    foreach ( $all_meta as $meta_key => $meta_values ) {
        if ( strpos( $meta_key, 'user_registration_' ) === 0 || stripos( $meta_key, 'business' ) !== false || stripos( $meta_key, 'company' ) !== false || stripos( $meta_key, 'företag' ) !== false || stripos( $meta_key, 'foretag' ) !== false ) {
            $val = is_array( $meta_values ) ? $meta_values[0] : $meta_values;
            if ( is_array( $val ) ) {
                $val = maybe_unserialize( $val );
                if ( is_array( $val ) ) {
                    $val = reset( $val );
                }
            }
            $val = is_scalar( $val ) ? trim( (string) $val ) : '';
            if ( $val !== '' ) {
                update_user_meta( $user_id, 'company', sanitize_text_field( $val ) );
                return;
            }
        }
    }
}

add_action( 'init', function() {
    if ( ! class_exists( 'WP_Roles' ) ) {
        return;
    }
    global $wp_roles;
    if ( ! isset( $wp_roles ) ) {
        $wp_roles = new WP_Roles();
    }

    // Change display name only (role key remains 'subscriber')
    $new_name = 'Medlem';
    if ( isset( $wp_roles->roles['subscriber'] ) ) {
        $wp_roles->roles['subscriber']['name'] = $new_name;
    }
    if ( isset( $wp_roles->role_names['subscriber'] ) ) {
        $wp_roles->role_names['subscriber'] = $new_name;
    }
} );


// Show upload field for profile-picture on profile pages
add_action( 'show_user_profile', 'ollie_profile_picture_field' );
add_action( 'edit_user_profile', 'ollie_profile_picture_field' );
// Ensure profile forms support file uploads
add_action( 'user_edit_form_tag', 'ollie_add_profile_form_enctype' );
function ollie_add_profile_form_enctype() {
    echo ' enctype="multipart/form-data"';
}
function ollie_profile_picture_field( $user ) {
    wp_nonce_field( 'ollie_save_profile_picture', 'ollie_profile_picture_nonce' );
    $att_id = get_user_meta( $user->ID, 'profile_picture', true );
    $img = $att_id ? wp_get_attachment_image( $att_id, array(96,96) ) : '';
    ?>
    <h2><?php esc_html_e( 'Profile picture', 'ollie-child' ); ?></h2>
    <table class="form-table">
    <tr>
      <th><label for="profile_picture"><?php esc_html_e( 'Upload', 'ollie-child' ); ?></label></th>
      <td>
        <?php echo $img; ?><br/>
        <input type="file" name="profile_picture_file" id="profile_picture_file" /><br/>
        <span class="description"><?php esc_html_e( 'Upload an image to use instead of Gravatar.', 'ollie-child' ); ?></span>
      </td>
    </tr>
    </table>
    <?php
}

// Handle saving/upload
add_action( 'personal_options_update', 'ollie_save_profile_picture' );
add_action( 'edit_user_profile_update', 'ollie_save_profile_picture' );
function ollie_save_profile_picture( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) return;
    if ( ! isset( $_POST['ollie_profile_picture_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ollie_profile_picture_nonce'] ), 'ollie_save_profile_picture' ) ) return;

    if ( ! empty( $_FILES['profile_picture_file']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $file = $_FILES['profile_picture_file'];
        $overrides = array( 'test_form' => false );
        $movefile = wp_handle_upload( $file, $overrides );
        if ( isset( $movefile['file'] ) ) {
            $filename = $movefile['file'];
            $filetype = wp_check_filetype( basename( $filename ), null );
            $attachment = array(
                'post_mime_type' => $filetype['type'],
                'post_title'     => sanitize_file_name( basename( $filename ) ),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            $attach_id = wp_insert_attachment( $attachment, $filename );
            $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
            wp_update_attachment_metadata( $attach_id, $attach_data );

            // Save attachment ID as user meta and delete previous attachment if any
            $old_id = get_user_meta( $user_id, 'profile_picture', true );
            update_user_meta( $user_id, 'profile_picture', intval( $attach_id ) );
            if ( $old_id && intval( $old_id ) !== intval( $attach_id ) ) {
                // delete old attachment (force delete files)
                wp_delete_attachment( intval( $old_id ), true );
            }
        }
    }
}

// Use uploaded image for get_avatar()
add_filter( 'get_avatar', 'ollie_get_local_avatar', 10, 5 );
function ollie_get_local_avatar( $avatar, $id_or_email, $size, $default, $alt = '' ) {
    $user = false;
    if ( is_numeric( $id_or_email ) ) {
        $user = get_user_by( 'id', (int) $id_or_email );
    } elseif ( is_object( $id_or_email ) && ! empty( $id_or_email->user_id ) ) {
        $user = get_user_by( 'id', (int) $id_or_email->user_id );
    } elseif ( is_string( $id_or_email ) ) {
        $user = get_user_by( 'email', $id_or_email );
    }

    if ( $user ) {
        $att_id = get_user_meta( $user->ID, 'profile_picture', true );
        if ( $att_id ) {
            $src = wp_get_attachment_image_url( $att_id, array( $size, $size ) );
            if ( $src ) {
                $alt = esc_attr( $alt ? $alt : $user->display_name );
                return "<img src=\"" . esc_url( $src ) . "\" alt=\"{$alt}\" class=\"avatar avatar-{$size} photo\" height=\"{$size}\" width=\"{$size}\" />";
            }
        }
    }
    return $avatar;
}


// Show upload field for cover photo on profile pages
add_action( 'show_user_profile', 'ollie_profile_cover_field' );
add_action( 'edit_user_profile', 'ollie_profile_cover_field' );
function ollie_profile_cover_field( $user ) {
    wp_nonce_field( 'ollie_save_cover', 'ollie_cover_nonce' );
    $att_id = get_user_meta( $user->ID, 'profile_cover', true );
    $img = $att_id ? wp_get_attachment_image( $att_id, 'medium' ) : '';
    ?>
    <h2><?php esc_html_e( 'Cover photo', 'ollie-child' ); ?></h2>
    <table class="form-table">
    <tr>
      <th><label for="profile_cover"><?php esc_html_e( 'Upload', 'ollie-child' ); ?></label></th>
      <td>
        <?php echo $img; ?><br/>
        <input type="file" name="cover_photo_file" id="cover_photo_file" /><br/>
        <span class="description"><?php esc_html_e( 'Upload a cover image for this user.', 'ollie-child' ); ?></span>
      </td>
    </tr>
    </table>
    <?php
}

// Handle saving/upload for cover photo
add_action( 'personal_options_update', 'ollie_save_profile_cover' );
add_action( 'edit_user_profile_update', 'ollie_save_profile_cover' );
function ollie_save_profile_cover( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) return;
    if ( ! isset( $_POST['ollie_cover_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ollie_cover_nonce'] ), 'ollie_save_cover' ) ) return;

    if ( ! empty( $_FILES['cover_photo_file']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $file = $_FILES['cover_photo_file'];
        $overrides = array( 'test_form' => false );
        $movefile = wp_handle_upload( $file, $overrides );
        if ( isset( $movefile['file'] ) ) {
            $filename = $movefile['file'];
            $filetype = wp_check_filetype( basename( $filename ), null );
            $attachment = array(
                'post_mime_type' => $filetype['type'],
                'post_title'     => sanitize_file_name( basename( $filename ) ),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            $attach_id = wp_insert_attachment( $attachment, $filename );
            $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
            wp_update_attachment_metadata( $attach_id, $attach_data );

            // Save attachment ID as user meta for cover and delete previous cover attachment if any
            $old_cover = get_user_meta( $user_id, 'profile_cover', true );
            update_user_meta( $user_id, 'profile_cover', intval( $attach_id ) );
            if ( $old_cover && intval( $old_cover ) !== intval( $attach_id ) ) {
                wp_delete_attachment( intval( $old_cover ), true );
            }
        }
    }
}

/**
 * Helper: get user cover image URL
 */
function ollie_get_user_cover_url( $user_id, $size = 'full' ) {
    $att_id = get_user_meta( $user_id, 'profile_cover', true );
    if ( ! $att_id ) {
        return '';
    }
    return wp_get_attachment_image_url( $att_id, $size );
}


// Load register member shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/register-member-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/register-member-shortcode.php';
}

// Load edit member shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/edit-member-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/edit-member-shortcode.php';
}

// Load Uploaded files shortcode
if ( file_exists( get_stylesheet_directory() . '/uploaded-files-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/uploaded-files-shortcode.php';
}
// Load events shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/events-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/events-shortcode.php';
}
