<?php
/*
Plugin Name: The App Push Woosh Notifications
*/

class TheAppPushWoosh{
	public function __construct(){
		add_action( 'TheAppFactory_init', array( &$this, 'init' ) );
	}
	
	public function init( &$the_app ){
		add_shortcode( 'app_push_woosh', array( &$this, 'shortcodes' ) );
		
		$the_app->register( 'controller', 'PushWooshController', dirname(__FILE__) . '/the-app/src/controller/PushWooshController.js' );
	}
	
	public function shortcodes( $atts = array(), $content = null, $code = '' ){
		$the_app = & TheAppFactory::getInstance();
		
		$defaults = array(
			'application_code' => '',			// Generated with PushWoosh.com
			'google_project_number' => '', 			// Android only
		);
		$the_app->set( 'pushwoosh_atts', shortcode_atts( $defaults, $atts ) );		
		$the_app->enqueue( 'controller', 'PushWooshController' );
		
		add_action( 'TheAppFactory_setupHelpers', array( &$this, 'helper' ) );
		add_action( 'the_app_package_cordova', array( &$this, 'package_cordova' ) );
		add_action( 'the_app_config_xml', array( &$this, 'config_xml' ), 10, 2 );
	}
	
	public function helper( &$the_app ){
		$helpers = $the_app->get( 'helpers' );
		$helpers['PUSHWOOSH'] = $the_app->get( 'pushwoosh_atts' );
		$the_app->set( 'helpers', $helpers );
		$the_app->enqueue( 'require', 'the_app.helper.PUSHWOOSH');
	}
	
	public function package_cordova( &$the_app ){
		$target = $the_app->get('package_target');
		
		switch( $target ){
		case 'android':
			$this->adjustAndroidManifest();
			$this->copyAndroidSourceFiles();
			break;
		case 'ios':
			$this->copyIOSSourceFiles();
			break;
		}

		$this->adjustCordovaPlugins();
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
		//$manifest->registerXPathNamespace( 'android', implode('',$manifest->getNamespaces()) );
		
		// Add in the intent filter to the activity node
		$node = $manifest->xpath( 'application/activity[@android:name="'.$name.'"]' );
		if ( $node ){
			$node = $node[0];
			/* adding in:
	            <intent-filter>
	                <action android:name="com.example.MyApp.MESSAGE" />
	                <category android:name="android.intent.category.DEFAULT" />
	            </intent-filter>
			*/
			$intent = $node->addChild( 'intent-filter' );
			$action = $intent->addChild( 'action' );
			$action->addAttribute( 'android:name', $app_identifier.'.MESSAGE', $ns );
			$category = $intent->addChild( 'category' );
			$category->addAttribute( 'android:name', 'android.intent.category.DEFAULT', $ns );
		}
		
		// 
		$node = $manifest->xpath( 'application[@android:label="@string/app_name"]' );
		if ( $node ){
			$node = $node[0];
			/* adding in:
		        <activity android:name="com.arellomobile.android.push.PushWebview" />
		        <activity android:name="com.arellomobile.android.push.MessageActivity" />
		        <activity android:name="com.arellomobile.android.push.PushHandlerActivity" />
		        <activity android:label="@string/app_name" android:name="com.facebook.LoginActivity" />
		        <receiver android:name="com.google.android.gcm.GCMBroadcastReceiver" android:permission="com.google.android.c2dm.permission.SEND">
		            <intent-filter>
		                <action android:name="com.google.android.c2dm.intent.RECEIVE" />
		                <action android:name="com.google.android.c2dm.intent.REGISTRATION" />
		                <category android:name="com.example.MyApp" />
		            </intent-filter>
		        </receiver>
		        <service android:name="com.arellomobile.android.push.PushGCMIntentService" />
		        <service android:name="com.arellomobile.android.push.GeoLocationService" />
		        <receiver android:name="com.arellomobile.android.push.AlarmReceiver" />
			*/
			$child = $node->addChild( 'activity' );
			$child->addAttribute( 'android:name', 'com.arellomobile.android.push.PushWebview', $ns );
			$child = $node->addChild( 'activity' );
			$child->addAttribute( 'android:name', 'com.arellomobile.android.push.MessageActivity', $ns );
			$child = $node->addChild( 'activity' );
			$child->addAttribute( 'android:name', 'com.arellomobile.android.push.PushHandlerActivity', $ns );
			$child = $node->addChild( 'activity' );
			$child->addAttribute( 'android:label', '@string/app_name', $ns );
			$child->addAttribute( 'android:name', 'com.facebook.LoginActivity', $ns );
			$child = $node->addChild( 'receiver' );
			$child->addAttribute( 'android:name', 'com.google.android.gcm.GCMBroadcastReceiver', $ns );
			$child->addAttribute( 'android:permission', 'com.google.android.c2dm.permission.SEND', $ns );
			$intent = $child->addChild( 'intent-filter' );
			$action = $intent->addChild( 'action' );
			$action->addAttribute( 'android:name', 'com.google.android.c2dm.intent.RECEIVE', $ns );
			$action = $intent->addChild( 'action' );
			$action->addAttribute( 'android:name', 'com.google.android.c2dm.intent.REGISTRATION', $ns );
			$action = $intent->addChild( 'category' );
			$action->addAttribute( 'android:name', $app_identifier, $ns );
			$child = $node->addChild( 'service' );
			$child->addAttribute( 'android:name', 'com.arellomobile.android.push.PushGCMIntentService', $ns );
			$child = $node->addChild( 'service' );
			$child->addAttribute( 'android:name', 'com.arellomobile.android.push.GeoLocationService', $ns );
			$child = $node->addChild( 'receiver' );
			$child->addAttribute( 'android:name', 'com.arellomobile.android.push.AlarmReceiver', $ns );
		}
		
		/* addding in: 
		    <uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
		    <uses-permission android:name="android.permission.READ_PHONE_STATE" />
		    <uses-permission android:name="android.permission.GET_ACCOUNTS" />
		    <uses-permission android:name="android.permission.WAKE_LOCK" />
		    <permission android:name="com.example.MyApp.permission.C2D_MESSAGE" android:protectionLevel="signature" />
		    <uses-permission android:name="com.example.MyApp.permission.C2D_MESSAGE" />
		    <uses-permission android:name="com.google.android.c2dm.permission.RECEIVE" />
		*/
		$child = $manifest->addChild( 'uses-permission' );
		$child->addAttribute( 'android:name', 'android.permission.ACCESS_NETWORK_STATE', $ns );
		$child = $manifest->addChild( 'uses-permission' );
		$child->addAttribute( 'android:name', 'android.permission.READ_PHONE_STATE', $ns );
		$child = $manifest->addChild( 'uses-permission' );
		$child->addAttribute( 'android:name', 'android.permission.GET_ACCOUNTS', $ns );
		$child = $manifest->addChild( 'uses-permission' );
		$child->addAttribute( 'android:name', 'android.permission.WAKE_LOCK', $ns );
		$child = $manifest->addChild( 'permission' );
		$child->addAttribute( 'android:name', $app_identifier.'.permission.C2D_MESSAGE', $ns );
		$child->addAttribute( 'android:protectionLevel', 'signature', $ns );
		$child = $manifest->addChild( 'uses-permission' );
		$child->addAttribute( 'android:name', $app_identifier.'.permission.C2D_MESSAGE', $ns );
		$child = $manifest->addChild( 'uses-permission' );
		$child->addAttribute( 'android:name', 'com.google.android.c2dm.permission.RECEIVE', $ns );
		
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
		    <feature name="PushNotification">
		        <param name="android-package" onload="true" value="com.pushwoosh.plugin.pushnotifications.PushNotifications" />
		    </feature>
		*/	
		$the_app = & TheAppFactory::getInstance();
		$feature = $xml->addChild( 'feature' );
		$feature->addAttribute( 'name', 'PushNotification' );
		$param = $feature->addChild( 'param' );
		$param->addAttribute( 'name', $the_app->get( 'package_target' ) . '-package' );
		$param->addAttribute( 'onload', 'true' );
		$param->addAttribute( 'value', ( $the_app->get( 'package_target' ) == 'android' ? 'com.pushwoosh.plugin.pushnotifications.PushNotifications' : 'PushNotification' ) );
	}
	
	public function adjustCordovaPlugins(){
		$the_app = & TheAppFactory::getInstance();
		$source = $the_app->get('package_native_www') . 'cordova_plugins.js';
		
		$in = file_get_contents( $source );
		$in = preg_replace( '/(module.exports.*)];/s', '$1
	,{
        "file": "plugins/com.pushwoosh.plugins.pushwoosh/www/PushNotification.js",
        "id": "com.pushwoosh.plugins.pushwoosh.PushNotification",
        "clobbers": [
            "plugins.pushNotification"
        ]
    }
];', $in);
		$in = preg_replace( '/(module.exports.metadata[^}]*)/s', '$1
	,"com.pushwoosh.plugins.pushwoosh": "3.1.0"
', $in);
		file_put_contents( $source, $in );
	}
}

new TheAppPushWoosh();
