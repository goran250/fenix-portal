<?php

add_action( 'wp_enqueue_scripts', 'ollie_child_enqueue_styles' );

/**
 * Enqueue Ollie child theme styles and scripts.
 * @return void
 */
function ollie_child_enqueue_styles(): void {
	wp_enqueue_style( 'ollie-child-style', get_stylesheet_uri(), array( 'ollie' ), wp_get_theme()->get( 'Version' ) );

	// Responsive style – laddas sist så den vinner i specificitet
	wp_enqueue_style( 'ollie-child-responsive-style', get_stylesheet_directory_uri() . '/responsive-style.css', array( 'ollie-child-style' ), wp_get_theme()->get( 'Version' ) );

	// Header scroll effect
	wp_enqueue_script( 'header-scroll', get_stylesheet_directory_uri() . '/header-scroll.js', array(), wp_get_theme()->get( 'Version' ), true );
}

// Load custom CSS on WordPress login page
add_action('login_enqueue_scripts', function () {
	$file_path = get_stylesheet_directory() . '/login-custom.css';
	$file_url  = get_stylesheet_directory_uri() . '/login-custom.css';
	$ver       = file_exists($file_path) ? filemtime($file_path) : wp_get_theme()->get('Version');

	wp_enqueue_style('ollie-child-login', $file_url, array(), $ver);
});

add_action('init', function () {
	register_post_type('tfa', array(
		'labels' => array(
			'name'          => 'TFA',
			'singular_name' => 'TFA',
		),
		'public'       => false,          // not public front-end archive
		'show_ui'      => true,           // show in wp-admin
		'show_in_menu' => true,
		'supports'     => array('title', 'author'),
		'has_archive'  => false,
		'rewrite'      => false,
		'menu_icon'    => 'dashicons-forms',
	));

	register_post_type('guest', array(
		'labels' => array(
			'name' => 'Guests',
			'singular_name' => 'Guest',
		),
		'public' => false,
		'show_ui' => true,
		'map_meta_cap' => true,
		'capability_type' => array('guest', 'guests'),
		'supports' => array('title', 'editor', 'author'),
	));

    register_post_type('replacer', array(
		'labels' => array(
			'name' => 'Replacers',
			'singular_name' => 'Replacer',
		),
		'public' => false,
		'show_ui' => true,
		'map_meta_cap' => true,
		'capability_type' => array('replacer', 'replacers'),
		'supports' => array('title', 'editor', 'author'),
	));

     register_post_type('absence_reason', array(
		'labels' => array(
			'name' => 'Absence reasons',
			'singular_name' => 'Absence reason',
		),
		'public' => false,
		'show_ui' => true,
		'map_meta_cap' => true,
		'capability_type' => array('absence_reason', 'absence_reasons'),
		'supports' => array('title', 'editor', 'author'),
	));

	register_post_type( 'event', array(
		'labels' =>  array(
            'name' => 'Events',
            'singular_name' => 'Event',
        ),
		'public' => false,
		'show_ui' => false,
		'supports' => array( 'title' ),
		'has_archive' => false,
		'show_in_rest' => false,
	) );

});


add_action('init', function () {
	$role = get_role('subscriber');
	if (!$role) return;

	$role->add_cap('read');
	$role->add_cap('edit_guest');
	$role->add_cap('read_guest');
	$role->add_cap('delete_guest');

	$role->add_cap('edit_guests');
	$role->add_cap('edit_published_guests');
	$role->add_cap('publish_guests');
	$role->add_cap('delete_guests');
	$role->add_cap('delete_published_guests');
});

// Gör så att användare som loggar ut skickas till startsidan.
add_filter('logout_redirect', function ($redirect_to, $requested_redirect_to, $user) {
	return home_url('/'); // start page
}, 10, 3);


// Prevent login for deactivated users
add_filter('authenticate', function ($user, $username, $password) {
	if (is_wp_error($user) || !$user instanceof WP_User) {
		return $user;
	}

	$is_deactivated = get_user_meta($user->ID, 'deactivated', true);
	if ((string)$is_deactivated === '1') {
		return new WP_Error('account_deactivated', 'Ditt konto är inaktiverat.');
	}

	return $user;
}, 30, 3);

// Change login logo link URL
add_filter('login_headerurl', function () {
	return home_url('/');
});

// Change login logo link title/tooltip
add_filter('login_headertext', function () {
	return get_bloginfo('name');
});

// Show admin bar only for administrators
add_filter( 'show_admin_bar', function( $show ) {
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }
    return false;
} );

/** Mailfunktion */
add_action('init', function () {
	if (isset($_POST['send_simple_mail'])) {
		$to      = sanitize_email($_POST['to'] ?? '');
		$subject = sanitize_text_field($_POST['subject'] ?? '');
		$message = wp_kses_post($_POST['message'] ?? '');

		if ($to && is_email($to) && $subject && $message) {
			$headers = array('Content-Type: text/html; charset=UTF-8');
			wp_mail($to, $subject, nl2br($message), $headers);
		}
	}
});

add_shortcode('start_page', function () {
    $out = '<div class="start-page-content">
                <p>
                   Välkommen till Fenix medlemsportal. De flesta sidorna är endast synliga för inloggade medlemmar. Kontakta Susanne Eneroth 
                   för att få inloggningsuppgifter. Du kan inte registrera dig själv.
                </p>';
    
    if (is_user_logged_in() ) {               
        $out .= '<p>I medlemsportalen kan du redigera dina uppgifter under menyalternativet <a href="' . esc_url( site_url( '/andra-medlem/' ) ) . '"><strong>Din profil</strong></a>.</p>
                 <p>Under <strong>Medlemmar-><a href="' . esc_url( site_url( '/alla-medlemmar/' ) ) . '">Alla medlemmar</a></strong> kan du se övriga medlemmars uppgifter.</p>
                 <p>Du kan se vilka <a href="' . esc_url( site_url( '/events/' ) ) . '"><strong>events</strong></a> som är på gång och registrera eller avregistrera dig från event.</p>
                 <p>Du kan även hantera <a href="' . esc_url( site_url( '/tfa/' ) ) . '"><strong>TFA (tack för affären)</strong></a> uppgifter.</p>
            </div>';
    }

	if ( !is_user_logged_in() ) {
	    $out .= '<div class="is-layout-flex" style="width: 100%">
                    <div class="wp-block-button start-login-button">    
                        <a class="wp-block-button__link wp-element-button" href="' . esc_url( wp_login_url() ) . '?redirect_to=http%3A%2F%2Ffenix-portal.local%2F">
                            Logga in
                        </a>
                    </div>
                </div>';
    }

    return $out;
});



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


// Hide Navigation block items with class "logged-in-only" from not-logged users (for FSE / Navigation block)
add_filter( 'render_block', 'fp_filter_navigation_block_logged_in_only', 10, 2 );
function fp_filter_navigation_block_logged_in_only( $block_content, $block ) {
    if ( empty( $block['blockName'] ) || $block['blockName'] !== 'core/navigation' ) {
        return $block_content;
    }
    // Don't modify HTML structure here — hide logged-in-only items with CSS instead so nested
    // submenu markup remains intact. This avoids removing nodes and breaking submenus.
    return $block_content;
}

// Output CSS in the head to hide elements that have the logged-in-only class for not-logged-in users.
add_action( 'wp_head', function() {
    if ( is_user_logged_in() ) {
        return;
    }

    echo "<style>/* hide logged-in-only navigation items for not-logged-in users */\n",
         ".wp-block-navigation .logged-in-only , .wp-block-navigation li.logged-in-only , .wp-block-navigation .wp-block-navigation-item.logged-in-only { display: none !important; }\n",
         ".wp-block-navigation__submenu-container .logged-in-only { display: none !important; }\n",
         "</style>";
} );


    

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

// Load all_members-shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/all-members-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/all-members-shortcode.php';
}

// Load register-member-shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/register-member-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/register-member-shortcode.php';
}

// Load edit-member-shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/edit-member-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/edit-member-shortcode.php';
}

// Load uploaded-files shortcode
if ( file_exists( get_stylesheet_directory() . '/uploaded-files-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/uploaded-files-shortcode.php';
}
// Load events shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/events-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/events-shortcode.php';
}

// Load password-setup shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/password-setup-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/password-setup-shortcode.php';
}

// Load tfa_page shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/tfa-shortcodes.php' ) ) {
    require_once get_stylesheet_directory() . '/tfa-shortcodes.php';
}

// Load register-guest-shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/register-guest-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/register-guest-shortcode.php';
}

// Load applications-shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/applications-shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/applications-shortcode.php';
}

// Load avanmalan-shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/avanmalan_shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/avanmalan_shortcode.php';
}

// Load foranmalan-shortcode implementation if present
if ( file_exists( get_stylesheet_directory() . '/foranmalan_shortcode.php' ) ) {
    require_once get_stylesheet_directory() . '/foranmalan_shortcode.php';
}
