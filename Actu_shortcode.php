<?php
/*
 * Plugin Name: EPFL Actu (shortcodes)
 * Plugin URI:  https://github.com/epfl-sti/wordpress.plugin.actu
 * Description: Insert EPFL news on your WordPress site from <a href="https://news.epfl.ch">Actu</a>.
 * Version:     0.5
 * Author:      STI-IT Web
 * Author URI:  mailto:stiitweb@groupes.epfl.ch
 * License:     MIT License / Copyright (c) 2017-2018 EPFL ⋅ STI ⋅ IT
 *
 * Usage:
 *   - [actu]
 *   - [actu tmpl=full channel=sti lang=en limit=3]
 *   - [actu tmpl=short channel=igm lang=en limit=10 category=1 project=204 fields=title,subtitle,news_thumbnail_absolute_url,visual_and_thumbnail_description,description,absolute_slug]
 *
 * Note:
 *   - Add `add_filter('actu','do_shortcode');` in theme to enable shortcodes in text widgets
 *   - Item's values are: "translation_id", "title", "video", "visual_and_thumbnail_description",
 *                        "subtitle", "text", "status", "slug", "absolute_slug", "order", "id_original",
 *                        "creation_date", "last_modification_date", "publish_date", "trash_date",
 *                        "delete_date", "channel_name", "news_id", "news_has_video", "news_category_id",
 *                        "news_category_label_en", "news_category_label_fr", "news_visual_absolute_url",
 *                        "news_thumbnail_absolute_url", "news_large_thumbnail_absolute_url", "language".
 *    - Doc
 *        + https://wiki.epfl.ch/api-rest-actu-memento/actu
 *        + https://help-actu.epfl.ch/outils-webmasters/exporter-tri-articles
 *
 * Logs:
 *   - v0.1   First WWIP
 *   - v0.2   More template
 *   - v0.3   Widgets enable
 *   - v0.4   Rewritten to use the Actu REST API
 *   - v0.5   Integration to EPFL-WS, full OOP class,
 *
 */

namespace EPFL\WS\Actu;

/*
 * ToDo:
 *    - Add TinyMCE button: https://wordpress.stackexchange.com/questions/72394/how-to-add-a-shortcode-button-to-the-tinymce-editor
 *    - Validate RSS's url
 *    - Add Cache (wp_cache)
 *    - Comments
 *    - Add CSS classes (similar to https://help-actu.epfl.ch/outils-webmasters/exporter-tri-articles ?)
 *    - INC0203354 - Author + Source
 */

use WP_Error;

class ActuShortCode {

  function __construct() {
    add_shortcode('actu', array($this, 'wp_shortcode'));
    add_action("admin_print_footer_scripts", array($this, 'actu_shortcode_button_script'));
  }
  
  function actu_shortcode_button_script() {
    if(wp_script_is("quicktags")) {
      ?>
        <script type="text/javascript">
          QTags.addButton(
            "actu_shortcode",
            "Actu",
            callback
          );

          function callback()
          {
            QTags.insertContent("[actu tmpl=full channel=sti lang=en limit=3]");
          }
        </script>
      <?php
  }
}
  function display_full($actus)
  {
    foreach ($actus as $item) {
      $actu .= '<h2>' . $item->title . '</h2>';
      $actu .= '<p><img src="' . $item->news_visual_absolute_url . '" title="' . $item->visual_and_thumbnail_description . '">' . $item->description . '</p>';
      $actu .= '<p>Created: ' . $item->creation_date . '</p>';
      $actu .= '<p>' . $item->subtitle . '</p>';
      $actu .= '<p>' . $item->text . '</p>';
      $actu .= '<p><a href="' . $item->absolute_slug . '">Read more</a></p>';
    }
    return $actu;
  }

  function display_short($actus)
  {
    foreach ($actus as $item) {
      $actu .= '<h2>' . $item->title . '</h2>';
      $actu .= '<p>' . $item->subtitle . '</p>';
      $actu .= '<img src="' . $item->news_thumbnail_absolute_url . '" title="' . $item->visual_and_thumbnail_description . '">';
      $actu .= '<a href="' . $item->absolute_slug . '">Read more</a>';
    }
    return $actu;
  }

  function display_widget($actus)
  {
    foreach ($actus as $item) {
      $actu .= '<h2>' . $item->title . '</h2>';
      $actu .= '<a href="' . $item->absolute_slug . '"><img src="' . $item->news_thumbnail_absolute_url . '" title="' . $item->visual_and_thumbnail_description . '"></a>';
    }
    return $actu;
  }

  /**
   * Main logic
   **/
  function wp_shortcode($atts, $content=null, $tag='') {
    // normalize attribute keys, lowercase
    $atts = array_change_key_case((array)$atts, CASE_LOWER);

    // override default attributes with user attributes
    $actu_atts = shortcode_atts([  'tmpl'      => 'full', // full, short, widget
                                   'channel'   => 'sti',
                                   'lang'      => 'en', //fr, en
                                   'limit'     => '10',
                                   'category'  => '', // https://actu.epfl.ch/api/v1/categories/
                                   'project'   => '', // https://actu.epfl.ch/api/jahia/channels/sti/projects/
                                   'fields'    => '', // title,slug,...
                                 ], $atts, $tag);

    $tmpl     = esc_attr($actu_atts['tmpl']);
    $channel  = esc_attr($actu_atts['channel']);
    $lang     = esc_attr($actu_atts['lang']);
    $limit    = esc_attr($actu_atts['limit']);
    $category = esc_attr($actu_atts['category']);
    $project  = esc_attr($actu_atts['project']);
    $fields   = esc_attr($actu_atts['fields']);

    // make the correct URL call
    $url = 'https://actu.epfl.ch/api/jahia/channels/'.$channel.'/news/'.$lang.'/?format=json';
    if ($limit)
      $url .= '&limit=' . $limit;
    if ($category)
      $url .= '&category=' . $category;
    if ($project)
      $url .= '&project=' . $project;
    if ($fields)
      $url .= '&fields=' . $fields;

    // fetch actus items
    require_once(dirname(__FILE__) . "/inc/epfl-ws.php");
    $ws = new \EPFL\WS\epflws();
    $actus = $ws->get_items($url);
    //$ws->debug($actus);
    switch ($tmpl) {
      default:
      case 'full':
        $display_html = $this->display_full($actus);
        break;
      case 'short':
        $display_html = $this->display_short($actus);
        break;
      case 'widget':
        $display_html = $this->display_widget($actus);
        break;
    }
    return $display_html;
  }
}
//add_shortcode('actu', 'EPFL\\WS\\Actu\\wp_shortcode');
new ActuShortCode();
?>