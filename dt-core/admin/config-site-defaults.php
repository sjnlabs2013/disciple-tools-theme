<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly
/**
 * Default Structure
 * This is for default structure settings.
 *
 * @author  Chasm Solutions
 * @package Disciple_Tools
 */

/*********************************************************************************************
 * Action and Filters
 */

add_action( 'init', 'dt_set_permalink_structure' );
add_action( 'update_option_permalink_structure', 'dt_permalink_structure_changed_callback' );
//unconditionally allow duplicate comments
add_filter( 'duplicate_comment_id', '__return_false' );
//allow multiple comments in quick succession
add_filter( 'comment_flood_filter', '__return_false' );
add_filter( 'pre_comment_approved', 'dt_filter_handler', '99', 2 );
add_filter( 'comment_notification_recipients', 'dt_override_comment_notice_recipients', 10, 2 );
add_filter( 'language_attributes', 'dt_custom_dir_attr' );
add_filter( 'retrieve_password_message', 'dt_custom_password_reset', 99, 4 );
add_filter( 'wpmu_signup_blog_notification_email', 'dt_wpmu_signup_blog_notification_email', 10, 8 );
add_filter( 'login_errors', 'login_error_messages' );
remove_action( 'plugins_loaded', 'wp_maybe_load_widgets', 0 );  //don't load widgets as we don't use them
remove_action( "init", "wp_widgets_init", 1 );

/*********************************************************************************************
 * Functions
 */

/**
 * Set default premalink structure
 * Needed for the rest api url structure (for wp-json to work)
 */
function dt_set_permalink_structure() {
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure( '/%postname%/' );
    flush_rewrite_rules();
}

/**
 *
 */
function dt_warn_user_about_permalink_settings() {
    ?>
    <div class="error notices">
        <p><?php esc_html_e( 'You may only set your permalink settings to "Post name"', "disciple_tools" ); ?></p>
    </div>
    <?php
}

/**
 * Notification that 'posttype' is the only permalink structure available.
 *
 * @param $permalink_structure
 */
function dt_permalink_structure_changed_callback( $permalink_structure ) {
    global $wp_rewrite;
    if ( $permalink_structure !== '/%postname%/' ) {
        add_action( 'admin_notices', 'dt_warn_user_about_permalink_settings' );
    }
}

function dt_override_comment_notice_recipients() {
    return [];
}

/**
 * Admin panel svg icon for disciple tools.
 *
 * @return string
 */
function dt_svg_icon() {
    return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMS40IDIwLjMyIj48ZGVmcz48c3R5bGU+LmF7ZmlsbDojMmQyZDJkO308L3N0eWxlPjwvZGVmcz48dGl0bGU+ZGlzY2lwbGUtdG9vbHM8L3RpdGxlPjxwb2x5Z29uIGNsYXNzPSJhIiBwb2ludHM9IjIxLjQgMjAuMzIgOS4zIDAgMi44NiAxMC44MSA4LjUyIDIwLjMyIDIxLjQgMjAuMzIiLz48cG9seWdvbiBjbGFzcz0iYSIgcG9pbnRzPSIwLjAyIDE1LjU4IDAgMTUuNjEgMi44MyAyMC4zMiA1LjUxIDE1LjM0IDAuMDIgMTUuNTgiLz48L3N2Zz4=';
}

/**
 * Using the dt_get_option guarantees the existence of the option and upgrades to the current plugin version defaults,
 * while returning the options array.
 *
 * @param string $name
 *
 * @return array|false
 */
function dt_get_option( string $name ) {

    switch ( $name ) {
        case 'dt_site_options':
            $site_options = dt_get_site_options_defaults();

            if ( !get_option( 'dt_site_options' ) ) { // options doesn't exist, create new.
                $add = add_option( 'dt_site_options', $site_options, '', true );
                if ( !$add ) {
                    return false;
                }
            }
            elseif ( get_option( 'dt_site_options' )['version'] < $site_options['version'] ) { // option exists but version is behind
                $upgrade = dt_site_options_upgrade_version( 'dt_site_options' );
                if ( !$upgrade ) {
                    return false;
                }
            }
            return get_option( 'dt_site_options' );

            break;

        case 'dt_site_custom_lists':
            $default_custom_lists = dt_get_site_custom_lists();

            if ( !get_option( 'dt_site_custom_lists' ) ) { // options doen't exist, create new.
                add_option( 'dt_site_custom_lists', $default_custom_lists, '', true );
            }
            else {
                if ( (int) get_option( 'dt_site_custom_lists' )['version'] < $default_custom_lists['version'] ) { // option exists but version is behind
                    $upgrade = dt_site_options_upgrade_version( 'dt_site_custom_lists' );
//                    updating the option is not always working right away, return the non updated option instead of failing.
                    if ( !$upgrade ) {
                        return $default_custom_lists;
                    }
                }
            }
            //return apply_filters( "dt_site_custom_lists", get_option( 'dt_site_custom_lists' ) );
            return get_option( 'dt_site_custom_lists' );
            break;

        case 'dt_field_customizations':
            return get_option( 'dt_field_customizations', [
                "contacts" => [],
                "groups" => []
            ]);
        case 'dt_custom_tiles':
            return get_option( 'dt_custom_tiles', [
                "contacts" => [],
                "groups" => []
            ]);
        case 'dt_custom_channels':
            return get_option( 'dt_custom_channels', [] );

        case 'base_user':
            if ( ! get_option( 'dt_base_user' ) ) { // options doesn't exist, create new.
                // set base users to system admin
                $users = get_users( [ 'role' => 'dispatcher' ] );
                if ( empty( $users ) ) {
                    $users = get_users( [ 'role' => 'administrator' ] );
                }
                if ( empty( $users ) ) {
                    return false;
                }

                $user_id = $users[0]->ID;

                // set as base user
                $add = update_option( 'dt_base_user', $user_id, false );
                if ( ! $add ) {
                    return false;
                }

                return get_option( 'dt_base_user' );
            }
            else {
                return get_option( 'dt_base_user' );
            }
            break;

        case 'map_key':
            return Disciple_Tools_Google_Geocode_API::get_map_key();
            break;

        case 'location_levels':
            $default_levels = dt_get_location_levels();
            $levels = get_option( 'dt_location_levels' );
            if ( ! $levels || empty( $levels ) ) { // options doesn't exist, create new.
                $update = update_option( 'dt_location_levels', $default_levels, true );
                if ( ! $update ) {
                    return false;
                }
                $levels = get_option( 'dt_location_levels' );
            }
            elseif ( $levels['version'] < $default_levels['version'] ) { // option exists but version is behind

                unset( $levels['version'] );
                $location_levels = wp_parse_args( $levels, $default_levels );
                $update = update_option( 'dt_location_levels', $location_levels, true );
                if ( ! $update ) {
                    return false;
                }
                $levels = get_option( 'dt_location_levels' );
            }
            return $levels['location_levels'];
            break;
        case 'auto_location':
            $setting = get_option( 'dt_auto_location' );
            if ( false === $setting ) {
                update_option( 'dt_auto_location', '1', false );
                $setting = get_option( 'dt_auto_location' );
            }
            return $setting;
            break;

        case 'dt_email_base_subject':
            $subject_base = get_option( "dt_email_base_subject", "Disciple Tools" );
            if ( empty( $subject_base )){
                update_option( "dt_email_base_subject", "Disciple Tools" );
            }
            return $subject_base;
            break;

        case 'group_type':
            $site_options = dt_get_option( "dt_site_custom_lists" );
            return $site_options["group_type"];
        default:
            return false;
            break;
    }
}

/**
 * Supports the complex array structure of versioned arrays
 *
 * @param      $name
 * @param      $value
 * @param bool $autoload
 *
 * @return bool
 */
function dt_update_option( $name, $value, $autoload = false ) {

    if ( empty( $name ) ) {
        return false;
    }

    switch ( $name ) {
        case 'location_levels':
            if ( ! is_array( $value ) ) {
                return false;
            }
            $levels = maybe_unserialize( get_option( 'dt_location_levels' ) );
            $levels['location_levels'] = $value;

            $default_levels = dt_get_location_levels();
            $levels = wp_parse_args( $levels, $default_levels );

            return update_option( 'dt_location_levels', $levels, $autoload );

            break;
        case 'auto_location':
            return update_option( 'dt_auto_location', $value, $autoload );
            break;

        default:
            return false;
            break;
    }
}

/**
 * Returns the default master array of site options
 * Versioning allows for additive changes. Removal of fields here in defaults will not delete the value in current installations.
 *
 * @return array
 */
function dt_get_site_options_defaults() {
    $fields = [];

    $fields['version'] = '7';

    $fields['user_notifications'] = [
        'new_web'          => true,
        'new_email'        => true,
        'mentions_web'     => true,
        'mentions_email'   => true,
        'comments_web'     => true,
        'comments_email'   => true,
        'updates_web'      => true,
        'updates_email'    => true,
        'changes_web'      => true,
        'changes_email'    => true,
        'milestones_web'   => true,
        'milestones_email' => true,
    ];

    $fields['notifications'] = [
        'new_assigned' => [
            'label' => __( 'Newly Assigned Contact', 'disciple_tools' ),
            'web'   => true,
            'email' => true
        ],
        'mentions' => [
            'label' => __( '@Mentions', 'disciple_tools' ),
            'web'   => true,
            'email' => true
        ],
        'comments' => [
            'label' => __( 'New comments', 'disciple_tools' ),
            'web'   => false,
            'email' => false
        ],
        'updates' => [
            'label' => __( 'Update Needed', 'disciple_tools' ),
            'web'   => true,
            'email' => true
        ],
        'changes' => [
            'label' => __( 'Contact Info Changed', 'disciple_tools' ),
            'web'   => false,
            'email' => false
        ],
        'milestones' => [
            'label' => __( 'Contact Milestones and Group Health metrics', 'disciple_tools' ),
            'web'   => false,
            'email' => false
        ]
    ];

    $fields['extension_modules'] = [
        'add_people_groups' => true,
        'add_assetmapping'  => true,
        'add_prayer'        => true,
        'add_worker'        => true,
    ];

    $fields['clear_data_on_deactivate'] = false; // todo need to add this option wrapper to the deactivate.php file for table deletes

    $fields['daily_reports'] = [
        'build_report_for_contacts'  => true,
        'build_report_for_groups'    => true,
        'build_report_for_facebook'  => false,
        'build_report_for_twitter'   => false,
        'build_report_for_analytics' => false,
        'build_report_for_adwords'   => false,
        'build_report_for_mailchimp' => false,
        'build_report_for_youtube'   => false,
    ];

    $fields['update_required'] = [
        "enabled" => true,
        "options" => [
            [
                "status"      => "active",
                "seeker_path" => "none",
                "days"        => 3,
                "comment"     => __( "This contact is active but there is no record of anybody contacting them. Please do contact them.", 'disciple_tools' )
            ],
            [
                "status"      => "active",
                "seeker_path" => "attempted",
                "days"        => 7,
                "comment"     => __( "Please try connecting with this contact again.", 'disciple_tools' )
            ],
            [
                "status"      => "active",
                "seeker_path" => "established",
                "days"        => 30,
                "comment"     => __( "We haven't heard about this person in a while. Do you have an update for this contact?", 'disciple_tools' )
            ],
            [
                "status"      => "active",
                "seeker_path" => "scheduled",
                "days"        => 30,
                "comment"     => __( "We haven't heard about this person in a while. Do you have an update for this contact?", 'disciple_tools' )
            ],
            [
                "status"      => "active",
                "seeker_path" => "met",
                "days"        => 30,
                "comment"     => __( "We haven't heard about this person in a while. Do you have an update for this contact?", 'disciple_tools' )
            ],
            [
                "status"      => "active",
                "seeker_path" => "ongoing",
                "days"        => 30,
                "comment"     => __( "We haven't heard about this person in a while. Do you have an update for this contact?", 'disciple_tools' )
            ],
            [
                "status"      => "active",
                "seeker_path" => "coaching",
                "days"        => 30,
                "comment"     => __( "We haven't heard about this person in a while. Do you have an update for this contact?", 'disciple_tools' )
            ]
        ]
    ];
    $fields["group_update_required"] = [
        "enabled" => true,
        "options" => [
            [
                "status"      => "active",
                "days"        => 30,
                "comment"     => __( "We haven't heard about this group in a while. Do you have an update?", 'disciple_tools' )
            ]
        ]
    ];

    return $fields;
}

/**
 * Gets site configured custom lists
 * Versioning allows for additive changes. Removal of fields here in defaults will not delete the value in current installations.
 *
 * @param string|null $list_title
 *
 * @version 1 - initialized
 *          9 - added "transfer" to source list
 *
 * @return array|mixed
 */
function dt_get_site_custom_lists( string $list_title = null ) {
    $fields = [];

    $fields['version'] = 9;

    // the prefix dt_user_ assists db meta queries on the user
    $fields['user_fields'] = [
        'dt_user_personal_phone'   => [
            'label'       => 'Personal Phone',
            'key'         => 'dt_user_personal_phone',
            'type'        => 'phone',
            'description' => 'Personal phone is private to the team, not for distribution.',
            'enabled'     => true,
        ],
        'dt_user_personal_email'   => [
            'label'       => 'Personal Email',
            'key'         => 'dt_user_personal_email',
            'type'        => 'email',
            'description' => 'Personal email is private to the team, not for distribution.',
            'enabled'     => true,
        ],
        'dt_user_personal_address' => [
            'label'       => 'Personal Address',
            'key'         => 'dt_user_personal_address',
            'type'        => 'address',
            'description' => 'Personal address is private to the team, not for distribution.',
            'enabled'     => true,
        ],
        'dt_user_work_phone'       => [
            'label'       => 'Work Phone',
            'key'         => 'dt_user_work_phone',
            'type'        => 'phone',
            'description' => 'Work phone is for distribution to contacts and seekers.',
            'enabled'     => true,
        ],
        'dt_user_work_email'       => [
            'label'       => 'Work Email',
            'key'         => 'dt_user_work_email',
            'type'        => 'email',
            'description' => 'Work email is for distribution to contacts and seekers.',
            'enabled'     => true,
        ],
        'dt_user_work_facebook'    => [
            'label'       => 'Work Facebook',
            'key'         => 'dt_user_work_facebook',
            'type'        => 'social',
            'description' => 'Work Facebook is for distribution to contacts and seekers.',
            'enabled'     => true,
        ],
        'dt_user_work_whatsapp'    => [
            'label'       => 'Work WhatsApp',
            'key'         => 'dt_user_work_whatsapp',
            'type'        => 'other',
            'description' => 'Work WhatsApp is for distribution to contacts and seekers.',
            'enabled'     => true,
        ],
    ];

    $fields['user_fields_types'] = [
        'phone'   => [
            'label' => 'Phone',
            'key'   => 'phone',
        ],
        'email'   => [
            'label' => 'Email',
            'key'   => 'email',
        ],
        'social'  => [
            'label' => 'Social Media',
            'key'   => 'social',
        ],
        'address' => [
            'label' => 'Address',
            'key'   => 'address',
        ],
        'other'   => [
            'label' => 'Other',
            'key'   => 'other',
        ],
    ];

    $fields['sources'] = [
        'personal'           => [
            'label'       => 'Personal',
            'key'         => 'personal',
            'description' => 'Personal contacts.',
            'enabled'     => true,
        ],
        'web'           => [
            'label'       => 'Web',
            'key'         => 'web',
            'description' => 'Contacts coming from the website.',
            'enabled'     => true,
        ],
        'phone'         => [
            'label'       => 'Phone',
            'key'         => 'phone',
            'description' => 'Contacts coming from phone.',
            'enabled'     => true,
        ],
        'facebook'      => [
            'label'       => 'Facebook',
            'key'         => 'facebook',
            'description' => 'Contacts coming from Facebook.',
            'enabled'     => true,
        ],
        'twitter'       => [
            'label'       => 'Twitter',
            'key'         => 'twitter',
            'description' => 'Contacts coming from Twitter.',
            'enabled'     => true,
        ],
        'linkedin'      => [
            'label'       => 'LinkedIn',
            'key'         => 'linkedin',
            'description' => 'Contacts coming from the LinkedIn.',
            'enabled'     => true,
        ],
        'referral'      => [
            'label'       => 'Referral',
            'key'         => 'referral',
            'description' => 'Contacts coming from relational network.',
            'enabled'     => true,
        ],
        'advertisement' => [
            'label'       => 'Advertisement',
            'key'         => 'advertisement',
            'description' => 'Contacts coming an advertisement campaign.',
            'enabled'     => true,
        ],
        'transfer' => [
            'label'       => 'Transfer',
            'key'         => 'transfer',
            'description' => 'Contacts coming an contact transfer partnership with another Disciple.Tools site.',
            'enabled'     => true,
        ],

    ];
    $fields["contact_address_types"] = [
        "home"  => [ "label" => __( 'Home', 'disciple_tools' ) ],
        "work"  => [ "label" => __( 'Work', 'disciple_tools' ) ],
        "other" => [ "label" => __( 'Other', 'disciple_tools' ) ],
    ];


    // $fields = apply_filters( 'dt_site_custom_lists', $fields );

        return $fields[ $list_title ] ?? $fields;
}

function dt_get_location_levels() {
    $fields = [];

    $fields['version'] = 3;

    $fields['location_levels'] = [
        'country' => 1,
        'administrative_area_level_1' => 1,
        'administrative_area_level_2' => 1,
        'administrative_area_level_3' => 0,
        'administrative_area_level_4' => 0,
        'locality' => 0,
        'neighborhood' => 0,
    ];

    $fields['location_levels_labels'] = [
        'country' => 'Country',
        'administrative_area_level_1' => 'Admin Level 1 (ex. state / province) ',
        'administrative_area_level_2' => 'Admin Level 2 (ex. county)',
        'administrative_area_level_3' => 'Admin Level 3',
        'administrative_area_level_4' => 'Admin Level 4',
        'locality' => 'Locality (ex. city name)',
        'neighborhood' => 'Neighborhood',
    ];

    return $fields;
}

/**
 * Processes the current configurations and upgrades the site options to the new version with persistent configuration settings.
 *
 * @return bool
 */
function dt_site_options_upgrade_version( string $name ) {
    $site_options_current = get_option( $name );
    if ( $name === "dt_site_custom_lists" ){
        $site_options_defaults = dt_get_site_custom_lists();
    } else if ( $name === "dt_site_options" ){
        $site_options_defaults = dt_get_site_options_defaults();
    } else {
        return false;
    }

    $new_version_number = $site_options_defaults['version'];

    if ( !is_array( $site_options_current ) ) {
        return false;
    }

    $new_options = array_replace_recursive( $site_options_defaults, $site_options_current );
    $new_options['version'] = $new_version_number;

    return update_option( $name, $new_options, "no" );
}

/**
 * Prepare input "type" from custom list types
 *
 * @param $type
 *
 * @return string
 */
function dt_prepare_user_fields_types_for_input( $type ) {
    switch ( $type ) {
        case 'phone':
            return 'tel';
            break;
        case 'email':
            return 'email';
            break;
        case 'social':
            return 'text';
            break;
        case 'address':
            return 'text';
            break;
        case 'other':
            return 'text';
            break;
        default:
            return 'text';
            break;
    }
}

/**
 * @param $approved
 * @param $commentdata
 *
 * @return int
 */
function dt_filter_handler( $approved, $commentdata ){
    // inspect $commentdata to determine approval, disapproval, or spam status
    //approve all comments.
    return 1;
}

function dt_custom_dir_attr( $lang ){
    if (is_admin()) {
        return $lang;
    }

    $current_user = wp_get_current_user();
    $user_language = $current_user->locale;
    $dir = _x( 'ltr', 'either rtl or ltr', 'disciple_tools' );
    if ( $dir === 'ltr' || $dir === 'text direction' || !$dir || empty( $dir ) ){
        $dir = "ltr";
    } else {
        $dir = "rtl";
    }
    $dir_attr = 'dir="' . $dir . '"';
    return 'lang="' . $user_language .'" ' .$dir_attr;
}


/**
 * @param $message
 * @param $key
 * @param $user_login
 * @param $user_data
 *
 * @return string
 */
function dt_custom_password_reset( $message, $key, $user_login, $user_data ){

    if ( is_multisite() ) {
        $site_name = get_network()->site_name;
    } else {
        $site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
    }

    $message = __( 'Someone has requested a password reset for the following account:' ) . "\r\n\r\n";
    /* translators: %s: site name */
    $message .= sprintf( __( 'Site Name: %s' ), $site_name ) . "\r\n\r\n";
    /* translators: %s: user login */
    $message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
    $message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
    $message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
    $message .= network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . "\r\n";

    return $message;

}

function dt_wpmu_signup_blog_notification_email( $message, $domain, $path, $title, $user, $user_email, $key, $meta ){
    return str_replace( "blog", "site", $message );
}

/**
 * change the error message if it is invalid_username or incorrect password
 *
 * @param $message string Error string provided by WordPress
 * @return $message string Modified error string
*/
function login_error_messages( $message ){
    global $errors;
    if ( isset( $errors->errors['invalid_username'] ) || isset( $errors->errors['incorrect_password'] ) ) {
        $message = __( '<strong>ERROR</strong>: Invalid username/password combination.', 'disciple_tools' ) . ' ' .
        sprintf(
            ( '<a href="%1$s" title="%2$s">%3$s</a>?' ),
            site_url( 'wp-login.php?action=lostpassword', 'login' ),
            __( 'Reset password', 'disciple_tools' ),
            __( 'Lost pour password', 'disciple_tools' )
        );
    }
    return $message;
}
