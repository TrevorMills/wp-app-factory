<?php
/*
Plugin Name: The App Push Plugin
*/

class TheAppPushPlugin{
	const ENDPOINT_VAR = 'push_endpoint';
	
	public function __construct(){
		add_action( 'TheAppFactory_init', array( &$this, 'init' ) );
	}
	
	public function init( &$the_app ){
		add_shortcode( 'app_push_plugin', array( &$this, 'shortcodes' ) );
		
		$the_app->register( 'controller', 'PushPluginController', dirname(__FILE__) . '/the-app/src/controller/PushPluginController.js' );
	}
	
	public function shortcodes( $atts = array(), $content = null, $code = '' ){
		$the_app = & TheAppFactory::getInstance();
		
		$defaults = array(
			'google_project_number' => '', 		// Android only
			'google_api_key' => '',				// Android only
			'app_api_key' => self::getAppApiKey()
		);
		$the_app->set( 'pushplugin_atts', shortcode_atts( $defaults, $atts ) );		
		$the_app->enqueue( 'controller', 'PushPluginController' );
		
		add_action( 'TheAppFactory_setupHelpers', array( &$this, 'helper' ) );
		add_action( 'the_app_package_cordova', array( &$this, 'package_cordova' ) );
		add_action( 'the_app_config_xml', array( &$this, 'config_xml' ), 10, 2 );
		add_filter( 'the_app_factory_package_app_json', array( &$this, 'app_json' ) );
		
		$this->maybeAddMetaBox();
	}
	
	public static function getAppApiKey( $id = null ){
		if ( !isset( $id ) ){
			$id = get_the_ID();
		}
		return md5( $id . 'push-plugin-service' );
	}
	
	public static function getAppApiSecret( $id = null ){
		if ( !isset( $id ) ){
			$id = get_the_ID();
		}
		return md5( $id . SECURE_AUTH_KEY . 'push-plugin-secret' );
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
				$param->addAttribute( 'value', 'PushNotification' );
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
	
	public static function maybe_invoke_api( & $the_app ){
		if ( get_query_var( self::ENDPOINT_VAR ) != '' ){
			self::api();
			exit;
		}
	}
	
	public static function api(){
		$the_app = & TheAppFactory::getInstance();
		
		extract( $_POST ); // $api_key, $os, $token
		
		if ( $api_key != self::getAppApiKey() ){
			status_header( 400 );
			exit;
		}
		
		switch( get_query_var( self::ENDPOINT_VAR ) ){
		case 'register':
			if ( !empty( $os ) && !empty( $token ) ){
				delete_post_meta( get_the_ID(), "{$os}_devices", $token ); // delete if exists
				if ( !empty( $previous_token ) ){
					delete_post_meta( get_the_ID(), "{$os}_devices", $previous_token ); // delete if exists
				}
				add_post_meta( get_the_ID(), "{$os}_devices", $token );
			}
			break;
		case 'unregister':
			if ( !empty( $os ) && !empty( $token ) ){
				delete_post_meta( get_the_ID(), "{$os}_devices", $token ); // delete if exists
			}
			break;
		case 'send':
			if ( empty( $secret ) || $secret != self::getAppApiSecret( get_the_ID() ) ){
				status_header( 400 );
			}
			else{
				self::sendMessage( $message, $os );
			}
		}
		exit();
	}
	
	public static function sendMessage( $message, $os = null ){
		if ( !isset( $os ) ){
			$os = array_keys( self::getAvailableTargets() );
		}
		elseif ( is_string( $os ) ){
			$os = explode( ',', $os );
		}
		
		foreach ( $os as $target ){
			switch( $target ){
			case 'android':
				self::sendMessageAndroid( $message );
				break;
			}
		}
	}
	
	public static function sendMessageAndroid( $message ){
		$registration_ids = get_post_meta( get_the_ID(), 'android_devices' );
		if ( !count( $registration_ids ) ){
			return;
		}
		
		$chunks = array_chunk( $registration_ids, 1000 ); // Android only allows up to 1000 devices per API call
		
		$url = 'https://android.googleapis.com/gcm/send';
		
		$the_app = & TheAppFactory::getInstance();
		$pushplugin_atts = $the_app->get( 'pushplugin_atts' );
		foreach ( $chunks as $chunk ){
			$fields = array(
				'registration_ids' => $chunk,
				'data' => array( 
					'title' => get_the_title(),
					'message' => $message
				)
			);
			
	        $headers = array(
	            'Authorization: key=' . $pushplugin_atts['google_api_key'] ,
	            'Content-Type: application/json'
	        );
	        // Open connection
	        $ch = curl_init();
 
	        // Set the url, number of POST vars, POST data
	        curl_setopt($ch, CURLOPT_URL, $url);
 
	        curl_setopt($ch, CURLOPT_POST, true);
	        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 
	        // Disabling SSL Certificate support temporarily
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
 
	        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields) );
 
	        // Execute post
	        $result = curl_exec($ch);
	        if ($result === FALSE) {
	            die('Curl failed: ' . curl_error($ch));
	        }
 
	        // Close connection
	        curl_close($ch);
			echo $result;
		}
	}
	
	public static function getAvailableTargets(){
		$targets = TheAppPackager::get_available_targets();
		unset( $targets['pb'] );
		return $targets;
	}
	
	public static function rewrite_rules($rules){
		$the_app_factory_rules[APP_POST_TYPE.'/([^/]+)/push/([^/]+)/?$'] = 'index.php?'.APP_POST_VAR.'=$matches[1]&'.self::ENDPOINT_VAR.'=$matches[2]'; // the push api endpoint

		$rules = array_merge($the_app_factory_rules,$rules);
		return $rules;
	}
	
	public static function query_vars($query_vars){
		$query_vars[] = self::ENDPOINT_VAR;
		return $query_vars;
	}
	
	public static function maybeAddMetaBox(){
		global $pagenow;
		if ( is_admin() && 'post.php' === $pagenow ){
			// They're editting the post and there is a app_push_plugin shortcode present, display the metabox
			add_meta_box( 'app_push_plugin', __( 'Push Notifications', 'app-factory' ), array( 'TheAppPushPlugin', 'metaBox' ), APP_POST_TYPE, 'normal', 'high');
		}
	}
	
	public function metaBox( $app ){
		$the_app = & TheAppFactory::getInstance();
		
		$registered_devices = array();
		foreach ( self::getAvailableTargets() as $os => $label ){
			$registered_devices[ $label ] = get_post_meta( $app->ID, "{$os}_devices" );
		}
	
		?>
		
		<p class="description"><?php echo __( 'Push notifications are an easy and effective way to communicate with your app users.', 'app-factory' ); ?></p>
		<div class="description">
			<strong><?php echo __( 'Api Key', 'app-factory' ); ?>: </strong><?php echo self::getAppApiKey( $app->ID ); ?><br/>
			<strong><?php echo __( 'Api Secret', 'app-factory' ); ?>: </strong><?php echo self::getAppApiSecret( $app->ID ); ?>
		</div>
		<div class="description">
			<strong><?php echo __( 'Registered Devices', 'app-factory' ); ?></strong>
			<?php foreach ( $registered_devices as $label => $devices ) : ?>
				<?php echo "$label: " . count( $devices ); ?>
			<?php endforeach; ?> 
		</div>
		<div id="push-notification-sender">
			<textarea id="push-message" placeholder="<?php _e( 'Your message', 'app-factory' ); ?>" rows="5" cols="40"></textarea><br/>
			<?php foreach ( self::getAvailableTargets() as $os => $label ) : ?>
				<label><input type="checkbox" class="os" name="push_to_os[]" value="<?php echo $os; ?>" <?php checked( true, true ); ?>> <?php printf( __( 'Send to %s devices', 'app-factory' ), $label ); ?></label><br/>
			<?php endforeach; ?>
			<input id="push-send" type="button" class="button-primary" value="<?php _e( 'Send', 'app-factory' ); ?>" >
		</div>
<script type="text/javascript">
	jQuery( function($){
		$( '#push-send' ).on( 'click', function(){
			var targets = [];
			$( '#push-notification-sender input.os:checked' ).each( function(){
				targets.push( $(this).val() );
			});
			$.post( "<?php echo get_permalink( $app->ID ); ?>push/send/", {
				api_key: "<?php echo self::getAppApiKey( $app->ID ); ?>",
				secret: "<?php echo self::getAppApiSecret( $app->ID ); ?>",
				os: targets.join(','),
				message: $( '#push-message' ).val()
			}, function( response ){
				console.log( response );
			});
		});
	});
</script>
	<?php
	}
}

new TheAppPushPlugin();

add_filter('option_rewrite_rules','TheAppPushPlugin::rewrite_rules');
add_filter('query_vars','TheAppPushPlugin::query_vars' );

add_action('the_app_factory_redirect', 'TheAppPushPlugin::maybe_invoke_api' );
add_action( 'admin_init', 'TheAppPushPlugin::maybeAddMetaBox' );

