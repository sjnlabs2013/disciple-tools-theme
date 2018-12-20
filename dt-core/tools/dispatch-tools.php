<?php

class Disciple_Tools_Dispatch_Tools {
    public function __construct() {}

    public static function get_diagnosis_results(){
        return [
            "undefined_sources" => self::undefined_sources(),
            "contacts_without_sources" => self::contacts_without_sources(),
            "users_accept" => [ "has_problem" => false ]
        ];
    }

    public static function undefined_sources(){
        global $wpdb;
        $source_labels = dt_get_option( 'dt_site_custom_lists' )['sources'];
        foreach ( $source_labels as $source_key => $source ) {
            if ( !isset( $source["enabled"] ) || $source["enabled"] != false ){
                $rv[$source_key] = $source['label'];
            }
        }
        //check for sources not in the defined list
        $results = $wpdb->get_results(
            "SELECT COUNT( meta_value ) as count, meta_value as key_2
            FROM $wpdb->postmeta WHERE meta_key = 'sources'
            GROUP BY meta_value
            ",
            ARRAY_N
        );
        $undefined_sources = [];
        foreach ( $results as $result ) {
            if ( ! array_key_exists( $result[1], $source_labels ) ) {
                $undefined_sources[ $result[1] ] = $result[0];
            }
        }
        return [
            "has_problem" => sizeof( $undefined_sources ) > 0,
            "sources" => $undefined_sources
        ];
    }

    public static function contacts_without_sources(){
        global $wpdb;
        $no_source_result = $wpdb->get_results("
            SELECT posts.ID
            FROM $wpdb->posts as posts
            LEFT JOIN $wpdb->postmeta as pm ON ( pm.post_id = posts.ID AND pm.meta_key = 'sources' )
            LEFT JOIN $wpdb->postmeta as user ON ( user.post_id = posts.ID AND user.meta_key = 'corresponds_to_user' )
            WHERE posts.post_type = 'contacts'
            AND pm.post_id IS NULL
            AND user.meta_value IS NULL
        ", ARRAY_A );

        return [
            "has_problem" => sizeof( $no_source_result ) > 0,
            "contacts" => array_slice( $no_source_result, 0, 10 ),
            "total" => sizeof( $no_source_result )
        ];
    }
}
