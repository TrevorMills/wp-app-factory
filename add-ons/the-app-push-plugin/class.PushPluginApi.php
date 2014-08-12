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
			$result = self::api();
			echo json_encode( $result );
			exit;
		}
	}
	
	/** 
	 * The api is called with $_POST
	 * 
	 * $_POST must have the following attributes set:
	 * 
	 * 	api_key => The API Key to use for this API request - see self::getApiKey()
	 *  os => A comma separated list of target platforms ( available are currently ios & android )
	 * 
	 *
	 * For the `register` endpoint, you also need
	 * 
	 *  token => the device token
	 *  previous_token => (optional) the device token the device USED to have (used for unregistering)
	 *
	 *
	 * For the `unregister` endpoint, you also need
	 * 
	 *  token => the device token
	 *  
	 *
	 * For the `message` endpoint, you also need
	 * 
	 *  secret => The API Secret for this app ( see self::getApiSecret() )
	 *  ios_environment => (iOS only) - either 'sandbox' or 'production' depending on which environment you are testing
	 *  ios_badge => (optional - iOS only) an integer to set the app badge to
	 *  data => array(
	 * 				'title' => the title for the push (defaults to the app title)
	 * 				'message' => the text for the push
	 * 				'url' => (optional) a URL that will open in InAppBrowser
	 *				'route' => (optional) the route to redirectTo (i.e. #tab/2)
	 *				'foo' => 'bar' (optionally set any other parameter you want)
	 * 			)
	 */
	public static function api(){
		$the_app = & TheAppFactory::getInstance();
		
		$_POST = stripslashes_deep( $_POST );
		extract( stripslashes_deep($_POST) ); // $api_key, $os, $token
		
		$response = array();
		
		if ( $api_key != self::getApiKey() ){
			return array( 
				'status' => 400,
				'message' => 'Invalid API Key'
			);
		}
		
		switch( get_query_var( self::ENDPOINT_VAR ) ){
		case 'register':
			if ( !empty( $os ) && !empty( $token ) ){
				delete_post_meta( get_the_ID(), "{$os}_devices", $token ); // delete if exists
				if ( !empty( $previous_token ) ){
					delete_post_meta( get_the_ID(), "{$os}_devices", $previous_token ); // delete if exists
				}
				add_post_meta( get_the_ID(), "{$os}_devices", $token );
				return array(
					'status' => 200
				);
			}
			else{
				return array(
					'status' => 400,
					'message' => 'Bad request - you must post parms for `os` and `token`'
				);
			}
			break;
		case 'unregister':
			if ( !empty( $os ) && !empty( $token ) ){
				delete_post_meta( get_the_ID(), "{$os}_devices", $token ); // delete if exists
				return array(
					'status' => 200
				);
			}
			else{
				return array(
					'status' => 400,
					'message' => 'Bad request - you must post parms for `os` and `token`'
				);
			}
			break;
		case 'send':
			if ( empty( $secret ) || $secret != self::getApiSecret( get_the_ID() ) ){
				return array( 
					'status' => 400,
					'message' => 'Invalid API Secret'
				);
			}
			else{				
				$default_options = array(
					'ios_environment' => 'sandbox',
					'ios_badge' => '',
					'data' => array()
				);
				return self::sendMessage( $os, wp_parse_args( $_POST, $default_options ));
			}
		default:
			return array(
				'status' => 400,
				'message' => 'Bad request - invalid endpoint'
			);
		}
	}
	
	public static function sendMessage( $os = null, $options = array() ){
		if ( !isset( $os ) ){
			$os = array_keys( self::getAvailableTargets() );
		}
		elseif ( is_string( $os ) ){
			$os = explode( ',', $os );
		}
		
		if ( empty( $options['data']['title'] ) ){
			$options['data']['title'] = get_the_title(); 
		}
		
		$result = array();
		foreach ( $os as $target ){
			switch( $target ){
			case 'android':
				$result[ 'android' ] = self::sendMessageAndroid( $options );
				break;
			case 'ios':
				try{
					$result[ 'ios' ] = self::sendMessageIOS( $options );
				}
				catch( ApnsPHP_Exception $e ){
					$result[ 'ios' ] = array(
						'status' => 400,
						'message' => $e->getMessage(),
						'log' => ob_get_clean()
					);
				}
				break;
			default:
				$result[ $target ] = array(
					'status' => 400,
					'message' => sprintf( 'Invalid target: %s', $target )
				);
				break;
			}
		}
		//print_r( $result );
		//echo "\n\n\n";
		return $result;
	}
	
	private static function isBlank( $data ){
		return empty( $data['message'] ) && empty( $data['url'] );
	}
	
	public static function sendMessageAndroid( $options ){
		$registration_ids = get_post_meta( get_the_ID(), 'android_devices' );
		if ( !count( $registration_ids ) ){
			return array(
				'status' => 200,
				'count' => 0
			);
		}
		
		if ( self::isBlank( $options['data'] ) ){
			return array( 
				'status' => 400,
				'message' => 'No message specified'
			);
		}		
		
		$chunks = array_chunk( $registration_ids, 1000 ); // Android only allows up to 1000 devices per API call
		
		$url = 'https://android.googleapis.com/gcm/send';
		
		$the_app = & TheAppFactory::getInstance();
		$pushplugin_atts = $the_app->get( 'pushplugin_atts' );
		
		$results = array( 
			'status' => 200,
			'count' => 0,
		);
		$obsolete_ids = array();
		foreach ( $chunks as $chunk ){
			$fields = array(
				'registration_ids' => $chunk,
				'data' => $options['data']
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
				return array( 
					'status' => 400,
					'errno' => curl_errno($ch),
					'message' => curl_error($ch)
				);
	        }
			$info = curl_getinfo( $ch );
			
			if ( 200 != $info['http_code'] ){
				if ( 401 == $info['http_code'] ){
					return array(
						'status' => $info['http_code'],
						'message' => 'Invalid Google Cloud Messaging API Key'
					);
				}
				else{
					return array(
						'status' => $info['http_code'],
						'message' => 'Unable to communicate with Google Cloud Messaging'
					);
				}
			}
			else{
				$result = json_decode( $result );
				$results['count'] += $result->success - $result->canonical_ids;
				foreach ( $result->results as $index => $msg ){
					if ( !empty( $msg->registration_id ) ){
						// See https://developer.android.com/google/gcm/adv.html#canonical
						$obsolete_ids[] = $chunk[ $index ];
					}
					if ( !empty( $msg->error ) && 'InvalidRegistration' == $msg->error ){
						$obsolete_ids[] = $chunk[ $index ];
					}
				}
			}
 
	        // Close connection
	        curl_close($ch);
		}
		
		// Clean up - remove any registration_ids that are no longer valid
		if ( !empty( $obsolete_ids ) ){
			foreach ( $obsolete_ids as $id ){
				delete_post_meta( get_the_ID(), 'android_devices', $id );
			}
		}
		
		return $results;
	}
	
	public static function sendMessageIOS( $options ){
		if ( self::isBlank( $options['data'] ) ){
			return array( 
				'status' => 400,
				'message' => 'No message specified'
			);
		}		
		
		require_once 'ApnsPHP/Autoload.php';
		
		$the_app = & TheAppFactory::getInstance();
		$pushplugin_atts = $the_app->get( 'pushplugin_atts' );
		
		$environment = $options['ios_environment'];
		if ( 'sandbox' !== $environment && 'production' !== $environment ){
			return array(
				'status' => 400,
				'message' => 'Invalid or missing ios_environment parameter' 
			);
		}
		if ( empty( $pushplugin_atts['pem'][ $environment ] ) ){
			return array(
				'status' => 400,
				'message' => 'Missing Server Certificate Bundle' 
			);
		}
		if ( false && empty( $pushplugin_atts['pem'][ 'entrust' ] ) ){
			return array(
				'status' => 400,
				'message' => 'Missing Entrust Peer Verification Certificate' 
			);
		}
		$server_certificates_bundle = get_attached_file( $pushplugin_atts['pem'][ $environment ] );
		//$entrust_certification = get_attached_file( $pushplugin_atts['pem']['entrust'] );
		if ( !$server_certificates_bundle ){
			return array(
				'status' => 400,
				'message' => 'Missing Server Certificate Bundle' 
			);
		}
		if ( false && !$entrust_certification ){
			return array(
				'status' => 400,
				'message' => 'Missing Entrust Peer Verification Certificate' 
			);
		}

		$registration_ids = get_post_meta( get_the_ID(), 'ios_devices' );
		if ( !count( $registration_ids ) ){
			return array(
				'status' => 200,
				'count' => 0
			);
		}
		

		// Instanciate a new ApnsPHP_Push object
		$push = new ApnsPHP_Push(
			$environment == 'production' ? ApnsPHP_Abstract::ENVIRONMENT_PRODUCTION : ApnsPHP_Abstract::ENVIRONMENT_SANDBOX,
			$server_certificates_bundle
		);

		// Set the Root Certificate Autority to verify the Apple remote peer
		// $push->setRootCertificationAuthority( $entrust_certification );

		// Increase write interval to 100ms (default value is 10ms).
		// This is an example value, the 10ms default value is OK in most cases.
		// To speed up the sending operations, use Zero as parameter but
		// some messages may be lost.
		// $push->setWriteInterval(100 * 1000);

		// Connect to the Apple Push Notification Service
		ob_start(); // don't want to have the APNSPHP log
		try{
			$push->connect();
		}
		catch( ApnsPHP_Exception $e ){
			return array( 
				'status' => 400,
				'message' => $e->getMessage(),
				'log' => ob_get_clean()
			);
		}

		foreach ( $registration_ids as $id ){
			// Instantiate a new Message with a single recipient
			$msg = new ApnsPHP_Message( $id );

			// Set text to our message
			foreach ( $options['data'] as $key => $value ){
				if ( $key == 'message' ){
					$msg->setText( $value );
				}
				else{
					$msg->setCustomProperty( $key, $value );
				}
			}
			$msg->setSound();
			if ( !empty( $options['ios_badge'] ) && is_numeric( $options['ios_badge'] ) ){
				$msg->setBadge( intval($options['ios_badge']) );
			}

			// Add the message to the message queue
			$push->add( $msg );
		}

		// Send all messages in the message queue
		$push->send();

		// Disconnect from the Apple Push Notification Service
		$push->disconnect();

		// Examine the error message container
		$aErrorQueue = $push->getErrors();
		
		$log = ob_get_clean();
		if (!empty($aErrorQueue)) {
			// Clean up - remove any registration_ids that are no longer valid
			// Only do this in production environment as (I think) it's easier to 
			// setup a sandbox device again than production.
			if ( $environment == 'production' ){
				foreach ( $aErrorQueue as $index => $item ){
					foreach ( $item['ERRORS'] as $e => $error ){
						if ( $error['statusMessage'] == 'Invalid token' && $token = $item['MESSAGE']->getRecipient($e) ){
							delete_post_meta( get_the_ID(), 'ios_devices', $token );
						}
					}
				}
			}
		
			return array(
				'status' => 400,
				'count' => count( $registration_ids ) - count( $aErrorQueue ),
				'error_count' => count( $aErrorQueue )
			);
		}
		else{
			return array(
				'status' => 200,
				'count' => count( $registration_ids )
			);
		}
	}
	
}

add_filter('option_rewrite_rules','PushPluginApi::rewrite_rules');
add_filter('query_vars','PushPluginApi::query_vars' );
add_action('the_app_factory_redirect', 'PushPluginApi::maybe_invoke_api' );
