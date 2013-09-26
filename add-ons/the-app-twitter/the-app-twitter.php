<?php
/*
Plugin Name: The App Twitter
*/

add_filter('TheAppFactory_init','TheAppFactoryTwitter_init');
function TheAppFactoryTwitter_init(& $the_app){
	add_shortcode('app_twitter','app_twitter_shortcode');
	add_shortcode('app_twitter_authentication','app_twitter_authentication'); // Provides a way to authenticate for Twitter without including a Twitter page

	$the_app->register('proxy','TwitterProxy',dirname(__FILE__).'/the-app/src/proxy/TwitterProxy.js');
	$the_app->register('view','TweetList',dirname(__FILE__).'/the-app/src/view/TweetList.js');
}

function app_twitter_shortcode($atts = array(),$content=null,$code=''){

	$the_app = & TheAppFactory::getInstance();
	$item_defaults = array(
		'xtype' => 'tweetlist',
		'icon' => 'chat'.($the_app->get('sdk') > '2.1' ? '-pictos' : ''),
		'title' => __('Twitter','app-factory'),
		'search' => '#WordPress',
		'key' => '',
		'secret' => ''
	);
	$meta_defaults = array(
		'_is_default' => 'false',
		'list_template' => ''
	);
	
	$item_atts = shortcode_atts($item_defaults,$atts);
	$meta_atts = shortcode_atts($meta_defaults,$atts);
	

	app_twitter_authentication($item_atts);
	
	// Don't send these to the browser
	unset($items['key']);
	unset($items['secret']);
	
	$the_app->addItem($item_atts,$meta_atts);

	if (!has_filter('TheAppFactory_models','app_twitter_models')){
		add_filter('TheAppFactory_models','app_twitter_models',10,2);
		add_action('the_app_factory_print_stylesheets','app_twitter_print_stylesheets');
		add_action('the_app_factory_print_manifest','app_twitter_print_manifest');
		$the_app->enqueue('proxy','TwitterProxy');
		$the_app->enqueue('view','TweetList');
		$the_app->enqueue('require','the_app.proxy.TwitterProxy');
		
		add_filter('TheAppFactory_app_json','app_twitter_add_stylesheet');
	}
}

function app_twitter_authentication($atts=array()){
	$defaults = array(
		'key' => '',
		'secret' => ''
	);

	$atts = shortcode_atts($defaults,$atts);
	
	if (empty($atts['key']) or empty($atts['secret'])){
		wp_die('When using the [app_twitter] shortcode, you must now include a `key` attribute and a `secret` attribute.  These can be obtained at <a href="https://dev.twitter.com/apps">https://dev.twitter.com/apps</a>. For example, [app_twitter search="WordPress" key="YOUR_KEY_HERE" secret="YOUR_SECRET_HERE"]');
	}
	
	// For Twitter API 1.1, all requests must be authenticated.  We can do the authentication
	// on an application basis, which is done below - obtaining the bearer token.  
	$the_app = & TheAppFactory::getInstance();
	$post = $the_app->get('post');
	$twitter_auth = get_post_meta($post->ID,'twitter_auth',true);
	if (!$twitter_auth or $atts['key'] != $twitter_auth['key'] or $atts['secret'] != $twitter_auth['secret']){
		// Need to generate a new app authentication
		require_once(APP_FACTORY_PATH . 'extras/codebird.php'); // thanks https://github.com/mynetx/codebird-php
		Codebird::setConsumerKey($atts['key'], $atts['secret']); 
		$cb = Codebird::getInstance();
		$reply = $cb->oauth2_token();
		if (empty($reply->access_token)){
			wp_die('Unable to obtain application authentication token.  Please double check your key & secret.');
		}
		$twitter_auth = array(
			'key' => $atts['key'],
			'secret' => $atts['secret'],
			'bearer_token' => $reply->access_token
		);
		update_post_meta($post->ID,'twitter_auth',$twitter_auth);
	}	
	
	// Register a query to the app
	$registered = $the_app->get('registered_post_queries');
	$registered['tweets'] = array();
	$registered['tweets'][] = array(
		'query_vars' => array(
			'xtype' => 'tweet',
			'data_callback' => 'app_twitter_tweets'
		),
		'useLocalStorage' => false
	);
	$the_app->set('registered_post_queries',$registered);
}

function app_twitter_tweets(){
	// Setup atts
	$defaults = array(
		'q' => '',
		'count' => '',
		'include_entities' => 'false',
		'next_results' => ''
	);
	
	$atts = shortcode_atts($defaults,$_GET);
	if (empty($atts['next_results'])){
		unset($atts['next_results']);
	}

	// Twitter Rate Limits to 180 requests per 15 minutes.  
	// To help server performance, I'm going to limit our
	// rate to 1 per minute.  I'll do this using WP transients
	$transient_key = 'tweets_'.md5($atts['q'].$atts['next_results']);
	$result = json_decode(get_transient($transient_key));
	if (empty($result)){
		$the_app = & TheAppFactory::getInstance();
		$post = $the_app->get('post');
		$twitter_auth = get_post_meta($post->ID,'twitter_auth',true);
		require_once(APP_FACTORY_PATH . 'extras/codebird.php'); // thanks https://github.com/mynetx/codebird-php
		Codebird::setConsumerKey($twitter_auth['key'], $twitter_auth['secret']); 
		$cb = Codebird::getInstance();
		$result = $cb->search_tweets( ( isset($atts['next_results']) ? ltrim($atts['next_results'],'?') : http_build_query($atts) ) , true);
		// a little massaging into our model
		$tweets = array();
		foreach ((array)$result->statuses as $id => $tweet){
			$tweet = array(
				'id' => $tweet->id_str, 
				'id_str' => $tweet->id_str, 
				'text' => $tweet->text, 
				'to_user_id' => $tweet->in_reply_to_user_id_str, 
				'to_user' => $tweet->in_reply_to_screen_name, 
				'from_user' => $tweet->user->screen_name, 
				'created_at' => $tweet->created_at, 
				'profile_image_url' => $tweet->user->profile_image_url, 
			);
			$result->statuses[$id] = $tweet;
		}
		
		set_transient($transient_key,json_encode($result),60);
	}
	
	
	header("Content-type: text/javascript");
	ob_start();
	if(isset($_GET['callback'])){
		echo $_GET['callback']."(";
	}
	echo json_encode($result);
	if(isset($_GET['callback'])){
		echo ");";
	}
	exit();
	
}

function app_twitter_get_bearer_token(){
	$the_app = & TheAppFactory::getInstance();
	$post = $the_app->get('post');
	$twitter_auth = get_post_meta($post->ID,'twitter_auth',true);
	return $twitter_auth['bearer_token'];
}

function app_twitter_models($Models,$args){
	$the_app = & $args[0];
	$Models['Tweet'] = array();
	$Models['Tweet']['fields'] = array('id', 'id_str', 'text', 'to_user_id', 'to_user', 'from_user', 'created_at', 'profile_image_url', 'created_ago');
	$Models['Tweet']['proxy'] = $the_app->do_not_escape("Ext.create('the_app.proxy.TwitterProxy')"); 
	
	return $Models;
}

function app_twitter_print_stylesheets(){
}

function app_twitter_print_manifest(){
}

function app_twitter_add_stylesheet($json){
	
	$json['css'][] = array(
        "path" => APP_FACTORY_URL.'add-ons/the-app-twitter/the-app/resources/css/app-twitter.css',
		"remote" => true,
        "update" => "full"
	);
	
	return $json;
	
}

?>