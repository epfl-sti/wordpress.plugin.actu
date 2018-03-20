<?php

if (! defined('ABSPATH')) {
    die('Access denied.');
}

require_once(__DIR__ . "/OrganizationalUnit.php");
use \EPFL\WS\OrganizationalUnits\OrganizationalUnit;

require_once(__DIR__ . "/Lab.php");
use \EPFL\WS\EPFL\WS\Labs\Lab;

require_once(__DIR__ . "/inc/templated-shortcode.inc");
use \EPFL\WS\ListTemplatedShortcodeView;

class LabsShortcode
{
    static function hook ()
    {
        add_shortcode('Labs', array(get_called_class(), 'wp_shortcode'));
    }

    static function wp_shortcode ($attrs, $content=null, $tag="")
    {
        $class = get_called_class();
        $that = new $class($attrs, $content, $tag);
        return $that->to_html();
    }

    function __construct ($attrs, $content=null, $tag="")
    {
        $this->shortcode_attrs = $attrs;
        $ou_id = $attrs["organizationalunitpostid"];
        if ($ou_id) {
            $this->ou = OrganizationalUnit::get($ou_id);
        }
    }

    function to_html () {
        if (! $this->ou) { return ""; }
        $view = new LabShortcodeView($this->shortcode_attrs);
        return $view->as_html($this->ou->get_all_labs());
    }
}

class LabShortcodeView extends ListTemplatedShortcodeView
{
    function get_slug () {
        return "labs";
    }
    function item_as_html ($lab)
    {
        $name        = $lab->get_name();
        $abbrev      = $lab->get_abbrev();
        $website_url = $lab->get_website_url();
        return "
       <div class=\"lab-box\">
        <h2>$name (<span class=\"lab-abbrev\"><a href=\"$website_url\">$abbrev</a></span>)</h2>
       </div>";
    }
}

LabsShortCode::hook();
