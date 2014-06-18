<?php
/*
Plugin Name: The App Push Plugin
*/

class TheAppPushPlugin{
	public function __construct(){
		add_action( 'TheAppFactory_init', array( &$this, 'init' ) );
		add_filter( 'upload_mimes', array( &$this, 'upload_mimes' ) );
	}
	
	public function init( &$the_app ){
		add_shortcode( 'app_push_plugin', array( &$this, 'shortcodes' ) );
		
		$the_app->register( 'controller', 'PushPluginController', dirname(__FILE__) . '/the-app/src/controller/PushPluginController.js' );
	}
	
	public function shortcodes( $atts = array(), $content = null, $code = '' ){
		$the_app = & TheAppFactory::getInstance();
		
		$the_app->set( 'pushplugin_atts', $this->getPushPluginSettings() );		
		$the_app->enqueue( 'controller', 'PushPluginController' );
		
		add_action( 'TheAppFactory_setupHelpers', array( &$this, 'helper' ) );
		add_action( 'the_app_package_cordova', array( &$this, 'package_cordova' ) );
		add_action( 'the_app_config_xml', array( &$this, 'config_xml' ), 10, 2 );
		add_filter( 'the_app_factory_package_app_json', array( &$this, 'app_json' ) );
	}
	
	public function getPushPluginSettings(){
		$the_app = & TheAppFactory::getInstance( 'TheAppPackager' ); 

		$app_meta = the_app_get_app_meta( $the_app->get('post')->ID );
		
		$pushplugin_atts = $app_meta[ 'pushplugin' ];
		if ( !isset( $pushplugin_atts['pem'] ) ){
			$pushplugin_atts['pem'] = array(
				'sandbox' => '',
				'production' => '',
				'entrust' => ''
			);
		}
		
		$pushplugin_atts[ 'app_api_key' ] = PushPluginApi::getApiKey();
		return $pushplugin_atts;
	}
	
	public function helper( &$the_app ){
		$helpers = $the_app->get( 'helpers' );
		$helpers['PUSHPLUGIN'] = $the_app->get( 'pushplugin_atts' );
		$the_app->set( 'helpers', $helpers );
		$the_app->enqueue( 'require', 'the_app.helper.PUSHPLUGIN');
	}
	
	public function package_cordova( &$the_app ){
		$target = $the_app->get('package_target');
		
		switch( $target ){
		case 'android':
			$this->adjustAndroidManifest();
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
	
	public function adjustAndroidManifest(){
		$the_app = & TheAppFactory::getInstance();
		$name = $the_app->get('package_name');
		$target = $the_app->get('package_target');
		$native_root = $the_app->get('package_native_root');
		$native_www = $the_app->get('package_native_www');
		$app_meta = the_app_get_app_meta( $the_app->get('post')->ID );
		$app_identifier = $app_meta['bundle_id'].'.'.$name;	
		$ns = 'http://schemas.android.com/apk/res/android';
	
		$manifest_file = $native_root . 'AndroidManifest.xml';
		$manifest = simplexml_load_file( $manifest_file );
		
		// 
		$node = $manifest->xpath( 'application[@android:label="@string/app_name"]' );
		if ( $node ){
			$node = $node[0];
			/* adding in:
				<activity android:name="com.plugin.gcm.PushHandlerActivity" />
		        <receiver android:name="com.plugin.gcm.CordovaGCMBroadcastReceiver" android:permission="com.google.android.c2dm.permission.SEND">
		            <intent-filter>
		                <action android:name="com.google.android.c2dm.intent.RECEIVE" />
		                <action android:name="com.google.android.c2dm.intent.REGISTRATION" />
		                <category android:name="com.example.MyApp" />
		            </intent-filter>
		        </receiver>
		        <service android:name="com.plugin.gcm.GCMIntentService" />
			*/
			$child = $node->addChild( 'activity' );
			$child->addAttribute( 'android:name', 'com.plugin.gcm.PushHandlerActivity', $ns );
			$child = $node->addChild( 'receiver' );
			$child->addAttribute( 'android:name', 'com.plugin.gcm.CordovaGCMBroadcastReceiver', $ns );
			$child->addAttribute( 'android:permission', 'com.google.android.c2dm.permission.SEND', $ns );
			$intent = $child->addChild( 'intent-filter' );
			$action = $intent->addChild( 'action' );
			$action->addAttribute( 'android:name', 'com.google.android.c2dm.intent.RECEIVE', $ns );
			$action = $intent->addChild( 'action' );
			$action->addAttribute( 'android:name', 'com.google.android.c2dm.intent.REGISTRATION', $ns );
			$action = $intent->addChild( 'category' );
			$action->addAttribute( 'android:name', $app_identifier, $ns );
			$child = $node->addChild( 'service' );
			$child->addAttribute( 'android:name', 'com.plugin.gcm.GCMIntentService', $ns );
		}
		
		/* addding in: 
		    <uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
		    <uses-permission android:name="android.permission.GET_ACCOUNTS" />
		    <uses-permission android:name="android.permission.WAKE_LOCK" />
		    <uses-permission android:name="android.permission.VIBRATE" />
		    <uses-permission android:name="com.google.android.c2dm.permission.RECEIVE" />
		    <permission android:name="com.example.MyApp.permission.C2D_MESSAGE" android:protectionLevel="signature" />
		    <uses-permission android:name="com.example.MyApp.permission.C2D_MESSAGE" />
		*/
		$child = $manifest->addChild( 'uses-permission' );
		$child->addAttribute( 'android:name', 'android.permission.ACCESS_NETWORK_STATE', $ns );
		$child = $manifest->addChild( 'uses-permission' );
		$child->addAttribute( 'android:name', 'android.permission.GET_ACCOUNTS', $ns );
		$child = $manifest->addChild( 'uses-permission' );
		$child->addAttribute( 'android:name', 'android.permission.WAKE_LOCK', $ns );
		$child = $manifest->addChild( 'uses-permission' );
		$child->addAttribute( 'android:name', 'android.permission.VIBRATE', $ns );
		$child = $manifest->addChild( 'uses-permission' );
		$child->addAttribute( 'android:name', 'com.google.android.c2dm.permission.RECEIVE', $ns );
		$child = $manifest->addChild( 'permission' );
		$child->addAttribute( 'android:name', $app_identifier.'.permission.C2D_MESSAGE', $ns );
		$child->addAttribute( 'android:protectionLevel', 'signature', $ns );
		$child = $manifest->addChild( 'uses-permission' );
		$child->addAttribute( 'android:name', $app_identifier.'.permission.C2D_MESSAGE', $ns );
		
		file_put_contents( $manifest_file, $the_app->prettify_xml( $manifest->asXML() ) );
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
	
	public function config_xml( & $xml, $path ){
		/* adding in:
		    <feature name="PushPlugin">
		        <param name="android-package" value="com.plugin.gcm.PushPlugin" />
		    </feature>
		*/	
		$the_app = & TheAppFactory::getInstance();
		if ( $the_app->get( 'package_target' ) == 'pb' ){
			$plugin = $xml->addChild( 'gap:plugin', null, 'http://phonegap.com/ns/1.0' );
			$plugin->addAttribute( 'name', 'com.phonegap.plugins.pushplugin' );
		}
		else{
			$feature = $xml->addChild( 'feature' );
			$feature->addAttribute( 'name', 'PushPlugin' );
			$param = $feature->addChild( 'param' );
			$param->addAttribute( 'name', $the_app->get( 'package_target' ) . '-package' );
			switch( $the_app->get( 'package_target' ) ){
			case 'ios':
				$param->addAttribute( 'value', 'PushPlugin' );
				break;
			case 'android':
				$param->addAttribute( 'value', 'com.plugin.gcm.PushPlugin' );
				break;
			}
		}
	}
	
	public function adjustCordovaPlugins(){
		$the_app = & TheAppFactory::getInstance();
		$source = $the_app->get('package_native_www') . 'cordova_plugins.js';
		
		$in = file_get_contents( $source );
		$in = preg_replace( '/(module.exports.*)];/s', '$1
	,{
        "file": "plugins/com.phonegap.plugins.PushPlugin/www/PushNotification.js",
        "id": "com.phonegap.plugins.PushPlugin.PushNotification",
        "clobbers": [
            "pushNotification"
        ]
    }
];', $in);
		$in = preg_replace( '/(module.exports.metadata[^}]*)/s', '$1
	,"com.phonegap.plugins.PushPlugin": "' . ( $the_app->get( 'target' ) == 'pb' ? '2.1.1' : '2.2.0' ) . '"
', $in);
		file_put_contents( $source, $in );
	}
	
	public function app_json( $json ){
		$the_app = & TheAppFactory::getInstance();
		if ( $the_app->get( 'package_target' ) == 'pb' ){
			$app_js = array_pop( $json->js );
			array_push( $json->js, (object)array(
				'path' => 'PushNotification.js',
				'remote' => true,
				'update' => 'full'
			));
			array_push( $json->js, $app_js );
		}
		return $json;
	}
	
	public function upload_mimes( $mimes ){
		$mimes[ 'pem' ] = 'application/x-pem-file';
		return $mimes;
	}

}

new TheAppPushPlugin();

include_once( 'class.PushPluginApi.php' );
include_once( 'class.PushPluginAdmin.php' );
