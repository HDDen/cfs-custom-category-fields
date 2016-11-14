=== CFS Custom Category Fields ===
Contributors: GatorDog
Donate link: http://gatordev.com/
Tags: category meta data, category custom fields, custom field suite addon
Requires at least: 3.6
Tested up to: 4.1
Stable tag: 1.3.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A Custom Field Suite Addon that provides custom meta data for categories and custom taxonomies.

== Description ==

CFS Category Fields is a Custom Field Suite addon that provides meta data or custom fields for categories and custom taxonomies. Key features are as follows:

*   Apply Custom Fields to Categories and Custom Taxonomies
*   Requires the Custom Field Suite Plugin

== Screenshots ==

1. Apply Field Group to Category or Taxonomy

== Installation ==

1. Upload `cfs-taxonomy.zip` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Install and activate Custom Field Suite if not already installed
4. On the Field Group editor you will see a box to apply the Field Group to a Category or Taxonomy
5. Update the Field Group and the fields will be editable on the Wordpress Category Editor

== Frequently Asked Questions ==

= How do I retrieve custom fields for display on a category page? =

Use the function get_category_meta('field_name'), or alternatively, call CfsTaxonomy::get('field_name') directly, in your php.

= How do I retrieve custom category fields anywhere? =

Use the function get_category_meta() and pass it the term object as the second parameter, eg: get_category_meta(false, get_term_by('slug', 'good-stuff', 'category')). This will return all field data from the category Good Stuff. 

= How do I retrieve the cfs front-end form? =

Use the function get_category_form(). If used on a category or archive page it takes no parameters. It can be used anywhere by passing the term object as the first parameter.

== Changelog ==

= 1.3.1 =
* Fixes imcompatibilities with latest version of CFS
= 1.3 =
* Adds an option apply a field group only to a category or taxonomy. When enabled, field groups that are only needed for categories or taxonomies will not show up in post, custom post type or page edit screens.
= 1.2.1 =
* Fixes bug with calling get_category_meta() in a loop, ie more than once.
= 1.2 =
* Fixes incompatiblity with relationship fields and the issue with making further cfs calls after calling get_category_meta().
= 1.1 =
* Introduces the function get_category_form() which retrieves the cfs front-end form for your field group.
= 1.0.1 =
* Flatten field data returned by get_category_meta() when all fields are returned.
= 1.0 =
* Initial Release.
