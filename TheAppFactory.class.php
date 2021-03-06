<?php
class TheAppFactory {
	/**
	 * Static property to hold our singleton instance
	 */
	static $instance = false;
	
	/**
	 * Variable to hold a reference to the instances we've setup
	 */
	var $instances_setup = array();	
	
	/**
	 * Variable to hold a reference to the post object
	 */
	var $post;	

	/**
	 * Variable to hold a reference to the parameters object
	 */
	var $parms;	

	/**
	 * Variable to hold the model, view, controller, etc queues
	 */
	var $queued;	

	/**
	 * Variable to hold the registered models, views, controllers, etc
	 */
	var $registered;	
	
	/**
	 * This is our constructor, which is private to force the use of
	 * getInstance() to make this a Singleton
	 *
	 * @return TheAppFactory
	 */
	private function __construct() {
		$this->init();
		// Fires only if in WordPress Admin Area, do some actions
		if (is_admin()){
			//add_action('init',array(&$this,'wp_init));
		}
		// Other actions to always perform.
		//add_action('template_redirect',array(&$this,'template_redirect'));
	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @return TheAppFactory
	 */
	public static function getInstance( $class_name = 'TheAppFactory' ) {
		if ( !self::$instance ) {
			self::instantiate( $class_name ); 
		}
		if (!in_array($class_name,self::$instance->instances_setup)){
			call_user_func( array( $class_name, 'setup_environment') );
			self::$instance->instances_setup[] = $class_name;
		}
		return self::$instance;
	}
	
	/**
	 * Simply instantiates the singleton
	 *
	 * @return void
	 */
	public function instantiate( $class_name = 'TheAppFactory' ) {
		if ( !self::$instance ) {
			self::$instance = new $class_name;
			self::$instance->setup();
			self::$instance->setup_post();
		}
		return void;
	}
	
	private function init(){
		$this->reset();
		add_shortcode('the_app',array(&$this,'shortcodes'));
		add_shortcode('app_item',array(&$this,'shortcodes'));
		add_shortcode('app_item_wrapper',array(&$this,'shortcodes'));
		add_shortcode('app_posts',array(&$this,'shortcodes'));
		add_shortcode('loading_spinner',array(&$this,'shortcodes'));
		add_shortcode('unacceptable_browser',array(&$this,'shortcodes'));
		add_shortcode('launch',array(&$this,'shortcodes'));
		add_shortcode('launch_item',array(&$this,'shortcodes'));
		
		add_filter('TheAppFactory_models',array(&$this,'addRegisteredModels'),10,2);
		add_filter('TheAppFactory_stores',array(&$this,'addRegisteredStores'),10,2);	
		add_filter('TheAppFactory_helpers',array(&$this,'addLaunchHelper'),10,2);
		
		add_action('TheAppFactory_setupStores',array(&$this, 'setupStoreStatusStore'),500); // Make sure the Store Status is setup last so as to allow the other stores to instantiate first
		add_action('TheAppFactory_setupStores',array(&$this, 'massageStoreConfigs'),600);
		add_action('TheAppFactory_parsePost', array(&$this, 'maybeSetupSheetMenuItems' ), 100 );
		
		
		do_action_ref_array('TheAppFactory_init',array(& $this));
	}
	
	// Override in subclasses
	public function setup(){
		self::set_environment();
	}
	
	// Override in subclasses
	public static function setup_environment(){

	}
	
	public function set_environment(){
		$the_app = self::getInstance();
		$app_meta = get_post_meta(get_the_ID(),'app_meta',true);
		$key = (current_user_can('administrator') ? 'admin' : 'regular');
		if ($app_meta and isset($app_meta['visibility']) and isset($app_meta['visibility'][$key])){
			$the_app->set('environment', $app_meta['visibility'][$key]); // i.e. 'development', 'production', 'native_ios', 'native_android'
		}
		else{
			$the_app->set('environment', 'development');
		}
	}
	
	public function save_postdata( $post_id ){
		/* When the post is saved, saves our custom data */
		// verify if this is an auto save routine. 
		// If it is our form has not been submitted, so we dont want to do anything
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
		    return;

		// verify this came from the our screen and with proper authorization,
		// because save_post can be triggered at other times

		if ( !wp_verify_nonce( $_POST['app_meta_nonce'], plugin_basename( __FILE__ ) ) )
		    return;

		$post = get_post($post_id);
		 //skip all cases where we shouldn't index
		if ( $post->post_type != APP_POST_TYPE )
			return;

		if (!empty($_POST['app_meta']['bundle_id'])){
			$_POST['app_meta']['bundle_id'] = trim($_POST['app_meta']['bundle_id'],'.');
		}
		if (isset($_POST['app_meta'])){
			update_post_meta($post_id,'app_meta',$_POST['app_meta']);
		}
		else{
			delete_post_meta($post_id,'app_meta');
		}
	}

	
	
	private function reset(){
		$this->parms = array();
		$this->set('transition','slide');
		$this->set('items',array());
		$this->set('meta',array('unacceptable_browser' => array('not_webkit' => true)));
		$this->set('registered_post_queries',array());
		$this->set('query_defaults',array(
			'author' => '',
			'author_name' => '',
			'cat' => '',
			'category_name' => '',
			'category__and' => '',
			'category__in' => '',
			'category__not_in' => '',
			'tag' => '',
			'tag_id' => '',
			'tag__and' => '',
			'tag__in' => '',
			'tag__not_in' => '',
			'tag_slug__and' => '',
			'tag_slug__in' => '',
			'p' => '',
			'name' => '',
			'numberposts' => -1,
			'page_id' => '',
			'pagename' => '',
			'post_parent' => '',
			'post__in' => '',
			'post__not_in' => '',
			'post_type' => 'post',
			'post_status' => '',
			'order' => 'ASC',
			'orderby' => 'title',
			'year' => '',
			'monthnum' => '',
			'w' => '',
			'day' => '',
			'hour' => '',
			'minute' => '',
			'second' => '',
			'meta_key' => '',
			'meta_value' => '',
			'meta_compare' => '',
			'data_callback' => null,
			'model_callback' => null,
			'timestamp_callback' => null,
			'xtype' => 'itemlist'
		));
		
	}
	
	public function get($what=null){
		if ($what != null){
			return $this->parms[$what];			
		}
		else{
			return $this->parms;
		}
	}
	
	public function set($what,$value){
		$this->parms[$what] = $value;
	}
	
	public function setup_post( $post = null ){
		if (!isset($post)){
			global $post;
		}
		// reset the app
		//$this->reset();
		
		// the post is the object that defines the app
		// though this is setup to work with WP posts as default
		// I'm keeping it open to allow other ways to instantiate 
		// an app
		$this->set('post',$post);
		if (isset($post->ID)){
			$this->is('wordpress_post',true);
		}
		else{
			$this->is('wordpress_post',false);
		}
		
		if ($this->is('wordpress_post')){
			// Let's see if there are any custom images to use for the app (startups and icon)
			$p = $this->get('post');
			$attachments = get_children( array( 'post_parent' => $p->ID, 'post_type' => 'attachment', 'orderby' => 'menu_order ASC, ID', 'order' => 'DESC') );
			$app_attachments_types = apply_filters('TheAppFactory_attachments_types',array('startup_phone','startup_tablet','startup_landscape_tablet','icon','stylesheet','splash'),array(& $this));
			foreach ($app_attachments_types as $type){
				$guid = null;
				if (is_array($attachments) and count($attachments)){
					foreach ($attachments as $attachment){
						if (strpos($attachment->post_title,$type) === 0 and !$this->get($type)){
							// We're going to deal with splashes a little differently.  We're
							// going to end up base64_encoding them and including them
							// in the index file.  So, we're going to save the filepath instead
							if ($type == 'splash'){
								add_filter('the_app_factory_body_style',array(&$this,'addSplashImage'));
								$splash_file = get_attached_file($attachment->ID);
							}
							$guid = $attachment->guid;
						}
					}
				}
				$guid = apply_filters('TheAppFactory_attachment_guid',$guid,$type,array(&$this));
				if ($guid){
					$this->set($type,$guid);
					if ( isset( $splash_file ) ){
						$this->set('has_splash',true);
						$this->set( 'splash_file', $splash_file );
						unset( $splash_file );
					}
				}
			}
		}
		// This is the mothership, where the app calls to get data
		$this->set('mothership',apply_filters('TheAppFactory_mothership',trailingslashit(get_permalink()),array(&$this)));
		
		$this->set('app_id',substr(md5($this->get('mothership')),0,5)); // a unique app_id
		
		// Register xtypes for Sencha 2 apps
		$this->setupSencha();
		
		// second, setup the parms based on the shortcodes within the post
		$this->parsePost();
		
		$this->setupModels();
		$this->setupStores();
		$this->setupHelpers();
		$this->setupProfiles();

	}
	
	public function is($what,$value = null){
		if (isset($value)){
			$this->set('_is_'.$what,$value);
		}
		return $this->get('_is_'.$what);
	}
	
	public function parsePost(){
		$post = $this->get('post');
		if ($this->is('wordpress_post')){
			$this->set('title',$post->post_title);
			do_shortcode($post->post_content);
		}
		do_action_ref_array('TheAppFactory_parsePost',array(& $this));
	}
	
	public function apply($what,$value){
		$current = $this->get($what);
		if (empty($current)){
			$current = array();
		}
		$this->set($what,$this->array_merge_recursive($current,$value));
		return $this->get($what);
	}
	
	public function remove($value,$what){
		if (isset($this->parms[$what]) and array_key_exists($value,$this->parms[$what])){
			unset($this->parms[$what][$value]);
		}
	}
	
	public function shortcode_atts( $defaults, $atts ){
		// This is exactly the same as the WP function shortcode_atts
		// except that it reCamelCases $atts keys (so if you enter
		// thisAttributeName in your shortcode, it actually survives as CamelCased, as opposed to 
		// being lowercased)
		foreach ( array_keys($defaults) as $key ){
			$lower_key = strtolower( $key );
			if ( isset( $atts[ $lower_key ] ) ){
				$atts[ $key ] = $atts[ $lower_key ];
				unset( $atts[ $lower_key ] ); 
			}
		}
		return shortcode_atts( $defaults, $atts );
	}

	// Exactly the same as the PHP array_merge_recursize, unless it's not an associative
	// array (i.e. numeric indexes), in which case, it just fully replaces the previous
	// with the next's value.
	// 
	// Adapted from @walf's comment at http://www.php.net/manual/en/function.array-merge-recursive.php
	public function array_merge_recursive() {

	    if (func_num_args() < 2) {
	        trigger_error(__FUNCTION__ .' needs two or more array arguments', E_USER_WARNING);
	        return;
	    }
	    $arrays = func_get_args();
	    $merged = array();
	    while ($arrays) {
	        $array = array_shift($arrays);
	        if (!is_array($array)) {
	            trigger_error(__FUNCTION__ .' encountered a non array argument', E_USER_WARNING);
	            return;
	        }
	        if (!$array)
	            continue;

			if ($this->is_assoc($array)){
		        foreach ($array as $key => $value)
	                if (is_array($value) && array_key_exists($key, $merged) && is_array($merged[$key]))
	                    $merged[$key] = call_user_func(array(&$this,'array_merge_recursive'), $merged[$key], $value);
	                else
	                    $merged[$key] = $value;
			}
			else{
				$merged = $array;
			}
	    }
	    return $merged;
	}

	// Thanks http://stackoverflow.com/questions/173400/php-arrays-a-good-way-to-check-if-an-array-is-associative-or-numeric
	public function is_assoc($array) {
	  return (bool)count(array_filter(array_keys($array), 'is_string'));
	}

	public function shortcodes($atts = array(),$content = null,$code = ''){
		if (!is_array($atts)){
			$atts = array();
		}
		$original_atts = $atts;
		$atts = $this->sanitize_atts($atts,$code); // calls @shortcode_atts
		switch($code){
		case 'the_app':
			foreach ($atts as $key => $att){
				if (substr($key,0,4) == '_is_'){
					$this->set($key,($att ? true : false));
				}
				else{
					$this->set($key,$att);
				}
			}
			
			if ( $this->is( 'using_manifest' ) ){
				switch( $this->get( 'storage_engine' ) ){
				case 'localstorage':
					// No need to do anything, 
					break;
				case 'sqlitestorage':
					$this->enqueue( 'require', 'SqliteDemo.util.InitSQLite' );
					break;
				}				
			}
			// Make sure we're always working with the latest Sencha Touch SDK
			$defaults = $this->get_default_atts( $code );
			$this->set( 'sdk', $defaults['sdk'] );
			break;
		case 'launch':
			$atts['slide_pause'] = intval( $atts['slide_pause'] );
			$this->set( 'launchConfig', $atts );
			do_shortcode( $content );
			break;
		case 'launch_item':
			$launch_items = $this->get( 'launchItems' );
			if ( !is_array( $launch_items ) ){
				$launch_items = array();
			}
			if ( isset( $atts['slide_pause'] ) ){
				$atts['slide_pause'] = intval( $atts['slide_pause'] );
			}
			$launch_items[] = array_filter($atts);
			$this->set( 'launchItems', $launch_items );
			break;
		case 'loading_spinner':
			foreach ($atts as $key => $att){
				$this->set('spinner-'.$key,$att);
			}	
			break;
		case 'unacceptable_browser':
			$unacceptable_browser = array();
			foreach ($atts as $key => $att){
				$unacceptable_browser[$key] = $att;
			}	
			$unacceptable_browser['content'] = isset( $content ) ? $content : __('Your browser is not supported.  Please use a Webkit Browser (i.e. Chrome, Safari, iPhone, Android).','app-factory');
			$meta = $this->get('meta');
			$meta['unacceptable_browser'] = $unacceptable_browser;
			$this->set('meta',$meta);
			break;
		case 'app_item_wrapper':
			$this->is('capturing',true);
			$this->set('captured_items',array());
			do_shortcode($content);
			$this->is('capturing',false);
			$atts['pages'] = $this->get('captured_items');
			$this->addWrapperItem($atts);
			break;
		case 'app_item':
			switch(true){
			case (isset($atts['callback']) and is_callable($atts['callback'])):
				$callback = $atts['callback'];
				call_user_func_array($atts['callback'],array($original_atts)); // pass the original, so there's no filtering.
				break;
			case (isset($atts['post_type'])):
				$this->addPostListItem($atts);
				break;
			case (isset($atts['post']) and is_numeric($atts['post'])):
				$p = get_post($atts['post']);
				if ($p){
					if (empty($atts['title'])) $atts['title'] = $p->post_title;
					$atts['content'] = apply_filters('the_content',$p->post_content);
					$this->addHTMLItem($atts);
				}
				break;
			case (isset($content) and trim($content) != ''):
				$atts['content'] = apply_filters('the_content',$content);
				$this->addHTMLItem($atts);
				break;
			}
			break;
		case 'app_posts':
			if ($atts['orderby'] == 'date'){
				// 'date' is formatted as "Jan 10 2011", which doesn't sort as well as 2011-01-10
				$atts['orderby'] = 'date_gmt';
			}
			$this->addPostListItem($atts);
			break;
		}
	}
	
	function sanitize_atts($atts = array(),$shortcode = null){
		// Sanitize boolean strings to actual booleans
		if ( !is_array( $atts ) ){
			$atts = array();
		}
		foreach ( $atts as $key => $value ){
			if ( $value === 'false' ){
				$atts[ $key ] = false;
			}
			elseif ( $value === 'true' ){
				$atts[ $key ] = true;
			}
			// Add in a camelCaseKey for any camel_case_key attributes;
			if ( strpos( $key, '_' ) !== false ) {
				$pieces = array_map( 'ucfirst', explode( '_', $key ) );
				$pieces[0] = strtolower( $pieces[0] );
				$camelCaseKey = implode( '', $pieces);
				$atts[ $camelCaseKey ] = $atts[ $key ];
			}
		}
		if ( isset($shortcode) ){
			$defaults = $this->get_default_atts($shortcode);
		}
		if ( isset( $defaults ) && !empty( $defaults ) ){
			$return = shortcode_atts($defaults,$atts);
		}
		else{
			$return = $atts;
		}
		return $return;
	}
		
	function get_default_atts($shortcode, $filter = false ){
		$item_defaults = $meta_defaults = array();
		switch($shortcode){
		case 'the_app':
			$meta_defaults = array(
				'_is_debug_on' => false,	// sends Javascript messages to console.log() via a maybelog() call. Loads debug version of Sencha library.
				'_is_using_manifest' => false,	// saves data to the user's device. An app gets 5MB for free without having to ask the user for permission.
				'storage_engine' => 'localstorage', // (experimental) - could be localstorage (default) or sqlitestorage
				'transition' => 'slide',	// slide (default), fade, pop, flip, cube, wipe (buggy) --+ the CSS3 transition to use between pages
				'manifest_version' => '',	// a version number for the manifest file.  Useful for forcing new manifest load. 
				'maxtabbaritems' => '',		// If you want to enable a slide-up menu panel to appear when there are more than N tabs, enter N for maxtabbaritems
				'splash_pause' => 2,		// If you have a splashscreen, you can force it to display for N seconds by setting splash_pause=N
				'ios_install_popup' => false, // True to enable the Install App popup on iOS devices,
				'sdk' => '2.3.1',				// the Sencha Touch SDK to use - only valid value currently is 2.3.1
				'theme' => 'sencha-touch',		// valid values are base, bb10, sencha-touch (default).  The blank SDK also have wp-app-factory
				'menu_style' => 'tabbar',		// could be 'tabbar' or 'sheet',
			);
			break;
		case 'launch':
			$meta_defaults = array(
				'show_all' => true, 		// Whether to show all slides before launching app, ( false to just launch when ready )
				'slide_pause' => 2000, 		// Millisecond delay between slides
				'text_color' => '#000',		// The color for the user-defined text messages
				'text_top' => '10%',		// The top of the user-defined texts
				'text_background' => 'none',// The background of the user-defined text box
				'message_color' => '#000',	// The color of the app-defined messages
				'message_top' => '80%',		// The top of the app-defined messages
				'message_background' => 'none',// The background of the app-defined messages
			);
			break;
		case 'launch_item':
			$meta_defaults = array(
				'text' => null,	 			// The text to display for this slide
				'image' => null,	 		// The image to display for this slide
				// If any of these are null, then the defaults for the 'launch' config are used instead
				'slide_pause' => null, 		// Millisecond delay for this slide
				'text_color' => null,		// The color for the text and the loading message for this slide
				'text_top' => null,			// The top of the user-defined text
				'text_background' => null,	// The background of the text box
				'message_color' => null,	// The color of the app-defined message
				'message_top' => null,		// The top of the app-defined message
				'message_background' => null,// The background of the app-defined message
			);
			break;
		case 'app_item':
			$item_defaults = array(
				'xtype' => 'htmlpage',		// the xtype for the container
				'id' => '', 				// the id for the container.
				'icon' => 'star',			// action, add, arrow_down, arrow_left, arrow_right, arrow_up, compose, delete, organize, refresh, reply, search, settings, star (default), trash, maps, locate, home
				'title' => '', 				// the title of page. Also the title on the bottom toolbar icon.
				'content' => '',
				'destroyOnDeactivate' => true,	// For LazyPanels, whether or not to destroy the panel when deactivating
			);
			$meta_defaults = array(
				'_is_default' => false,		// makes this item the first one that appears.
				'template' => '{content}',	// the XTemplate to use to display the content
				'callback' => null,			// a function to call to setup the page. Gives developers finer control
			);
			
			break;
		case 'app_item_wrapper':
			$item_defaults = array(
				'xtype' => 'itemwrapper',	// the xtype for the container
				'id' => '',					// the id for the container.
				'icon' => 'star',			// action, add, arrow_down, arrow_left, arrow_right, arrow_up, compose, delete, organize, refresh, reply, search, settings, star (default), trash, maps, locate, home
				'title' => '', 				// the title of page. Also the title on the bottom toolbar icon.
				'pages' => array(),			// data for the list, should be array of values with keys 'item' & 'meta'
				'destroyOnDeactivate' => true,	// For LazyPanels, whether or not to destroy the panel when deactivating
			);
			$meta_defaults = array(
				'_is_default' => false,		// makes this item the first one that appears.
				'ui' => 'round',			// could be round or normal
				'list_template' => '{item.title}' // the Sencha tpl for the list item
			);
			break;
		case 'app_posts':
			$item_defaults = array(
				'xtype' => 'itemlist',		// the xtype for the container
				'store' => 'postStore',		// The store
				'id' => '',					// the id for the container.
				'icon' => 'star',			// action, add, arrow_down, arrow_left, arrow_right, arrow_up, compose, delete, organize, refresh, reply, search, settings, star (default), trash, maps, locate, home
				'title' => 'Posts', 		// the title of page. Also the title on the bottom toolbar icon.
				'destroyOnDeactivate' => true,	// For LazyPanels, whether or not to destroy the panel when deactivating
				'infinite' => true			// infinite scrolling
			);
			$meta_defaults = array(
				'_is_default' => false,		// makes this item the first one that appears.
				'store' => 'postStore',		// The store
				'title' => 'Posts',
				'query_vars' => $this->simplify_atts( $this->get( 'query_defaults' ) ),
				'grouped' => true,		// whether to create group headers
				'group_by' => 'first_letter', 	// first_letter, category, month
				'group_order' => 'ASC',		// the order for the group headers
				'orderby' => 'title',		// what to sort the posts on
				'order' => 'ASC',			// the direction
				'indexbar' => true,			// whether to create index bar
				'numberposts' => -1,		// the maximum number of posts to show
				'searchable' => false,		// Include a search field at the top of the list
				'searchableFields' => 'title',	// which fields are searchable - accepts a comma separated list
				// the Sencha tpl for the list item
				'storage_engine' => 'localstorage', // if there is a lot of data, try using 'sqlitestorage'
				'list_template' => '<div class="avatar"<tpl if="thumbnail"> style="background-image: url({thumbnail})"</tpl>></div><span class="name">{title}</span>',
				// the Sencha tpl for the detail page
				'detail_template' => '<tpl if="thumbnail"><img class="thumbnail" src="{thumbnail}"></tpl></div><h3>{title}</h3> {content}'
			);
			
			// Add in anything allowed by @get_posts();
			$get_post_defaults = WP_Query::fill_query_vars(array());
			$meta_defaults = array_merge($get_post_defaults,$meta_defaults,$this->get('query_defaults'));
			break;
		case 'unacceptable_browser':
			$meta_defaults = array(
				'not_webkit' => true, 	// this should ALWAYS be true.  Displays Unacceptable Browser message if browser is not webkit
				'desktop'	 => false		// display the Unacceptable Browser message if it's a desktop browser
			);
			break;
		}
		
		if ( !$filter ){
			$defaults = array_merge( $item_defaults, $meta_defaults );
		}
		else{
			switch( $filter ){
			case 'item':
				$defaults = $item_defaults;
				break;
			case 'meta':
				$defaults = $meta_defaults;
				break;
			}
		}
		if ( !is_array( $defaults ) ){
			$defaults = array();
		}
		
		return apply_filters('the_app_shortcode_defaults',$defaults,$shortcode);
	}
	
	function addHTMLItem($atts){
		// HTML pages are easy
		$shortcode = 'app_item';
		$atts = $this->sanitize_atts( $atts, $shortcode );
		$item_atts = shortcode_atts( $this->get_default_atts( $shortcode, 'item' ), $atts );
		$meta_atts = shortcode_atts( $this->get_default_atts( $shortcode, 'meta' ), $atts );
		
		static $html_page_counter;
		if (!isset($html_page_counter)){
			$html_page_counter = 1;
		}
		$html_store_contents = $this->get('html_store_contents');
		if (empty($html_store_contents)){
			$html_store_contents = array();
		}
		if (empty($item_atts['id'])){
			$item_atts['id'] = 'htmlpage_'.$html_page_counter;
		}
		$html_store_contents[] = array(
			'id' => $html_page_counter++,
			'key' => $item_atts['id'],
			'title' => $item_atts['title'],
			'content' => $item_atts['content']
		);
		unset($item_atts['content']);
		//unset($item_atts['title']);
		$this->set('html_store_contents', $html_store_contents);
		
		$this->addItem($item_atts,$meta_atts);
	}
	
	function addWrapperItem($atts){
		$shortcode = 'app_item_wrapper';
		$atts = $this->sanitize_atts( $atts, $shortcode );
		$item_atts = shortcode_atts( $this->get_default_atts( $shortcode, 'item' ), $atts );
		$meta_atts = shortcode_atts( $this->get_default_atts( $shortcode, 'meta' ), $atts );

		static $wrapper_counter;
		if (!isset($wrapper_counter)){
			$wrapper_counter = 1;
		}
		$wrapper_store_contents = $this->get('wrapper_store_contents');
		if (empty($wrapper_store_contents)){
			$wrapper_store_contents = array();
		}
		if (empty($item_atts['id'])){
			$item_atts['id'] = 'wrapper_'.$wrapper_counter;
		}
		$wrapper_store_contents[] = array(
			'id' => $wrapper_counter++,
			'key' => $item_atts['id'],
			'title' => $item_atts['title'],
			'pages' => $item_atts['pages']
		);
		unset($item_atts['pages']);
		//unset($item_atts['title']);
		$this->set('wrapper_store_contents', $wrapper_store_contents);

		$this->addItem($item_atts,$meta_atts);
	}
	
	function addPostListItem($atts){
		$shortcode = 'app_posts';
		$atts = $this->sanitize_atts( $atts, $shortcode );

		$meta_defaults = $this->get_default_atts( $shortcode, 'meta' );
		$query_defaults = $this->get('query_defaults'); 
		$query_atts = $this->simplify_atts(shortcode_atts($query_defaults,$atts));

		$atts[ 'store' ] = $query_atts[ 'post_type' ] . 'Store';
		$atts[ 'query_vars' ] = $query_atts;

		$item_atts = shortcode_atts( $this->get_default_atts( $shortcode, 'item' ), $atts );		
		$meta_atts = shortcode_atts( $meta_defaults, $atts );
		
		$index = $this->registerPostQuery($meta_atts);
		$item_atts['queryInstance'] = $index;
		
		if ( $this->get( 'sdk' ) >= '2.3.1' && !$meta_atts['grouped'] ){
			// There's a bug in Sencha Touch 2.3.1 where if a list is not grouped, but is infinite, it throws an error
			$item_atts['infinite'] = false;
		}
		
		if ( isset( $meta_atts['searchable'] ) && $meta_atts['searchable'] ){
			$this->enqueue('controller','Search');
		}
		
		$this->addItem($item_atts,$meta_atts);
	}
	
	function addItem($atts,$meta = array()){
		$new_item = array();
		if (isset($atts['icon'])){
			$atts['iconCls'] = $atts['icon'];
		}
		if (isset($atts['id']) and empty($atts['id'])){
			unset($atts['id']);
		}
		$new_item = array('item' => $atts);
		if (count($meta)){
			$new_item['meta'] = $meta;
		}

		if ($new_item['meta']['_is_default']){
			$items = $this->get('items');
			array_unshift($items,$new_item);
			$this->set('items',$items);
		}
		elseif ($this->is('capturing')){
			$captured_items = $this->get('captured_items');
			$captured_items[] = $new_item; //['item'];
			$this->set('captured_items',$captured_items);
		}
		else{
			$items = $this->get('items');
			$items[] = $new_item;
			$this->set('items',$items);
		}

		if ( $this->is_registered( 'view', $atts['xtype'] ) ){
			$this->enqueue('view',$atts['xtype']);
		}
		if ($atts['xtype'] == 'itemlist'){
			$this->enqueue('view','ItemDetail');
		}
	}
	
	private function setupModels(){
		$models = array();
				
		if ($this->is('using_manifest')){
			$models['StoreStatus'] = array('fields' => array('id','store','timestamp'));
		}
		if ($this->get('wrapper_store_contents')){
			$models['WrapperPage'] = array('fields' => array('id','title','pages','key'));
		}
		if ($this->get('html_store_contents')){
			$models['HtmlPages'] = array('fields' => array('id','title','content','key'));
		}
		if ( 'sheet' == $this->get( 'menu_style' ) ){
			$models['SheetMenuItems'] = array( 'fields' => array( 'id', 'text', 'items', 'iconCls' ) ); 
		}
		
		$this->set('models',apply_filters('TheAppFactory_models',$models,array(&$this)));
		do_action_ref_array('TheAppFactory_setupModels',array(&$this));
	}
	
	private function setupHelpers(){
		$helpers = array();
			
		global $month;	
		$queryFilter = "
		function(panel){
			return new Ext.util.Filter({
					filterFn: function(item){
						return item.get('query_num').match(new RegExp('_'+panel.queryInstance+'_')) && (panel.meta.group_by == 'category' || item.get('spoof_id') == undefined);
					}
				});
			}
		";
		$helpers['WP'] = array(
			'months' => array_values($month),
			'_' => $this->do_not_escape('function(s){ return s; }'), // Sencha 2 adds a '_' in front of the property, so this will become WP.__()
			'queryFilter' => $this->do_not_escape(preg_replace('/[\n\r\t]/','',$queryFilter)),
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'url' => get_permalink(),
			'ID' => get_the_ID(),
			'appId' => md5(get_the_ID()), // This is the app id in the app.json file
			'localStorageKey' => $this->get( 'app_id' ),
			'appName' => get_the_title()
		);
		
		$this->set('helpers',apply_filters('TheAppFactory_helpers',$helpers,array(&$this)));
		do_action_ref_array('TheAppFactory_setupHelpers',array(&$this));
	}
	
	private function setupProfiles(){
		$profiles = array();
		
		$this->set('profiles',apply_filters('TheAppFactory_profiles',$profiles,array(&$this)));
		do_action_ref_array('TheAppFactory_setupProfiles',array(&$this));
	}
	
	private function registerPostQuery($meta_atts){
		$registered_post_queries = $this->get('registered_post_queries');
		if (isset($meta_atts['query_vars']['post_type'])){
			$post_type = $meta_atts['query_vars']['post_type'];
		}
		elseif(isset($meta_atts['query_vars']['xtype'])){
			$post_type = $meta_atts['query_vars']['xtype'];
		}
		
		if (!isset($post_type)){
			die(__('Attempting to register a list without a post type.  Please set either $atts[query_vars][post_type] or $atts[query_vars][xtype] when calling TheAppFactory::addPostListItem','app-factory'));
		}
		
		if (!array_key_exists($post_type,$registered_post_queries)){
			$registered_post_queries[$post_type] = array();
		}
		$registered_post_queries[$post_type][] = $meta_atts;
		$this->set('registered_post_queries',$registered_post_queries);
		return (count($registered_post_queries[$post_type]) - 1); // the query instance index
	}
	
	function addRegisteredModels($models,$_this){
		if (is_array($this->get('registered_post_queries'))){
			
			foreach ($this->get('registered_post_queries') as $post_type => $registered_meta){
				
				$callback_exists = false;
				foreach($registered_meta as $queryInstance => $registered_query){
					if (isset($registered_query['query_vars']['model_callback'])){
						$post_type = $registered_query['query_vars']['post_type'];
						$models[$post_type] = call_user_func($registered_query['query_vars']['model_callback'],$post_type);
						if (!in_array('query_num',$models[$post_type]['fields'])){
							$models[$post_type]['fields'][] = 'query_num';
						}
						$callback_exists = true;
					}
				}
				if ($callback_exists){
					continue;
				}
				$models[$post_type] = array('fields' => array());
				// common parms are parms that are common to all post types
				// We'll just get them from the app post  
				foreach ($this->get('post') as $field => $value){
					if ($field != 'post_password'){
						$field = $this->sanitize_key($field);
						$models[$post_type]['fields'][] = $field;
					}
				}

				// Now, we need to get all custom fields that exist for posts of this type
				// Turns out to be tricker than I thought.  I don't know if WP has something 
				// native for this...
				$meta_keys = $this->lookupCustomFields($post_type);
				foreach ($meta_keys as $key){
					$models[$post_type]['fields'][] = $key;
				}
				
				// Finally, let's add one for the thumbnail
				$models[$post_type]['fields'][] = 'thumbnail';
				
				// If there are more than one queries registered for this post_type,
				// we'll add another field which will indicate which registered_query
				// we're dealing with
				$models[$post_type]['fields'][] = 'query_num';
				
				// Add for category
				$models[$post_type]['fields'][] = 'category';
				$models[$post_type]['fields'][] = 'spoof_id'; // allows a single post to appear under more than one category
			}
		}
		return $models;
	}
	
	public function lookupCustomFields($post_type){
		global $wpdb;
		static $query;
		if (!isset($query)){
			$query = "SELECT DISTINCT `meta_key` FROM $wpdb->postmeta where `post_id` in (SELECT ID FROM $wpdb->posts WHERE `post_type` = %s)";			
		}
		$meta_keys = $wpdb->get_col($wpdb->prepare($query, $post_type));
		foreach ($meta_keys as $k => $key){
			if (substr($key,0,1) == '_'){
				unset($key->$k);
			}
		}
		return $meta_keys;
	}
	
	private function setupStores(){
		$stores = array();
		
		if ($this->get('wrapper_store_contents')){
			$stores['WrapperPageStore'] = array(
				'fields' => array('id','title','content')
			);
			$stores['WrapperPageStore'] = array(
				'model' => 'WrapperPage',
				'autoLoad' => true, 
				'proxy' => array(
					'type' => 'scripttag',
					'url' => $this->get('mothership').'data/wrapperpages',
					'reader' => array('type' => 'json', 'rootProperty' => 'wrapperpages')
				)
			);
			if ($this->is('using_manifest')){
				$stores['WrapperPageStore']['useLocalStorage'] = true;
			}
		}
		
		if ($this->get('html_store_contents')){
			$stores['HtmlPagesStore'] = array(
				'model' => 'HtmlPages',
				'autoLoad' => true, 
				'proxy' => array(
					'type' => 'scripttag',
					'url' => $this->get('mothership').'data/htmlpages',
					'reader' => array('type' => 'json', 'rootProperty' => 'htmlpages')
				)
			);
			if ($this->is('using_manifest')){
				$stores['HtmlPagesStore']['useLocalStorage'] = true;
			}
		}
		
		$this->set('stores',apply_filters('TheAppFactory_stores',$stores,array(&$this)));
		
		do_action_ref_array('TheAppFactory_setupStores',array(&$this));
	}
	
	public function setupStoreStatusStore($the_app){
		if ($this->is('using_manifest')){
			$stores = $this->get('stores');
			$stores['StoreStatusStore'] = array();
			$stores['StoreStatusStore']['model'] = 'StoreStatus';
			$stores['StoreStatusStore']['useLocalStorage'] = true;
			$stores['StoreStatusStore']['autoLoad'] = true; // Note camelCase...
			$stores['StoreStatusStore']['proxy'] = array(
				'type' => 'scripttag',
				'url' => $this->get('mothership').'data/storemeta',
				'reader' => array('type' => 'json', 'rootProperty' => 'stores')
			);
			$stores = $this->set('stores',$stores);
		}		
	}
	
	public function massageStoreConfigs( & $the_app ){
		$stores = $this->get( 'stores' );
		
		foreach( $stores as $key => $store ){

			if (!isset($store['storeId'])){
				$store['storeId'] = $key;
			}
			/*
			// because of other functionality, I'm going to hold off on this and only do it in the actual store factory
			if( isset( $store['model'] ) and strpos( $store['model'], 'the_app.model' ) === false){
				$store['model'] = 'the_app.model.' . $store['model'];
			}
			*/
	
			// Setup offline versino of the store
			if ( $store[ 'useLocalStorage' ] ){
				$store[ 'serverProxy' ] = $store[ 'proxy' ];
				if ( !isset( $store[ 'storage_engine'] ) ){
					$store[ 'storage_engine'] = $the_app->get( 'storage_engine' );
				}
				switch( $store[ 'storage_engine' ] ){
				case 'localstorage':
					$store['localProxy'] = /* $store['proxy'] = */ array(
						'type' => 'localstorage',
						'id' => apply_filters('the_app_factory_localstorage_id',"{$store['storeId']}_{$the_app->get('app_id')}",$store)
						/*	 Come back to it.  This is the way to catch that allowed storage has been exhausted.
						'listeners' => array(
							'exception' => $the_app->do_not_escape('function(proxy,e){console.log([\'error\',e]);}')
						)
						*/
					);
					break;
				case 'sqlitestorage':
					$tablename = preg_replace( '/[^A-Za-z0-9_]/', '_', str_replace( 'the_app.model.', '', $store['model'] ) );
					$store['localProxy'] = /* $store['proxy'] = */ array(
						'type' => 'sqlitestorage',
						'dbConfig' => array(
							'tablename' => $tablename,
							'dbConn' => $the_app->do_not_escape( 'null' )
						)
					);
					$store['tableName'] = $tablename;
					break;
				}
				$store['storageEngine'] = $store['storage_engine']; // for happy Sencha configuration
				if ( $key == 'StoreStatusStore' ){
					$store['extend'] = 'Ext.ux.OfflineSyncStatusStore';
				}
				else{
					$store['extend'] = 'Ext.ux.OfflineSyncStore';
				}
			}
			else{
				$store['extend'] = 'Ext.data.Store';
			}
			
			// Going to try lazy loading all stores, unless we're building (even StoreStatusStore)
			//if ( $the_app->is( 'building' ) || in_array($store['storeId'],apply_filters('the_app_factory_autoload_stores',array( 'HtmlPagesStore' ) ) ) ){
			//	$store[ 'autoLoad' ] = true;
			//}
			//else{
			//	$store[ 'autoLoad' ] = false;
			//}
			
			$store['launchLoad'] = false;
			if ( $the_app->is( 'building' ) ){
				$store['autoLoad'] = true;
			}
			elseif ( isset($store['autoLoad']) && $store['autoLoad'] ){
				$store['launchLoad'] = true;
				$store['autoLoad'] = false;
			}
			
			if ( $the_app->is( 'packaging' ) ){
				$store['ajaxProxy'] = $store['serverProxy'];
				$store['ajaxProxy']['type'] = 'ajax';
				$store['ajaxProxy']['url'] = 'resources/data/'.preg_replace( '/Store$/', '', $key ).'.json';
			}
			
			if ( !isset( $store['prettyLabel'] ) ){
				$store['prettyLabel'] = preg_replace( '/Store$/', '', $key );
			}
			
			$stores[ $key ] = $store;
		}
		$this->set( 'stores', $stores );
		
	}
	
	function addRegisteredStores($stores,$_this){
		if (is_array($this->get('registered_post_queries'))){
			foreach ($this->get('registered_post_queries') as $post_type => $registered_meta){
				$store_name = "{$post_type}Store";
				$stores[$store_name] = array();
				$stores[$store_name]['model'] = $post_type;

				if ($this->is('using_manifest') and (!isset($registered_meta[0]['useLocalStorage']) or $registered_meta[0]['useLocalStorage'])){
					$stores[$store_name]['useLocalStorage'] = true;
				}
				$stores[$store_name]['proxy'] = array(
					'type' => 'scripttag',
					'url' => $this->get('mothership').'data/'.$post_type.'/', // Note trailing slash, necesasry to avoid Status: 301 calls (which merely add a slash)
					'reader' => array('type' => 'json', 'rootProperty' => $post_type)
				);
				$stores[$store_name]['autoLoad'] = isset( $registered_meta[0][ 'autoLoad' ] ) ? $registered_meta[0][ 'autoLoad' ] : true; // Note camelCase...
				if ( isset( $registered_meta[0][ 'storage_engine' ] ) ){
					$stores[$store_name]['storage_engine'] = $registered_meta[0][ 'storage_engine' ];
				}
			}
		}
		return $stores;
	}
	
	function addLaunchHelper( $helpers, $args ){
		$launch_config = $this->get( 'launchConfig' );
		if ( empty( $launch_config ) ){
			$launch_config = $this->get_default_atts( 'launch' );
		}
		$launch_items = $this->get( 'launchItems' );
		if ( !is_array( $launch_items ) ){
			$launch_items = array();
		}
		if ( empty( $launch_items ) && $this->get( 'has_splash' ) ){
			$launch_items = array(
				array(
					'image' => $this->get( 'splash' )
				)
			);
		}
		if ( empty( $launch_items ) ){
			$launch_items = array(
				array( 'text' => '' )
			);
		}
		foreach ( $launch_items as $key => $item ){
			if ( isset( $item['image'] ) ){
				$launch_items[$key]['image'] = do_shortcode( "[app_package_image]{$item['image']}[/app_package_image]" );
			}
		}
		$launch_config['items'] = $launch_items;
		$helpers['LAUNCHER'] = $launch_config;
		return $helpers;
	}

	private function setupSencha(){
		$this->register('controller','Main');
		$this->register('controller','Launcher');
		$this->register('controller','Search');
		$this->register('controller','ExpandedTabBar');
		$this->register('view','HtmlPage');
		$this->register('view','Main');
		$this->register('view','Launcher');
		$this->register('view','ItemWrapper');
		$this->register('view','ItemList');
		$this->register('view','UnsupportedBrowser');
		$this->register('view','LazyPanel');

		// This is a way to set the path for components that are not in the normal path tree
		// The third argument is the path and it is relative the wp-app-factory/the-app directory
		// It can be a full URL
		$this->register('path','Ext.ux.OfflineSyncStore','app/store/Ext.ux.OfflineSyncStore.js');
		$this->register('path','Ext.ux.OfflineSyncStatusStore','app/store/Ext.ux.OfflineSyncStatusStore.js');
		$this->register('path','Ext.ux.InstallApp','app/helper/Ext.ux.InstallApp.js');
		$this->register('path','Sqlite.Connection','app/proxy/SqliteConnection.js');
		$this->register('path','Sqlite.data.proxy.SqliteStorage','app/proxy/SqliteStorage.js');
		$this->register('path','SqliteDemo.util.InitSQLite','app/proxy/SqliteInit.js');
		$this->register('path','My.data.proxy.LocalStorage','app/proxy/LocalStorage.js');
		$this->register('path','Ext.data.ModelFaster','app/model/Ext.data.ModelFaster.js');
		
		$this->enqueue('controller','Launcher');
		$this->enqueue('controller','Main');
		$this->enqueue('controller','ExpandedTabBar');
		$this->enqueue('require','Ext.MessageBox');
		$this->enqueue('require','Ext.log.Logger');
		$this->enqueue('require','Ext.data.proxy.JsonP');
		$this->enqueue('require','Ext.layout.Fit'); // This seems to be necessary in Sencha Touch 2.1.1 - not sure why.
		$this->enqueue('require','Ext.layout.VBox'); // This seems to be necessary in Sencha Touch 2.1.1 - not sure why.
		$this->enqueue('require','Ext.layout.HBox'); // This seems to be necessary in Sencha Touch 2.1.1 - not sure why.
		$this->enqueue('view','Main');
		$this->enqueue('view','Launcher');
		$this->enqueue('view','UnsupportedBrowser');
		$this->enqueue('view','LazyPanel');
		$this->enqueue('require','the_app.helper.WP');
		$this->enqueue('require','the_app.helper.LAUNCHER');
		
		do_action_ref_array('TheAppFactory_setupSencha',array(&$this));
	}
	
	public function do_not_escape($text){
		$text = '__dne__'.$text.'__dne__';
		$text = str_replace(
			array(
				"\t",
				"\n",
				"\r",
				"/",
				'\\',
			),
			array(
				"__tab__",
				"__newline__",
				"__return__",
				"__slash__",
				"__backslash__"
			),
			$text
		);
		return $text;
	}

	public function anti_escape($text){
		$text = preg_replace('/\"?'.'__dne__'.'\"?/','',$text);
		$text = str_replace(
			array(
				"__tab__",
				"__newline__",
				"__return__",
				"__slash__",
				"__backslash__"
			),
			array(
				"\t",
				"\n",
				"\r",
				"/",
				'\\',
			),
			$text
		);
		return $text;
	}
	
	public function simplify_atts($atts){
		return array_filter($atts,create_function('$v','return ($v != "");'));
	}
	
	public function sanitize_key($key){
		$key = preg_replace('/^post_/','',$key);
		$key = strtolower($key);  // strtolower so ID turns to id
		return $key;
	}
	
	public function getPostImages($post_id,$index = null){
		$images = get_children( array( 'post_parent' => $post_id, 'post_type' => 'attachment', 'post_mime_type' => 'image', 'orderby' => 'menu_order', 'order' => 'ASC', 'numberposts' => -1 ) );
		$output = false;
		if ( $images ) {
			$output = array();
			foreach ($images as $image){
				$image_img_tag = wp_get_attachment_image_src( $image->ID, 'thumbnail' );
				$output[] = $image_img_tag[0];
			}
			if (is_numeric($index)){
				return $output[$index];
			}
		}
		return $output;
		
	}
	
	public function is_registered($what,$key){
		if (!isset($this->registered[$what])){
			return false;
		}
		foreach ($this->registered[$what] as $registered){
			if (strtolower($registered) == strtolower($key)){
				return true;
			}
		}
		return false;
	}
	
	public function register($what,$key,$path=null){
		if (!isset($this->registered[$what])){
			$this->registered[$what] = array();
		}
		if (!in_array($key,$this->registered[$what])){
			$this->registered[$what][] = $key;
		}
		if (isset($path)){
			if (!isset($this->registered['paths'])){
				$this->registered['paths'] = array();
			}
			if (!isset($this->registered['paths'][$what])){
				$this->registered['paths'][$what] = array();
			}
			$this->registered['paths'][$what][$key] = $path;
		}
	}
	
	public function enqueue($what,$key,$path=null){
		if (!isset($this->queued[$what])){
			$this->queued[$what] = array();
		}
		if (isset($this->registered[$what]) and !in_array($key,$this->registered[$what])){
			// It's possible that an xtype was passed in as the key.
			// This check is going to see if the key is in array_map('strtolower',$this->registered[$what])
			// (i.e. ItemDetail (a key) becomes itemdetail (an xtype)).  
			// If it is, then we'll replace the xtype $key with the CamelCased key
			foreach ($this->registered[$what] as $CamelCase){
				if (strtolower($CamelCase) == $key){
					$key = $CamelCase;
					break;
				}
			}
		}
		if (isset($this->registered[$what]) and !in_array($key,$this->registered[$what])){
			$this->register($what,$key,$path);
		}
		if (!in_array($key,$this->queued[$what])){
			// If it's not already queued
			$this->queued[$what][] = $key;
		}
	}
	
	public function render($what){
		switch($what){
		case 'view':
		case 'controller':
		case 'require':
		case 'profile':
			echo json_encode((isset($this->queued[$what]) ? $this->queued[$what] : array()));
			break;
		case 'meta': 
			$items = $this->get($what);
			echo json_encode((!empty($items) ? $items : new stdClass()));
			break;
		default:
			$items = $this->get($what);
			echo json_encode((!empty($items) ? array_keys($items) : array()));
			break;
		}
	}
	
	function the_items(){
		$the_items = $this->get('items');
		$items = array();
		if (is_array($the_items)){
			foreach ($the_items as $item){
				$the_item = $item['item'];
				$the_item['meta'] = $item['meta'];
				$items[] = $the_item;
			}
		}
		return $items;
	}
	
	function addSplashImage($style){
		$the_app = & TheAppFactory::getInstance();
		
		$image = $this->get('splash_file');
		if ($this->is('packaging')){ // 'packaging_via_ajax')){
			// We are packaging the app, so we'll copy this to the images directory
			$dest = $the_app->get( 'package_native_www' ).'resources/images/'.basename($image);
			TheAppBuilder::build_mkdir(dirname($dest));
			copy($image,$dest);
			$style = 'background:url(resources/images/'.basename($image).') center center no-repeat;background-size:contain;';
			return $style;
		}

		$encoded = $this->base64_encode_image($image);
		$style = 'background:url(\''.$encoded.'\') center center no-repeat;background-size:contain;';
		return $style;
	}
	
	function base64_encode_image($imagefile) {
        $imgtype = array('jpg', 'gif', 'png');
        $filename = file_exists($imagefile) ? htmlentities($imagefile) : false;
		if (!$filename){
			return '';
		}
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        if (in_array($filetype, $imgtype)){
            $imgbinary = fread(fopen($filename, "r"), filesize($filename));
        } else {
            return '';
        }
        return 'data:image/png;base64,' . base64_encode($imgbinary);
    }	

	function getSpinnerStyle(){
		$spinner = array(
			'size' => '40', 		// In px
			'color' => '',		// must be r,g,b
			'top' => '50%',			// should be % of screen height
			'left' => '50%',		// should be % of screen width
 		);
		foreach ($spinner as $property => $value){
			if ($this->get('spinner-'.$property)){
				$spinner[$property] = $this->get('spinner-'.$property);
			}
		}
		
		extract($spinner);
		
		$style = array(
			'height' => $size.'px',
			'width' => $size.'px',
			'font-size' => $size.'px',
			'height' => $size.'px',
			'position' => 'absolute',
			'top' => $top,
			'left' => $left,
			'margin-top' => '-'.($size/2).'px',
			'margin-left' => '-'.($size/2).'px',
		);
		
		$return = '.x-mask.splash .x-loading-spinner{';
		foreach ($style as $property => $value){
			$return.="$property:$value;";
		}
		$return.= '}';
		$return.= '.x-mask.splash .x-mask-inner{height:100%;}';
		
		if (!empty($color)){
			$selectors = array(
				'top' => '0.99',
				'top::after' => '0.9',
				'left::before' => '0.8',
				'left' => '0.7',
				'left::after' => '0.6',
				'bottom::before' => '0.5',
				'bottom' => '0.4',
				'bottom::after' => '0.35',
				'right::before' => '0.3',
				'right' => '0.25',
				'right::after' => '0.2',
				'top::before' => '0.15'
			);
			
			foreach ($selectors as $selector => $opacity){
				$return.= '.x-mask.splash .x-loading-spinner > span.x-loading-'.$selector.'{background-color:rgba('.$color.','.$opacity.');}'."\n";
			}
		}
		return $return;
	}
	
	function getAppImages(){
		// Here's where the magic happens for startup images and icons
		$icon_sizes = array(57 => 'Icon.png',72 => 'Icon~ipad.png',114 => 'Icon@2x.png',144 => 'Icon~ipad@2x.png');
		$startup_sizes = array('320x460','640x920','768x1004','748x1024','1536x2008','1496x2048');

		$attachment_types = array();
		foreach ($icon_sizes as $size => $default){
			$attachment_types[] = 'icon'.$size;
		}
		foreach ($startup_sizes as $size){
			$attachment_types[] = 'startup'.$size;
		}
		$p = $this->get('post');
		$attachments = get_children( array( 'post_parent' => $p->ID, 'post_type' => 'attachment', 'orderby' => 'menu_order ASC, ID', 'order' => 'DESC') );

		$found = array();
		$defaults = array();

		foreach ($attachments as $attachment){
			if (in_array($attachment->post_title,$attachment_types)){
				$found[$attachment->post_title] = $attachment->ID;
			}
			elseif(in_array($attachment->post_title,array('splash','icon','startup'))){
				$key = ($attachment->post_title == 'splash' ? 'startup' : $attachment->post_title);
				if (isset($defaults[$key])){
					// We only will pay attention to the first one we've found
					continue;
				}
				$defaults[$key] = array(
					'id' => $attachment->ID,
					'url' => wp_get_attachment_url($attachment->ID),
					'file' => get_attached_file($attachment->ID),
					'app_images' => get_post_meta($attachment->ID,'app_made_images',true)
				);
				$defaults[$key]['pathinfo'] = pathinfo($defaults[$key]['file']);
				if (!is_array($defaults[$key]['app_images'])){
					$defaults[$key]['app_images'] = array();
				}
			}
		}

		$images = array();
		foreach (array('icon','startup') as $type){
			$images[$type] = array();
			$looper = "{$type}_sizes";
			$update_meta = false;
			foreach ($$looper as $size => $default){
				if ($type == 'startup') $size = $default; // normalizing to the way I have the arrays setup above
				
				$key = $type.$size;
				
				if (!empty($found[$key])){
					// There's a specific image attached to the app for this size.  Use that
					$images[$type][$size] = wp_get_attachment_url($found[$key]);
				}
				elseif(isset($defaults[$type])){
					// There's a default one that we can use.
					$url = $defaults[$type]['url'];
					$file = $defaults[$type]['file'];
					$info = $defaults[$type]['pathinfo'];
					$dir = $info['dirname'];
					$ext = $info['extension'];
					$name = wp_basename($file, ".$ext");
					$suffix = ($type == 'icon' ? "{$size}x{$size}" : $size);
					$target_name = "{$name}-{$suffix}.{$ext}";

					$target_filename = "{$dir}/{$target_name}";
					if (file_exists($target_filename)){
						// An appropriate image exists already
						$images[$type][$size] = preg_replace('/[^\/]+$/',$target_name,$url);
					}
					else{
						// Need to make one
						if ($type == 'icon'){
							$made = image_resize($file,$size,$size,true);
						}
						else{
							list($width,$height) = explode('x',$size);
							$made = image_resize($file,$width,$height,false,$suffix);
						}
						if (is_wp_error($made)){
							// WP won't enlarge an image, so if the size is bigger than we asked for, we'll just use what we've got
							$made = $file;
						}
						$update_meta = true;
						$defaults[$type]['app_images'][] = $dir.'/'.wp_basename( $made ); // Adding this in there so if an attachment ever gets deleted, I can delete the images I made as well.
						$images[$type][$size] = preg_replace('/[^\/]+$/',wp_basename( $made ),$url);
					}
				}
				else{
					// just fall back to the Sencha defaults
					if ($type == 'icon'){
						$images[$type][$size] = 'resources/icons/'.$default;
					}
					else{
						$images[$type][$size] = 'resources/startup/'.$size.'.jpg';
					}
				}
			}
			if ($update_meta){
				// Adding this in there so if an attachment ever gets deleted, I can delete the images I made as well.
				update_post_meta($defaults[$type]['id'],'app_made_images',$defaults[$type]['app_images']);
			}
		}
		
		return array($images['icon'],$images['startup']);
	}
	
	public function sanitize_json( $json = null ){
		if ($json){
			$json = json_decode( $json );
		}
		if (!$json){
			$json = new stdClass;
		}

		if (!is_array($json->js)){
			$json->js = array();
		}
		if (!is_array($json->css)){
			$json->css = array();
		}
		return $json;
	}

	public static function visibility_metabox( $app ){
		$app_meta = the_app_get_app_meta( $app->ID );

		wp_nonce_field( plugin_basename( __FILE__ ), 'app_meta_nonce' );
		
		$versions = self::get_available_versions();

		if (count($versions) > 1) : 
			$app_meta = the_app_get_app_meta( $app->ID ); 
			?>
			<p><?php _e('There is more than one version this app built.  Please set your choices below:','app-factory'); ?></p>
			<?php foreach ( array_keys($app_meta['visibility']) as $type ) : ?>
				<div>
					<label><?php printf ( __('%s users see: ', 'app-factory' ), ucwords( $type ) ); ?></label>
					<select name="app_meta[visibility][<?php echo $type; ?>]">
					<?php foreach ($versions as $version => $label) : ?>
						<option value="<?php echo $version; ?>" <?php selected( $version, $app_meta['visibility'][$type] ); ?>><?php echo $label; ?></option>
					<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; 
		else : ?>
			<p><?php _e('You only have the development version available.  Build or package your app.','app-factory'); ?></p>
		<?php endif; 
	}	
	
	public function get_available_versions(){
		$the_app = & TheAppFactory::getInstance(); 
		TheAppBuilder::setup_environment();
		TheAppPackager::setup_environment();
		
		$available_versions = array(
			'development' => __('Development','app-factory')
		);
		if (file_exists($the_app->get('production_root').'index.html')){
			$available_versions['production'] = __('Production (Web App)');
		}
		
		$targets = TheAppPackager::get_available_targets();
		
		foreach ($targets as $target => $label){
			if ( file_exists($the_app->get("package_native_www_$target").'index.html') ){
				switch($target){
				case 'ios':
					$extra = __('iTunes Candidate','app-factory');
					break;
				case 'android':
					$extra = __('Marketplace Candidate','app-factory');
					break;
				case 'pb':
					$extra = __('Upload to Phonegap Build');
					break;
				}
				$available_versions["native_$target"] = sprintf('Native %s (%s)',$label,$extra);
			}
		}
		return $available_versions;		
	}
	
	public function maybeSetupSheetMenuItems(){
		if ( 'sheet' == $this->get( 'menu_style' ) ){
			$menu_list = array(
				'xtype' => 'list',
				'store' => array(
					'data' => array(),
					'model' => 'the_app.model.SheetMenuItems'
				)
			);
			
			$items = $this->get( 'items' );
			foreach ( $items as & $item ){
				if ( !isset( $item['item']['id'] ) ){
					$item['item']['id'] = 'an-id-for-' . sanitize_title( $item['item']['title'] );
				}
				$menu_list['store']['data'][] = array(
					'id' => $item['item']['id'],
					'text' => $item['item']['title'],
					'iconCls' => $item['item']['iconCls']
				);
			}
			unset( $item ); // unsetting a reference just removes the reference
			$this->set( 'items', $items );
			
			$this->set( 'sheet_menu_items', apply_filters( 'the_app_sheet_menu_items', array( $menu_list ) ) );
		}
	}
}
?>