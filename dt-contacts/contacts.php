<?php
/**
 * @package  Disciple_Tools
 * @category Plugin
 * @author   Chasm.Solutions & Kingdom.Training
 * @since    0.1.0
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

/**
 * Class Disciple_Tools_Contacts
 */
class Disciple_Tools_Contacts extends Disciple_Tools_Posts
{
    public static $channel_list;
    public static $address_types;
    public static $contact_connection_types;
    
    public static $contact_phone_headings;
    public static $contact_address_headings;
    public static $contact_email_headings;
    public static $contact_name_headings;
    public static $contact_gender_headings;
    public static $contact_notes_headings;

    /**
     * Disciple_Tools_Contacts constructor.
     */
    public function __construct() {
        self::$channel_list = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        self::$address_types = dt_get_option( "dt_site_custom_lists" )["contact_address_types"];
        self::$contact_connection_types = [
            "locations",
            "groups",
            "people_groups",
            "baptized_by",
            "baptized",
            "coached_by",
            "coaching",
            "subassigned",
            "relation"
        ];
        add_action( "dt_contact_created", [ $this, "check_for_duplicates" ], 10, 2 );
        add_action( "dt_contact_updated", [ $this, "check_for_duplicates" ], 10, 2 );
        add_action( "dt_contact_updated", [ $this, "check_seeker_path" ], 10, 4 );
    
        self::$contact_phone_headings = [ 
                                'contact_phone',
                                'phone',
                                'mobile',
                                'telephone',
                        ];
        
        self::$contact_address_headings = [
                                'contact_address',
                                'address'
                        ];
        
        self::$contact_email_headings = [ 
                                'contact_email',               
                                'email',
                                'email_address',
                        ];
        
        self::$contact_name_headings = [ 
                                'title',               
                                'name',
                                'contact_name',
                        ];
        self::$contact_gender_headings = [ 
                                'gender',               
                                'sex',
                        ];
        
        self::$contact_notes_headings = [
                                'cf_notes',
                                'note', 
                                'notes',
                                'comment',
                                'comments'
            ];

        parent::__construct();
    }

    /**
     * Helper method for creating a WP_Query with pagination and ordering
     * separated into a separate argument for validation.
     * These two statements are equivalent in this example:
     * $query = self::query_with_pagination( [ "post_type" => "contacts" ], [ "orderby" => "ID" ] );
     * // equivalent to:
     * $query = new WP_Query( [ "post_type" => "contacts", "orderby" => "ID" ] );
     * The second argument, $query_pagination_args, may only contain keys
     * related to ordering and pagination, if it doesn't, this method will
     * return a WP_Error instance. This is useful in case you want to allow a
     * caller to modify pagination and ordering, but not anything else, in
     * order to keep permission checking valid. If $query_pagination_args is
     * specified with at least one value, then all pagination-related keys in
     * the first argument are ignored.
     *
     * @param array $query_args
     * @param array $query_pagination_args
     *
     * @return WP_Query | WP_Error
     */
    private static function query_with_pagination( array $query_args, array $query_pagination_args ) {
        $allowed_keys = [
            'order',
            'orderby',
            'nopaging',
            'posts_per_page',
            'posts_per_archive_page',
            'offset',
            'paged',
            'page',
            'ignore_sticky_posts',
        ];
        $error = new WP_Error();
        foreach ( $query_pagination_args as $key => $value ) {
            if ( !in_array( $key, $allowed_keys ) ) {
                $error->add( __FUNCTION__, sprintf( "Key %s was an unexpected pagination key", $key ) );
            }
        }
        if ( count( $error->errors ) ) {
            return $error;
        }
        if ( count( $query_pagination_args ) ) {
            foreach ( $allowed_keys as $pagination_key ) {
                unset( $query_args[ $pagination_key ] );
            }
        }

        return new WP_Query( array_merge( $query_args, $query_pagination_args ) );
    }

    /**
     * @return mixed
     */
    public static function get_contact_fields() {
        return Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
    }

    /**
     * @return mixed
     */
    public static function get_channel_list() {
        return self::$channel_list;
    }



    public function get_field_details( $field, $contact_id ){
        if ( $field === "title" ){
            return [
                "type" => "text",
                "name" => __( "Name", 'disciple_tools' ),
            ];
        }
        if ( strpos( $field, "contact_" ) === 0 ){
            $channel = explode( '_', $field );
            if ( isset( $channel[1] ) && self::$channel_list[ $channel[1] ] ){
                return [
                    "type" => "contact_method",
                    "name" => self::$channel_list[ $channel[1] ]["label"],
                    "channel" => $channel[1]
                ];
            }
        }
        if ( in_array( $field, self::$contact_connection_types ) ){
            return [
                "type" => "connection",
                "name" => $field
            ];
        }

        if ( $contact_id ){
            $contact_fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings( isset( $contact_id ), $contact_id );
        } else {
            $contact_fields = $contact_fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
        }
        if ( isset( $contact_fields[$field] ) ){
            return $contact_fields[ $field ];
        }

        return [
            "type" => "unknown",
            "name" => "unknown"
        ];
    }

    /**
     * Create a new Contact
     *
     * @param  array     $fields , the new contact's data
     * @param  bool|true $check_permissions
     * @param  bool|true $silent
     *
     * @access private
     * @since  0.1.0
     * @return int | WP_Error
     */
    public static function create_contact( array $fields = [], $check_permissions = true, $silent = false ) {
        
//echo '<pre>'; print_r($fields); echo '</pre>';
        
        if ( $check_permissions && !current_user_can( 'create_contacts' ) ) {
            return new WP_Error( __FUNCTION__, "You may not publish a contact", [ 'status' => 403 ] );
        }
        $fields = dt_sanitize_array_html( $fields );

        $initial_fields = $fields;
        $contact_fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();

        $continue = apply_filters( "dt_create_contact_check_proceed", true, $fields );
        if ( !$continue ){
            return new WP_Error( __FUNCTION__, "Could not create this contact. Maybe it already exists", [ 'status' => 409 ] );
        }

        //required fields
        if ( !isset( $fields["title"] ) ) {
            return new WP_Error( __FUNCTION__, "Contact needs a title", [ 'fields' => $fields ] );
        }

        //make sure the assigned to is in the right format (user-1)
        if ( isset( $fields["assigned_to"] ) ) {
            if ( filter_var( $fields["assigned_to"], FILTER_VALIDATE_EMAIL ) ){
                $user = get_user_by( "email", $fields["assigned_to"] );
                if ( $user ) {
                    $fields["assigned_to"] = $user->ID;
                } else {
                    return new WP_Error( __FUNCTION__, "Unrecognized user", $fields["assigned_to"] );
                }
            }
            if ( is_numeric( $fields["assigned_to"] ) ||
                 strpos( $fields["assigned_to"], "user" ) === false ){
                $fields["assigned_to"] = "user-" . $fields["assigned_to"];
            }
        }

        $create_date = null;
        if ( isset( $fields["create_date"] )){
            $create_date = $fields["create_date"];
            unset( $fields["create_date"] );
        }
        $initial_comment = null;
        if ( isset( $fields["initial_comment"] ) ) {
            $initial_comment = $fields["initial_comment"];
            unset( $fields["initial_comment"] );
        }
        $notes = null;
        if ( isset( $fields["notes"] ) ) {
            if ( is_array( $fields["notes"] ) ) {
                $notes = $fields["notes"];
                unset( $fields["notes"] );
            } else {
                return new WP_Error( __FUNCTION__, "'notes' field expected to be an array" );
            }
        }

        $bad_fields = self::check_for_invalid_fields( $fields );
        if ( !empty( $bad_fields ) ) {
            return new WP_Error( __FUNCTION__, "These fields do not exist", [ 'bad_fields' => $bad_fields ] );
        }

        $current_roles = wp_get_current_user()->roles;

        $defaults = [
            "seeker_path"    => "none",
            "type" => "media",
            "last_modified" => time(),
        ];
        if ( get_current_user_id() ) {
            $defaults["assigned_to"] = sprintf( "user-%d", get_current_user_id() );
        } else {
            $base_id = dt_get_base_user( true );
            if ( is_wp_error( $base_id ) ) { // if default editor does not exist, get available administrator
                $users = get_users( [ 'role' => 'administrator' ] );
                if ( count( $users ) > 0 ) {
                    foreach ( $users as $user ) {
                        $base_id = $user->ID;
                    }
                }
            }
            $defaults["assigned_to"] = sprintf( "user-%d", $base_id );
        }

        if (in_array( "dispatcher", $current_roles, true ) || in_array( "marketer", $current_roles, true )) {
            $defaults["overall_status"] = "unassigned";
        } else if (in_array( "multiplier", $current_roles, true ) ) {
            $defaults["overall_status"] = "active";
        } else {
            $defaults["overall_status"] = "unassigned";
        }

        $fields = array_merge( $defaults, $fields );

        $title = $fields["title"];
        unset( $fields["title"] );

        $contact_methods_and_connections = [];
        $multi_select_fields = [];
        foreach ( $fields as $field_key => $field_value ){
            if ( self::is_key_contact_method_or_connection( $field_key )){
                $contact_methods_and_connections[$field_key] = $field_value;
                unset( $fields[$field_key] );
            }
            $field_type = $contact_fields[$field_key]["type"] ?? '';
            if ( $field_type === "multi_select" ){
                $multi_select_fields[$field_key] = $field_value;
                unset( $fields[$field_key] );
            }
            if ( $field_type === 'date' && !is_numeric( $field_value )){
                $fields[$field_value] = strtotime( $field_value );
            }
        }

        $post = [
            "post_title"  => $title,
            'post_type'   => "contacts",
            "post_status" => 'publish',
            "meta_input"  => $fields,
        ];
        if ( $create_date ){
            $post["post_date"] = $create_date;
        }

        $post_id = wp_insert_post( $post );

        if ( isset( $fields["assigned_to"] )) {
            $user_id = explode( '-', $fields["assigned_to"] )[1];
            if ( $user_id ) {
                self::add_shared( "contacts", $post_id, $user_id, null, false, false, false );
            }
        }

        $potential_error = self::parse_contact_methods( $post_id, $contact_methods_and_connections );
        if ( is_wp_error( $potential_error )){
            return $potential_error;
        }

        $potential_error = self::parse_connections( $post_id, $contact_methods_and_connections, null );
        if ( is_wp_error( $potential_error )){
            return $potential_error;
        }

        $potential_error = self::parse_multi_select_fields( $post_id, $multi_select_fields, null );
        if ( is_wp_error( $potential_error )){
            return $potential_error;
        }

        if ( $initial_comment ) {
            $potential_error = self::add_comment( $post_id, $initial_comment, "comment", [], false );
            if ( is_wp_error( $potential_error ) ) {
                return $potential_error;
            }
        }

        if ( $notes ) {
            if ( ! is_array( $notes ) ) {
                return new WP_Error( 'notes_not_array', 'Notes must be an array' );
            }
            $error = new WP_Error();
            foreach ( $notes as $note ) {
                $potential_error = self::add_comment( $post_id, $note, "comment", [], false );
                if ( is_wp_error( $potential_error ) ) {
                    $error->add( 'comment_fail', $potential_error->get_error_message() );
                }
            }
            if ( count( $error->get_error_messages() ) > 0 ) {
                return $error;
            }
        }

        //hook for signaling that a contact has been created and the initial fields
        if ( !is_wp_error( $post_id )){
            do_action( "dt_contact_created", $post_id, $initial_fields );
            if ( !$silent ){
                Disciple_Tools_Notifications::insert_notification_for_new_post( "contacts", $fields, $post_id );
            }
        }

        return $post_id;
    }

    private static function is_key_contact_method_or_connection( $key ) {
        $channel_keys = [];
        foreach ( self::$channel_list as $channel_key => $channel_value ) {
            $channel_keys[] = "contact_" . $channel_key;
        }
        return in_array( $key, self::$contact_connection_types ) || in_array( $key, $channel_keys );
    }

    /**
     * Make sure there are no extra or misspelled fields
     * Make sure the field values are the correct format
     *
     * @param          $fields  , the contact meta fields
     * @param int|null $post_id , the id of the contact
     *
     * @access private
     * @since  0.1.0
     * @return array
     */
    private static function check_for_invalid_fields( $fields, int $post_id = null ) {
        $bad_fields = [];
        $contact_fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings( isset( $post_id ), $post_id );
        $contact_fields['title'] = "";
        foreach ( $fields as $field => $value ) {
            if ( !isset( $contact_fields[ $field ] ) && !self::is_key_contact_method_or_connection( $field ) ) {
                $bad_fields[] = $field;
            }
        }

        return $bad_fields;
    }

    private static function parse_multi_select_fields( $contact_id, $fields, $existing_contact = null ){
        $contact_fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
        foreach ( $fields as $field_key => $field ){
            if ( isset( $contact_fields[$field_key] ) && $contact_fields[$field_key]["type"] === "multi_select" ){
                if ( !isset( $field["values"] )){
                    return new WP_Error( __FUNCTION__, "missing values field on: " . $field_key );
                }
                if ( isset( $field["force_values"] ) && $field["force_values"] === true ){
                    delete_post_meta( $contact_id, $field_key );
                }
                foreach ( $field["values"] as $value ){
                    if ( isset( $value["value"] )){
                        if ( isset( $value["delete"] ) && $value["delete"] == true ){
                            delete_post_meta( $contact_id, $field_key, $value["value"] );
                        } else {
                            $existing_array = isset( $existing_contact[ $field_key ] ) ? $existing_contact[ $field_key ] : [];
                            if ( !in_array( $value["value"], $existing_array ) ){
                                add_post_meta( $contact_id, $field_key, $value["value"] );
                            }
                        }
                    } else {
                        return new WP_Error( __FUNCTION__, "Something wrong on field: " . $field_key );
                    }
                }
            }
        }
        return $fields;
    }

    private static function parse_contact_methods( $contact_id, $fields, $existing_contact = null ){
        $contact_details_field_keys = array_keys( self::$channel_list );
        // update contact details (phone, facebook, etc)
        foreach ( $contact_details_field_keys as $channel_key ){
            $details_key = "contact_" . $channel_key;
            $values = [];
            if ( isset( $fields[$details_key] ) && isset( $fields[$details_key]["values"] ) ){
                $values = $fields[$details_key]["values"];
            } else if ( isset( $fields[$details_key] ) && is_array( $fields[$details_key] ) ) {
                $values = $fields[$details_key];
            }
            if ( $existing_contact && isset( $fields[$details_key] ) &&
                 isset( $fields[$details_key]["force_values"] ) &&
                 $fields[$details_key]["force_values"] === true ){
                foreach ( $existing_contact[$details_key] as $contact_value ){
                    $potential_error = self::delete_contact_field( $contact_id, $contact_value["key"], false );
                    if ( is_wp_error( $potential_error ) ){
                        return $potential_error;
                    }
                }
            }
            foreach ( $values as $field ){
                if ( isset( $field["delete"] ) && $field["delete"] == true){
                    if ( !isset( $field["key"] )){
                        return new WP_Error( __FUNCTION__, "missing key on: " . $details_key );
                    }
                    //delete field
                    $potential_error = self::delete_contact_field( $contact_id, $field["key"] );
                } else if ( isset( $field["key"] ) ){
                    //update field
                    $potential_error = self::update_contact_method( $contact_id, $field["key"], $field, false );
                } else if ( isset( $field["value"] ) ) {
                    $field["key"] = "new-".$channel_key;
                    //create field
                    $potential_error = self::add_contact_method( $contact_id, $field["key"], $field["value"], $field, false );

                } else {
                    return new WP_Error( __FUNCTION__, "Is not an array or missing value on: " . $details_key );
                }
                if ( isset( $potential_error ) && is_wp_error( $potential_error ) ) {
                    return $potential_error;
                }
            }
        }
        return $fields;
    }



    private static function parse_connections( $contact_id, $fields, $existing_contact){
        //update connections (groups, locations, etc)
        foreach ( self::$contact_connection_types as $connection_type ){
            if ( isset( $fields[$connection_type] ) ){
                if ( !isset( $fields[$connection_type]["values"] )){
                    return new WP_Error( __FUNCTION__, "Missing values field on connection: " . $connection_type, [ 'status' => 500 ] );
                }
                $existing_connections = [];
                if ( isset( $existing_contact[$connection_type] ) ){
                    foreach ( $existing_contact[$connection_type] as $connection){
                        $existing_connections[] = $connection->ID;
                    }
                }
                //check for new connections
                $connection_field = $fields[$connection_type];
                $new_connections = [];
                foreach ($connection_field["values"] as $connection_value ){
                    if ( isset( $connection_value["value"] ) && !is_numeric( $connection_value["value"] ) ){
                        if ( filter_var( $connection_value["value"], FILTER_VALIDATE_EMAIL ) ){
                            $user = get_user_by( "email", $connection_value["value"] );
                            if ( $user ){
                                $corresponding_contact = Disciple_Tools_Users::get_contact_for_user( $user->ID );
                                if ( $corresponding_contact ){
                                    $connection_value["value"] = $corresponding_contact;
                                }
                            }
                        } else {
                            $post_types = self::$contact_connection_types;
                            $post_types[] = "contacts";
                            $post = self::get_post_by_title_cached( $connection_value["value"], OBJECT, $post_types, $connection_type );
                            if ( $post && !is_wp_error( $post ) ){
                                $connection_value["value"] = $post->ID;
                            }
                        }
                    }

                    if ( isset( $connection_value["value"] ) && is_numeric( $connection_value["value"] )){
                        if ( isset( $connection_value["delete"] ) && $connection_value["delete"] === true ){
                            if ( in_array( $connection_value["value"], $existing_connections )){
                                $potential_error = self::remove_contact_connection( $contact_id, $connection_type, $connection_value["value"], false );
                                if ( is_wp_error( $potential_error ) ) {
                                    return $potential_error;
                                }
                            }
                        } else if ( !empty( $connection_value["value"] )) {
                            $new_connections[] = $connection_value["value"];
                            if ( !in_array( $connection_value["value"], $existing_connections )){
                                $potential_error = self::add_contact_detail( $contact_id, $connection_type, $connection_value["value"], false );
                                $existing_connections[] = $connection_value["value"];
                                if ( is_wp_error( $potential_error ) ) {
                                    return $potential_error;
                                }
                                $fields["added_fields"][$connection_type] = $potential_error;
                            }
                        }
                    } else {
                         return new WP_Error( __FUNCTION__, "Cannot determine target on connection: " . $connection_type, [ 'status' => 500 ] );
                    }
                }
                //check for deleted connections
                if ( isset( $connection_field["force_values"] ) && $connection_field["force_values"] === true ){
                    foreach ($existing_connections as $connection_value ){
                        if ( !in_array( $connection_value, $new_connections )){
                            $potential_error = self::remove_contact_connection( $contact_id, $connection_type, $connection_value, false );
                            if ( is_wp_error( $potential_error ) ) {
                                return $potential_error;
                            }
                        }
                    }
                }
            }
        }
        return $fields;
    }


    public static function close_duplicate_contact( int $duplicate_id, int $contact_id) {
        $duplicate = self::get_contact( $duplicate_id );
        $contact = self::get_contact( $contact_id );

        self::update_contact( $duplicate_id, [
            "overall_status" => "closed",
            "reason_closed" => "duplicate",
            "duplicate_of" => $contact_id
        ] );

        $link = "<a href='" . get_permalink( $contact_id ) . "'>{$contact['title']}</a>";
        $comment = sprintf( esc_html_x( '%1$s is a duplicate and was merged into %2$s', 'Contact1 is a duplicated and was merged into Contact2', 'disciple_tools' ), $duplicate['title'], $link );

        self::add_comment( $duplicate_id, $comment, "duplicate", [], true, true );

        //comment on master
        $link = "<a href='" . get_permalink( $duplicate_id ) . "'>{$duplicate['title']}</a>";
        $comment = sprintf( esc_html_x( '%1$s was merged into %2$s', 'Contact1 was merged into Contact2', 'disciple_tools' ), $link, $contact['title'] );
        self::add_comment( $contact_id, $comment, "duplicate", [], true, true );
    }


    /**
     * Update an existing Contact
     *
     * @param  int|null $contact_id , the post id for the contact
     * @param  array $fields , the meta fields
     * @param  bool|null $check_permissions
     * @param bool $silent
     *
     * @return array | WP_Error the contact
     * @access public
     * @since  0.1.0
     */
    public static function update_contact( int $contact_id, array $fields, $check_permissions = true, bool $silent = false ) {

        if ( $check_permissions && !self::can_update( 'contacts', $contact_id ) ) {
            return new WP_Error( __FUNCTION__, "You do not have permission for this", [ 'status' => 403 ] );
        }
        $fields = dt_sanitize_array_html( $fields );
        $initial_fields = $fields;
        $initial_keys = array_keys( $fields );
        $contact_fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
        $post = get_post( $contact_id );
        if ( isset( $fields['id'] ) ) {
            unset( $fields['id'] );
        }

        if ( !$post ) {
            return new WP_Error( __FUNCTION__, "Contact does not exist" );
        }


        // don't try to update fields that don't exist
        $bad_fields = self::check_for_invalid_fields( $fields, $contact_id );
        if ( !empty( $bad_fields ) ) {
            return new WP_Error( __FUNCTION__, "These fields do not exist", [ 'bad_fields' => $bad_fields ] );
        }
        $existing_contact = self::get_contact( $contact_id, false );

        if ( isset( $fields['title'] ) && $existing_contact["title"] != $fields['title'] ) {
            wp_update_post( [
                'ID' => $contact_id,
                'post_title' => $fields['title']
            ] );
            dt_activity_insert( [
                'action'            => 'field_update',
                'object_type'       => "contacts",
                'object_subtype'    => 'title',
                'object_id'         => $contact_id,
                'object_name'       => $fields['title'],
                'meta_key'          => 'title',
                'meta_value'        => $fields['title'],
                'old_value'         => $existing_contact['title'],
            ] );
        }

        $potential_error = self::parse_contact_methods( $contact_id, $fields, $existing_contact );
        if ( is_wp_error( $potential_error )){
            return $potential_error;
        }

        $potential_error = self::parse_connections( $contact_id, $fields, $existing_contact );
        if ( is_wp_error( $potential_error )){
            return $potential_error;
        }

        $potential_error = self::parse_multi_select_fields( $contact_id, $fields, $existing_contact );
        if ( is_wp_error( $potential_error )){
            return $potential_error;
        }


        if ( isset( $fields["assigned_to"] ) ) {
            if ( filter_var( $fields["assigned_to"], FILTER_VALIDATE_EMAIL ) ){
                $user = get_user_by( "email", $fields["assigned_to"] );
                if ( $user ) {
                    $fields["assigned_to"] = $user->ID;
                } else {
                    return new WP_Error( __FUNCTION__, "Unrecognized user", $fields["assigned_to"] );
                }
            }
            //make sure the assigned to is in the right format (user-1)
            if ( is_numeric( $fields["assigned_to"] ) ||
                 strpos( $fields["assigned_to"], "user" ) === false ){
                $fields["assigned_to"] = "user-" . $fields["assigned_to"];
            }
            if ( !isset( $existing_contact["assigned_to"] ) || $fields["assigned_to"] !== $existing_contact["assigned_to"]["assigned-to"] ){
                if ( current_user_can( "assign_any_contacts" ) ) {
                    $fields["overall_status"] = 'assigned';
                }
                $user_id = explode( '-', $fields["assigned_to"] )[1];
                if ( $user_id ){
                    self::add_shared( "contacts", $contact_id, $user_id, null, false, false, false );
                }
                $fields['accepted'] = false;
            }
        }

        if ( isset( $fields["reason_unassignable"] ) ){
            $fields["overall_status"] = 'unassignable';
        }

        if ( isset( $fields["seeker_path"] ) ){
            self::update_quick_action_buttons( $contact_id, $fields["seeker_path"] );
        }

        foreach ( $fields as $field_key => $value ){
            if ( strpos( $field_key, "quick_button" ) !== false ){
                self::handle_quick_action_button_event( $contact_id, [ $field_key => $value ] );
            }
        }


        $fields["last_modified"] = time(); //make sure the last modified field is updated.
        foreach ( $fields as $field_id => $value ) {
            if ( !self::is_key_contact_method_or_connection( $field_id ) ) {
                // Boolean contact field are stored as yes/no

                $field_type = $contact_fields[$field_id]["type"] ?? '';
                //we handle multi_select above.
                if ( $field_type === 'date' && !is_numeric( $value )){
                    $value = strtotime( $value );
                }

                if ( $field_type && $field_type !== "multi_select" ){
                    update_post_meta( $contact_id, $field_id, $value );
                }
            }
        }

        //if ( !isset( $fields["requires_update"] )){
            //only mark as updated with a comment or when is quick action button is pressed.
            //self::check_requires_update( $contact_id );
        //}
//        @todo permission?
        $contact = self::get_contact( $contact_id, false );
        if (isset( $fields["added_fields"] )){
            $contact["added_fields"] = $fields["added_fields"];
        }

        //hook for signaling that a contact has been updated and which keys have been changed
        if ( !is_wp_error( $contact )){
            do_action( "dt_contact_updated", $contact_id, $initial_fields, $contact, $existing_contact );
            if ( !$silent ){
                Disciple_Tools_Notifications::insert_notification_for_post_update( "contacts", $contact, $existing_contact, $initial_keys );
            }
        }

        return $contact;
    }

    //check to see if the contact is marked as needing an update
    //if yes: mark as updated
    private static function check_requires_update( $contact_id ){
        if ( get_current_user_id() ){
            $requires_update = get_post_meta( $contact_id, "requires_update", true );
            if ( $requires_update == "yes" || $requires_update == true || $requires_update = "1"){
                //don't remove update needed if the user is a dispatcher (and not assigned to the contacts.)
                if ( self::can_view_all( 'contacts' ) ){
                    if ( dt_get_user_id_from_assigned_to( get_post_meta( $contact_id, "assigned_to", true ) ) === get_current_user_id() ){
                        update_post_meta( $contact_id, "requires_update", false );
                    }
                } else {
                    update_post_meta( $contact_id, "requires_update", false );
                }
            }
        }
    }


    /**
     * @param $contact_id
     * @param $location_id
     *
     * @return mixed
     */
    public static function add_location_to_contact( $contact_id, $location_id ) {
        return p2p_type( 'contacts_to_locations' )->connect(
            $location_id, $contact_id,
            [ 'date' => current_time( 'mysql' ) ]
        );
    }

    /**
     * @param $contact_id
     * @param $group_id
     *
     * @return mixed
     */
    public static function add_group_to_contact( $contact_id, $group_id ) {
        // share the group with the owner of the contact.
        $assigned_to = get_post_meta( $contact_id, "assigned_to", true );
        if ( $assigned_to && strpos( $assigned_to, "-" ) !== false ){
            $user_id = explode( "-", $assigned_to )[1];
            if ( $user_id ){
                self::add_shared( "groups", $group_id, $user_id, null, false, false );
            }
        }
        $added = p2p_type( 'contacts_to_groups' )->connect(
            $group_id, $contact_id,
            [ 'date' => current_time( 'mysql' ) ]
        );
        do_action( 'group_member_count', $group_id, "added" );
        return $added;
    }

    /**
     * @param $contact_id
     * @param $people_group_id
     *
     * @return mixed
     */
    public static function add_people_group_to_contact( $contact_id, $people_group_id ) {
        return p2p_type( 'contacts_to_peoplegroups' )->connect(
            $people_group_id, $contact_id,
            [ 'date' => current_time( 'mysql' ) ]
        );
    }

    /**
     * @param $contact_id
     * @param $baptized_by
     *
     * @return mixed
     */
    public static function add_baptized_by_to_contact( $contact_id, $baptized_by ) {
        $p2p = p2p_type( 'baptizer_to_baptized' )->connect(
            $contact_id, $baptized_by,
            [ 'date' => current_time( 'mysql' ) ]
        );
//        $baptism_date = get_post_meta( $contact_id, 'baptism_date', true );
//        if ( empty( $baptism_date )){
//            update_post_meta( $contact_id, "baptism_date", time() );
//        }
        $milestones = get_post_meta( $contact_id, 'milestones' );
        if ( empty( $milestones ) || !in_array( "milestone_baptized", $milestones ) ){
            add_post_meta( $contact_id, "milestones", "milestone_baptized" );
        }
        Disciple_Tools_Counter_Baptism::reset_baptism_generations_on_contact_tree( $contact_id );
        return $p2p;
    }

    /**
     * @param $contact_id
     * @param $baptized
     *
     * @return mixed
     */
    public static function add_baptized_to_contact( $contact_id, $baptized ) {
        $p2p = p2p_type( 'baptizer_to_baptized' )->connect(
            $baptized, $contact_id,
            [ 'date' => current_time( 'mysql' ) ]
        );
        Disciple_Tools_Counter_Baptism::reset_baptism_generations_on_contact_tree( $baptized );
        return $p2p;

    }

    /**
     * @param $contact_id
     * @param $coached_by
     *
     * @return mixed
     */
    public static function add_coached_by_to_contact( $contact_id, $coached_by ) {
        return p2p_type( 'contacts_to_contacts' )->connect(
            $contact_id, $coached_by,
            [ 'date' => current_time( 'mysql' ) ]
        );
    }

    /**
     * @param $contact_id
     * @param $coaching
     *
     * @return mixed
     */
    public static function add_coaching_to_contact( $contact_id, $coaching ) {
        return p2p_type( 'contacts_to_contacts' )->connect(
            $coaching, $contact_id,
            [ 'date' => current_time( 'mysql' ) ]
        );
    }

    public static function add_relation_to_contact( $contact_id, $relation ) {
        return p2p_type( 'contacts_to_relation' )->connect(
            $contact_id, $relation,
            [ 'date' => current_time( 'mysql' ) ]
        );
    }

    /**
     * @param $contact_id
     * @param $subassigned
     *
     * @return mixed
     */
    public static function add_subassigned_to_contact( $contact_id, $subassigned ) {

        $user_id = get_post_meta( $subassigned, "corresponds_to_user", true );
        if ( $user_id ){
            self::add_shared_on_contact( $contact_id, $user_id, null, false, false, false );
            Disciple_Tools_Notifications::insert_notification_for_subassigned( $user_id, $contact_id );
        }

        return p2p_type( 'contacts_to_subassigned' )->connect(
            $subassigned, $contact_id,
            [ 'date' => current_time( 'mysql' ) ]
        );
    }

    /**
     * @param $contact_id
     * @param $location_id
     *
     * @return mixed
     */
    public static function remove_location_from_contact( $contact_id, $location_id ) {
        return p2p_type( 'contacts_to_locations' )->disconnect( $location_id, $contact_id );
    }

    /**
     * @param $contact_id
     * @param $people_group_id
     *
     * @return mixed
     */
    public static function remove_group_from_contact( $contact_id, $group_id ) {
        $removed = p2p_type( 'contacts_to_groups' )->disconnect( $group_id, $contact_id );
        if ( !is_wp_error( $removed ) ){
            do_action( 'group_member_count', $group_id, "removed" );
        }
        return $removed;
    }

    /**
     * @param $contact_id
     * @param $group_id
     *
     * @return mixed
     */
    public static function remove_people_group_from_contact( $contact_id, $group_id ) {
        return p2p_type( 'contacts_to_peoplegroups' )->disconnect( $group_id, $contact_id );
    }

    /**
     * @param $contact_id
     * @param $baptized_by
     *
     * @return mixed
     */
    public static function remove_baptized_by_from_contact( $contact_id, $baptized_by ) {
        $p2p = p2p_type( 'baptizer_to_baptized' )->disconnect( $contact_id, $baptized_by );
        Disciple_Tools_Counter_Baptism::reset_baptism_generations_on_contact_tree( $contact_id );
        return $p2p;
    }

    /**
     * @param $contact_id
     * @param $baptized
     *
     * @return mixed
     */
    public static function remove_baptized_from_contact( $contact_id, $baptized ) {
        $p2p = p2p_type( 'baptizer_to_baptized' )->disconnect( $baptized, $contact_id );
        Disciple_Tools_Counter_Baptism::reset_baptism_generations_on_contact_tree( $baptized );
        return $p2p;
    }

    /**
     * @param $contact_id
     * @param $coached_by
     *
     * @return mixed
     */
    public static function remove_coached_by_from_contact( $contact_id, $coached_by ) {
        return p2p_type( 'contacts_to_contacts' )->disconnect( $contact_id, $coached_by );
    }

    /**
     * @param $contact_id
     * @param $coaching
     *
     * @return mixed
     */
    public static function remove_coaching_from_contact( $contact_id, $coaching ) {
        return p2p_type( 'contacts_to_contacts' )->disconnect( $coaching, $contact_id );
    }

    /**
     * @param $contact_id
     * @param $subassigned
     *
     * @return mixed
     */
    public static function remove_subassigned_from_contact( $contact_id, $subassigned ) {
        return p2p_type( 'contacts_to_subassigned' )->disconnect( $subassigned, $contact_id );
    }

    public static function remove_relation_from_contact( $contact_id, $relation ) {
        $success = p2p_type( 'contacts_to_relation' )->disconnect( $contact_id, $relation );
        if ( $success === 0 ){
            $success = p2p_type( 'contacts_to_relation' )->disconnect( $relation, $contact_id );
        }
        return $success;
    }

    public static function remove_fields( $contact_id, $fields = [], $ignore = []) {
        global $wpdb;
        foreach ($fields as $field) {
            $ignore_keys = preg_grep( "/$field/", $ignore );
            $sql = "delete
                from
                    wp_postmeta
                where
                    post_id = %d and
                    meta_key like %s";
            $params = array( $contact_id, "$field%" );
            if ( !empty( $ignore_keys )) {
                foreach ( $ignore_keys as $key ){
                    $sql .= " and meta_key not like %s";
                }
                array_push( $params, ...$ignore_keys );
            }
            $wpdb->query( $wpdb->prepare( $sql, $params ) ); // @codingStandardsIgnoreLine
        }
    }

    /**
     * @param int       $contact_id
     * @param string    $key
     * @param string    $value
     * @param bool      $check_permissions
     *
     * @return array|mixed|null|string|\WP_Error|\WP_Post
     */
    public static function add_contact_detail( int $contact_id, string $key, string $value, bool $check_permissions ) {
        if ( $check_permissions && !self::can_update( 'contacts', $contact_id ) ) {
            return new WP_Error( __FUNCTION__, "You do not have permission for this", [ 'status' => 403 ] );
        }
        if ( strpos( $key, "new-" ) === 0 ) {
            $type = explode( '-', $key )[1];

            $new_meta_key = '';
            if ( isset( self::$channel_list[ $type ] ) ) {
                //check if this is a new field and is in the channel list
                $new_meta_key = Disciple_Tools_Contact_Post_Type::instance()->create_channel_metakey( $type, "contact" );
            }
            update_post_meta( $contact_id, $new_meta_key, $value );
            $details = [ "verified" => false ];
            update_post_meta( $contact_id, $new_meta_key . "_details", $details );

            return $new_meta_key;
        }
        $connect = null;
        if ( $key === "locations" ) {
            $connect = self::add_location_to_contact( $contact_id, $value );
        } elseif ( $key === "groups" ) {
            $connect = self::add_group_to_contact( $contact_id, $value );
        } elseif ( $key === "people_groups" ) {
            $connect = self::add_people_group_to_contact( $contact_id, $value );
        } else if ( $key === "baptized_by" ) {
            $connect = self::add_baptized_by_to_contact( $contact_id, $value );
        } elseif ( $key === "baptized" ) {
            $connect = self::add_baptized_to_contact( $contact_id, $value );
        } elseif ( $key === "coached_by" ) {
            $connect = self::add_coached_by_to_contact( $contact_id, $value );
        } elseif ( $key === "coaching" ) {
            $connect = self::add_coaching_to_contact( $contact_id, $value );
        } elseif ( $key === "subassigned" ){
            $connect = self::add_subassigned_to_contact( $contact_id, $value );
        } elseif ( $key === "relation" ){
            $connect = self::add_relation_to_contact( $contact_id, $value );
        } else {
            return new WP_Error( __FUNCTION__, "Field not recognized: " . $key, [ "status" => 400 ] );
        }
        if ( is_wp_error( $connect ) ) {
            return $connect;
        }
        if ( $connect ) {
            $connection = get_post( $value );
            $connection->permalink = get_permalink( $value );

            return $connection;
        } else {
            return new WP_Error( __FUNCTION__, "Field not parsed or understood: " . $key, [ "status" => 400 ] );
        }
    }

    public static function add_contact_method( int $contact_id, string $key, string $value, array $field, bool $check_permissions ) {
        if ( $check_permissions && ! self::can_update( 'contacts', $contact_id ) ) {
            return new WP_Error( __FUNCTION__, "You do not have permission for this", [ 'status' => 403 ] );
        }
        if ( strpos( $key, "new-" ) === 0 ) {
            $type = explode( '-', $key )[1];

            $new_meta_key = '';
            if ( isset( self::$channel_list[ $type ] ) ) {
                //check if this is a new field and is in the channel list
                $new_meta_key = Disciple_Tools_Contact_Post_Type::instance()->create_channel_metakey( $type, "contact" );
            }
            update_post_meta( $contact_id, $new_meta_key, $value );
            $details = [ "verified" => false ];
            foreach ( $field as $key => $value ){
                if ( $key != "value" && $key != "key" ){
                    $details[$key] = $value;
                }
            }
            update_post_meta( $contact_id, $new_meta_key . "_details", $details );

            return $new_meta_key;
        }
    }


    /**
     * @param int    $contact_id
     * @param string $key
     * @param array  $values
     * @param bool   $check_permissions
     *
     * @return int|\WP_Error
     */
    public static function update_contact_method( int $contact_id, string $key, array $values, bool $check_permissions ) {
        if ( $check_permissions && !self::can_update( 'contacts', $contact_id ) ) {
            return new WP_Error( __FUNCTION__, "You do not have permission for this", [ 'status' => 403 ] );
        }
        if ( ( strpos( $key, "contact_" ) === 0 || strpos( $key, "address_" ) === 0 ) &&
            strpos( $key, "_details" ) === false
        ) {
            $old_value = get_post_meta( $contact_id, $key, true );
            //check if it is different to avoid setting saving activity
            if ( isset( $values["value"] ) && $old_value != $values["value"] ){
                update_post_meta( $contact_id, $key, $values["value"] );
            }
            unset( $values["value"] );
            unset( $values["key"] );

            $details_key = $key . "_details";
            $old_details = get_post_meta( $contact_id, $details_key, true );
            $details = isset( $old_details ) ? $old_details : [];
            $new_value = false;
            foreach ( $values as $detail_key => $detail_value ) {
                if ( !isset( $details[$detail_key] ) || $details[$detail_key] !== $detail_value){
                    $new_value = true;
                }
                $details[ $detail_key ] = $detail_value;
            }
            if ($new_value){
                update_post_meta( $contact_id, $details_key, $details );
            }
        }

        return $contact_id;
    }

    /**
     * @param int     $contact_id
     * @param string  $key
     * @param string  $value
     * @param bool    $check_permissions
     *
     * @return bool|mixed|\WP_Error
     */
    public static function remove_contact_connection( int $contact_id, string $key, string $value, bool $check_permissions ) {
        if ( $check_permissions && !self::can_update( 'contacts', $contact_id ) ) {
            return new WP_Error( __FUNCTION__, "You do not have permission for this", [ 'status' => 403 ] );
        }
        if ( $key === "locations" ) {
            return self::remove_location_from_contact( $contact_id, $value );
        } elseif ( $key === "groups" ) {
            return self::remove_group_from_contact( $contact_id, $value );
        } elseif ( $key === "baptized_by" ) {
            return self::remove_baptized_by_from_contact( $contact_id, $value );
        } elseif ( $key === "baptized" ) {
            return self::remove_baptized_from_contact( $contact_id, $value );
        } elseif ( $key === "coached_by" ) {
            return self::remove_coached_by_from_contact( $contact_id, $value );
        } elseif ( $key === "coaching" ) {
            return self::remove_coaching_from_contact( $contact_id, $value );
        } elseif ( $key === "people_groups" ) {
            return self::remove_people_group_from_contact( $contact_id, $value );
        } elseif ( $key === "subassigned" ) {
            return self::remove_subassigned_from_contact( $contact_id, $value );
        } elseif ( $key === "relation" ) {
            return self::remove_relation_from_contact( $contact_id, $value );
        }

        return false;
    }

    /**
     * @param int $contact_id
     * @param string $key
     * @param bool $check_permissions
     *
     * @return bool|\WP_Error
     */
    public static function delete_contact_field( int $contact_id, string $key, $check_permissions = true ){
        if ( $check_permissions && !self::can_update( 'contacts', $contact_id )){
            return new WP_Error( __FUNCTION__, "You do not have permission for this", [ 'status' => 401 ] );
        }
        delete_post_meta( $contact_id, $key .'_details' );
        return delete_post_meta( $contact_id, $key );
    }

    /**
     * Get a single contact
     *
     * @param int  $contact_id , the contact post_id
     * @param bool $check_permissions
     * @param bool $load_cache
     *
     * @access public
     * @since  0.1.0
     * @return array| WP_Error, On success: the contact, else: the error message
     */
    public static function get_contact( int $contact_id, $check_permissions = true, $load_cache = false ) {
        if ( $check_permissions && !self::can_view( 'contacts', $contact_id ) ) {
            return new WP_Error( __FUNCTION__, "No permissions to read contact", [ 'status' => 403 ] );
        }
        $cached = wp_cache_get( "contact_" . $contact_id );
        if ( $cached && $load_cache ){
            return $cached;
        }

        $contact = get_post( $contact_id );
        $contact_fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
        if ( $contact ) {
            $fields = [];

            $locations = get_posts(
                [
                    'connected_type'   => 'contacts_to_locations',
                    'connected_items'  => $contact,
                    'nopaging'         => true,
                    'suppress_filters' => false,
                ]
            );
            foreach ( $locations as $l ) {
                $l->permalink = get_permalink( $l->ID );
            }
            $fields["locations"] = $locations;
            $groups = get_posts(
                [
                    'connected_type'   => 'contacts_to_groups',
                    'connected_items'  => $contact,
                    'nopaging'         => true,
                    'suppress_filters' => false,
                ]
            );
            foreach ( $groups as $g ) {
                $g->permalink = get_permalink( $g->ID );
            }
            $fields["groups"] = $groups;

            $people_groups = get_posts(
                [
                    'connected_type'   => 'contacts_to_peoplegroups',
                    'connected_items'  => $contact,
                    'nopaging'         => true,
                    'suppress_filters' => false,
                ]
            );
            foreach ( $people_groups as $g ) {
                $g->permalink = get_permalink( $g->ID );
            }
            $fields["people_groups"] = $people_groups;

            $baptized = get_posts(
                [
                    'connected_type'      => 'baptizer_to_baptized',
                    'connected_direction' => 'to',
                    'connected_items'     => $contact,
                    'nopaging'            => true,
                    'suppress_filters'    => false,
                ]
            );
            foreach ( $baptized as $b ) {
                $b->fields = p2p_get_meta( $b->p2p_id );
                $b->permalink = get_permalink( $b->ID );
            }
            $fields["baptized"] = $baptized;
            $baptized_by = get_posts(
                [
                    'connected_type'      => 'baptizer_to_baptized',
                    'connected_direction' => 'from',
                    'connected_items'     => $contact,
                    'nopaging'            => true,
                    'suppress_filters'    => false,
                ]
            );
            foreach ( $baptized_by as $b ) {
                $b->fields = p2p_get_meta( $b->p2p_id );
                $b->permalink = get_permalink( $b->ID );
            }
            $fields["baptized_by"] = $baptized_by;
            $coaching = get_posts(
                [
                    'connected_type'      => 'contacts_to_contacts',
                    'connected_direction' => 'to',
                    'connected_items'     => $contact,
                    'nopaging'            => true,
                    'suppress_filters'    => false,
                ]
            );
            foreach ( $coaching as $c ) {
                $c->permalink = get_permalink( $c->ID );
            }
            $fields["coaching"] = $coaching;
            $coached_by = get_posts(
                [
                    'connected_type'      => 'contacts_to_contacts',
                    'connected_direction' => 'from',
                    'connected_items'     => $contact,
                    'nopaging'            => true,
                    'suppress_filters'    => false,
                ]
            );
            foreach ( $coached_by as $c ) {
                $c->permalink = get_permalink( $c->ID );
            }
            $fields["coached_by"] = $coached_by;
            $subassigned = get_posts(
                [
                    'connected_type'      => 'contacts_to_subassigned',
                    'connected_direction' => 'to',
                    'connected_items'     => $contact,
                    'nopaging'            => true,
                    'suppress_filters'    => false,
                ]
            );
            foreach ( $subassigned as $c ) {
                $c->permalink = get_permalink( $c->ID );
            }
            $fields["subassigned"] = $subassigned;
            $relation = get_posts(
                [
                    'connected_type'      => 'contacts_to_relation',
                    'connected_direction' => 'any',
                    'connected_items'     => $contact,
                    'nopaging'            => true,
                    'suppress_filters'    => false,
                ]
            );
            foreach ( $relation as $c ) {
                $c->permalink = get_permalink( $c->ID );
            }
            $fields["relation"] = $relation;
//            @todo limit the amount of data returned
//            foreach ( self::$contact_connection_types as $type ){
//                foreach ( $fields[$type] as $index => $post ){
//                    $fields[$type][$index] = [
//                        "ID" => $post->ID,
//                        "post_type" => $post->post_type,
//                        "post_date_gmt" => $post->post_date_gmt,
//                        "post_date" => $post->post_date,
//                        "post_title" => $post->post_title
//                    ];
//                }
//            }

            self::adjust_custom_fields( $contact_id, $fields, $contact_fields );

            $comments = self::get_comments( $contact_id );
            $fields["comments"] = $comments;
            $fields["ID"] = $contact->ID;
            $fields["title"] = $contact->post_title;
            $fields["created_date"] = $contact->post_date;

            $contact = apply_filters( 'dt_contact_fields_post_filter', $fields );
            wp_cache_set( "contact_" . $contact_id, $contact );
            return $contact;
        } else {
            return new WP_Error( __FUNCTION__, "No contact found with ID", [ 'contact_id' => $contact_id ] );
        }
    }

    /**
     * Used in in the method get_custom, this method mutates $fields to add
     * data about a particular contact in the required format. You might want
     * to use this instead of get_custom for performance reasons.
     *
     * @param int   $contact_id     The ID number of the contact
     * @param array $fields         This array will be mutated with the results
     * @param array $contact_fields This is what get_custom_fields_settings() returns
     *
     * @return void
     */
    public static function adjust_custom_fields( int $contact_id, array &$fields, array $contact_fields ) {
        $meta_fields = get_post_custom( $contact_id );
        foreach ( $meta_fields as $key => $value ) {
            //if is contact details and is in a channel
            if ( !( isset( self::$channel_list ) )){
                self::$channel_list = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
            }
            if ( strpos( $key, "contact_" ) === 0 && isset( self::$channel_list[ explode( '_', $key )[1] ] ) ) {
                if ( strpos( $key, "details" ) === false ) {
                    $type = explode( '_', $key )[1];
                    $fields[ "contact_" . $type ][] = self::format_contact_details( $meta_fields, $type, $key, $value[0] );
                }
            } elseif ( strpos( $key, "address" ) === 0 ) {
                if ( strpos( $key, "_details" ) === false ) {

                    $details = [];
                    if ( isset( $meta_fields[ $key . '_details' ][0] ) ) {
                        $details = maybe_unserialize( $meta_fields[ $key . '_details' ][0] );
                    }
                    $details["value"] = $value[0];
                    $details["key"] = $key;
                    if ( isset( $details["type"] ) ) {
                        $details["type_label"] = self::$address_types[ $details["type"] ]["label"];
                    }
                    $fields["address"][] = $details;
                }
            } elseif ( isset( $contact_fields[ $key ] ) && $contact_fields[ $key ]["type"] == "key_select" && !empty( $value[0] )) {
                $value_options = $contact_fields[ $key ]["default"][ $value[0] ] ?? $value[0];
                if ( isset( $value_options["label"] ) ){
                    $label = $value_options["label"];
                } elseif ( is_string( $value_options ) ) {
                    $label = $value_options;
                } else {
                    $label = $value[0];
                }
//                        $label = $contact_fields[ $key ]["default"][ $value[0] ]["label"] ?? $value[0];
                $fields[ $key ] = [
                    "key" => $value[0],
                    "label" => $label
                ];
            } elseif ( $key === "assigned_to" ) {
                if ( $value ) {
                    $meta_array = explode( '-', $value[0] ); // Separate the type and id
                    $type = $meta_array[0]; // Build variables
                    if ( isset( $meta_array[1] ) ) {
                        $id = $meta_array[1];
                        if ( $type == 'user' && $id) {
                            $user = get_user_by( 'id', $id );
                            $fields[ $key ] = [
                                "id" => $id,
                                "type" => $type,
                                "display" => ( $user ? $user->display_name : "Nobody" ) ,
                                "assigned-to" => $value[0]
                            ];
                        }
                    }
                }
            } else if ( isset( $contact_fields[ $key ] ) && $contact_fields[ $key ]['type'] === 'multi_select' ){
                $fields[ $key ] = $value;
            } else if ( isset( $contact_fields[ $key ] ) && $contact_fields[ $key ]['type'] === 'boolean' ){
                $fields[ $key ] = $value[0] === "1";
            } else if ( isset( $contact_fields[ $key ] ) && $contact_fields[ $key ]['type'] === 'array' ){
                $fields[ $key ] = maybe_unserialize( $value[0] );
            } else if ( isset( $contact_fields[ $key ] ) && $contact_fields[ $key ]['type'] === 'date' ){
                $fields[ $key ] = [
                    "timestamp" => $value[0],
                    "formatted" => dt_format_date( $value[0] ),
                ];
            } else {
                $fields[ $key ] = $value[0];
            }
        }
    }


    public static function get_merge_data( int $contact_id, int $duplicate_id) {
        if ( !$contact_id && !$duplicate_id) { return; }

        $contact = self::get_contact( $contact_id );
        $duplicate = self::get_contact( $duplicate_id );

        $fields = array(
            'contact_phone' => 'Phone',
            'contact_email' => 'Email',
            'contact_address' => 'Address',
            'contact_facebook' => 'Facebook'
        );

        $c_fields = array();
        $d_fields = array();

        $data = array(
            'contact_phone' => array(),
            'contact_email' => array(),
            'contact_address' => array(),
            'contact_facebook' => array()
        );

        foreach (array_keys( $fields ) as $key) {
            foreach ($contact[$key] ?? [] as $vals) {
                if ( !isset( $c_fields[$key] )) {
                    $c_fields[$key] = array();
                }
                array_push( $c_fields[$key], $vals['value'] );
            }
            foreach ($duplicate[$key] ?? [] as $vals) {
                if ( !isset( $d_fields[$key] )) {
                    $d_fields[$key] = array();
                }
                array_push( $d_fields[$key], $vals['value'] );
            }
        }

        foreach (array_keys( $fields ) as $field) {
            $max = max( array( count( $c_fields[$field] ?? [] ), count( $d_fields[$field] ?? [] ) ) );
            for ($i = 0; $i < $max; $i++) {
                $hide = false;
                $o_value = $c_fields[$field][$i] ?? null;
                $d_value = $d_fields[$field][$i] ?? null;
                if (in_array( $o_value, $d_fields[$field] ?? [] )) { $hide = true; }
                array_push($data[$field], array(
                    'original' => array(
                        'hide' => $hide,
                        'value' => $o_value
                    ),
                    'duplicate' => array(
                        'hide' => $hide,
                        'value' => $d_value
                    )
                ));
            }
        }

        return array( $contact, $duplicate, $data, $fields );
    }

    /**
     * @param $meta_fields
     * @param $type
     * @param $key
     * @param $value
     *
     * @return array|mixed
     */
    public static function format_contact_details( $meta_fields, $type, $key, $value ) {

        $details = [];

        if ( isset( $meta_fields[ $key . '_details' ][0] ) ) {
            $details = maybe_unserialize( $meta_fields[ $key . '_details' ][0] );

            if ( !is_array( $details ) ) {
                $details = [];
            }
        }

        $details["value"] = $value;
        $details["key"] = $key;
        if ( isset( $details["type"] ) ) {
            $details["type_label"] = self::$channel_list[ $type ]["types"][ $details["type"] ]["label"];
        }

        return $details;
    }

    public static function merge_p2p( int $master_id, int $non_master_id) {
        if ( !$master_id || !$non_master_id) { return; }
        $master = self::get_contact( $master_id );
        $non_master = self::get_contact( $non_master_id );
        $keys = self::$contact_connection_types;

        $update = [];
        $to_remove = [];

        foreach ($keys as $key) {
            $results = $non_master[$key] ?? array();
            foreach ($results as $result) {
                if ( !isset( $update[$key] )) {
                    $update[$key] = array();
                    $update[$key]['values'] = array();
                }
                if ( !isset( $to_remove[$key] )) {
                    $to_remove[$key] = array();
                    $to_remove[$key]['values'] = array();
                }
                if ( in_array( $key, [ "baptized", "coaching", "subassigned" ] ) ){
                    array_push($update[$key]['values'], array(
                        'value' => $result->p2p_from
                    ));
                    array_push($to_remove[$key]['values'], array(
                        'value' => $result->p2p_from,
                        'delete' => true
                    ));
                } else {
                    array_push($update[$key]['values'], array(
                        'value' => $result->p2p_to
                    ));
                    array_push($to_remove[$key]['values'], array(
                        'value' => $result->p2p_to,
                        'delete' => true
                    ));
                }
            }
        }

        self::update_contact( $master_id, $update );
        self::update_contact( $non_master_id, $to_remove );
    }

    public static function copy_comments( int $master_id, int $non_master_id, $check_permissions = true ){
        if ( $check_permissions && ( !self::can_update( 'contacts', $master_id ) || !self::can_update( 'contacts', $non_master_id ) )) {
            return new WP_Error( __FUNCTION__, "You do not have permission for this", [ 'status' => 403 ] );
        }
        $comments = self::get_comments( $non_master_id );
        foreach ( $comments as $comment ){
            $comment->comment_post_ID = $master_id;
            if ( $comment->comment_type === "comment" ){
                $comment->comment_content = sprintf( esc_html_x( '(From Duplicate): %s', 'duplicate comment', 'disciple_tools' ), $comment->comment_content );
            }
            if ( $comment->comment_type !== "duplicate" ){
                wp_insert_comment( (array) $comment );
            }
        }
    }

    /**
     * @param $contact_id
     *
     * @return array|null|object
     */
    public static function get_activity( $contact_id ) {
        $fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings( true, $contact_id );
        return self::get_post_activity( "contacts", $contact_id, $fields );
    }

    /**
     * @param $contact_id
     * @param $activity_id
     *
     * @return array|null|object
     */
    public static function get_single_activity( $contact_id, $activity_id ) {
        $fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings( true, $contact_id );
        return self::get_post_single_activity( "contacts", $contact_id, $fields, $activity_id );
    }

    /**
     * @param $contact_id
     * @param $activity_id
     *
     * @return bool|int|WP_Error
     */
    public static function revert_activity( $contact_id, $activity_id ){
        if ( !self::can_update( 'contacts', $contact_id ) ) {
            return new WP_Error( __FUNCTION__, "You do not have permission for this", [ 'status' => 403 ] );
        }
        $activity = self::get_single_activity( $contact_id, $activity_id );
        if ( empty( $activity->old_value ) ){
            if ( strpos( $activity->meta_key, "quick_button_" ) !== false ){
                $activity->old_value = 0;
            }
        }
        update_post_meta( $contact_id, $activity->meta_key, $activity->old_value ?? "" );
        return self::get_contact( $contact_id );
    }


    /**
     * Get Contacts assigned to a user
     *
     * @param int   $user_id
     * @param bool  $check_permissions
     * @param array $query_pagination_args -Pass in pagination and ordering parameters if wanted.
     *
     * @access public
     * @since  0.1.0
     * @return WP_Query | WP_Error
     */
    public static function get_user_contacts( int $user_id, bool $check_permissions = true, array $query_pagination_args = [] ) {
        if ( $check_permissions && !self::can_access( 'contacts' ) ) {
            return new WP_Error( __FUNCTION__, "You do not have access to these contacts", [ 'status' => 403 ] );
        }

        $query_args = [
            'post_type'  => 'contacts',
            'meta_key'   => 'assigned_to',
            'meta_value' => "user-$user_id",
            'orderby'    => 'ID',
            'nopaging'   => true,
        ];

        return self::query_with_pagination( $query_args, $query_pagination_args );
    }

    /**
     * Get Contacts viewable by a user
     *
     * @param int   $most_recent date of most recent update
     * @param bool  $check_permissions
     *
     * @access public
     * @since  0.1.0
     * @return array | WP_Error | WP_Query
     */
    public static function get_viewable_contacts( int $most_recent, bool $check_permissions = true ) {
        if ( $check_permissions && !self::can_access( 'contacts' ) ) {
            return new WP_Error( __FUNCTION__, "You do not have access to these contacts", [ 'status' => 403 ] );
        }
        $current_user = wp_get_current_user();

        $query_args = [
            'post_type' => 'contacts',
//            'nopaging'  => true,
            'meta_query' => [
                'relation' => "AND",
                [
                    'relation' => "OR",
                    [
                        'relation' => "AND",
                        [
                            'key' => 'type',
                            'value' => "user",
                            'compare' => '!='
                        ],
                        [
                            'key' => 'type',
                            'value' => "partner",
                            'compare' => '!='
                        ],
                    ],
                    [
                        'key' => 'type',
                        'compare' => 'NOT EXISTS'
                    ]
                ],
                [
                    'key' => "last_modified",
                    'value' => $most_recent,
                    'compare' => '>'
                ]
            ],
            'orderby' => 'meta_value_num',
            'meta_key' => "last_modified",
            'order' => 'ASC',
            // @codingStandardsIgnoreLine
            'posts_per_page' => 1000,
        ];
        $contacts_shared_with_user = [];
        if ( !self::can_view_all( 'contacts' ) ) {
            $contacts_shared_with_user = self::get_posts_shared_with_user( 'contacts', $current_user->ID );

            $query_args['meta_key'] = 'assigned_to';
            $query_args['meta_value'] = "user-" . $current_user->ID;
        }
//        $queried_contacts = self::query_with_pagination( $query_args, $query_pagination_args );
        $queried_contacts = new WP_Query( $query_args );
        if ( is_wp_error( $queried_contacts ) ) {
            return $queried_contacts;
        }
        $contacts = $queried_contacts->posts;
        $contact_ids = array_map(
            function( $contact ) {
                return $contact->ID;
            },
            $contacts
        );
        //add shared contacts to the list avoiding duplicates
        foreach ( $contacts_shared_with_user as $shared ) {
            if ( !in_array( $shared->ID, $contact_ids ) ) {
                $contact_ids[] = $shared->ID;
                $contacts[] = $shared;
            }
        }
//        $team_contacts = self::get_team_contacts( $current_user->ID, true, true, $most_recent );
//        if ( isset( $team_contacts["contacts"] ) ){
//            foreach ( $team_contacts["contacts"] as $team_contact ){
//                if ( !in_array( $team_contact->ID, $contact_ids ) ) {
//                    $team_contact->is_team_contact = true;
//                    $contacts[] = $team_contact;
//                }
//            }
//        }

        $delete_contacts = [];
        if ($most_recent){
            global $wpdb;
            $deleted_query = $wpdb->get_results( $wpdb->prepare(
                "SELECT object_id
                FROM `$wpdb->dt_activity_log`
                WHERE
                    ( `action` = 'deleted' || `action` = 'trashed' )
                    AND `object_subtype` = 'contacts'
                    AND hist_time > %d
                ", $most_recent
            ), ARRAY_A);
            foreach ( $deleted_query as $deleted ){
                $delete_contacts[] = $deleted["object_id"];
            }
        }


        return [
            "contacts" => $contacts,
            "total" => $queried_contacts->found_posts,
            "deleted" => $delete_contacts
        ];
    }

    /**
     * @param string $search_string
     *
     * @return array|\WP_Query
     */
    public static function get_viewable_contacts_compact( string $search_string ) {
        return self::get_viewable_compact( 'contacts', $search_string );
    }


    public static function get_team_members(){
        global $wpdb;
        $user_connections = [];
        $user_connections['relation'] = 'OR';
        $members = [];
        $user_id = get_current_user_id();


        // First Query
        // Build arrays for current groups connected to user
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT
                `$wpdb->term_relationships`.`term_taxonomy_id`
            FROM
                `$wpdb->term_relationships`
            INNER JOIN
                `$wpdb->term_taxonomy`
            ON
                `$wpdb->term_relationships`.`term_taxonomy_id` = `$wpdb->term_taxonomy`.`term_taxonomy_id`
            WHERE
                object_id  = %d
                AND taxonomy = %s
            ",
            $user_id,
            'user-group'
        ), ARRAY_A );

        // Loop
        foreach ( $results as $result ) {
            // create the meta query for the group
            $user_connections[] = [
                'key' => 'assigned_to',
                'value' => 'group-' . $result['term_taxonomy_id']
            ];

            // Second Query
            // query a member list for this group
            // build list of member ids who are part of the team
            $results2 = $wpdb->get_results( $wpdb->prepare(
                "SELECT
                    `$wpdb->term_relationships`.`object_id`
                FROM
                    `$wpdb->term_relationships`
                WHERE
                    `term_taxonomy_id` = %d
                ",
                $result['term_taxonomy_id']
            ), ARRAY_A );

            // Inner Loop
            foreach ( $results2 as $result2 ) {

                if ( $result2['object_id'] != $user_id ) {
                    $members[] = $result2['object_id'];
                }
            }
        }

        $members = array_unique( $members );
        return $members;
    }

    public static function search_viewable_contacts( array $query, bool $check_permissions = true){
        $viewable = self::search_viewable_post( "contacts", $query, $check_permissions );
        if ( is_wp_error( $viewable ) ){
            return $viewable;
        }
        return [
            "contacts" => $viewable["posts"],
            "total" => $viewable["total"]
        ];
    }


    /**
     * Get Contacts assigned to a user's team
     *
     * @param int $user_id
     * @param bool $check_permissions
     *
     * @param bool $exclude_current_user
     * @param int $most_recent
     *
     * @return array | WP_Error
     * @access public
     * @since  0.1.0
     */
//    public static function get_team_contacts( int $user_id, bool $check_permissions = true, $exclude_current_user = false, $most_recent = 0 ) {
//        if ( $check_permissions ) {
//            $current_user = wp_get_current_user();
//            // TODO: the current permissions required don't make sense
//            if ( !self::can_access( 'contacts' )
//                || ( $user_id != $current_user->ID && !current_user_can( 'edit_team_contacts' ) ) ) {
//                return new WP_Error( __FUNCTION__, __( "You do not have permission" ), [ 'status' => 404 ] );
//            }
//        }
//        $user_connections = [];
//        $user_connections['relation'] = 'OR';
//
//        $members = self::get_team_members();
//
//        $query_args = [
//            'relation' => "AND",
//            [
//                'relation' => "OR",
//                [
//                    'relation' => "AND",
//                    [
//                        'key' => 'type',
//                        'value' => "user",
//                        'compare' => '!='
//                    ],
//                    [
//                        'key' => 'type',
//                        'value' => "partner",
//                        'compare' => '!='
//                    ],
//                ],
//                [
//                    'key' => 'type',
//                    'compare' => 'NOT EXISTS'
//                ]
//            ],
//            [
//                'key' => "last_modified",
//                'value' => $most_recent,
//                'compare' => '>'
//            ]
//        ];
//
//        if ( sizeof( $members ) === 0 ){
//            return [
//                "members"  => $user_connections,
//                "contacts" => [],
//            ];
//        }
//        foreach ( $members as $member ) {
//            if ( !$exclude_current_user || ( $exclude_current_user && $member != $user_id ) ){
//                $user_connections[] = [
//                'key' => 'assigned_to',
//                'value' => 'user-' . $member
//                ];
//            };
//        }
//        $query_args[] = $user_connections;
//
//
//        $args = [
//            'post_type'  => 'contacts',
//            'nopaging'   => true,
//            'meta_query' => $query_args,
//        ];
//        $query2 = new WP_Query( $args );
//
//        return [
//            "members"  => $user_connections,
//            "contacts" => $query2->posts,
//        ];
//    }

    /**
     * @param int    $contact_id
     * @param string $path_option
     * @param bool   $check_permissions
     *
     * @return array|int|\WP_Error
     */
    public static function update_seeker_path( int $contact_id, string $path_option, $check_permissions = true ) {
        $contact_fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
        $seeker_path_options = $contact_fields["seeker_path"]["default"];
        $option_keys = array_keys( $seeker_path_options );
        $current_seeker_path = get_post_meta( $contact_id, "seeker_path", true );
        $current_index = array_search( $current_seeker_path, $option_keys );
        $new_index = array_search( $path_option, $option_keys );
        if ( $new_index > $current_index ) {
            $current_index = $new_index;
            $update = self::update_contact( $contact_id, [ "seeker_path" => $path_option ], $check_permissions );
            if ( is_wp_error( $update ) ) {
                return $update;
            }
            $current_seeker_path = $path_option;
        }

        return [
            "currentKey" => $current_seeker_path,
            "current" => $seeker_path_options[ $option_keys[ $current_index ] ],
            "next"    => isset( $option_keys[ $current_index + 1 ] ) ? $seeker_path_options[ $option_keys[ $current_index + 1 ] ] : "",
        ];

    }

    public static function update_quick_action_buttons( $contact_id, $seeker_path ){
        if ( $seeker_path === "established" ){
            $quick_button = get_post_meta( $contact_id, "quick_button_contact_established", true );
            if ( empty( $quick_button ) || $quick_button == "0" ){
                update_post_meta( $contact_id, "quick_button_contact_established", "1" );
            }
        }
        if ( $seeker_path === "scheduled" ){
            $quick_button = get_post_meta( $contact_id, "quick_button_meeting_scheduled", true );
            if ( empty( $quick_button ) || $quick_button == "0" ){
                update_post_meta( $contact_id, "quick_button_meeting_scheduled", "1" );
            }
        }
        if ( $seeker_path === "met" ){
            $quick_button = get_post_meta( $contact_id, "quick_button_meeting_complete", true );
            if ( empty( $quick_button ) || $quick_button == "0" ){
                update_post_meta( $contact_id, "quick_button_meeting_complete", "1" );
            }
        }
        self::check_requires_update( $contact_id );
    }

    /**
     * @param int   $contact_id
     * @param array $field
     * @param bool  $check_permissions
     *
     * @return array|int|\WP_Error
     */
    private static function handle_quick_action_button_event( int $contact_id, array $field, bool $check_permissions = true ) {
        $update = [];
        $key = key( $field );

        if ( $key == "quick_button_no_answer" ) {
            $update["seeker_path"] = "attempted";
        } elseif ( $key == "quick_button_phone_off" ) {
            $update["seeker_path"] = "attempted";
        } elseif ( $key == "quick_button_contact_established" ) {
            $update["seeker_path"] = "established";
        } elseif ( $key == "quick_button_meeting_scheduled" ) {
            $update["seeker_path"] = "scheduled";
        } elseif ( $key == "quick_button_meeting_complete" ) {
            $update["seeker_path"] = "met";
        }

        if ( isset( $update["seeker_path"] ) ) {
            self::check_requires_update( $contact_id );
            return self::update_seeker_path( $contact_id, $update["seeker_path"], $check_permissions );
        } else {
            return $contact_id;
        }
    }

    /**
     * @param int $contact_id
     * @param string $comment_html
     * @param string $type      normally 'comment', different comment types can have their own section in the comments activity
     * @param array $args       [user_id, comment_date, comment_author etc]
     * @param bool $check_permissions
     * @param bool $silent
     *
     * @return false|int|\WP_Error
     */
    public static function add_comment( int $contact_id, string $comment_html, string $type = "comment", array $args = [], bool $check_permissions = true, $silent = false ) {
        $result = self::add_post_comment( "contacts", $contact_id, $comment_html, $type, $args, $check_permissions, $silent );
        if ( $type === "comment" && !is_wp_error( $result )){
            self::check_requires_update( $contact_id );
        }
        return $result;
    }

    /**
     * @param int $contact_id
     * @param bool $check_permissions
     *
     * @param string $type
     *
     * @return array|int|\WP_Error
     */
    public static function get_comments( int $contact_id, bool $check_permissions = true, $type = "all" ) {
        return self::get_post_comments( 'contacts', $contact_id, $check_permissions, $type );
    }


    public static function delete_comment( int $contact_id, int $comment_id, bool $check_permissions = true ){
        $comment = get_comment( $comment_id );
        if ( $check_permissions && isset( $comment->user_id ) && $comment->user_id != get_current_user_id() ) {
            return new WP_Error( __FUNCTION__, "You don't have permission to delete this comment", [ 'status' => 403 ] );
        }
        if ( !$comment ){
            return new WP_Error( __FUNCTION__, "No comment found with id: " . $comment_id, [ 'status' => 403 ] );
        }
        return wp_delete_comment( $comment_id );
    }

    public static function update_comment( int $contact_id, int $comment_id, string $comment_content, bool $check_permissions = true ){
        $comment = get_comment( $comment_id );
        if ( $check_permissions && isset( $comment->user_id ) && $comment->user_id != get_current_user_id() ) {
            return new WP_Error( __FUNCTION__, "You don't have permission to edit this comment", [ 'status' => 403 ] );
        }
        if ( !$comment ){
            return new WP_Error( __FUNCTION__, "No comment found with id: " . $comment_id, [ 'status' => 403 ] );
        }
        $comment = [
            "comment_content" => $comment_content,
            "comment_ID" => $comment_id,
        ];
        return wp_update_comment( $comment );
    }

    /**
     * @param int  $contact_id
     * @param bool $accepted
     *
     * @return array|\WP_Error
     */
    public static function accept_contact( int $contact_id, bool $accepted ) {
        if ( !self::can_update( 'contacts', $contact_id ) ) {
            return new WP_Error( __FUNCTION__, "You do not have permission for this", [ 'status' => 403 ] );
        }

        if ( $accepted ) {
            $update = [
                "overall_status" => 'active',
                "accepted" => true
            ];
            dt_activity_insert(
                [
                    'action'         => 'assignment_accepted',
                    'object_type'    => get_post_type( $contact_id ),
                    'object_subtype' => '',
                    'object_name'    => get_the_title( $contact_id ),
                    'object_id'      => $contact_id,
                    'meta_id'        => '', // id of the comment
                    'meta_key'       => '',
                    'meta_value'     => '',
                    'meta_parent'    => '',
                    'object_note'    => '',
                ]
            );
            return self::update_contact( $contact_id, $update, true );
        } else {
            $assign_to_id = 0;
            $last_activity = self::get_most_recent_activity_for_field( $contact_id, "assigned_to" );
            if ( isset( $last_activity->user_id )){
                $assign_to_id = $last_activity->user_id;
            } else {
                $base_user = dt_get_base_user( true );
                if ( $base_user ){
                    $assign_to_id = $base_user;
                }
            }

            $update = [
                "assigned_to" => $assign_to_id,
                "overall_status" => 'unassigned'
            ];
            $contact = self::update_contact( $contact_id, $update, true );
            $current_user = wp_get_current_user();
            dt_activity_insert(
                [
                    'action'         => 'assignment_decline',
                    'object_type'    => get_post_type( $contact_id ),
                    'object_subtype' => 'decline',
                    'object_name'    => get_the_title( $contact_id ),
                    'object_id'      => $contact_id,
                    'meta_id'        => '', // id of the comment
                    'meta_key'       => '',
                    'meta_value'     => '',
                    'meta_parent'    => '',
                    'object_note'    => ''
                ]
            );
            Disciple_Tools_Notifications::insert_notification_for_assignment_declined( $current_user->ID, $assign_to_id, $contact_id );
            return $contact;
        }
    }

    /**
     * Gets an array of users whom the contact is shared with.
     *
     * @param int $post_id
     *
     * @return array|mixed
     */
    public static function get_shared_with_on_contact( int $post_id ) {
        return self::get_shared_with( 'contacts', $post_id );
    }

    /**
     * Removes share record
     *
     * @param int $post_id
     * @param int $user_id
     *
     * @return false|int|WP_Error
     */
    public static function remove_shared_on_contact( int $post_id, int $user_id ) {
        return self::remove_shared( 'contacts', $post_id, $user_id );
    }

    /**
     * Adds a share record
     *
     * @param int $post_id
     * @param int $user_id
     * @param array $meta
     *
     * @param bool $send_notifications
     * @param bool $check_permissions
     * @param bool $insert_activity
     *
     * @return false|int|WP_Error
     */
    public static function add_shared_on_contact( int $post_id, int $user_id, $meta = null, $send_notifications = true, $check_permissions = true, $insert_activity = true ) {
        return self::add_shared( 'contacts', $post_id, $user_id, $meta, $send_notifications, $check_permissions, $insert_activity );
    }

    public static function find_contacts_with( $field, $value, $exclude_id = "", $exact_match = false ){
        global $wpdb;
        $contact_ids = $wpdb->get_results(
            $wpdb->prepare(
                "
                        SELECT post_id
                        FROM {$wpdb->prefix}postmeta
                        INNER JOIN $wpdb->posts posts ON ( posts.ID = post_id AND posts.post_type = 'contacts' AND posts.post_status = 'publish' )
                        WHERE meta_key
                        LIKE %s
                        AND meta_value LIKE %s
                        AND post_id != %s
                        ",
                [
                    esc_sql( $field ) .'%',
                    $exact_match ? esc_sql( $value ) : ( '%' . esc_sql( $value ) . '%' ),
                    esc_sql( $exclude_id )
                ]
            ),
            ARRAY_N
        );
//        @todo return just an array
        // if there are more than 50, it is most likely not a duplicate
        return sizeof( $contact_ids ) > 50 ? [] : $contact_ids;
    }

    public function find_contacts_by_title( $title, $exclude_id ){
        global $wpdb;
        $dups = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $wpdb->posts
                WHERE post_title
                LIKE %s
                AND ID != %s
                AND post_type = 'contacts' AND post_status = 'publish'",
                '%'. $wpdb->esc_like( $title ) .'%',
                $exclude_id
            ), ARRAY_N
        );
        // if there are more than 25, it is most likely not a duplicate
        return sizeof( $dups ) > 25 ? [] : $dups;
    }

    public function get_all_duplicates() {
        global $wpdb;
        $records = $wpdb->get_results(
            $wpdb->prepare("
                select
                    *
                from
                    wp_posts p join wp_postmeta m on p.ID = m.post_id
                where
                    p.post_type = %s and
                    m.meta_key = %s
            ", [ 'contacts', 'duplicate_data' ]), ARRAY_A
        );

        $duplicates = array();
        foreach ($records as $record) {
            $dupes = unserialize( $record['meta_value'] );
            $count = 0;
            foreach ($dupes as $key => $dupe) {
                if ($key === 'override') { continue; }
                $count += count( $dupe );
            }
            $duplicates[$record['ID']]['count'] = $count;
            $duplicates[$record['ID']]['name'] = $record['post_title'];
        }

        return $duplicates;
    }

    public static function get_duplicates_on_contact( $contact_id ){
        if ( !self::can_view_all( 'contacts' ) ) {
            return new WP_Error( __FUNCTION__, "You do not have permission for this", [ 'status' => 403 ] );
        }
        $contact = self::get_contact( $contact_id );
        $all_ids = [];
        $dups = [];
        if ( isset( $contact["duplicate_data"] ) ){
            foreach ( $contact["duplicate_data"] as $dup_ids ){
                $ids = array_diff( $dup_ids, $all_ids );
                $all_ids = array_merge( $all_ids, $ids );
                foreach ( $ids as $index => $contact_id ) {
                    if ( $index < 100 ){
                        $dup = self::get_contact( $contact_id );
                        $dups[] = $dup;
                    }
                }
            }
        }
        return $dups;
    }

    private function get_duplicate_data( $contact_id, $field ){
        $duplicate_data = get_post_meta( $contact_id, "duplicate_data", true );
        if ( empty( $duplicate_data )){
            $duplicate_data = [];
        }
        if ( !isset( $duplicate_data[$field] ) ){
            $duplicate_data[$field] = [];
        }
        return $duplicate_data;
    }

    private function save_duplicate_finding( $field, $dups, $contact_id ){
        if ( sizeof( $dups ) > 0 ){
            $duplicate_data = $this->get_duplicate_data( $contact_id, $field );
            $has_unconfirmed_duplicates = 0;
            $message = "";
            foreach ( $dups as $row ){
                $id_of_duplicate = (int) $row[0];
                if ( $id_of_duplicate != (int) $contact_id ){
                    if ( !in_array( $id_of_duplicate, $duplicate_data[$field] ) ) {
                        $duplicate_data[$field][] = $id_of_duplicate;
                        $post = get_post( $id_of_duplicate );
                        $has_unconfirmed_duplicates++;
                        if ( $has_unconfirmed_duplicates <= 5 ){
                            $message .= "- [" . $post->post_title .  "](".  get_permalink( $id_of_duplicate ) . ")\n";
                        }
                        //uncomment to enable tracking both ways.
//                        $other_contact_duplicate_data = $this->get_duplicate_data( $id_of_duplicate, $field );
//                        if ( !in_array( $contact_id, $other_contact_duplicate_data[$field] )){
//                            self::add_comment( $id_of_duplicate, "Same phone as " . get_permalink( $contact_id ), false );
//                            $other_contact_duplicate_data[$field][] = $contact_id;
//                            update_post_meta( $id_of_duplicate, "duplicate_data", $other_contact_duplicate_data );
//                        }
                    }
                }
            }
            if ( $has_unconfirmed_duplicates ){
                $field_details = $this->get_field_details( $field, $contact_id );
                $message = __( "Possible duplicates on", "disciple_tools" ) . " " . $field_details["name"] . ":
                " . $message;
                if ( $has_unconfirmed_duplicates > 5 ){
                    $message .= "- " . $has_unconfirmed_duplicates . " " . __( "more duplicates not shown", "disciple_tools" );
                }
                self::add_comment( $contact_id, $message, "duplicate", [
                    "user_id" => 0,
                    "comment_author" => "Duplicate Checker"
                ], false );
                update_post_meta( $contact_id, "duplicate_data", $duplicate_data );
            }
        }
    }

    public function check_for_duplicates( $contact_id, $fields ){
        $fields_to_check = [ "contact_phone", "contact_email", "contact_address", "title" ];
        $fields_to_check = apply_filters( "dt_contact_duplicate_fields_to_check", $fields_to_check );
        foreach ( $fields as $field_id => $field_value ){
            if ( in_array( $field_id, $fields_to_check ) && !empty( $field_value ) ){
                if ( $field_id == "title" ){
                    $contacts = $this->find_contacts_by_title( $field_value, $contact_id );
                    $this->save_duplicate_finding( $field_id, $contacts, $contact_id );
                } else {
                    if ( isset( $field_value["values"] ) ){
                        $values = $field_value["values"];
                    } else {
                        $values = $field_value;
                    }
                    foreach ( $values as $val ){
                        if ( !empty( $val["value"] ) ){
                            $contacts = $this->find_contacts_with( $field_id, $val["value"], $contact_id );
                            $this->save_duplicate_finding( $field_id, $contacts, $contact_id );
                        }
                        //else if ( $this->get_field_details( $field_id, $contact_id )["type"] === "array" ){
//                            @todo, specify which field(s) in the array to look for duplicates on
//                            $contacts = $this->find_contacts_with( $field_id, $val, $contact_id );
//                            $this->save_duplicate_finding( $field_id, $contacts, $contact_id );
                        //}
                    }
                }
            }
        }
    }

    public static function escape_regex_mysql( string $regex ) {
        return preg_replace( '/&/', '\\&', preg_quote( $regex ) );
    }


    public static function recheck_duplicates( int $contact_id) {
        global $wpdb;
        $contact = self::get_contact( $contact_id );
        if (empty( $contact )) { return; }
        $fields = array( 'contact_phone', 'contact_email', 'contact_address' );
        $values = array();
        foreach ($fields as $field) {
            foreach ($contact[$field] ?? [] as $arr_val) {
                if ( !empty( $arr_val['value'] ) ){
                    $values[] = $arr_val['value'];
                }
            }
        }
        $unsure = $contact['duplicate_data']['unsure'] ?? array();
        $dismissed = $contact['duplicate_data']['override'] ?? array();
        if (count( $values ) == 0 || count( $fields ) == 0) {
            $results = [];
        } else {
            $vals = join( '|', array_map( [ __CLASS__, 'escape_regex_mysql' ], $values ) );
            $flds = join( '|', array_map( [ __CLASS__, 'escape_regex_mysql' ], $fields ) );
            $results = $wpdb->get_results( $wpdb->prepare( "
                select
                    *
                from
                    wp_posts p join
                    wp_postmeta m on p.ID = m.post_id
                where
                    ID != %d and
                    (meta_key regexp %s and meta_key not like %s) and
                    meta_value regexp %s
                ",
                array(
                    $contact_id,
                    "$flds",
                    '%details',
                    "$vals"
                )
            ), ARRAY_A );
        }
        $duplicates = array();
        foreach ($results as $result) {
            $key = $result['meta_key'];
            if (preg_match( "/contact_/i", $key )) {
                $keys = explode( "_", $key );
                $key = "$keys[0]_$keys[1]";
            }
            if ( !isset( $duplicates[$key] )) {
                $duplicates[$key] = array();
            }
            if ( !in_array( $result['ID'], $unsure ) && !in_array( $result['ID'], $dismissed )) {
                array_push( $duplicates[$key], $result['ID'] );
            }
        }
        foreach ($duplicates as $key => $duplicate) {
            $duplicates[$key] = array_merge( array_unique( $duplicates[$key] ) );
        }
        if ( !empty( $unsure )) {
            $duplicates['unsure'] = $unsure;
        }
        if ( !empty( $dismissed )) {
            $duplicates['override'] = $dismissed;
        }

        self::save_duplicate_data( $contact_id, $duplicates );
    }


    public static function save_duplicate_data( int $contact_id, array $duplicates) {
        if (empty( $duplicates )) { return; }
        update_post_meta( $contact_id, "duplicate_data", $duplicates );
    }

    public static function unsure_all( int $contact_id) {
        if ( !$contact_id) { return; }
        $contact = self::get_contact( $contact_id );
        $data = isset( $contact['duplicate_data'] ) ? is_array( $contact['duplicate_data'] ) ? $contact['duplicate_data'] : unserialize( $contact['duplicate_data'] ) : array();
        $duplicates = array();
        foreach ($data as $key => $duplicate) {
            if ($key === 'override') { continue; }
            foreach ($duplicate as $duplicate_id) {
                $duplicates['unsure'][] = $duplicate_id;
            }
        }

        self::save_duplicate_data( $contact_id, $duplicates );
    }

    public static function unsure_duplicate( int $contact_id, int $unsure_id) {
        if ( !$contact_id || !$unsure_id) { return; }
        $contact = self::get_contact( $contact_id );
        $duplicates = isset( $contact['duplicate_data'] ) ? is_array( $contact['duplicate_data'] ) ? $contact['duplicate_data'] : unserialize( $contact['duplicate_data'] ) : array();
        $unsure = $duplicates['unsure'] ?? array();
        foreach ($duplicates as $key => $values) {
            if (preg_match( "/unsure|override/", $key )) { continue; }
            $index = array_search( $unsure_id, $values );
            if ($index !== false) {
                unset( $duplicates[$key][$index] );
                array_merge( $duplicates[$key] );
            }
            if (empty( $duplicates[$key] )) {
                unset( $duplicates[$key] );
            }
        }
        if ( !in_array( $unsure_id, $unsure )) {
            if (isset( $duplicates['unsure'] )) {
                array_push( $duplicates['unsure'], $unsure_id );
            } else {
                $duplicates['unsure'] = [ $unsure_id ];
            }
        }

        self::save_duplicate_data( $contact_id, $duplicates );
    }

    public static function dismiss_all( int $contact_id) {
        if ( !$contact_id) { return; }
        $contact = self::get_contact( $contact_id );
        $data = isset( $contact['duplicate_data'] ) ? is_array( $contact['duplicate_data'] ) ? $contact['duplicate_data'] : unserialize( $contact['duplicate_data'] ) : array();
        $duplicates = array();
        foreach ($data as $key => $duplicate) {
            foreach ($duplicate as $duplicate_id) {
                $duplicates['override'][] = $duplicate_id;
            }
        }

        self::save_duplicate_data( $contact_id, $duplicates );
    }

    public static function dismiss_duplicate( int $contact_id, int $dismiss_id) {
        if ( !$contact_id || !$dismiss_id) { return; }
        $contact = self::get_contact( $contact_id );
        $duplicates = isset( $contact['duplicate_data'] ) ? is_array( $contact['duplicate_data'] ) ? $contact['duplicate_data'] : unserialize( $contact['duplicate_data'] ) : array();
        $dismissed = $duplicates['override'] ?? array();
        foreach ($duplicates as $key => $values) {
            if (preg_match( "/override/", $key )) { continue; }
            $index = array_search( $dismiss_id, $values );
            if ($index !== false) {
                unset( $duplicates[$key][$index] );
                array_merge( $duplicates[$key] );
            }
            if (empty( $duplicates[$key] )) {
                unset( $duplicates[$key] );
            }
        }
        if ( !in_array( $dismiss_id, $dismissed )) {
            if (isset( $duplicates['override'] )) {
                array_push( $duplicates['override'], $dismiss_id );
            } else {
                $duplicates['override'] = [ $dismiss_id ];
            }
        }

        self::save_duplicate_data( $contact_id, $duplicates );
    }


    /**
     * Returns numbers for multiplier and dispatcher
     *
     * Example Array Return:
     * [
     * [my_contacts] => 39
     * [update_needed] => 0
     * [contact_attempted] => 1
     * [meeting_scheduled] => 0
     * [shared] => 5
     * [all_contacts] => 43
     * [needs_assigned] => 0
     * ]
     *
     * This function will always return this array even if the counts are zero.
     *
     * If the current user/supplied user is not a dispatcher role or similar, then it will skip the query and return zeros for
     * all_contacts and needs assigned array elements.
     *
     * @param string $tab
     * @param bool $show_closed
     *
     * @return array|\WP_Error
     */
    public static function get_count_of_contacts( $tab = "my", $show_closed = false ) {
        global $wpdb;
        if ( !self::can_access( "contacts" ) ) {
            return new WP_Error( __FUNCTION__, "Permission denied.", [ 'status' => 403 ] );
        }

        $numbers = [];

        $user_id = get_current_user_id();
        $access_sql = "";
        $user_post = Disciple_Tools_Users::get_contact_for_user( $user_id ) ?? 0;
        // contacts assigned to me
        $my_access = "INNER JOIN $wpdb->postmeta as assigned_to
            ON a.ID=assigned_to.post_id
              AND assigned_to.meta_key = 'assigned_to'
              AND assigned_to.meta_value = CONCAT( 'user-', " . $user_id . " )";
        //contacts subassigned to me
        $subassigned_access = "INNER JOIN $wpdb->p2p as from_p2p 
            ON ( from_p2p.p2p_to = a.ID 
                AND from_p2p.p2p_type = 'contacts_to_subassigned' 
                AND from_p2p.p2p_from = " . $user_post. ")";
        //contacts shared with me
        $shared_access = "
            INNER JOIN $wpdb->dt_share AS shares 
            ON ( shares.post_id = a.ID  
                AND shares.user_id = " . $user_id . "
                AND a.ID NOT IN (
                    SELECT assigned_to.post_id 
                    FROM $wpdb->postmeta as assigned_to
                    WHERE a.ID = assigned_to.post_id
                      AND assigned_to.meta_key = 'assigned_to'
                      AND assigned_to.meta_value = CONCAT( 'user-', " . $user_id . " )
                )
            )";
        $all_access = "";
        $closed = "";
        if ( !$show_closed ){
            $closed = " INNER JOIN $wpdb->postmeta as status
              ON ( a.ID=status.post_id 
              AND status.meta_key = 'overall_status'
              AND status.meta_value != 'closed' )";
        }
        //contacts shared with me.
        if ( !self::can_view_all( "contacts" ) ){
            $all_access = "INNER JOIN $wpdb->dt_share AS shares 
            ON ( shares.post_id = a.ID
                 AND shares.user_id = " . $user_id . " ) ";
        }
        if ( $tab === "my" ){
            $access_sql = $my_access;
        } elseif ( $tab === "subassigned" ){
            $access_sql = $subassigned_access;
        } elseif ( $tab === "shared" ){
            $access_sql = $shared_access;
        } elseif ( $tab === "all" ){
            $access_sql = $all_access;
        }


        // phpcs:disable
        // WordPress.WP.PreparedSQL.NotPrepare
        $personal_counts = $wpdb->get_results("
            SELECT (SELECT count(a.ID)
            FROM $wpdb->posts as a
              " . $access_sql . $closed . "
              INNER JOIN $wpdb->postmeta as type
                ON a.ID=type.post_id AND type.meta_key = 'type'
            WHERE a.post_status = 'publish'
            AND post_type = 'contacts'
            AND (( type.meta_value = 'media' OR type.meta_value = 'next_gen' )
                OR ( type.meta_key IS NULL ))
            )
            as total_count,
            (SELECT count(a.ID)
            FROM $wpdb->posts as a
            " . $my_access . $closed . "
            INNER JOIN $wpdb->postmeta as type
              ON a.ID=type.post_id AND type.meta_key = 'type'
            WHERE a.post_status = 'publish'
            AND post_type = 'contacts'
            AND (( type.meta_value = 'media' OR type.meta_value = 'next_gen' )
                OR ( type.meta_key IS NULL ))
            )
            as total_my,
            (SELECT count(a.ID)
            FROM $wpdb->posts as a
            " . $subassigned_access . $closed . "
            INNER JOIN $wpdb->postmeta as type
              ON a.ID=type.post_id AND type.meta_key = 'type'
            WHERE a.post_status = 'publish'
            AND post_type = 'contacts'
            AND (( type.meta_value = 'media' OR type.meta_value = 'next_gen' )
                OR ( type.meta_key IS NULL ))
            )
            as total_subassigned,
            (SELECT count(a.ID)
            FROM $wpdb->posts as a
            " . $shared_access . $closed . "
            INNER JOIN $wpdb->postmeta as type
              ON a.ID=type.post_id AND type.meta_key = 'type'
            WHERE a.post_status = 'publish'
            AND post_type = 'contacts'
            AND (( type.meta_value = 'media' OR type.meta_value = 'next_gen' )
                OR ( type.meta_key IS NULL ))
            )
            as total_shared,
            (SELECT count(a.ID)
            FROM $wpdb->posts as a
            " . $all_access . $closed . "
            INNER JOIN $wpdb->postmeta as type
              ON a.ID=type.post_id AND type.meta_key = 'type'
            WHERE a.post_status = 'publish'
            AND post_type = 'contacts'
            AND (( type.meta_value = 'media' OR type.meta_value = 'next_gen' )
                OR ( type.meta_key IS NULL ))
            )
            as total_all,
            (SELECT count(a.ID)
              FROM $wpdb->posts as a
                " . $access_sql . $closed . "
                JOIN $wpdb->postmeta as b
                  ON a.ID=b.post_id
                    AND b.meta_key = 'requires_update'
                    AND b.meta_value = '1'
                INNER JOIN $wpdb->postmeta as e
                  ON a.ID=e.post_id
                  AND (( e.meta_key = 'type'
                    AND ( e.meta_value = 'media' OR e.meta_value = 'next_gen' ) )
                  OR e.meta_key IS NULL)
              WHERE a.post_status = 'publish'
              AND post_type = 'contacts')
            as update_needed,
            (SELECT count(a.ID)
              FROM $wpdb->posts as a
                " . $access_sql . "
                JOIN $wpdb->postmeta as b
                  ON a.ID=b.post_id
                    AND b.meta_key = 'overall_status'
                    AND b.meta_value = 'active'
                INNER JOIN $wpdb->postmeta as e
                  ON a.ID=e.post_id
                  AND (( e.meta_key = 'type'
                    AND ( e.meta_value = 'media' OR e.meta_value = 'next_gen' ) )
                  OR e.meta_key IS NULL)
              WHERE a.post_status = 'publish'
              AND post_type = 'contacts')
            as active,
            (SELECT count(a.ID)
              FROM $wpdb->posts as a
                INNER JOIN $wpdb->postmeta as b
                  ON a.ID=b.post_id
                    AND b.meta_key = 'accepted'
                    AND b.meta_value = ''
                " . $access_sql . $closed . "
                INNER JOIN $wpdb->postmeta as d
                  ON a.ID=d.post_id
                    AND d.meta_key = 'overall_status'
                    AND d.meta_value = 'assigned'
                INNER JOIN $wpdb->postmeta as e
                  ON a.ID=e.post_id
                  AND (( e.meta_key = 'type'
                    AND ( e.meta_value = 'media' OR e.meta_value = 'next_gen' ) )
                  OR e.meta_key IS NULL)
              WHERE a.post_status = 'publish'
              AND post_type = 'contacts')
            as needs_accepted,
            (SELECT count(a.ID)
              FROM $wpdb->posts as a
                JOIN $wpdb->postmeta as b
                  ON a.ID=b.post_id
                    AND b.meta_key = 'seeker_path'
                    AND b.meta_value = 'none'
                " . $access_sql . $closed . "
                JOIN $wpdb->postmeta as d
                  ON a.ID=d.post_id
                    AND d.meta_key = 'overall_status'
                    AND d.meta_value = 'active'
                INNER JOIN $wpdb->postmeta as e
                  ON a.ID=e.post_id
                  AND (( e.meta_key = 'type'
                    AND ( e.meta_value = 'media' OR e.meta_value = 'next_gen' ) )
                  OR e.meta_key IS NULL)
              WHERE a.post_status = 'publish'
              AND post_type = 'contacts')
            as contact_unattempted,
            (SELECT count(a.ID)
              FROM $wpdb->posts as a
                JOIN $wpdb->postmeta as b
                  ON a.ID=b.post_id
                    AND b.meta_key = 'seeker_path'
                    AND b.meta_value = 'scheduled'
                " . $access_sql . $closed . "
                JOIN $wpdb->postmeta as d
                  ON a.ID=d.post_id
                    AND d.meta_key = 'overall_status'
                    AND d.meta_value = 'active'
                INNER JOIN $wpdb->postmeta as e
                  ON a.ID=e.post_id
                  AND (( e.meta_key = 'type'
                    AND ( e.meta_value = 'media' OR e.meta_value = 'next_gen' ) )
                  OR e.meta_key IS NULL)
              WHERE a.post_status = 'publish'
              AND post_type = 'contacts' )
            as meeting_scheduled
            ", ARRAY_A );

        if ( empty( $personal_counts ) ) {
            return new WP_Error( __METHOD__, 'No results from the personal count query' );
        }

        foreach ( $personal_counts[0] as $key => $value ) {
            $numbers[$key] = $value;
        }

        if ( user_can( $user_id, 'view_any_contacts' ) ) {
            $dispatcher_counts = $wpdb->get_results( "
            SELECT (SELECT count(a.ID)
                FROM $wpdb->posts as a
                INNER JOIN $wpdb->postmeta as b
                  ON a.ID=b.post_id
                     AND b.meta_key = 'overall_status'
                     AND b.meta_value = 'unassigned'
                " . $access_sql . "
                INNER JOIN $wpdb->postmeta as e
                  ON a.ID=e.post_id
                  AND (( e.meta_key = 'type'
                    AND ( e.meta_value = 'media' OR e.meta_value = 'next_gen' ) )
                  OR e.meta_key IS NULL)
                    WHERE a.post_status = 'publish'
                  )
                as needs_assigned,
            (SELECT count(a.ID)
                FROM $wpdb->posts as a
                INNER JOIN $wpdb->postmeta as b
                  ON a.ID=b.post_id
                     AND b.meta_key = 'overall_status'
                     AND b.meta_value = 'new'
                " . $access_sql . "
                INNER JOIN $wpdb->postmeta as e
                  ON a.ID=e.post_id
                  AND (( e.meta_key = 'type'
                    AND ( e.meta_value = 'media' OR e.meta_value = 'next_gen' ) )
                  OR e.meta_key IS NULL)
                    WHERE a.post_status = 'publish'
                  )
                as new
              ", ARRAY_A );

            foreach ( $dispatcher_counts[0] as $key => $value ) {
                $numbers[$key] = $value;
            }
        }
        // phpcs:enable

        $numbers = wp_parse_args( $numbers, [
            'my_contacts' => 0,
            'update_needed' => 0,
            'needs_accepted' => 0,
            'contact_unattempted' => 0,
            'meeting_scheduled' => 0,
            'all_contacts' => 0,
            'needs_assigned' => 0,
        ] );

        return $numbers;
    }


    /**
     * Return an associative array of sources for contacts that the current
     * user can see, (or all sources if the user has permission). The return
     * value looks like this:
     *
     *  $rv = [
     *      // $source_key => $source_label,
     *      "facebook" => null, // when a label could not be found for a source
     *      "phone" => "The phone",
     *      "partner" => "Our partners",
     *      "web" => "Website",
     *  ];
     *
     *  @access public
     *  @return array | WP_Error
     */
    public static function list_sources() {
        if ( !self::can_access( "contacts" ) ) {
            return new WP_Error( __FUNCTION__, "Permission denied.", [ 'status' => 403 ] );
        }
        global $wpdb;
        $source_labels = dt_get_option( 'dt_site_custom_lists' )['sources'];
        $rv = [];

        if ( current_user_can( 'view_any_contacts' ) ) {
            foreach ( $source_labels as $source_key => $source ) {
                if ( !isset( $source["enabled"] ) || $source["enabled"] != false ){
                    $rv[$source_key] = $source['label'];
                }
            }
            //check for sources not in the defined list
            $results = $wpdb->get_results(
                "SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = 'sources'",
                ARRAY_N
            );
            foreach ( $results as $result ) {
                if ( ! array_key_exists( $result[0], $rv ) ) {
                    $rv[ $result[0] ] = null;
                }
            }
        } else {
            $user_id = get_current_user_id();
            // get the sources for the contacts shared with the user
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT meta_value 
                FROM $wpdb->postmeta 
                JOIN $wpdb->dt_share as shares ON ( 
                    shares.post_id = $wpdb->postmeta.post_id
                    AND shares.user_id = %s
                )  
                WHERE meta_key = 'sources'",
                $user_id
            ), ARRAY_N );
            foreach ( $results as $result ) {
                $post_source_key = $result[0];
                if ( ! array_key_exists( $post_source_key, $rv ) ) {
                    if ( array_key_exists( $post_source_key, $source_labels ) ) {
                        if ( !isset( $source_labels[$post_source_key]["enabled"] ) || $source_labels[$post_source_key]["enabled"] != false ) {
                            $rv[ $post_source_key ] = $source_labels[ $post_source_key ]['label'];
                        }
                    } else {
                        $rv[ $post_source_key ] = null;
                    }
                }
            }
        }

        asort( $rv );
        return $rv;
    }


    /**
     * Make sure activity is created for all the steps before the current seeker path
     *
     * @param $contact_id
     * @param $initial_fields
     * @param $contact
     * @param $previous_values
     */
    public function check_seeker_path( $contact_id, $initial_fields, $contact, $previous_values ){
        if ( isset( $contact["seeker_path"]["key"] ) && $contact["seeker_path"]["key"] != "none" ){
            $current_key = $contact["seeker_path"]["key"];
            $prev_key = isset( $previous_values["seeker_path"]["key"] ) ? $previous_values["seeker_path"]["key"] : "none";
            $field_settings = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
            $seeker_path_options = $field_settings["seeker_path"]["default"];
            $option_keys = array_keys( $seeker_path_options );
            $current_index = array_search( $current_key, $option_keys );
            $prev_option_key = $option_keys[ $current_index - 1 ];

            if ( $prev_option_key != $prev_key && $current_index > array_search( $prev_key, $option_keys ) ){
                global $wpdb;
                $seeker_path_activity = $wpdb->get_results( $wpdb->prepare( "
                    SELECT meta_value, hist_time, meta_id
                    FROM $wpdb->dt_activity_log
                    WHERE object_id = %s
                    AND meta_key = 'seeker_path'
                ", $contact_id), ARRAY_A );
                $existing_keys = [];
                $most_recent = 0;
                $meta_id = 0;
                foreach ( $seeker_path_activity as $activity ){
                    $existing_keys[] = $activity["meta_value"];
                    if ( $activity["hist_time"] > $most_recent ){
                        $most_recent = $activity["hist_time"];
                    }
                    $meta_id = $activity["meta_id"];
                }
                $activity_to_create = [];
                for ( $i = $current_index; $i > 0; $i-- ){
                    if ( !in_array( $option_keys[$i], $existing_keys ) ){
                        $activity_to_create[] = $option_keys[$i];
                    }
                }
                foreach ( $activity_to_create as $missing_key ){
                    $wpdb->query( $wpdb->prepare("
                        INSERT INTO $wpdb->dt_activity_log
                        ( action, object_type, object_subtype, object_id, user_id, hist_time, meta_id, meta_key, meta_value, field_type )
                        VALUES ( 'field_update', 'contacts', 'seeker_path', %s, %d, %d, %d, 'seeker_path', %s, 'key_select' )",
                        $contact_id, get_current_user_id(), $most_recent - 1, $meta_id, $missing_key
                    ));
                }
            }
        }
    }
    
    public static function get_contact_header_info(){
/** //global $wpdb;
//$query = "SELECT 
//            `id`, 
//            `entity`, 
//            `name`, 
//            `label`, 
//            `sort_num`, 
//            `rv`, 
//            `rvs`, 
//            `mv`, 
//            `ty`, 
//            `tyl`, 
//            `tyf` 
//            FROM `wp_dt_headers` 
//            WHERE `entity` = 'contact' 
//          ORDER BY `sort_num`";
//$results = $wpdb->get_results($query, ARRAY_A);
//return $results; */
        
        $data = [];      
        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();

        foreach($channels as $label=>$channel){ 
            $label = "contact_{$label}";  // @NOTE: prefix "contact_"          
            $data[$label] = ['name'=>$channel['label'], 'type'=>'standard', 'defaults'=>null];
        }
        
        $fields = self::get_contact_fields();
        if(isset($fields)){ 
            $data = array_merge($data,$fields);
        }        
        return $data;
    }
    
/** //public static function get_contact_headers(){
//
//    //$data = array(
//    //'title', 
//    //'contact_fullname',
//    //'contact_phone',
//    //'contact_email',
//    //'contact_address',
//    //'gender',
//    //'notes'
//    //);
//    //
//    //$data = [];
//    //global $wpdb;
//    //$query = "SELECT `name` 
//    //          FROM `wp_dt_headers` 
//    //          WHERE `entity` = 'contact' 
//    //          ORDER BY `sort_num`"; 
//    //$results = $wpdb->get_results($query, ARRAY_A);  
//    //foreach ($results as $result) { $data[] = $result['name']; }
//    //return $data;
//
//    $data = [];
//    $custom_field_options = dt_get_option( "dt_field_customizations" );
//    if(isset($custom_field_options['contacts'])){
//        foreach($custom_field_options['contacts'] as $cfopts){
//            $data[] = $cfopts['name'];
//        }
//    }
//    return $data;        
//}
    
    //public static function get_contact_header_alias(){
    //    $data = [];
    //    global $wpdb;
    //    $query = "SELECT `id`,`name` 
    //              FROM `wp_dt_headers` 
    //              WHERE `entity` = 'contact' 
    //              ORDER BY `sort_num`";
    //    $results = $wpdb->get_results($query, ARRAY_A);  
    //    foreach ($results as $i=>$result) { 
    //        $data[$i] = [
    //            'id'=>$result['id'],
    //            'label'=>$result['name']]; 
    //
    //        $query = "SELECT alias 
    //                  FROM `wp_dt_headers_aliases` 
    //                  WHERE entity = 'contact' 
    //                  AND header_id = %s";
    //
    //        $aliases = $wpdb->get_results($wpdb->prepare($query,$result['id']), ARRAY_A);
    //        foreach($aliases as $alias){
    //            $data[$i]['alias'][] = $alias['alias'];
    //        }
    //    }
    //    return $data;
    //} */
    
    public static function get_mapper($name=''){        
        /** //$column_name = '';
//global $wpdb;         
//$query = "SELECT wp_dt_headers.name 
//    FROM wp_dt_headers_aliases, wp_dt_headers 
//    WHERE wp_dt_headers_aliases.entity = 'contact' 
//    AND wp_dt_headers.entity = 'contact' 
//    AND wp_dt_headers_aliases.header_id = wp_dt_headers.id 
//    AND wp_dt_headers_aliases.alias = %s";
//$column_name = $wpdb->get_var( $wpdb->prepare( $query, $name ) );                
//return $column_name; */     
        
        return self::colheadcompare($name);
    }
    
    public static function colheadcompare ($source_col_heading){
        $column_name = null;
        $src = strtolower(trim($source_col_heading));
        //echo "souce-heading:{$src}<br/>";
        
        // @NOTE: prefix "contact_"
        $prefix = 'contact_';
        
        if(array_search($src, self::$contact_name_headings)>0){ 
            $column_name = 'title';         
        } else if(array_search($src, self::$contact_phone_headings)>0){
            $column_name = "{$prefix}phone"; 
            
        } else if(array_search($src, self::$contact_email_headings)>0){
            $column_name = "{$prefix}email";
            
        } else if(array_search($src, self::$contact_address_headings)>0){
            $column_name = "{$prefix}address";             

        } else {
            //get custom contact fields added by user
            //$custom_field_options = dt_get_option( "dt_field_customizations" );
            //if(isset($custom_field_options['contacts'][$src])){ 
            //    $column_name = $src; 
            //}
            $fields = self::get_contact_fields();
            if(isset($fields[$src])){ 
                $column_name = $src;
            } else {
                $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
                if(isset($channels[$src])){ 
                    //$column_name = $src;
                    $column_name = "{$prefix}{$src}";
                }
            }
            
            //try to match the field label
            //assigned_to => ['name'=>"Assigned To"]
            if($column_name==null){
                foreach($fields as $f=>$field){                    
                    if(isset($field['name'])&&strtolower(trim($field['name']))==$src){
                        $column_name = $f;
                    }
                }            
            }
            
            if($column_name==null){
                $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
                foreach($channels as $f=>$field){
                    if(isset($field['name'])&&strtolower(trim($field['name']))==$src){
                        $column_name = $f;
                    }
                }
            }
            
            
        }
        
        //echo "destination-column-name:{$column_name}";

        return $column_name;
    }

    public static function getDropdownListHtml($field, $id='selector',$data=[],$selected=null,$htmlOptions=[],$allowAllTypes=true){
        
        if(isset($htmlOptions['id'])){ unset($htmlOptions['id']); }
        
        $f = true;
        
        $html = "<select id=\"{$id}\"";  
        
        $html .= " data=\"".$field."\"";
        
        foreach($htmlOptions as $opt=>$values){
            $html .= " {$opt}=\"{$values}\"";
        }        
        
        $html .= ">";   
        
        
        $html .= "<option value=\"NONE\">--select-one--</option>";
        $html .= "<option value=\"IGNORE\">don't import</option>";
         
////////////////////////////////////////////////////////////////////////////////
        
        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $html .= "<optgroup label=\"Standard Fields\">";        
        
        $html .= "<option value=\"title\"";
        if($selected=='title'){ $html .= " selected=\"selected\""; }
        $html .= ">Contact Name</option>";
        
        foreach($channels as $label=>$item){ 
            
            //if($label=='phone'||$label=='email'||$label=='address'){
                $label = "contact_{$label}";
            //} // @NOTE: prefix "contact_"
            
            $html .= "<option value=\"{$label}\"";
            if($selected!=null && $selected=="{$label}"){ $html .= " selected=\"selected\""; }
            $html .= ">{$item['label']}</option>";
        }
        $html .= "</optgroup>"; 

        $data = self::get_contact_fields();

        $listData = array();
        foreach($data as $key=>$item){   
            $listData[$key] = $item['name'];
        }
        asort($listData);

        $html .= "<optgroup label=\"Other Fields\">";
        foreach($listData as $key=>$label){ 
            
            $f = true; $type = null;            
            
            if(isset($data[$key]['type'])){ 
                $type = $data[$key]['type']; 
                if($data[$key]['type']=='multi_select'){
                    $html .= " disabled=\"disabled\"";
                    $f = false;
                }
            }
            
            if($f){
                $html .= "<option value=\"{$key}\"";
                if($selected!=null && $selected==$key){ 
                    $html .= " selected=\"selected\""; 
                }
                $html .= ">";
                $html .= "{$label}";
                $html .= "</option>";
            }
            
            

        }
        $html .= "</optgroup>";  
////////////////////////////////////////////////////////////////////////////////
        
        
        $html .= '</select>';        
        return $html;
    }

    public static function process_data(
            $dataRows, 
            $csvHeaders=[], 
            $valueMapperiData=[], 
            $valueMapperData=[], 
            $assign='', 
            $source='', 
            $del=';'){
        
//echo 'X<br/><pre>'; print_r($valueMapperiData); echo '</pre>'; echo '<hr>';
//echo 'Y<br/><pre>'; print_r($valueMapperData); echo '</pre>'; echo '<hr>';
//        
        $mulSep = ';';
        $people = [];

        $chi = array_count_values($csvHeaders);
        
        foreach($csvHeaders as $ci=>$ch){
            $_mc = self::get_mapper($ch);
            if($_mc != null && strlen($_mc)>0){ 
                $csvHeaders[$ci] = $_mc;   
            }
        } 
        
        //handle N columns to ONE column mapping
        //phone(primary)/phone(mobile) -> phone
        $mids = []; $delCsvHeaders = [];
        foreach($chi as $mch=>$count){
            if($count>1){
                $mids[$mch]['count'] = $count;
                foreach($csvHeaders as $ci=>$ch){
                    if($mch==$ch){ 
                        $mids[$mch]['columIds'][] = $ci;
                    }
                }
                $mids[$mch]['primaryCol'] = $mids[$mch]['columIds'][0];
                unset($mids[$mch]['columIds'][0]); //array_pop
             }
        }

        foreach ($dataRows as $rowId=>$rowData) { 
            foreach ($rowData as $colId => $colData){
                if(isset($csvHeaders[$colId])){
                    $ch = $csvHeaders[$colId];
                    if(isset($mids[$ch]['columIds'])){
                        foreach($mids[$ch]['columIds'] as $xcolid){
                            if($colId==$xcolid){
                                $dataRows[$rowId][ $mids[$ch]['primaryCol'] ] .= $mulSep.$colData;
                                unset($dataRows[$rowId][$xcolid]);
                            }
                        }            
                    } 
                }  
            }     
        }

        foreach($mids as $ch=>$xdata){
            foreach($xdata['columIds'] as $xcolid){
                if(isset($csvHeaders[$xcolid])){        
                    unset($csvHeaders[$xcolid]);
                }
            }
        }

        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $cfs = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();

        
        foreach ($dataRows as $ri=>$row) {             
            $fields = [];
            foreach ($row as $index => $i) { 
                
                if ($assign != '') { $fields["assigned_to"] = (int) $assign; }                
                $fields["sources"] = [ "values" => array( [ "value" => $source ] ) ];
                
                //cleanup
                $i = str_replace( "\"", "", $i );
                            
                if(isset($csvHeaders[$index])){
                    
                    $ch = $csvHeaders[$index];                
                    
                    $type = isset($cfs[$ch]['type'])?$cfs[$ch]['type']:null;
                    if($type==null){ $type = isset($channels[$ch])?$channels[$ch]:null; }
                    
                    if($ch=='title'){
                        $fields[$ch] = $i;
                        
//                    } else if($ch=='gender'||$ch=='cf_gender'){
//
//                        $i = strtolower( $i );
//                        $i = substr( $i, 0, 1 );
//                        $gender = "not-set";
//                        if ($i == "m" ){ $gender = "male";
//                        } else if ($i == "f" ){ $gender = "female"; }
//                        $fields[$ch] = $gender;           
                    
                    } else if($type=='key_select'){

                        //$valueMapperData[$index]
                        if(isset($valueMapperiData[$index])){
                            foreach($valueMapperiData[$index] as $vmdi=>$vmdv){
                                if($vmdv==$i && isset($valueMapperData[$index][$vmdi])){
                                    $fields[$ch] = $valueMapperData[$index][$vmdi];
                                }
                            }
                        }
//
//                    } else if($type=='multi_select'){
//                        
//                        //not handle yet!
//                        
//                    //} else if($type=='user_select'){
//                    //    $fields[$ch] = $i;
//                        
                    } else if($type=='boolean'){
                        $fields[$ch] = $i[0] === "1";
                        
                    } else if($type=='date'){
                        $fields[$ch] = date('Y-m-d', strtotime($i));
                        
//                    } else if($type=='array'){//                        
//                        foreach((array)$i as $av){
//                            $fields[$ch][] = [ "value" => $av ];
//                        }//                                            
//                    //} else if($type=='text'){           
//                    //} else if($type=='connection'){
                    } else {
                        
                        //handle multivalued data
                        $pos = strpos($i, $del);
                        if ($pos === false) {
                            $fields[$ch][] = [ "value" => $i ];
                        } else {
                            $multivalued = explode($del, $i);
                            foreach($multivalued as $mx){
                               $fields[$ch][] = [ "value" => $mx ]; 
                            }  
                        } 
                    }
                } 
            }          
            
            //add person
            $people[] = array( $fields );
            unset( $fields );

        }
        
        return $people;
    }
    
//public static function labelx($ch){
//    $f = self::get_contact_fields();
//    if(isset($f[$ch])){ return $f[$ch]; } else { return null; }
//}
    
    public static function display_data2($people){
        
        if(isset($people[0][0])){
            $headings = array_keys($people[0][0]);
            //echo '<pre>'; print_r($headings); echo '</pre>';
            //echo '<pre>'; print_r($people[0][0]); echo '</pre>';
            if(isset($headings['sources'])){ unset($headings['sources']); }
            if(isset($headings['assigned_to'])){ unset($headings['assigned_to']); }
            //if(isset($headings['title'])){ unset($headings['title']); }
        }
        
        $prefix = 'contact_';
        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $cfs = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();        
        
        
        $html = '';        
        
        $html .= '<table class="data-table">';
        $html .= '<thead>';
        $html .= '<tr>';        
        $html .= '<th></th>';
        
        $html .= '<th>';
        $html .= '<span class="cflabel">';
        $html .= esc_html( translate( 'Contact Name', 'disciple_tools' ));        
        $html .= '</span>';        
        //$html .= '<br/><span class="cffield">title</span>';
        $html .= '</th> '; 
        
        foreach($headings as $heading){
            
            if($heading=='title'){
            } else if($heading=='assigned_to'){                    
            } else if($heading=='sources'){                    
            } else {

                $html .= '<th><span class="cflabel">';
                $ch = str_replace($prefix,'',$heading);
                if(isset($cfs[$ch],$cfs[$ch]['name'])){
                    $html .= esc_html( translate( $cfs[$ch]['name'], 'disciple_tools' ));                    
                } else if(isset($channels[$ch],$channels[$ch]['label'])){
                    $html .= esc_html( translate( $channels[$ch]['label'], 'disciple_tools' ));
                }                
                $html .= '</span>';                
                
                $html .= '<br/>';
                $html .= '<span class="cffield">';
                $html .= esc_html( translate( $heading, 'disciple_tools' )); 
                $html .= '</span></th>';                            
            }
        }        
        
        $html .= '<th><span class="cflabel">';
        $html .= esc_html( translate( 'Source', 'disciple_tools' ));        
        $html .= '</span></th>';
        
        $html .= '<th><span class="cflabel">';
        $html .= esc_html( translate( 'Assigned To', 'disciple_tools' ));        
        $html .= '</span></th>';        
        
        $html .= '</tr>';
        $html .= '</thead>';  
        
        
        
        $html .= '<tbody>';
        
        foreach($people as $pid=>$pplData){ 
            $rowindex++;
            $personData = $pplData[0];

            $html .= '<tr id="person-data-item-'.$pid.'" 
                     class="person-data-item">'; 
            
            $html .= '<td>'.$rowindex.'</td>';
            
            $html .= '<td>';
            $html .= $personData['title'];
            $html .= '</td>'; 
        
            
            foreach($headings as $ch){

                $type = isset($cfs[$ch]['type'])?$cfs[$ch]['type']:null;
                if($type==null){ $type = isset($channels[$ch])?$channels[$ch]:null; }                

                //if(isset( $personData[$ch][0]["value"] )){ 
                    //$html .= esc_html( $personData[$ch][0]["value"] );
                
                if($ch=='title'){                    
                } else if($ch=='assigned_to'){                    
                } else if($ch=='sources'){
                } else {
                    
                    $html .= '<td data-key="'.$ch.'">';
                    if($type=='key_select'||$type=='date'||$type=='boolean'){
                        $html .= esc_html( $personData[$ch]);

                    } else if(isset($personData[$ch]) && is_array($personData[$ch])){

                        foreach($personData[$ch] as $vi=>$val){
                            if($vi>0){ $html .= $del; }
                            $html .= esc_html( $val["value"] );
                        }

                    }
                    $html .= '</td>';
                } 
            }            

            $html .= '<td>';
            $html .= $personData['sources']["values"][0]["value"];
            $html .= '</td>';
            

            $html .= '<td>';        
            if(( isset( $personData['assigned_to'] ) && $personData['assigned_to'] != '' )){
                $html .= esc_html( get_user_by( 'id', $personData['assigned_to'] )->data->display_name );
            } else { $html .= 'Not Set'; }        
            $html .= '</td>';
            
            $html .= '</tr>';        
        }
        
        $html .= '</tbody>';        
        $html .= '</table>';       
        
        return $html;    
        
    }

    public static function display_data($people, $conHeadersInfo, $csvHeaders){
        $html = '';        
        
        $html .= '<table class="data-table">';
        $html .= '<thead>';
        $html .= '<tr>';        
        $html .= '<th></th>';
        $html .= '<th>';
        $html .= '<span class="cflabel">';
        $html .= esc_html( translate( 'Contact Name', 'disciple_tools' ));        
        $html .= '</span><br/>';
        
        $html .= '<span class="cffield">title</span>';
        $html .= '</th> ';   
        
        
        foreach($csvHeaders as $ci=>$ch){        
        
            if($ch=='title'){ continue; } 

            $html .= '<th>';            
            $html .= '<span class="cflabel">';
            if($ch=='csv_source'){
                $html .= '<span style="color:green">Source</span>';
            } else if(isset($conHeadersInfo[$ch]['name'])){ 
                $html .= $conHeadersInfo[$ch]['name']; 
            } else {
                $html .= '<span style="color:red">UNMAPPED</span>';
            }   
            $html .= '</span><br/>';

            $html .= '<span class="cffield">';
            //$html .= esc_html( translate( 'Title', 'disciple_tools' ));
            $html .= $ch;
            $html .= '</span>';
            $html .= '</th>';
        }

        $html .= '<th><span class="cflabel">';
        $html .= esc_html( translate( 'Source', 'disciple_tools' ));        
        $html .= '</span></th>';
        
        $html .= '<th><span class="cflabel">';
        $html .= esc_html( translate( 'Assigned To', 'disciple_tools' ));        
        $html .= '</span></th>';
        $html .= '</tr>';
        $html .= '</thead>';              
        
        $html .= '<tbody>';
        
        $rowindex=0;
        foreach($people as $pid=>$pplData){ 
            $rowindex++;
            $personData = $pplData[0];

            $html .= '<tr id="person-data-item-'.$pid.'" 
                     class="person-data-item">'; 
            
            $html .= '<td>'.$rowindex.'</td>';
            
            $html .= '<td>';
            $html .= $personData['title'];
            $html .= '</td>';       

            foreach($csvHeaders as $ci=>$ch){  

                if($ch=='title'){ continue; }
                $html .= '<td data-key="'.$ch.'">';

                if($ch=='cf_gender'){
                    if(isset( $personData[$ch] )){ $html .= esc_html( $personData[$ch] );                    
                    } else { $html .= 'None'; }

                } else if($ch=='cf_notes'){
                //} else if($ch=='cf_notes'||$ch=='cf_dob'||$ch=='cf_join_date'){
                    if(isset( $personData[$ch][0] )){ $html .= esc_html( $personData[$ch][0] );
                    } else { $html .= 'None'; }

                } else {

                    if(isset( $personData[$ch][0]["value"] )){ 
                        $html .= esc_html( $personData[$ch][0]["value"] );
                    } else {
                        $html .= 'None';
                    }
                }

                $html .= '</td>';    
            }


            $html .= '<td>';
            $html .= $personData['sources']["values"][0]["value"];
            $html .= '</td>';

            $html .= '<td>';        
            if(( isset( $personData['assigned_to'] ) && $personData['assigned_to'] != '' )){
                $html .= esc_html( get_user_by( 'id', $personData['assigned_to'] )->data->display_name );
            } else { $html .= 'Not Set'; }        
            $html .= '</td>';        
            $html .= '</tr>';        
        }
        
        $html .= '</tbody>';        
        $html .= '</table>';       
        
        return $html;        
    }
    
    
    public static function getAllDefaultValues(){
        $data = array();
        $data['channels'] = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();        
        $data['fields'] = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();        
        return $data;
    }
}
