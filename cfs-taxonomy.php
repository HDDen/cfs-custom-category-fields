<?php
/**
 * @package CFS Custom Category Fields
 * @version 1.3.1
 */
/*
Plugin Name: CFS Custom Category Fields
Plugin URI: http://wordpress.org/plugins/cfs-custom-category-fields/
Description: CFS Addon for category meta data. Apply custom fields to categories and custom taxonomies. Requires Custom Field Suite.
Author: GatorDev
Author URI: http://gatordev.com/
Version: 1.3.1
*/
class CfsTaxonomy
{
    protected static $virtualPosts;
    protected static $fieldGroup;
    protected static $taxonomies;
    protected static $termCache;
    protected static $options;
    private static $isCfs = false;
    private static $initGet = false;
    const ID = 'cfs-taxonomy';
    const POST_TYPE = 'cfs_virtual';
    const TAXONOMY = 'cfs_bridget';
    const VERSION = '1.3';

/**
 * showForm
 *
 * Shows the CFS form for categories and custom taxonomies.
 *
 * @note: Hooked in context from $taxonomy_edit_form_fields
 */
    public static function showForm($term, $taxonomy)
    {
        if (false === (self::$fieldGroup = self::matchGroups($taxonomy))) {
            //no matching groups
            return;
        }
        //virtual post for our fields
        global $post;
        if (false === ($post = self::getVirtualPosts()->getPost($term->term_id))
          && false === ($post = self::getVirtualPosts()->setPost($term->term_id))) {
            //houston we have a problem
            return;
        }
        add_filter('postbox_classes_' . self::ID . '_tagsdiv-post_tag', 'CfsTaxonomy::postBoxClasses');
        echo '<tr class="form-field"><th valign="top" scope="row">Custom Fields</th><td><div id="poststuff" class="metabox-holder" style="min-width:0;padding-top:0">';
        foreach(self::$fieldGroup as $group){
            add_meta_box('tagsdiv-post_tag', $group, 'CfsTaxonomy::showMetaBox', self::ID, 'side', 'core');
        }
        $meta_boxes = do_meta_boxes(self::ID, 'side', 'rabbit');
        echo '</div></td></tr>';
    }

    public static function showMetaBox($context)
    {
        if ('rabbit' !== $context || !self::$isCfs) {
            echo '<p>Where is that Wiley Wabbit!</p>';
            return;
        }
        global $post;//the virtual post is set in the showForm
        $groups = array_keys(self::$fieldGroup);
        $args = array( 'box' => 'input', 'group_id' => $groups);
        //cfs api->get_fields calls get_matching_groups again, so hook that here to pass our field groups
        add_filter('cfs_matching_groups', 'CfsTaxonomy::matchingGroups');
        CFS()->group_ids = $groups;//the form api checks this for backend groups
        $metabox = array( 'args' => array( 'group_id' => $groups ) );
        $fields_ids = get_post_meta($groups[0],'cfs_fields');
        foreach($fields_ids[0] as $field)
        {
          if($field['type'] == 'wysiwyg')
          {
            echo '<div class="hidden">';
            wp_editor($field['options']['default_value'],$field['name']);
            echo '</div>';
          }
        }
        include( CFS_DIR . '/templates/meta_box_input.php' );
        //@depricated in newer versions
        //CFS()->meta_box($post, array('args' => $args));
    }

    public static function matchingGroups($matches)
    {
        if (isset(self::$fieldGroup)) {
            //this is only hooked in context so it should always be set
            return self::$fieldGroup;
        }
        return $matches;
    }

    public static function matchPosts($matches)
    {
        if (isset(self::$fieldGroup)) {
            // taxonomy matches are set
            return $matches;
        }
        // filter out field groups applied to posts that are for taxonomies only
        foreach ($matches as $id => $group) {
            if (self::isTaxonomyOnly($id)) {
                unset($matches[$id]);
            }
        }

        return $matches;
    }

    public static function postBoxClasses($classes)
    {
        //cfs_postbox_classes
        $classes[] = 'cfs_input';
        return $classes;
    }

    public static function cfsMetaBox()
    {
        add_meta_box(self::ID, __('Apply to Category / Taxonomy', self::ID), 'CfsTaxonomy::showCfsMetaBox', 'cfs', 'side', 'core');
    }

    public static function showCfsMetaBox()
    {
        global $post;
        $choices = array();
        foreach (self::getTaxonomies() as $key => $taxonomy) {
            $choices[$key] = $taxonomy->labels->singular_name;
        }
        //var_dump($post->ID);var_dump(wp_get_object_terms($post->ID, 'cfs_taxonomy', array('fields' => 'slugs')));
        CFS()->create_field(array(
            'type' => 'select',
            'input_class' => 'select2',
            'input_name' => 'cfs[' . self::ID . ']',
            'options' => array('multiple' => '1', 'choices' => $choices),
            'value' => wp_get_object_terms($post->ID, 'cfs_taxonomy', array('fields' => 'slugs'))
        ));

        echo '<label for="' . self::ID . '_only"><input id="' . self::ID . '_only" name="cfs[' . self::ID . '_only]" type="checkbox"' . (self::isTaxonomyOnly($post->ID) ? ' checked="checked"' : '') . ' value="1"> Apply only to selected taxonomies</label>';
    }

    public static function saveCfsPost($post_id, $post)
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
          || 'cfs' !== $post->post_type//make sure it's not a revision
          || !isset($_POST['cfs']['save'])
          || !current_user_can('edit_posts')) {
            //|| !wp_verify_nonce( $_POST['cfs']['save'], 'cfs_save_fields' )){
            return;
        }

        // only apply to taxonomies and not posts if checked
        self::setTaxonomyOnly($post->ID, isset($_POST['cfs'][$key = self::ID . '_only']) && '1' == $_POST['cfs'][$key]);

        if (!isset($_POST['cfs'][self::ID])) {
            wp_delete_object_term_relationships($post->ID, 'cfs_taxonomy');
            return;
        }
        $taxonomies = self::getTaxonomies();
        foreach ($_POST['cfs'][self::ID] as $key => $val) {
            if (!isset($taxonomies[$val])) {
                //not valid
                unset($_POST['cfs'][self::ID][$key]);
            }
        }
        if (empty($_POST['cfs'][self::ID])) {
            wp_delete_object_term_relationships($post->ID, 'cfs_taxonomy');
            return;
        }
        //var_dump($_POST['cfs'][self::ID]);var_dump($post->ID);
        $result = wp_set_object_terms($post->ID, $_POST['cfs'][self::ID], 'cfs_taxonomy');
        return;
        if (is_wp_error($result)) {
            echo $result->get_error_message();
        }
    }

    public static function onInit()
    {
        self::getVirtualPosts()->registerPostType();
    }

    public static function cfsInit()
    {
        self::$isCfs = true;
        if (taxonomy_exists('cfs_taxonomy')) {
            return;
        }
        register_taxonomy('cfs_taxonomy', 'cfs',
            array(
                'public'                => false,
                'hierarchical'          => false,
                'show_ui'               => false,
                'show_admin_column'     => false
            )
        );
        register_taxonomy_for_object_type('cfs_taxonomy', 'cfs');
    }

    public static function getVirtualPosts()
    {
        if (!isset(self::$virtualPosts)) {
            require_once(dirname(__FILE__) . '/lib/VirtualPosts.php');
            self::$virtualPosts = new VirtualPosts(self::POST_TYPE, self::TAXONOMY);
        }
        return self::$virtualPosts;
    }

    public static function matchGroups($taxonomy)
    {
        $posts = get_posts(array('post_type' => 'cfs', 'post_status' => 'publish', 'posts_per_page' => -1, //'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => 'cfs_taxonomy',
                    'field' => 'slug',
                    'terms' => $taxonomy,
                )
            )
        ));

        $return_arr = array();
        if (!empty($posts)){
            foreach ($posts as $post_entity){
                $return_arr[$post_entity->ID] = $post_entity->post_title;
            }
        }

        return empty($return_arr) ? false : $return_arr;
        //return empty($posts) ? false : array($posts[0]->ID => $posts[0]->post_title);//need the title for the menu
    }

    public static function adminInit()
    {
        if (!is_plugin_active('custom-field-suite/cfs.php')) {
            //based on wp_option active_plugins
            add_action('admin_notices', 'CfsTaxonomy::showNotice');
            add_action('network_admin_notices', 'CfsTaxonomy::showNotice');
        }
        //@note: This is not necessary since cfs stores a session with virtual post id and save the fields automatically
        /*
        if(!isset($_POST['action']) || 'editedtag' !== $_POST['action'] || !isset($_POST['taxonomy']) || !isset($_POST['cfs'])){
            return;//nothing to see here
        }
        foreach(self::getTaxonomies() as $taxonomy){
            if($_POST['taxonomy'] === $taxonomy->name){
                add_action('edit_' . $taxonomy->name, 'CfsTaxonomy::editTaxonomy', 11, 2);
            }
        }*/
    }

    public static function loadJs($context)
    {
        if ('term.php' === $context && !empty($_GET['taxonomy']) && !empty($_GET['tag_ID'])) {
            wp_enqueue_script('post');
            wp_enqueue_style(self::ID, plugins_url('css/admin.css', __FILE__));
            //hook the edit forms for category and other custom hierarchical taxonomies
            foreach (self::getTaxonomies() as $taxonomy) {
                add_action($taxonomy->name . '_edit_form_fields', 'CfsTaxonomy::showForm', 11, 2);
            }
            return;
        }
        if ('post.php' === $context) {
            //It's a post Jim
            global $post;
            if ('cfs' === $post->post_type) {
                //It's a cfs post Mr. Spock
                wp_enqueue_style(self::ID, plugins_url('css/admin.css', __FILE__));
            }
        }
    }

    public static function get($name = false, $term = null)
    {
        if (false === ($post = self::prepareTerm($term))) {
            return false === $name ? array() : '';
        }
        if (!self::$initGet) {
            add_filter('cfs_matching_groups', 'CfsTaxonomy::matchingGroups');
            self::$initGet = true;
        }
        if (false === $name) {
            return CFS()->api->get_fields($post->ID, array());
            //this is non-compatible with relationship field and not really needed in recent cfs versions
            //return array_map('CfsTaxonomy::flattenData', CFS()->api->get_fields($post->ID));
        }
        return CFS()->api->get_field($name, $post->ID, array());
    }

    public static function getForm($term = null)
    {
        if (false === ($post = self::prepareTerm($term))) {
            return '';
        }
        ob_start();
        CFS()->form->render(array('post_id' => $post->ID));
        return ob_get_clean();
    }

    public static function flattenData($data)
    {
        if (is_array($data)) {
            return current($data);
        }
        return $data;
    }

    public static function showNotice()
    {
        $path = 'plugin-install.php?tab=search&s=Custom+Field+Suite&plugin-search-input=Search+Plugins';
        printf('<div class="updated">
    <p>Warning: CFS Custom Category Fields will not work without Custom Field Suite.
    <a href="%s" style="vertical-align:middle;margin-left:.75em" class="button-primary">Repair</a>
    </p></div>', is_multisite() ? network_admin_url($path) : admin_url($path));
    }

    public static function removeFilters()
    {
        remove_filter('cfs_matching_groups', 'CfsTaxonomy::matchingGroups');
        self::$initGet = false;
    }

    public static function filterTaxonomies($taxonomy)
    {
        return $taxonomy->hierarchical;
    }

    protected static function getTaxonomies()
    {
        if (!isset(self::$taxonomies)) {
            self::$taxonomies = array_filter(get_taxonomies(array('public' => true), 'objects'), 'CfsTaxonomy::filterTaxonomies');
        }
        return self::$taxonomies;
    }

    protected static function prepareTerm($term)
    {
        if (!isset($term)) {
            $term = isset(self::$termCache) ? self::$termCache : (self::$termCache = get_queried_object());
        }
        if (!self::$isCfs || !isset($term->term_id) || false === (self::$fieldGroup = self::matchGroups($term->taxonomy))
          || false === ($post = self::getVirtualPosts()->getPost($term->term_id))) {
            return false;
        }
        if (!self::$initGet) {
            add_filter('cfs_matching_groups', 'CfsTaxonomy::matchingGroups');
            self::$initGet = true;
        }
        return $post;
    }

    /**
 * isTaxonomyOnly
 *
 * Instead of saving this to the field group post meta, store as options and check here
 *
 * @param $id int ID of the field group
 * @see setTaxonomyOnly
 */
    protected static function isTaxonomyOnly($id)
    {
        if (!isset(self::$options) && false === (self::$options = get_option(self::ID))) {
            self::$options = array();
        }
        return isset(self::$options[$id]);
    }

    protected static function setTaxonomyOnly($id, $on)
    {
        if (self::isTaxonomyOnly($id)) {
            if ($on) {
                return;
            }
            unset(self::$options[$id]);
        } elseif ($on) {
            self::$options[$id] = true;
        } else {
            return; // it is off and not set
        }
        // update
        update_option(self::ID, self::$options);
    }
}
/**
 * Hooks
 */
add_action('init', 'CfsTaxonomy::onInit');
add_action('admin_init', 'CfsTaxonomy::adminInit');
add_action('cfs_init', 'CfsTaxonomy::cfsInit');
add_action('add_meta_boxes', 'CfsTaxonomy::cfsMetaBox');
add_action('admin_enqueue_scripts', 'CfsTaxonomy::loadJs');
add_action('save_post_cfs', 'CfsTaxonomy::saveCfsPost', 9, 2);
add_filter('cfs_matching_groups', 'CfsTaxonomy::matchPosts', 11);
/**
 * get_category_meta
 *
 * wrapper function to get category fields
 */
if (!function_exists('get_category_meta')) {
    function get_category_meta($name = false, $term = null)
    {
        $data = CfsTaxonomy::get($name, $term);
        CfsTaxonomy::removeFilters();
        return $data;
    }
}
/**
 * get_category_form
 *
 * wrapper function to get category fields
 */
if (!function_exists('get_category_form')) {
    function get_category_form($term = null)
    {
        $form = CfsTaxonomy::getForm($term);
        CfsTaxonomy::removeFilters();
        return $form;
    }
}

add_action( 'category_add_form_fields', 'add_to_mainpg_fields' );
add_action( 'category_edit_form', 'add_to_mainpg_fields' );
add_action( 'created_category', 'add_to_mainpg_fields' );
add_action( 'edited_category', 'add_to_mainpg_fields' );
add_action( 'edit_category_form_fields', 'add_to_mainpg_fields' );

function add_to_mainpg_fields()
{
  //return 'okllllll';
  ?>
  <input type="checkbox" name="add_to_main" id="add_to_main" value="1" />
  <label for="add_to_main">This category on main page</label>

  <?php
}
