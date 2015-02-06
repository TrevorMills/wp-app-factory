<?php

add_filter('TheAppFactory_init','GoogleAnalytics_init');
function GoogleAnalytics_init(& $the_app){
	add_shortcode('google_analytics','google_analytics');

	$the_app->register('controller','GoogleAnalytics',dirname(__FILE__).'/the-app/src/controller/GoogleAnalytics.js');
	$the_app->register('helper','GoogleAnalytics',dirname(__FILE__).'/the-app/src/helper/GoogleAnalytics.js');
}

function google_analytics($atts=array()){
	$defaults = array(
		'account' => '',
		'domainName' => '', 	// Set this to 'none' if you're working on a local domain that isn't called "localhost"
		'debug' => false 	// Set to true to console.log() all tracked events.  useful for testing.	
	);
	
	$atts = shortcode_atts($defaults,TheAppFactory::sanitize_atts($atts));
	
	if (empty($atts['account'])){
		wp_die(sprintf(__('In order to use Google Analytics, you must add an `account` attribute with a Google Analytics Account ID (i.e. UA-XXXXXXXX-Y) obtained at %s.  Your shortcode should look like this: %s','app-factory'),'<a href="http://google.com/analytics">google.com/analytics</a>','<br/><pre>[google_analytics account=UA-XXXXXXXX-X]</pre>'));
	}
	
	$the_app = & TheAppFactory::getInstance();
	
	$the_app->set('google_analytics',$atts);
	
	add_filter('TheAppFactory_helpers','google_analytics_config_helper',10,2);
	add_filter('unsupported_browser_controllers',create_function('$c','$c[] = "GoogleAnalytics"; return $c;'));
	add_filter('unsupported_browser_requires',create_function('$c','$c[] = "the_app.helper.GoogleAnalytics"; $c[] = "the_app.helper.GoogleAnalyticsConfig"; return $c;'));
	$the_app->enqueue('helper','GoogleAnalytics');
	$the_app->enqueue('controller','GoogleAnalytics');
	$the_app->enqueue('require','the_app.helper.GoogleAnalytics');
	$the_app->enqueue('require','the_app.helper.GoogleAnalyticsConfig');
}

function google_analytics_config_helper($helpers,$args){
	$the_app = & $args[0];
	
	$google_analytics = $the_app->get('google_analytics');
	
	$helpers['GoogleAnalyticsConfig'] = $the_app->get('google_analytics');
	return $helpers;
}


?>