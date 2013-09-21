<?php
/*
Plugin Name: WP App Factory
Plugin URI: http://topquark.com/wp-app-factory/extend/plugins/wp-app-factory
Description: Creates a cross-device mobile app out of any post type using Sencha Touch as the framework
Version: 2.0.3beta3
Author: Top Quark
Author URI: http://topquark.com

Copyright (C) 2011 Trevor Mills (support@topquark.com)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

Note: This plugin is distributed with a debug and production version of
Sencha Touch, which is also released under GPLv3.  
See http://www.sencha.com/products/touch/license/

*/

/**
 * WP App Factory
 *
 * This plugin is an effort to marry Sencha Touch and WordPress
 * It creates a custom post type called 'App'.  Any app that
 * gets run is defined by App posts and the shortcodes within them.  
 * 
 * @TODO
 * 	- add storemeta_callback for callback queries
 *  - fix category bug in the-data/index.php 
 *  - allow shortcode atts to effectively add fields to the model (?)
 */


define('APP_FACTORY_URL',trailingslashit(plugins_url(basename(dirname(__FILE__)))));
define('APP_FACTORY_PATH',trailingslashit(dirname(__FILE__)));
require_once( APP_FACTORY_PATH . 'TheAppFactory.class.php');

register_activation_hook(__FILE__, 'the_app_factory_install');
function the_app_factory_install() {
	// These rules are added so that the .htaccess file can make for quick redirection to /sdk and /resources files for the app
	// At this point, this means The App Factory doesn't work on non-apache web servers.  Sorry.
	$site_root = parse_url(get_option('siteurl'));
	if ( isset( $site_root['path'] ) )
		$site_root = trailingslashit($site_root['path']);
	else
		$site_root = '/';

	$home_root = parse_url(home_url());
	if ( isset( $home_root['path'] ) )
		$home_root = trailingslashit($home_root['path']);
	else
		$home_root = '/';

	if (is_multisite()){
		$site_root = parse_url(get_site_url(1));
		if ( isset( $site_root['path'] ) )
			$site_root = trailingslashit($site_root['path']);
		else
			$site_root = '/';
			
		$root_site_prefix = apply_filters('wp_app_factory_root_site_prefix','blog/');
		if (!empty($root_site_prefix))
			$root_site_prefix = preg_quote($root_site_prefix,'/');
		else
			$root_site_prefix = '.';
		
		$insertion = array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteBase '.$site_root,
			// Note, the blog part at the beginning is to handle the root site on Multisite installations.  This should be 
			'RewriteRule ^('.$root_site_prefix.')?([^\/]*)/?apps/[^\/]+/sdk2.1/(.*)$ '.$site_root.'$2/wp-content/plugins/wp-app-factory/the-app/sdk2.1/$3 [L]',
			'RewriteRule ^('.$root_site_prefix.')?([^\/]*)/?apps/[^\/]+/resources/(.*)$ '.$site_root.'$2/wp-content/plugins/wp-app-factory/the-app/resources/$3 [L]',
			'RewriteRule ^('.$root_site_prefix.')?([^\/]*)/?apps/[^\/]+/sdk2.2.1/(.*)$ '.$site_root.'$2/wp-content/plugins/wp-app-factory/the-app/sdk2.2.1/$3 [L]',
			'</IfModule>'
		);
	}
	else{
		$insertion = array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteBase '.$home_root,
			'RewriteRule ^apps/[^\/]+/sdk2.1/(.*)$ '.$site_root.'wp-content/plugins/wp-app-factory/the-app/sdk2.1/$1 [L]',
			'RewriteRule ^apps/[^\/]+/sdk2.2.1/(.*)$ '.$site_root.'wp-content/plugins/wp-app-factory/the-app/sdk2.2.1/$1 [L]',
			'RewriteRule ^apps/[^\/]+/resources/(.*)$ '.$site_root.'wp-content/plugins/wp-app-factory/the-app/resources/$1 [L]',
			'</IfModule>'
		);
	}
	
	$home_path = get_home_path();
	$htaccess_file = $home_path.'.htaccess';
	if (is_writable($htaccess_file)){
		$contents = file_get_contents($htaccess_file);
		if (strpos($contents,'# BEGIN The App Factory') === false){
			// We want our rules to be at the beginning of the .htaccess file
			$contents = "# BEGIN The App Factory\n# END The App Factory\n\n".$contents;
			file_put_contents($htaccess_file,$contents);
		}
		insert_with_markers($htaccess_file,'The App Factory',$insertion);
	}
	
	// This function registers the custom post type so that it gets into the rewrite rules properly
	the_app_factory_init();
	// flush rewrite rules so that ours get into the cache
	flush_rewrite_rules(false);
}

register_deactivation_hook(__FILE__, 'the_app_factory_uninstall');
function the_app_factory_uninstall(){
	if (is_multisite()){
		// On multisite installations, we're not going to remove the rules, just in case another site has the plugin activated
		return;
	}
	$home_path = get_home_path();
	$htaccess_file = $home_path.'.htaccess';
	insert_with_markers($htaccess_file,'The App Factory',array()); // removes The App Factory from the .htaccess file

	// flush rewrite rules to remove ours
	flush_rewrite_rules(false);
	
}


add_action('init', 'the_app_factory_init');
function the_app_factory_init() 
{
	// Defining the App Custom Post Type
	$labels = array(
		'name' => _x('Apps', 'post type general name'),
		'singular_name' => _x('App', 'post type singular name'),
		'add_new' => _x('Add New', 'apps'),
		'add_new_item' => __('Add New App'),
		'edit_item' => __('Edit App'),
		'new_item' => __('New App'),
		'all_items' => __('All Apps'),
		'view_item' => __('View App'),
		'search_items' => __('Search Apps'),
		'not_found' =>  __('No apps found'),
		'not_found_in_trash' => __('No apps found in Trash'), 
		'parent_item_colon' => '',
		'menu_name' => 'Apps'
	);
	$args = array(
		'labels' => $labels,
		'public' => true,
		'publicly_queryable' => true,
		'show_ui' => true, 
		'show_in_menu' => true, 
		'query_var' => true,
		'rewrite' => true,
		'capability_type' => 'post',
		'has_archive' => true, 
		'hierarchical' => false,
		'menu_position' => null,
		'supports' => array('title','editor','author','thumbnail','permalink','revisions')
	); 
	if (!defined('APP_POST_TYPE')){
		define('APP_POST_TYPE','apps');
	}
	if (!defined('APP_POST_VAR')){
		define('APP_POST_VAR','app');
	}
	if (!defined('APP_DATA_VAR')){
		define('APP_DATA_VAR','the_data');
	}
	if (!defined('APP_MANIFEST_VAR')){
		define('APP_MANIFEST_VAR','the_manifest');
	}
	if (!defined('APP_APPSCRIPT_VAR')){
		define('APP_APPSCRIPT_VAR','the_script');
	}
	if (!defined('APP_GLOBALS_VAR')){
		define('APP_GLOBALS_VAR','globals');
	}

	if (!defined('APP_JSON_VAR')){
		define('APP_JSON_VAR','json');
	}
	if (!defined('APP_JS_VAR')){
		define('APP_JS_VAR','js');
	}
	if (!defined('APP_APP_VAR')){
		define('APP_APP_VAR','app_file');
	}
	if (!defined('APP_COMMAND_VAR')){
		define('APP_COMMAND_VAR','command');
	}
	
	register_post_type(APP_POST_TYPE,$args);
	
	add_action( 'template_redirect', 'the_app_factory_redirect', 1);	
	
	// Load some plugins
	include_once('add-ons/the-app-twitter/the-app-twitter.php');
	include_once('add-ons/the-app-maps/the-app-maps.php');
	do_action('TheAppFactory_load_plugins');
}

add_action('admin_init','the_app_factory_admin_init');
function the_app_factory_admin_init(){
	add_meta_box( 'the_app_build', __('Build','app-factory'), 'the_app_build', APP_POST_TYPE, 'normal', 'high' );
	add_meta_box( 'the_app_package', __('Package','app-factory'), 'the_app_package', APP_POST_TYPE, 'normal', 'high' );
}

function the_app_get_app_meta( $id ){
	$app_meta = get_post_meta($id,'app_meta',true);
	if (!is_array($app_meta)){
		$app_meta = array();
	}
	if (!isset($app_meta['visibility'])){
		$app_meta['visibility'] = array(
			'regular' => 'development',
			'admin' => 'development'
		);
	}
	return $app_meta;
}

function the_app_build( $app ){	
	// Use nonce for verification
	$the_app = & TheAppFactory::getInstance();
	setup_the_app_factory(); // will setup our different environments
	
	wp_nonce_field( plugin_basename( __FILE__ ), 'app_build_nonce' );
	
	echo '<p>'.sprintf(__('Build and test your app and when you are ready to release it to the public, hit this Build button:','app-factory')).'</p>';
	?>
	<a href="<?php bloginfo('url'); ?>/<?php echo APP_POST_TYPE; ?>/<?php echo $app->post_name; ?>::build/" class="button-primary" target="_blank"><?php echo __('Build','app-factory'); ?></a>
	<a href="<?php bloginfo('url'); ?>/<?php echo APP_POST_TYPE; ?>/<?php echo $app->post_name; ?>::build_no_minify/" class="button-primary" target="_blank"><?php echo __('Build (no minify)','app-factory'); ?></a>
	<?php
	echo '<p>'.sprintf(__('The Build process optimizes your app such that it loads and runs faster.  It does this by launching your app, gathering the list of file dependencies and then concatenating all javascript files into a single, minimized file. ','app-factory')).'</p>';
	
	if (file_exists($the_app->get('production_root').'index.html') || file_exists( $the_app->get('package_native_www_ios').'index.html') || file_exists( $the_app->get('package_native_www_android').'index.html')){
		$app_meta = the_app_get_app_meta( $app->ID );
		ob_start(); ?>
		<div>
			<p><?php _e('There is more than one version this app built.  Please set your choices below:','app-factory'); ?></p>
			<?php foreach( array_keys( $app_meta['visibility'] ) as $type ) : ?>
			
				<div>
					<label><?php printf ( __('%s users see: ', 'app-factory' ), ucwords( $type ) ); ?></label>
					<select name="app_meta[visibility][<?php echo $type; ?>]">
						<option value="development" <?php selected( 'development', $app_meta['visibility'][$type] ); ?>><?php echo __('Development','app-factory'); ?></option>
			
						<?php if ( file_exists($the_app->get('production_root').'index.html') ): ?>
							<option value="production" <?php selected( 'production', $app_meta['visibility'][$type] ); ?>><?php _e('Production (Web App)','app-factory'); ?></option>
						<?php endif; ?>

						<?php if ( file_exists($the_app->get('package_native_www_ios').'index.html') ): ?>
							<option value="native_ios" <?php selected( 'native_ios', $app_meta['visibility'][$type] ); ?>><?php _e('Native iOS (iTunes candidate)','app-factory'); ?></option>
						<?php endif; ?>

						<?php if ( file_exists($the_app->get('package_native_www_android').'index.html') ): ?>
							<option value="native_android" <?php selected( 'native_android', $app_meta['visibility'][$type] ); ?>><?php _e('Native Android (Marketplace candidate)','app-factory'); ?></option>
						<?php endif; ?>

					</select>
				</div>
			<?php endforeach; ?>
		</div>
		
		<?php if ( file_exists($the_app->get('production_root').'index.html') ) : ?>
			<p class="description"><?php printf(__('When switching from production to development, if you\'re testing in Chrome, you should visit %s and clear the Application Cache for %s','app-factory'),'<code>chrome://appcache-internals</code>','<code>'.get_permalink().'manifest</code>'); ?></p>
		<?php endif;
		
		echo ob_get_clean(); 
	}
}

function the_app_package( $app ){
	$the_app = & TheAppFactory::getInstance();
	$app_meta = the_app_get_app_meta( $app->ID );
	
	echo '<p>'.__('Packaging your app prepares it for submission into the App Store (iOS) or the Android Marketplace (Android).','app-factory').'</p>';
	echo '<p>'.__('The packaged app is delivered as a .zip file that contains a project that you can open and run in XCode (iOS) or build using the Android SDK (Android).','app-factory').'</p>';
	wp_nonce_field( plugin_basename( __FILE__ ), 'app_package_nonce' ); ?>
	<p>
		<strong><?php echo sprintf(__('You have to give your app a bundle identifier.  This is usually your domain name backwards, followed by the app name (i.e. com.example.%s).','app-factory'),$the_app->get('package_name')); ?></strong><br/>
		<?php echo __('Bundle Identifier','app-factory'); ?>: <input type="text" name="app_meta[bundle_id]" value="<?php echo esc_attr($app_meta['bundle_id']); ?>" />.<?php echo $the_app->get('package_name'); ?></p>
	
	<?php if (!empty($app_meta['bundle_id'])) : ?>
		<a href="<?php bloginfo('url'); ?>/<?php echo APP_POST_TYPE; ?>/<?php echo $app->post_name; ?>::package_ios/" class="button-primary" target="_blank"><?php echo __( 'Package iOS', 'app-factory' ); ?></a>
		<a href="<?php bloginfo('url'); ?>/<?php echo APP_POST_TYPE; ?>/<?php echo $app->post_name; ?>::package_ios_no_minify/" class="button-primary" target="_blank"><?php echo __( 'Package iOS (no minify)', 'app-factory' ); ?></a>
		<?php	
		if (file_exists( $the_app->get('package_native_www_ios') ) ){
			echo '<p>'.sprintf(__('You have a packaged %s app ready to go.  %sDownload as ZIP%s'),'iOS','<a href="'.admin_url('admin-ajax.php').'?post='.$app->ID.'&action=download_ios_zip" target="_blank">','</a>').'</p>';
		}
		?>
		<a href="<?php bloginfo('url'); ?>/<?php echo APP_POST_TYPE; ?>/<?php echo $app->post_name; ?>::package_android/" class="button-primary" target="_blank"><?php echo __( 'Package Android', 'app-factory' ); ?></a>
		<a href="<?php bloginfo('url'); ?>/<?php echo APP_POST_TYPE; ?>/<?php echo $app->post_name; ?>::package_android_no_minify/" class="button-primary" target="_blank"><?php echo __( 'Package Android (no minify)', 'app-factory' ); ?></a>
		<?php
		if (file_exists(  $the_app->get('package_native_www_android') )){
			echo '<p>'.sprintf(__('You have a packaged %s app ready to go.  %sDownload as ZIP%s'),'Android','<a href="'.admin_url('admin-ajax.php').'?post='.$app->ID.'&action=download_android_zip" target="_blank">','</a>').'</p>';
		}
	endif;
}

/* When the post is saved, saves our custom data */
add_action('save_post','the_app_save_postdata');
function the_app_save_postdata( $post_id ) {
	// verify if this is an auto save routine. 
	// If it is our form has not been submitted, so we dont want to do anything
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
	    return;

	// verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times

	if ( !wp_verify_nonce( $_POST['app_build_nonce'], plugin_basename( __FILE__ ) ) )
	    return;

	$post = get_post($post_id);
	 //skip all cases where we shouldn't index
	if ( $post->post_type != APP_POST_TYPE )
		return;

	$_POST['app_meta']['bundle_id'] = trim($_POST['app_meta']['bundle_id'],'.');
	if (isset($_POST['app_meta'])){
		update_post_meta($post_id,'app_meta',$_POST['app_meta']);
	}
	else{
		delete_post_meta($post_id,'app_meta');
	}
}

//add filter to ensure the text Record, or record, is displayed when user updates a record 
add_filter('post_updated_messages', 'the_app_factory_updated_messages');
function the_app_factory_updated_messages( $messages ) {
  global $post, $post_ID;

  $messages[APP_POST_TYPE] = array(
    0 => '', // Unused. Messages start at index 1.
    1 => sprintf( __('App updated. <a href="%s">View App</a>'), esc_url( get_permalink($post_ID) ) ),
    2 => __('Custom field updated.'),
    3 => __('Custom field deleted.'),
    4 => __('App updated.'),
    /* translators: %s: date and time of the revision */
    5 => isset($_GET['revision']) ? sprintf( __('App restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
    6 => sprintf( __('App published. <a href="%s">View app</a>'), esc_url( get_permalink($post_ID) ) ),
    7 => __('Record saved.'),
    8 => sprintf( __('App submitted. <a target="_blank" href="%s">Preview app</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
    9 => sprintf( __('App scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview app</a>'),
      // translators: Publish box date format, see http://php.net/date
      date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
    10 => sprintf( __('App draft updated. <a target="_blank" href="%s">Preview app</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
  );

  return $messages;
}

add_filter('option_rewrite_rules','the_app_factory_rewrite_rules');
function the_app_factory_rewrite_rules($rules){
	$the_app_factory_rules[APP_POST_TYPE.'/([^/]+)/data/?$'] = 'index.php?'.APP_POST_VAR.'=$matches[1]&'.APP_DATA_VAR.'=posts'; // the posts page
	$the_app_factory_rules[APP_POST_TYPE.'/([^/]+)/data/([^/]+)/?$'] = 'index.php?'.APP_POST_VAR.'=$matches[1]&'.APP_DATA_VAR.'=$matches[2]'; // the posts page
	$the_app_factory_rules[APP_POST_TYPE.'/([^/]+)/manifest/?$'] = 'index.php?'.APP_POST_VAR.'=$matches[1]&'.APP_MANIFEST_VAR.'=on'; // the cache manifest
	$the_app_factory_rules[APP_POST_TYPE.'/([^/]+)/globals/?$'] = 'index.php?'.APP_POST_VAR.'=$matches[1]&'.APP_GLOBALS_VAR.'=on'; // the WordPress Globals js file
	$the_app_factory_rules[APP_POST_TYPE.'/([^/]+)/appscript/?$'] = 'index.php?'.APP_POST_VAR.'=$matches[1]&'.APP_APPSCRIPT_VAR.'=on'; // the app js file
	//$the_app_factory_rules[APP_POST_TYPE.'/([^/]*)/storemeta/?$'] = 'index.php?'.APP_POST_VAR.'=$matches[1]&'.APP_DATA_VAR.'=storemeta'; // the store meta page
	
	// The following rules are for Sencha Touch Version 2 apps
	$the_app_factory_rules[APP_POST_TYPE.'/([^/]+)/([^/\.]+)\.json$'] = 'index.php?'.APP_POST_VAR.'=$matches[1]&'.APP_JSON_VAR.'=$matches[2]'; // for app.json or package.json
	$the_app_factory_rules[APP_POST_TYPE.'/([^/]+)/([^/\.]+)\.js$'] = 'index.php?'.APP_POST_VAR.'=$matches[1]&'.APP_JS_VAR.'=$matches[2]'; // for app.js
	$the_app_factory_rules[APP_POST_TYPE.'/([^/]+)/app/(.+)/?$'] = 'index.php?'.APP_POST_VAR.'=$matches[1]&'.APP_APP_VAR.'=$matches[2]'; // the posts page

	// I want the CONF_APP rules to appear at the beginning - thereby taking precedence over other rules
	$rules = $the_app_factory_rules + $rules;
	//var_dump($rules);
	return $rules;
}

add_filter('query_vars','the_app_factory_query_vars');
function the_app_factory_query_vars($query_vars){
	$query_vars[] = APP_POST_VAR;
	$query_vars[] = APP_DATA_VAR;
	$query_vars[] = APP_MANIFEST_VAR;
	$query_vars[] = APP_GLOBALS_VAR;
	$query_vars[] = APP_APPSCRIPT_VAR;
	$query_vars[] = APP_JSON_VAR;
	$query_vars[] = APP_JS_VAR;
	$query_vars[] = APP_APP_VAR;
	$query_vars[] = APP_COMMAND_VAR;
	return $query_vars;
}

//add_action('parse_request','the_app_factory_parse_request'); // uncomment to check what was matched
function the_app_factory_parse_request($wp_rewrite){
	print_r($wp_rewrite);
	exit();
}

function the_app_factory_redirect(){
	$build_the_app = false;
	switch(true){
	case (get_query_var('post_type') == APP_POST_TYPE and have_posts() and !is_archive()):
		$build_the_app = true;
		break;
	case (count($parse = explode('::',get_query_var(APP_POST_TYPE))) > 1):
		// CASE: http://mydomain.com/apps/my-app::build
		set_query_var(APP_POST_VAR,$parse[0]);
		set_query_var(APP_POST_TYPE,$parse[0]);
		set_query_var(APP_COMMAND_VAR,$parse[1]);
		
		// necessary to get redirect_canonical below to work
		global $wp_query;
		$wp_query->is_404 = false; 
		$wp_query->is_single = true;
		
		the_app_factory_redirect(); // Call again with new query_vars
		return;
		break;
	case (count($parse = explode('::',get_query_var(APP_POST_VAR))) > 1):
		// CASE: http://mydomain.com/apps/my-app::build/app.js
		set_query_var(APP_POST_VAR,$parse[0]);
		set_query_var(APP_COMMAND_VAR,$parse[1]);
		the_app_factory_redirect(); // Call again with new query_vars
		return;
		break;
	case (get_query_var(APP_POST_VAR) != ''):
		// We need to find the App Custom Post that corresponds to the slug in APP_POST_VAR
		$query = 'name=' . get_query_var(APP_POST_VAR) . '&post_type=' . APP_POST_TYPE;
		$posts = get_posts($query);
		if (count($posts)){
			global $post;
			reset($posts); $post = current($posts); 
			$build_the_app = true;
		}
		break;
	}
	if ($build_the_app){
		//remove_filter('template_redirect', 'redirect_canonical');
		setup_the_app_factory();
		$the_app = & TheAppFactory::getInstance();
		switch(true){
		case get_query_var(APP_JSON_VAR) != '': 
			switch( $the_app->get('environment') ){
			case 'production':
				$include = $the_app->get('production_root').get_query_var(APP_JSON_VAR).'.json';
				break;
			case 'native_ios':
			case 'native_android':
				// shouldn't be here as when loading in index, it should just redirect to the native app itself,
				wp_die( 'oops' );
				break;
			case 'development':
			default: 
				$include = 'the-app/'.get_query_var(APP_JSON_VAR).'.json.php'; 
				break;
			}
			break;
		case get_query_var(APP_JS_VAR) != '': $include = 'the-app/'.get_query_var(APP_JS_VAR).'.js.php'; break;
		case get_query_var(APP_RESOURCE_VAR) != '': $include = 'the-app/resources/'.get_query_var(APP_RESOURCE_VAR); break;
		case get_query_var(APP_APP_VAR) != '': 
			if (file_exists(APP_FACTORY_PATH.'the-app/app/'.get_query_var(APP_APP_VAR))){
				header('Content-type: text/javascript');
				$include = 'the-app/app/'.get_query_var(APP_APP_VAR);
			}
			else{
				list($root,$args) = explode('/',get_query_var(APP_APP_VAR),2);
				// Let's see if there's a registered path for this one
				$key = basename($args,'.js');
				if (!empty($the_app->registered['paths']) and !empty($the_app->registered['paths'][$root]) and isset($the_app->registered['paths'][$root][$key])){
					header('Content-type: text/javascript');
					$include = $the_app->registered['paths'][$root][$key];
				}
				else{
					// Build it dynamically
					$include = 'the-app/app/'.$root.'/factory.js.php';						
				}
			}
			break;
		case get_query_var(APP_DATA_VAR) != '': $include = 'the-data/index.php'; break;
		case get_query_var(APP_MANIFEST_VAR) != '': $include = 'the-manifest/index.php'; break;
		default:
			switch( $the_app->get('environment') ){
			case 'production':
				$include = $the_app->get('production_root').'index.html';
				break;
			case 'native_ios':
				wp_redirect( $the_app->get('package_native_root_url_ios').'www/index.html?no_cordova' );
				die();
				break;
			case 'native_android':
				wp_redirect( $the_app->get('package_native_root_url_android').'assets/www/index.html?no_cordova' );
				die();
				break;
			case 'development':
			default:
				$include = 'the-app/index.php'; 
				break;
			}

			redirect_canonical(); // Adds a trailing slash and redirect if there isn't one on the REQUEST_URI.  If there's already one, this does nothing
			break;
		}
		
		do_action('the_app_factory_template_redirect',$include);
	}
	elseif (get_option('show_on_front') == 'app' and $app_id = get_option('app_on_front')){
		// If they've chosen to show an app on the front, then we're going to redirect ALL
		// requests to the specified app. It essentially means that this WordPress installation is ONLY
		// around to serve up that app.  
		wp_redirect(get_permalink($app_id));
		die();
	}
}

function setup_the_app_factory_environment(){
	$the_app = & TheAppFactory::getInstance();

	global $post;
	$uploads = wp_upload_dir();

	// Some vars for building
	$the_app->set('build_root',trailingslashit($uploads['basedir'])); // multisite safe - uploads or files directory
	$the_app->set('build_root_url',trailingslashit($uploads['baseurl']));
	$the_app->set('production_root',$the_app->get('build_root') . trailingslashit( "wp-app-factory/build/production/$post->post_name" ));
	$the_app->set('production_root_url',$the_app->get('build_root_url') . trailingslashit( "wp-app-factory/build/production/$post->post_name" ));

	// Some vars for packaging
	$the_app->set('package_root',trailingslashit($uploads['basedir'])); // multisite safe - uploads or files directory
	$the_app->set('package_root_url',trailingslashit($uploads['baseurl']));
	$the_app->set('package_name', preg_replace('/[^a-zA-Z0-9]/','',$post->post_title));
	if(preg_match('/package_([^_]+)/', get_query_var(APP_COMMAND_VAR),$matches)){
		$the_app->set('package_target',$matches[1]);		
	}
	elseif( $_REQUEST['action'] == 'package_app' and isset($_REQUEST['target']) ){
		$the_app->set('package_target',$_REQUEST['target']);
		$the_app->is('packaging',true);
		//$the_app->is('packaging_via_ajax',true);
	}
	elseif( isset($_REQUEST['packaging']) and $_REQUEST['packaging'] == 'true' and isset($_REQUEST['target'])){
		$the_app->set('package_target',$_REQUEST['target']);
		$the_app->is('packaging',true);
		//$the_app->is('packaging_via_ajax',true);
	}
	
	$relative = array(
		'ios' => trailingslashit( "wp-app-factory/build/native/ios/$post->post_name" ),
		'android' => trailingslashit( "wp-app-factory/build/native/android/$post->post_name" )
	);
	$the_app->set('package_native_root_ios',$the_app->get('package_root') . $relative['ios'] );
	$the_app->set('package_native_root_android',$the_app->get('package_root') . $relative['android'] );
	$the_app->set('package_native_root_url_ios', $the_app->get('package_root_url') . $relative['ios'] );
	$the_app->set('package_native_root_url_android', $the_app->get('package_root_url') . $relative['android'] );
	$the_app->set('package_native_www_ios', $the_app->get('package_native_root_ios') . 'www/' );
	$the_app->set('package_native_www_android', $the_app->get('package_native_root_android') . 'assets/www/' );
	if ( $the_app->get('package_target') ){
		$the_app->set( 'package_native_root', $the_app->get( 'package_native_root_' . $the_app->get( 'package_target' ) ) );
		$the_app->set( 'package_native_root_url', $the_app->get( 'package_native_root_url_' . $the_app->get( 'package_target' ) ) );
		$the_app->set( 'package_native_www', $the_app->get( 'package_native_www_' . $the_app->get( 'package_target' ) ) );
	}

	// Additional vars for administrators who are doing the actual building/packaging
	if (current_user_can('administrator') && get_query_var(APP_COMMAND_VAR) != ''){
		switch( get_query_var( APP_COMMAND_VAR ) ){
		case 'build':
		case 'build_no_minify':
			$the_app->is('building',true);
			$the_app->is('doing_build_command',true);
			
			// If building, we want to make sure that the Desktop is an allowed browser
			$the_app->apply('meta',array(
				'unacceptable_browser' => array(
					'desktop' => false
				)
			));
			$the_app->enqueue('controller','Build');
			add_filter('TheAppFactory_helpers','the_app_factory_build_helper',10,2);
			break;
		case 'package_ios':
		case 'package_android':
		case 'package_ios_no_minify':
		case 'package_android_no_minify':
			//$the_app->is('packaging',true); // Don't set this here - it screws things up elsewhere
			$the_app->is('doing_package_command',true);

			// If packaging, we want to make sure that the Desktop is an allowed browser
			$the_app->apply('meta',array(
				'unacceptable_browser' => array(
					'desktop' => false
				)
			));
			$the_app->enqueue('controller','Package');
			add_filter('TheAppFactory_helpers','the_app_factory_package_target_helper',10,2);
			// setup_package_environment();
			break;
		}
		
		$the_app->is('minifying', strpos( get_query_var(APP_COMMAND_VAR), 'no_minify' ) ? false : true );
	}

	if ( isset($_REQUEST['minify'])){
		$the_app->is('minifying',($_REQUEST['minify'] == 'true'));
	}
	
	$app_meta = get_post_meta(get_the_ID(),'app_meta',true);
	$key = (current_user_can('administrator') ? 'admin' : 'regular');
	if ( current_user_can('administrator') and ($the_app->is('doing_build_command') or $the_app->is('doing_package_command'))){
		// We are building or packaging the app.  Set the environment to development so as to load the dev files
		$the_app->set('environment', 'development');
	}
	elseif (isset($_GET['building']) and $_GET['building'] == 'true'){
		// True for anything called via build_cp();
		$the_app->set('environment', 'development');
	}
	elseif ($app_meta and isset($app_meta['visibility']) and isset($app_meta['visibility'][$key])){
		$the_app->set('environment', $app_meta['visibility'][$key]); // i.e. 'development', 'production', 'native_ios', 'native_android'
	}
	else{
		$the_app->set('environment', 'development');
	}
	$the_app->is('native',($the_app->get('environment') == 'native_ios' || $the_app->get('environment') == 'native_android'));	
	
	if (substr(get_query_var(APP_COMMAND_VAR),0,7) == 'package' and get_query_var( APP_DATA_VAR ) != ''){
		//$the_app->is('requesting_data_for_native',true);
		$the_app->is('packaging',true);
	}
}

function the_app_factory_build_helper( $helpers, $args ){
	$the_app = & $args[0];
	$helpers['WP']['isMinifying'] = $the_app->is('minifying');
	return $helpers;
}

function the_app_factory_package_target_helper( $helpers, $args ){
	$the_app = & $args[0];
	$helpers['WP']['packageTarget'] = $the_app->get('package_target');
	$helpers['WP']['isMinifying'] = $the_app->is('minifying');
	return $helpers;
}

add_action('the_app_factory_template_redirect','the_app_factory_include_and_exit');
function the_app_factory_include_and_exit($include){
	$the_app = & TheAppFactory::getInstance();
	include($include);
	exit();
}

function setup_the_app_factory(){
	$the_app = & TheAppFactory::getInstance();

	// First, we need to detect the environment.  There are three options:
	// - Development
	// - Production
	// - Native (with iOS and Android flavours)
	setup_the_app_factory_environment();
	
	// This is the function where we set up some globals
	global $post;
	$the_app->setup($post);
	
	do_action('setup_the_app_factory');
}

function the_app_gettext($text){
	return apply_filters('the_app_gettext',$text);
}
add_filter('the_app_gettext','the_app_gettext_format',99);
function the_app_gettext_format($text){
	// We're sending back text that will end up within single quotes 
	// in JavaScript code.  Therefore, we need to properly escape what 
	// we're returning
	
	$text = str_replace("'","\\'",$text);
	
	// Also, need to deal with linebreaks
	$text = str_replace("\r","",$text);
	$text = str_replace("\n","<br/>",$text);
	
	return $text;
}

add_filter('the_app_gettext','the_app_textblocks');
function the_app_textblocks($text){
	if(substr($text,0,2) == '__'){
		switch($text){
		case '__updating':
			$text = __('Downloading updates from the server.  Please standby.','app-factory');
			break;
		case '__new_version':
			$text = __('A new version of this app is ready.  The app must be restarted.  Restart now?','app-factory');
			break;
		}
	}
	return $text;
}

add_filter('upload_mimes', 'the_app_upload_mimes');
function the_app_upload_mimes ( $existing_mimes=array() ) {
    // add the file extension to the array
    $existing_mimes['css'] = 'text/css';
    // call the modified list of extensions 
    return $existing_mimes;
 
}

add_action('parse_query','the_app_parse_query',1);
function the_app_parse_query(&$query){
	// This was an interesting one.  When loading the data or the manifest
	// WP_Query thinks that it's on the home page.  Some plugins (The Events 
	// Calendar, for example) might suppress certain categories on the home
	// page.  I don't want that.  I want The App Factory to have complete control
	// over its query.  
	if (get_query_var(APP_POST_VAR) and $query->is_home){
		$query->is_home = false;
	}
}

add_action('delete_attachment','the_app_delete_attachement_app_images');
function the_app_delete_attachement_app_images($id){
	$app_images = get_post_meta($id,'app_made_images',true);
	if (is_array($app_images)){
		foreach ($app_images as $file){
			if(file_exists($file)){
				@unlink($file);
			}
		}
	}
}

add_action('admin_print_scripts','the_app_admin_print_scripts',20);
function the_app_admin_print_scripts(){
	global $pagenow;
	if ($pagenow == 'options-reading.php'){
		wp_enqueue_script('wp-app-factory.options-reading',APP_FACTORY_URL.'extras/wp-app-factory.options-reading.js','jquery');
		$app_posts = get_posts('post_type='.APP_POST_TYPE);
		$apps = array();
		foreach ($app_posts as $app){
			$apps[] = array(
				'id' => $app->ID,
				'title' => $app->post_title
			);
		}
		
		// Thanks http://wordpress.stackexchange.com/questions/36551/create-a-dropdown-with-custom-post-types-as-option-in-admin
		function generate_post_select($select_id, $post_type, $selected = 0) {
	        $post_type_object = get_post_type_object($post_type);
	        $label = $post_type_object->label;
	        $posts = get_posts(array('post_type'=> $post_type, 'post_status'=> 'publish', 'suppress_filters' => false, 'posts_per_page'=>-1));
			$return = '';
	        $return.= '<select name="'. $select_id .'" id="'.$select_id.'">';
	        $return.= '<option value="0" >'.__( '&mdash; Select &mdash;' ).'</option>';
	        foreach ($posts as $post) {
	            $return.= '<option value="'. $post->ID. '"'. ($selected == $post->ID ? ' selected="selected"' : ''). '>'. $post->post_title. '</option>';
	        }
	        $return.= '</select>';
			return $return;
	    }
		$localized = array(
			'apps' => $apps,
			'show_on_front' => get_option( 'show_on_front' ),
			'show_on_front_message' => sprintf(__('An %sapp%s (select below).  Note: by choosing this option, all pages (including 404), lead to this app.  Use it if you would like your WordPress installation to ONLY host this app.','app-factory'),'<a href="'.admin_url('edit.php?post_type='.APP_POST_TYPE).'">','</a>'),
			'app' => __('App','app-factory'),
			'dropdown' => generate_post_select('app_on_front',APP_POST_TYPE,get_option( 'app_on_front' )),
			'app_post_type' => APP_POST_TYPE
		);
		wp_localize_script('wp-app-factory.options-reading','WP_APP_FACTORY',$localized);
	}
}

add_filter('whitelist_options','the_app_whitelist_options');
function the_app_whitelist_options($whitelist_options){
	$whitelist_options['reading'][] = 'app_on_front';
	return $whitelist_options;
}


require_once('the-app-builder.php');
require_once('the-app-packager.php');

?>