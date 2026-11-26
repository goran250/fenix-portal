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

    register_post_type('hidden_user', array(
		'labels' => array(
			'name' => 'Hidden users',
			'singular_name' => 'Hidden user',
		),
		'public' => false,
		'show_ui' => true,
		'map_meta_cap' => true,
		'capability_type' => array('hidden_users', 'hidden_users'),
		'supports' => array('title', 'editor', 'author'),
	));

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
