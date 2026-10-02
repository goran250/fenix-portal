<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '.$N7|n_gcr!gXXB0*7P^H(1+NVSouho;c$gBqyXdF4=@Q!YkyNskr5oL1X0;1*;z' );
define( 'SECURE_AUTH_KEY',   '*ztb7bdOG>h0QI }x)%nO8L6D(0uV#hQCPRu$QcJ68sav5x.AT]i%`ItgdKg~GlB' );
define( 'LOGGED_IN_KEY',     'OL!LYS=dGDD^]B)2:svi8V*#7yHS{y;>G]!$l ;Qa5gH<T.~ mbxieTC^]*EnvSi' );
define( 'NONCE_KEY',         'sq99Z US,YHlZ~yOh:8kage31QyQGZeEa8Kr{o^D9AdmGOY@Gl8#TV[{op[6UIzy' );
define( 'AUTH_SALT',         '3h[fnN?Y|BdU?O@ EmGsUAMO8D,!UuQ7A=jXs!nl 18!,_Qvg+$d8pppu,yKlEk[' );
define( 'SECURE_AUTH_SALT',  'NFLl*!&7F${);&/o |#C:Y`j*toMl|Ep(|>83y:VBw#JdY%BRJ.vqH0!Mi{n[IG}' );
define( 'LOGGED_IN_SALT',    ']9[oY,Z.LmAy*ZW)X5c{D--#l7~D%)XB((}~pbOD`qI-5j %zUE[ZL7Lo&Gb2-Km' );
define( 'NONCE_SALT',        'y}6#k=MkGC>eZgb bSZSGM=Ix9N.m%;Gwv#[97YxxJdc% SrB:A=~>D^K$[tRaH^' );
define( 'WP_CACHE_KEY_SALT', 'D,=aDhh.66i/o.NVLOuVE56+T$4?I1>cF>oDdX7-%m&17PUoz[AP4b0uf]+Q=(6@' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
