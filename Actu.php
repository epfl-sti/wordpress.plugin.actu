<?php

/**
 * "Actu" custom post type and taxonomy.
 *
 * For each entry in actus.epfl.ch that the WordPress administrators
 * are interested in, there is a local copy as a post inside the
 * WordPress database whose contents typically consists of a single
 * shortcode. This allows e.g. putting actus news into the newsletter.
 */

namespace EPFL\Actu;

if (! defined('ABSPATH')) {
    die('Access denied.');
}

require_once(dirname(__FILE__) . "/inc/i18n.php");

/**
 * Configuration and WP callbacks for the post type and taxonomy (all in one class)
 *
 * This is a "pure static" class; no instances are ever constructed.
 */
class ActuConfig
{
    const SLUG = "epfl-actu";

    static function hook ()
    {
        add_action('init', array(get_called_class(), 'register_post_type'));
        add_action('init', array(get_called_class(), 'register_taxonomy'));
        add_filter('enter_title_here', array(get_called_class(), 'enter_title_here'),
                   10, 2);
        $main_plugin_file = dirname(__FILE__) . "/EPFL-actu.php";
        register_activation_hook($main_plugin_file, array(get_called_class(), "register_caps"));
        register_deactivation_hook($main_plugin_file, array(get_called_class(), "deregister_caps"));
    }

    /**
     * Replace the "Enter title here" prompt for Actu-typed posts
     */
    static function enter_title_here ($text, $post)
    {
        if ($post->post_type != self::SLUG) return $text;
        return __x("Event name", "enter_title_here");
    }

    /**
     * Make it so that actus pages exist.
     *
     * Under WordPress, almost everything publishable is a post.
     * register_post_type() is invoked to create a particular flavor
     * of posts that describe news.
     */
    static function register_post_type ()
    {
        register_post_type(
            self::SLUG,
            array(
                'labels'             => array(
                    'name'               => __x( 'EPFL News', 'post type general name' ),
                    'singular_name'      => __x( 'EPFL News', 'post type singular name' ),
                    'menu_name'          => __x( 'EPFL News', 'admin menu' ),
                    'name_admin_bar'     => __x( 'EPFL News', 'add new on admin bar' ),
                    'view_item'          => ___( 'View EPFL News Item' ),
                    'all_items'          => ___( 'All EPFL News for this site' ),
                    'search_items'       => ___( 'Search News' ),
                    'not_found'          => ___( 'No news found.' ),
                    'not_found_in_trash' => ___( 'No news found in Trash.' )
                ),
                'description'        => ___( 'EPFL News from news.epfl.ch' ),
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'query_var'          => true,
                'rewrite'            => array( 'slug' => self::SLUG ),
                  // ad hoc access control, see (de|)register_caps() below:
                'capability_type'    => array('epfl_actu', 'epfl_actus'),
                'has_archive'        => true,
                'hierarchical'       => false,
                'menu_position'      => null,
                'menu_icon'          => 'dashicons-megaphone',
                'supports'           => array( 'title' )
            ));
    }

    /**
     * Create Actu channels as a taxonomy.
     *
     * A "taxonomy" is a complicated word for a category of Actu
     * entries. Actu entries are grouped by "channels", i.e. the feed
     * they come from. Channels have names and host suitable metadata,
     * i.e. an API URL to fetch from.
     */
    function register_taxonomy ()
    {
        $taxonomy_slug = 'epfl-actu-channel';
        register_taxonomy( $taxonomy_slug, array( self::SLUG ),
            array(
                'hierarchical'      => false,
                'labels'            => array(
                'name'              => __x( 'News Channels', 'taxonomy general name'),
                'singular_name'     => __x( 'News Channel', 'taxonomy singular name'),
                'search_items'      => ___( 'Search News Channels'),
                'all_items'         => ___( 'All News Channels'),
                'edit_item'         => ___( 'Edit News Channel'),
                'update_item'       => ___( 'Update News Channel'),
                'add_new_item'      => ___( 'Add News Channel'),
                'new_item_name'     => ___( 'New Channel Name'),
                'menu_name'         => ___( 'News Channels'),
                ),
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                // TODO: capabilities here.
                'rewrite'           => array( 'slug' => $taxonomy_slug ),
            ));
        add_action("${taxonomy_slug}_add_form_fields", array(get_called_class(), "create_channel_widget"));
        add_action( "${taxonomy_slug}_edit_form_fields", array(get_called_class(), "update_channel_widget"), 10, 2);
        add_action( "created_${taxonomy_slug}", array(get_called_class(), 'created_channel'), 10, 2 );
        add_action( "edited_${taxonomy_slug}", array(get_called_class(), 'edited_channel'), 10, 2 );
    }

    const CHANNEL_API_URL_SLUG = "epfl_actu_channel_api_url";
    function create_channel_widget ($taxonomy)
    {
        self::render_channel_widget(array("placeholder" => "http://example.com/"));
    }

    function update_channel_widget ($term, $taxonomy)
    {
        self::render_channel_widget(array("value" => get_term_meta( $term->term_id, self::CHANNEL_API_URL_SLUG, true )));
    }

    function render_channel_widget ($input_attributes)
    {
      ?><div class="form-field term-group">
        <label for="<?php echo self::CHANNEL_API_URL_SLUG ?>"><?php echo ___('Actu Channel API URL'); ?></label>
        <input id="<?php echo self::CHANNEL_API_URL_SLUG ?>" name="<?php echo self::CHANNEL_API_URL_SLUG ?>" <?php
           foreach ($input_attributes as $k => $v) {
               echo "$k=" . htmlspecialchars($v);
           }?> />
       </div><?php
    }

    function created_channel( $term_id, $tt_id )
    {
        $channel_api_url = $_POST[self::CHANNEL_API_URL_SLUG];
        add_term_meta( $term_id, self::CHANNEL_API_URL_SLUG, $channel_api_url, true );
    }

    function edited_channel( $term_id, $tt_id ){
        $channel_api_url = $_POST[self::CHANNEL_API_URL_SLUG];
        update_term_meta( $term_id, self::CHANNEL_API_URL_SLUG, $channel_api_url, true );
    }

    const ROLES_THAT_MAY_VIEW_ACTUS = array('administrator', 'editor', 'author', 'contributor');
    const CAPS_FOR_VIEWERS = array(
        'edit_epfl_actus'
    );
    const ALL_ROLES = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
    const ALL_CAPS = array(
        'edit_epfl_actu', 
        'read_epfl_actu',
        'delete_epfl_actu', 
        'edit_others_epfl_actus', 
        'publish_epfl_actus',       
        'read_private_epfl_actus', 
        'edit_epfl_actus'
    );

    /**
     * Register permissions ("capabilities") on Actu posts.
     *
     * Called at plugin activation time.
     */
    static function register_caps ()
    {
        foreach (self::ROLES_THAT_MAY_VIEW_ACTUS as $role_name) {
            $role = get_role($role_name);
            foreach (self::CAPS_FOR_VIEWERS as $cap) {
                $role->add_cap($cap);
            }
        }
    }

    /**
     * De-register permissions ("capabilities") on Actu posts.
     *
     * Called at plugin deactivation time.
     */
    static function deregister_caps ()
    {
        foreach (self::ALL_ROLES as $role_name) {
            $role = get_role($role_name);
            foreach (self::ALL_CAPS as $cap) {
                $role->remove_cap($cap);
            }
        }
    }
}
