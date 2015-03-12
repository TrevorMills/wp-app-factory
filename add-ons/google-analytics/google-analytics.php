<?php

class AppGoogleAnalytics{
	public function __construct(){
		add_filter('TheAppFactory_init',array( &$this, 'init'));
	}
	
	public function init(& $the_app){
		add_shortcode('google_analytics',array( &$this,'shortcodes'));

		$the_app->register('controller','GoogleAnalytics',dirname(__FILE__).'/the-app/src/controller/GoogleAnalytics.js');
		$the_app->register('helper','GoogleAnalyticsHelper',dirname(__FILE__).'/the-app/src/helper/GoogleAnalyticsHelper.js');
	
	}
	
	public function shortcodes( $atts = array(), $content = null, $code = '' ) {
		switch ( $code ) {
		case 'google_analytics':
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
	
			add_filter('TheAppFactory_helpers', array( &$this, 'helpers' ),10,2);
			add_action( 'the_app_config_xml', array( &$this, 'config_xml' ), 10, 2 );
			add_filter('unsupported_browser_controllers',create_function('$c','$c[] = "GoogleAnalytics"; return $c;'));
			add_filter('unsupported_browser_requires',create_function('$c','$c[] = "the_app.helper.GoogleAnalyticsHelper"; $c[] = "the_app.helper.GoogleAnalyticsConfig"; return $c;'));
			add_action( 'the_app_package_cordova', array( &$this, 'package_cordova' ) );
			$the_app->enqueue('helper','GoogleAnalyticsHelper');
			$the_app->enqueue('controller','GoogleAnalytics');
			$the_app->enqueue('require','the_app.helper.GoogleAnalyticsHelper');
			$the_app->enqueue('require','the_app.helper.GoogleAnalyticsConfig');
			break;
		}
	}
	
	public function helpers( $helpers, $args ) {
		$the_app = & $args[0];
	
		$google_analytics = $the_app->get('google_analytics');
	
		$helpers['GoogleAnalyticsConfig'] = $the_app->get('google_analytics');
		return $helpers;
	}
		
	public function package_cordova( &$the_app ){
		$target = $the_app->get('package_target');
		
		switch( $target ){
		case 'android':
			$this->copyAndroidSourceFiles();
			$this->adjustCordovaPlugins();
			break;
		case 'ios':
			$this->copyIOSSourceFiles();
			$this->adjustCordovaPlugins();
			break;
		case 'pb':
			// Nothing to do for Phonegap Build
			break;
		}

	}
	
	public function copyAndroidSourceFiles(){
		$the_app = & TheAppFactory::getInstance();
		$the_app->build_cp_deep( dirname( __FILE__ ) . '/the-app/platforms/android/src', $the_app->get( 'package_native_root' ) . 'src/', null, true );
		$the_app->build_cp_deep( dirname( __FILE__ ) . '/the-app/platforms/android/assets', $the_app->get( 'package_native_root' ) . 'assets/', null, true );
		$the_app->build_cp_deep( dirname( __FILE__ ) . '/the-app/platforms/android/libs', $the_app->get( 'package_native_root' ) . 'libs/', null, true );
	}
	
	public function copyIOSSourceFiles(){
		$the_app = & TheAppFactory::getInstance();
		$the_app->build_cp_deep( dirname( __FILE__ ) . '/the-app/platforms/ios/MyApp/Plugins', $the_app->get( 'package_native_root' ) . $the_app->get( 'package_name' ) . '/Plugins/', null, true );
		$the_app->build_cp_deep( dirname( __FILE__ ) . '/the-app/platforms/ios/www', $the_app->get( 'package_native_root' ) . 'www/', null, true );
	}
	
	public function adjustCordovaPlugins(){
		$the_app = & TheAppFactory::getInstance();
		$source = $the_app->get('package_native_www') . 'cordova_plugins.js';
		
		$in = file_get_contents( $source );
		$in = preg_replace( '/(module.exports.*)];/s', '$1
	,{
        "file": "plugins/com.danielcwilson.plugins.googleanalytics/www/analytics.js",
        "id": "com.danielcwilson.plugins.googleanalytics.UniversalAnalytics",
        "clobbers": [
            "analytics"
        ]
    }
];', $in);
		$in = preg_replace( '/(module.exports.metadata[^}]*)/s', '$1
		    ,"com.danielcwilson.plugins.googleanalytics": "' . ( $the_app->get( 'target' ) == 'pb' ? '0.7.0' : '0.7.0' ) . '"
		    ,"com.google.playservices": "19.0.0"
', $in);
		file_put_contents( $source, $in );
	}
	
	public function config_xml( & $xml, $path ){
		/* adding in:
		    <feature name="UniversalAnalytics">
		        <param name="ios-package" value="UniversalAnalyticsPlugin" />
		    </feature>
		OR
		    <feature name="UniversalAnalytics">
		        <param name="android-package" value="com.danielcwilson.plugins.analytics.UniversalAnalyticsPlugin" />
		    </feature>
		*/	
		$the_app = & TheAppFactory::getInstance();
		if ( $the_app->get( 'package_target' ) == 'pb' ){
			$plugin = $xml->addChild( 'gap:plugin', null, 'http://phonegap.com/ns/1.0' );
			$plugin->addAttribute( 'name', 'com.danielcwilson.plugins.analytics.UniversalAnalyticsPlugin' );
		}
		else{
			$feature = $xml->addChild( 'feature' );
			$feature->addAttribute( 'name', 'UniversalAnalytics' );
			$param = $feature->addChild( 'param' );
			$param->addAttribute( 'name', $the_app->get( 'package_target' ) . '-package' );
			switch( $the_app->get( 'package_target' ) ){
			case 'ios':
				$param->addAttribute( 'value', 'UniversalAnalyticsPlugin' );
				break;
			case 'android':
				$param->addAttribute( 'value', 'com.danielcwilson.plugins.analytics.UniversalAnalyticsPlugin' );
				break;
			}
		}
	}
	
}

new AppGoogleAnalytics();
