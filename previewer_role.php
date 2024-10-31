<?php
/**
*
* Plugin Name: Previewer Role
* Description: This lightweight plugin looks to create a 'Previewer' user role for Wordpress that allows viewing draft posts without the ability to edit. Its purpose is to allow for client or stakeholder sign-off when you don't want to burden that user with access to the dashboard, the admin bar, etc.
* Version: 1.0.1
* Requires at least: 4.0
* Requires PHP: 5.4
* Author: dancingpigeon
* License: GPL2
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
*
*/

//Base action registration
register_activation_hook(__FILE__, 'previewer_role_plugin_activation');
register_deactivation_hook(__FILE__, 'previewer_role_plugin_deactivation');
add_action('plugins_loaded', 'previewer_role_plugin_plugins_loaded');

/**
 * previewer_role_plugin_activation()
 *
 * Activation callback.
 * Creates the previewer role. Disables the plugin if that process fails.
 */
function previewer_role_plugin_activation()
{
	$role_created = previewer_role_register_previewer_role();

	if(!$role_created) //Oops
		deactivate_plugins(plugin_basename(__FILE__));
}

/**
 * previewer_role_plugin_deactivation()
 *
 * Deactivatopm callback.
 * Disables the previewer role.
 */
function previewer_role_plugin_deactivation()
{
	if(get_role('previewer'))
		remove_role('previewer');
}

/**
 * previewer_role_plugin_plugins_loaded()
 *
 * Plugin loaded actions.
 * Currently only registers plugin init actions.
 */
function previewer_role_plugin_plugins_loaded()
{
	add_action('init', 'previewer_role_plugin_init');
}


/**
 * previewer_role_plugin_init()
 *
 * Init time actions.
 * Removes the admin bar and adds the filter to allow Previewer users the ability
 * to see preview posts.
 */
function previewer_role_plugin_init()
{
	//remove admin bar for previewers if its their only role
	if(is_user_logged_in())
	{
		$user = wp_get_current_user();
		$roles = (array)$user->roles;
		if(in_array('previewer', $roles))
		{
			if(count($roles) == 1)
				add_filter('show_admin_bar', '__return_false');
			add_filter('user_has_cap', 'previewer_role_add_preview_cap', 10, 3);
		}
	}
}

/**
 * previewer_role_register_previewer_role()
 *
 * Create the previewer role and give it a set of basic caps.
 * Explicitly deny the ability to see the backend and edit posts.
 * 
 *
 * @param array $slug The unique slug to use for the previewer role.
 * @param array $name The friendly name used to display the role on the front end.
 */
function previewer_role_register_previewer_role($slug = 'previewer', $name = 'Previewer')
{
	$result = add_role($slug, $name, array(
		'previewer_role_review_post' => true, 
		'read' => false, 
		'edit_posts' => false,
		'edit_others_posts' =>false
		)
	);
	
	return $result;
}

/**
 * previewer_role_add_preview_cap()
 *
 * Filter when visiting a single preview page.
 * If we're looking to for the edit_others_posts cap, temporarily grant it.
 * 
 *
 * @param array $allcaps All the capabilities of the user
 * @param array $cap     [0] Required capability
 * @param array $args    [0] Requested capability
 *                       [1] User ID
 *                       [2] Associated object ID
 */
function previewer_role_add_preview_cap($allcaps, $cap, $args ) 
{
	$preview_caps = array(
		'edit_others_posts'
	);
	
	//if we're viewing a preview singular post add the edit cap
	if(is_singular() && is_preview())
	{
		foreach($preview_caps as $p_cap)
		{
			if(in_array($p_cap, $cap))
			{
				$allcaps[$cap[0]] = true;
			}
		}
	}
	return $allcaps;
}

/**
 * previewer_role_redirect_preview_to_login()
 *
 * If we're sent to a preview and we're unauth'd, redirect to wp login before
 * sending us back to the page.
 *
 *
 * @param WP_Query $query The WP query
 */
function previewer_role_redirect_preview_to_login($query)
{
	//If we're unauthenticated and we're on a preview
	if(!is_user_logged_in() && $query->is_preview() && $query->is_singular())
	{
		//send to wp_login();
		wp_redirect(wp_login_url(get_preview_post_link($query->query['p'])));
		die;
	}
}

add_action('pre_get_posts', 'previewer_role_redirect_preview_to_login');
?>