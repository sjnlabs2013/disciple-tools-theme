<?php
// Register menus
register_nav_menus(
	array(
		'main-nav' => __( 'The Main Menu', 'disciple_tools' ),   // Main nav in header
		'footer-links' => __( 'Footer Links', 'disciple_tools' ) // Secondary nav in footer
	)
);

// The Top Menu
function disciple_tools_top_nav() {

    echo '<div class="menu-centered">
          <ul class="vertical medium-horizontal menu" data-accordion-menu>
            <li><a href="/">Dashboard</a></li>
            <li><a href="/contacts">Contacts</a></li>
            <li><a href="/groups">Groups</a></li>
            <li><a href="/prayer">Prayer-Guide</a></li>
            <li><a href="/profile">Profile</a></li>
            <li>
            <form role="search" method="get" class="search-form" action="'. home_url( '/' ) .'">
                <input type="search" class="small" placeholder="' . esc_attr_x( 'Search...', 'disciple_tools' ) . '" value="' . get_search_query() . '" name="s" title="'. esc_attr_x( 'Search for:', 'disciple_tools' ).'" />
                <input type="hidden" class=" button small" value="'. esc_attr_x( 'Search', 'disciple_tools' ) .'" />
            </form>
            </li>
          </ul>
        </div>';

//    wp_nav_menu(array(
//        'container' => false,                           // Remove nav container
//        'menu_class' => 'vertical medium-horizontal menu',       // Adding custom nav class
//        'items_wrap' => '<ul id="%1$s" class="%2$s" data-responsive-menu="accordion medium-dropdown">%3$s</ul>',
//        'theme_location' => 'main-nav',        			// Where it's located in the theme
//        'depth' => 5,                                   // Limit the depth of the nav
//        'fallback_cb' => false,                         // Fallback function (see below)
//        'walker' => new Topbar_Menu_Walker()
//    ));
}

// Big thanks to Brett Mason (https://github.com/brettsmason) for the awesome walker
class Topbar_Menu_Walker extends Walker_Nav_Menu {
    function start_lvl(&$output, $depth = 0, $args = Array() ) {
        $indent = str_repeat("\t", $depth);
        $output .= "\n$indent<ul class=\"menu\">\n";
    }
}

// The Off Canvas Menu
function disciple_tools_off_canvas_nav() {

    echo '
          <ul class="vertical menu" data-accordion-menu>
            <li><span class="title">Disciple Tools</span></li>
            <li><hr /></li>
            <li><a href="/">Dashboard</a></li>
            <li><a href="/contacts">Contacts</a></li>
            <li><a href="/groups">Groups</a></li>
            <li><a href="/prayer">Prayer-Guide</a></li>
            <li><a href="/profile">Profile</a></li>
            <li><hr /></li>
            <li>
            <form role="search" method="get" class="search-form" action="'. home_url( '/' ) .'" >
                <input type="search" class="small" placeholder="' . esc_attr_x( 'Search...', 'disciple_tools' ) . '" value="' . get_search_query() . '" name="s" title="'. esc_attr_x( 'Search for:', 'disciple_tools' ).'" />
                <input type="hidden" class=" button small" value="'. esc_attr_x( 'Search', 'disciple_tools' ) .'" />
            </form>
            </li>
          </ul>
        ';

    // Removed because we are not using the menu tool in wp admin for the main nav.
//    wp_nav_menu(array(
//        'container' => false,                           // Remove nav container
//        'menu_class' => 'vertical menu',       // Adding custom nav class
//        'items_wrap' => '<ul id="%1$s" class="%2$s" data-accordion-menu>%3$s</ul>',
//        'theme_location' => 'main-nav',        			// Where it's located in the theme
//        'depth' => 5,                                   // Limit the depth of the nav
//        'fallback_cb' => false,                         // Fallback function (see below)
//        'walker' => new Off_Canvas_Menu_Walker()
//    ));


} 

class Off_Canvas_Menu_Walker extends Walker_Nav_Menu {
    function start_lvl(&$output, $depth = 0, $args = Array() ) {
        $indent = str_repeat("\t", $depth);
        $output .= "\n$indent<ul class=\"vertical menu\">\n";
    }
}

// The Footer Menu
function disciple_tools_footer_links() {
    wp_nav_menu(array(
    	'container' => 'false',                         // Remove nav container
    	'menu' => __( 'Footer Links', 'disciple_tools' ),   	// Nav name
    	'menu_class' => 'menu',      					// Adding custom nav class
    	'theme_location' => 'footer-links',             // Where it's located in the theme
        'depth' => 0,                                   // Limit the depth of the nav
    	'fallback_cb' => ''  							// Fallback function
	));
} /* End Footer Menu */

// Header Fallback Menu
function disciple_tools_main_nav_fallback() {
	wp_page_menu( array(
		'show_home' => true,
    	'menu_class' => '',      						// Adding custom nav class
		'include'     => '',
		'exclude'     => '',
		'echo'        => true,
        'link_before' => '',                           // Before each link
        'link_after' => ''                             // After each link
	) );
}

// Footer Fallback Menu
function disciple_tools_footer_links_fallback() {
	/* You can put a default here if you like */
}

// Add Foundation active class to menu
function required_active_nav_class( $classes, $item ) {
    if ( $item->current == 1 || $item->current_item_ancestor == true ) {
        $classes[] = 'active';
    }
    return $classes;
}
add_filter( 'nav_menu_css_class', 'required_active_nav_class', 10, 2 );




/**
 * Checks the existence of core pages for Disciple Tools
 * @return boolean
 */
function dt_pages_check () {



    $postarr = array(
        'Reports',
        'Profile'
    );

    foreach ($postarr as $item) {
        if (! post_exists ($item)) {
            return true;
        }
    }
    return false;
}

/**
 * Installs or Resets Core Pages
 *
 */
function dt_add_core_pages ()
{
    $html = '';

    if ( TRUE == get_post_status( 2 ) ) {	wp_delete_post(2);  } // Delete default page

    $postarr = array(
        array(
            'post_title'    =>  'Reports',
            'post_name'     =>  'reports',
            'post_content'  =>  'The content of the page is controlled by the Disciple Tools plugin, but this page is required by the plugin to display the dashboard.',
            'post_status'   =>  'Publish',
            'comment_status'    =>  'closed',
            'ping_status'   =>  'closed',
            'menu_order'    =>  '4',
            'post_type'     =>  'page',
        ),
        array(
            'post_title'    =>  'Profile',
            'post_name'     =>  'profile',
            'post_content'  =>  'The content of the page is controlled by the Disciple Tools plugin, but this page is required by the plugin to display the dashboard.',
            'post_status'   =>  'Publish',
            'comment_status'    =>  'closed',
            'ping_status'   =>  'closed',
            'menu_order'    =>  '4',
            'post_type'     =>  'page',
        ),

    );

    foreach ($postarr as $item) {
        if (! post_exists ($item['post_title']) ) {
            wp_insert_post( $item, false );
        } else {
            $page = get_page_by_title($item['post_title']);
            wp_delete_post($page->ID);
            wp_insert_post( $item, false );
        }

    }

    return $html;
}





