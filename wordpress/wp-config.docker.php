<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'wordpress' );

/** Database password */
define( 'DB_PASSWORD', 'wordpress' );

/** Database hostname */
define( 'DB_HOST', 'nrfc-wp-dev-db' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         '4#yHQK*[*jPCMjJl_^m`c9;2e$ /8nEwb)TC*),;bPVe(aOVEui`($by@xdNq[l1' );
define( 'SECURE_AUTH_KEY',  '6A9pf1B[7#Zaq#LMv6hxct6b4UX /x7{Ot){DNuiB5O65bHYfCw:GnyA vyT9ssI' );
define( 'LOGGED_IN_KEY',    'Zy;_Y]L^VM>&1=RN_MdY4S9o=62|gcJ2=od0R_B]L.u)OdF}O}:=7;sk0v]m{:br' );
define( 'NONCE_KEY',        '^{1RkrGxI,hKtP.Wl |O-<T~Acnp#0.CY?W^EMIWt`s9h=)hQC8;&`sp8:hy[E2M' );
define( 'AUTH_SALT',        'u`K-[Jz|oW4SSnX 3l!%;g7{8EJAdw3T=1f}$7w2:+RipEf5n&|B$_9GyurQs9/7' );
define( 'SECURE_AUTH_SALT', 'Q:TFL~dg<p-E]mo{.[.D8rl$2n0doi]Q^)lp la?5Tcy4/>prW^DN{wvF2+!e(RF' );
define( 'LOGGED_IN_SALT',   'e3&>[.U]NM:#?g.k-tub *h@Ff7p&VMU~C{DZ79ZTAyH@QX)m[gpK9[rlK_2v]w)' );
define( 'NONCE_SALT',       'NA(~XANb_;rs%4l[O:V6zONh]*uX{Sf7N?W*~%e66G&gmn$t-4D,%.je.6W?xsf=' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
