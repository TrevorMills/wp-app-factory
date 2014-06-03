<?php

class PushPluginApi{
	const ENDPOINT_VAR = 'push_endpoint';
	
	public static function getApiKey( $id = null ){
		if ( !isset( $id ) ){
			$id = get_the_ID();
		}
		return md5( $id . 'push-plugin-service' );
	}
	
	public static function getApiSecret( $id = null ){
		if ( !isset( $id ) ){
			$id = get_the_ID();
		}
		return md5( $id . SECURE_AUTH_KEY . 'push-plugin-secret' );
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
	
	public static function getAvailableTargets(){
		$targets = TheAppPackager::get_available_targets();
		unset( $targets['pb'] );
		return $targets;
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
		
		if ( $api_key != self::getApiKey() ){
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
			if ( empty( $secret ) || $secret != self::getApiSecret( get_the_ID() ) ){
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
			case 'ios':
				self::sendMessageIOS( $message );
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
	
	public static function sendMessageIOS( $message ){
		$registration_ids = get_post_meta( get_the_ID(), 'ios_devices' );
		if ( !count( $registration_ids ) ){
			return;
		}
		
		require_once 'ApnsPHP/Autoload.php';

		// Instanciate a new ApnsPHP_Push object
		$push = new ApnsPHP_Push(
			ApnsPHP_Abstract::ENVIRONMENT_SANDBOX,
			'/Users/trevor/Downloads/apns-certificates/com.topquark.PushPluginTester/server_certificates_bundle_sandbox.pem'
		);

		// Set the Root Certificate Autority to verify the Apple remote peer
		$push->setRootCertificationAuthority('/Users/trevor/Downloads/apns-certificates/com.topquark.PushPluginTester/entrust_root_certification_authority.pem');

		// Increase write interval to 100ms (default value is 10ms).
		// This is an example value, the 10ms default value is OK in most cases.
		// To speed up the sending operations, use Zero as parameter but
		// some messages may be lost.
		// $push->setWriteInterval(100 * 1000);

		// Connect to the Apple Push Notification Service
		$push->connect();

		foreach ( $registration_ids as $id ){
			// Instantiate a new Message with a single recipient
			$msg = new ApnsPHP_Message( $id );

			// Set text to our message
			$msg->setText( $message );
			$msg->setSound();
			$msg->setBadge( 300 );

			// Add the message to the message queue
			$push->add( $msg );
		}

		// Send all messages in the message queue
		$push->send();

		// Disconnect from the Apple Push Notification Service
		$push->disconnect();

		// Examine the error message container
		$aErrorQueue = $push->getErrors();
		if (!empty($aErrorQueue)) {
			var_dump($aErrorQueue);
		}
	}
	
}

add_filter('option_rewrite_rules','PushPluginApi::rewrite_rules');
add_filter('query_vars','PushPluginApi::query_vars' );
add_action('the_app_factory_redirect', 'PushPluginApi::maybe_invoke_api' );
