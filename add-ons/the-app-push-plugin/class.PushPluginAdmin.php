<?php

class PushPluginAdmin{
	public static function maybeAddMetaBox(){
		global $pagenow;
		if ( is_admin() && 'post.php' === $pagenow ){
			// They're editting the post, display the metabox
			add_meta_box( 'app_push_plugin', __( 'Push Notifications', 'app-factory' ), array( 'PushPluginAdmin', 'metaBox' ), APP_POST_TYPE, 'normal', 'high');
		}
	}
	
	public static function metaBox( $app ){
		if ( !preg_match( '/\[app_push_plugin/', $app->post_content ) ){
			// No shortcode present, put out instructions 
			?>
			<p class="description"><?php _e('To enable Push Notifications for this app, simply add this shortcode: [app_push_plugin] and return to this box to complete the setup.', 'app-factory'); ?></p>
			<?php return;
		}
		
		$the_app = & TheAppFactory::getInstance();
		
		$tabs = array();
		
		// Tabs are setup as key = title & value = display callback 
		foreach ( PushPluginApi::getAvailableTargets() as $os => $label ){
			$tabs[sprintf( __( 'Setup %s', 'app-factory' ), $label )] = array( self, 'metabox_tab_setup_' . $os );
		}
		
		$tabs[ __( 'API Settings', 'app-factory' ) ] = array( self, 'metabox_tab_api_settings' );
		$tabs[ __( 'Send a Push', 'app-factory') ] = array( self, 'metabox_tab_send_push' );
		
		$current = current( array_keys( $tabs ) );
		?>

		<p class="description"><?php echo __( 'Push notifications are an easy and effective way to communicate with your app users.', 'app-factory' ); ?></p>

		<h2 id="app-push-nav-tabs" class="nav-tab-wrapper" style="padding:0 0 0 10px;margin-bottom:0">
			<?php foreach ( $tabs as $title => $callback ) : ?>
				<a class="nav-tab<?php echo $current == $title ? ' nav-tab-active' : ''; ?>" href="#<?php echo sanitize_title( $title ); ?>"><?php echo $title; ?></a>
			<?php endforeach; ?>
		</h2>
		
		<div id="app-push-nav-tabs-content" class="nav-tab-wrapper" style="padding:1em;border:1px solid #ccc;border-top:none">
			<?php foreach ( $tabs as $title => $callback ) : ?>
				<div class="nav-tab-content nav-tab-<?php echo sanitize_title( $title ); ?>" style="display:<?php echo $current == $title ? 'block' : 'none' ?>">
					<?php call_user_func( $callback ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		
<script type="text/javascript">		
jQuery(function($){
	$( '#app-push-nav-tabs' ).on( 'click', 'a', function(e){
		e.preventDefault();
		$(this).addClass( 'nav-tab-active' ).siblings( 'a' ).removeClass( 'nav-tab-active' );
		$( '#app-push-nav-tabs-content .nav-tab-content' ).hide();
		$( '#app-push-nav-tabs-content .nav-tab-' + $(this).attr( 'href' ).match( /[^#]+$/ )[0] ).show();
	});
});
</script>
			
		<?php 
		return;
		
		/*
		$registered_devices = array();
		foreach ( PushPluginApi::getAvailableTargets() as $os => $label ){
			$registered_devices[ $label ] = get_post_meta( $app->ID, "{$os}_devices" );
		}
	
		?>
		
		<div class="description">
			<strong><?php echo __( 'Api Key', 'app-factory' ); ?>: </strong><?php echo PushPluginApi::getApiKey( $app->ID ); ?><br/>
			<strong><?php echo __( 'Api Secret', 'app-factory' ); ?>: </strong><?php echo PushPluginApi::getApiSecret( $app->ID ); ?>
		</div>
		<div class="description">
			<strong><?php echo __( 'Registered Devices', 'app-factory' ); ?></strong>
			<?php foreach ( $registered_devices as $label => $devices ) : ?>
				<?php echo "$label: " . count( $devices ); ?>
			<?php endforeach; ?> 
		</div>
		<div id="push-notification-sender">
			<textarea id="push-message" placeholder="<?php _e( 'Your message', 'app-factory' ); ?>" rows="5" cols="40"></textarea><br/>
			<?php foreach ( PushPluginApi::getAvailableTargets() as $os => $label ) : ?>
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
				api_key: "<?php echo PushPluginApi::getApiKey( $app->ID ); ?>",
				secret: "<?php echo PushPluginApi::getApiSecret( $app->ID ); ?>",
				os: targets.join(','),
				message: $( '#push-message' ).val()
			}, function( response ){
				console.log( response );
			});
		});
	});
</script>
	<?php
		*/
	}
	
	public static function metabox_tab_setup_ios(){
		global $post;
		$the_app = & TheAppFactory::getInstance();
		
		$pushplugin_atts = $the_app->get( 'pushplugin_atts' );
		?>
		
		<div class="description"><?php printf( __( 'In order to setup Push Notifications for the Apple Push Notification Service, you must (carefully) follow the instructions at %s to create the necessary .pem files.  Then, use the links below to upload and associate those files to this app.  You need to create three .pem files  %s (used in development environment) %s (used in production apps) %s (used for peer verification) %s  The first two are specific to this app.  For the last one, the same file can be used for multiple apps.', 'app-factory' ), '<a href="https://code.google.com/p/apns-php/wiki/CertificateCreation" target="_blank">https://code.google.com/p/apns-php/wiki/CertificateCreation</a>', '<ol><li>server_certificates_bundle_sandbox.pem', '</li><li>server_certificates_bundle_production.pem', '</li><li>entrust_root_certification_authority.pem', '</li></ol>' )?></div>
			
		<div id="pem-uploaders">
		<?php foreach ( array( 'sandbox' => 'Sandbox .pem', 'production' => 'Production .pem', 'entrust' => 'Entrust .pem' ) as $slug => $file ) : 
			$upload_iframe_src = esc_url( get_upload_iframe_src('image', $post->ID ) );
			$filename = empty( $pushplugin_atts['pem'][$slug] ) ? '' : basename( wp_get_attachment_url( $pushplugin_atts['pem'][$slug] ) );
			?>
			<div style="display:inline-block;padding:1em;border:1px solid #ccc;vertical-align:top;width:200px;text-align:center;margin-right:10px">
				<div class="attachment selected" style="margin:auto;width:120px;float:none;display:<?php echo empty( $pushplugin_atts['pem'][$slug] ) ? 'none' : 'block'; ?>">
					<div class="attachment-preview" style="margin:auto;width:120px;height:120px;text-align:center">
						<img src="<?php bloginfo( 'wpurl' ); ?>/wp-includes/images/media/default.png" class="icon" draggable="false">
						<div class="filename">
							<div id="<?php echo $slug; ?>-pem-filename" style="word-wrap:break-word;"><?php echo $filename; ?></div>
						</div>
						<a class="check" id="remove-<?php echo $slug; ?>" href="#" title="<?php _e( 'Remove', 'app-factory' ); ?>"><div class="media-modal-icon"></div></a>
					</div>
				</div>
				<a href="<?php echo esc_url($upload_iframe_src); ?>" class="set-pem" data-uploader_title="<?php echo esc_attr( sprintf( __( 'Set %s file', 'app-factory'), $file ) ); ?>" id="set-<?php echo $slug; ?>-pem" class="thickbox"><?php printf( __( 'Set %s file', 'app-factory'), $file ); ?></a>
				<input type="hidden" id="<?php echo $slug; ?>-pem-value" name="app_meta[pushplugin][pem][<?php echo $slug; ?>]" value="<?php echo esc_attr( $pushplugin_atts['pem'][$slug] ); ?>" >
			</div>
		<?php endforeach; ?>
		</div>
		<script type="text/javascript">
		jQuery( function($){
			var file_frame;
			$( '#pem-uploaders' ).on( 'click', '.set-pem', function(e){
				e.preventDefault();
 			    var slug = $(this).attr( 'id' ).match( /^set-(.+)-pem$/ )[1];

			    // Create the media frame.
			    file_frame = wp.media.frames.file_frame = wp.media({
			      title: $( this ).data( 'uploader_title' ),
			      button: {
			        text: $( this ).data( 'uploader_title' ),
			      },
			      multiple: false  // Set to true to allow multiple files to be selected
			    });
 
			    // When an image is selected, run a callback.
			    file_frame.on( 'select', function() {
			      // We set multiple to false so only get one image from the uploader
			      attachment = file_frame.state().get('selection').first().toJSON();
 
			      // Do something with attachment.id and/or attachment.url here
			      // write the selected image url to the value of the #cupp_meta text field
				  $( '#' + slug + '-pem-value' ).val( attachment.id );
				  $( '#' + slug + '-pem-filename' ).text( attachment.filename );
				  $( '#' + slug + '-pem-filename' ).closest( '.attachment' ).show();
			    });
 
			    // Finally, open the modal
			    file_frame.open();
				
			}).on( 'click', '.check', function(e){
				e.preventDefault();
				$(this).closest( '.attachment' ).hide();
				$( '#' + $(this).attr( 'id' ).match( /remove-(.+)$/ )[1] + '-pem-value' ).val( '' );
			});
		});
		</script>
		<?php
	}

	public static function metabox_tab_setup_android(){
		$the_app = & TheAppFactory::getInstance();

		$pushplugin_atts = $the_app->get( 'pushplugin_atts' );
		?>
		<p class="description"><?php printf( __( 'In order to setup Push Notifications for Android Devices, you must enter your Google Cloud Messaging Credentials below ', 'app-factory' ) )?></p>
		<p class="description"><?php printf( __('You obtain your Google Cloud Messaging Credentials at %s.  (Follow the instructions at %s)', 'app-factory' ), '<a href="https://code.google.com/apis/console" target="_blank">Google API Console</a>', '<a href="http://developer.android.com/google/gcm/gs.html" target="_blank">http://developer.android.com/google/gcm/gs.html</a>' ); ?></p>
		
		<label><?php _e( 'Google Api Key', 'app-factory' ); ?>:</label> <input type="text" name="app_meta[pushplugin][google_api_key]" value="<?php echo esc_attr( $pushplugin_atts[ 'google_api_key' ] ); ?>"><br/>
		<label><?php _e( 'Google Project Number', 'app-factory' ); ?>:</label> <input type="text" name="app_meta[pushplugin][google_project_number]" value="<?php echo esc_attr( $pushplugin_atts[ 'google_project_number' ] ); ?>"><br/>
		
		<?php
	}
	
	public static function metabox_tab_api_settings(){
		$the_app = & TheAppFactory::getInstance();
		$post = $the_app->get( 'post' );
		?>
		<p class="description"><?php printf( __('You can send push notifications from outside of this system by using the Push Plugin API.  To access it, you must POST to the following URL: %sPassing along the following POST variables:' ), '<pre>' . get_permalink( $post->ID ) . 'push/send/</pre>' ); ?></p>
		
		<div style="overflow:scroll">
			<pre>
{
	api_key: "<?php echo PushPluginApi::getApiKey( $post->ID ); ?>",
	secret: "<?php echo PushPluginApi::getApiSecret( $post->ID ); ?>",
	os: "<?php echo implode( ',', array_keys( PushPluginApi::getAvailableTargets() ) ); ?>",	// comma separated list of targets (available targets are <?php echo implode( ' &amp; ', array_keys( PushPluginApi::getAvailableTargets() ) ); ?>)
	ios_environment: "sandbox", // or "production"
	ios_badge: "", // an integer to set the badge text to
	data: {
		message: <?php _e( 'The Message you want to push.  Example: "Hello World"', 'app-factory' ); ?>,
		title: <?php _e( 'The Title for the Push (defaults to the name of the app).  Example: "Check it out!"', 'app-factory' ); ?>,
		url: <?php _e( '(optional) A URL to open as part of the notification.  Useful for creating splash ads.  Example: "http://mydomain.com/buystuff.html"', 'app-factory' ); ?>,
		route: <?php _e( '(optional) The route to redirectTo upon notification.  This is an advanced and somewhat unstable feature.  Example: "#tab/2"', 'app-factory' ); ?>,
		foo: "bar", <?php _e( 'You can additionally specify any other attributes you want to pass along.'); ?>
		
	},
}
			</pre>
		</div>
		
		<p class="description"><?php _e( 'cURL example:', 'app-factory'); ?></p>
		<div style="overflow:scroll">
			<pre style="word-wrap:break-word">
curl --data "api_key=<?php echo PushPluginApi::getApiKey( $post->ID ); ?>&secret=<?php echo PushPluginApi::getApiSecret( $post->ID ); ?>&os=ios&data[message]=Hello%20World&data[route]=%23tab%2F2&ios_environment=sandbox&ios_badge=1" <?php echo get_permalink( $post->ID ); ?>push/send/
			</pre>
		</div>
		<?php
	}
	
	public static function metabox_tab_send_push(){
		$the_app = & TheAppFactory::getInstance();
		$app = $the_app->get( 'post' );

		$registered_devices = array();
		foreach ( PushPluginApi::getAvailableTargets() as $os => $label ){
			$registered_devices[ $label ] = get_post_meta( $app->ID, "{$os}_devices" );
			if ( !is_array( $registered_devices[ $label ] ) ){
				$registered_devices[ $label ] = array();
			}
		}
		
		$localization = array(
			'general_error_msg' => __( 'Error sending push notifications.  Please review messages in console.', 'app-factory' ),
			'sending' => __( 'Sending', 'app-factory' ),
			'os' => PushPluginApi::getAvailableTargets(),
			'success_msg' => __( 'Successfully sent to %1 %2 devices. ', 'app-factory' ),
			'error_msg' => __( 'Error sending to %1 devices.  %3 (%2). ', 'app-factory' )
		);
		
	
		?>
		<p class="description">
			<strong><?php echo __( 'Registered Devices', 'app-factory' ); ?></strong>
			<?php foreach ( $registered_devices as $label => $devices ) : ?>
				<?php echo "$label: " . count( $devices ); ?>
			<?php endforeach; ?> 
		</p>
		<div id="push-notification-sender">
			<label><?php _e( 'Title', 'app-factory' ); ?>:</label> <input type="text" value="<?php echo esc_attr( $app->post_title ); ?>" id="push-title" /><br/>
			<textarea id="push-message" placeholder="<?php _e( 'Your message', 'app-factory' ); ?>" rows="5" cols="40"></textarea><br/>
			<p class="description">
				<?php _e( 'A URL entered here will be opened in the In App Browser (optional)', 'app-factory' ); ?><br/>
				<input type="text" value="" id="push-url" placeholder="http://"/>
			</p>
			<p class="description">
				<?php _e( '(Advanced) A Route entered here will route the app to a particular page.  To determine the route, navigate around the app in Chrome to the page you want and then copy everything from the # sign', 'app-factory' ); ?><br/>
				<input type="text" value="" id="push-route" placeholder="i.e. #tab/2"/>
			</p>
			<?php foreach ( PushPluginApi::getAvailableTargets() as $os => $label ) : ?>
				<label><input type="checkbox" class="os" name="push_to_os[]" value="<?php echo $os; ?>" <?php checked( true, true ); ?>> <?php printf( __( 'Send to %s devices', 'app-factory' ), $label ); ?></label><br/>
				<?php if ( 'ios' === $os ) : ?>
					<div id="extra-ios-settings" style="padding:0 1em">
						<label><input type="radio" name="ios_environment" <?php checked( true, true ); ?> value="sandbox"> <?php _e( 'Use Sandbox Environment', 'app-factory' ); ?></label><br/>
						<label><input type="radio" name="ios_environment" value="production"> <?php _e( 'Use Production Environment', 'app-factory' ); ?></label><br/>
						<label><?php _e( 'Badge Number', 'app-factory' ); ?>: <input type="number" minimum="0" style="width:50px" name="ios_badge_number" value="" ><br/>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
			<input id="push-send" type="button" class="button-primary" value="<?php _e( 'Send', 'app-factory' ); ?>" >
		</div>
<script type="text/javascript">
	jQuery( function($){
		var PUSHPLUGIN = <?php echo json_encode( $localization ); ?>;
		$( '#push-send' ).on( 'click', function(){
			var me = $(this);
			if ( me.is( ':disabled' ) ){
				return;
			}
			var targets = [];
			$( '#push-notification-sender input.os:checked' ).each( function(){
				targets.push( $(this).val() );
			});
			
			me.data( 'original-text', me.val() ).val( PUSHPLUGIN.sending ).prop( 'disabled', 'true' );
			me.next( '.message' ).remove();
			$.post( "<?php echo get_permalink( $app->ID ); ?>push/send/", {
				api_key: "<?php echo PushPluginApi::getApiKey( $app->ID ); ?>",
				secret: "<?php echo PushPluginApi::getApiSecret( $app->ID ); ?>",
				os: targets.join(','),
				ios_environment: $( '#push-notification-sender input[name="ios_environment"]:checked' ).val(),
				ios_badge: $( '#push-notification-sender input[name="ios_badge_number"]' ).val(),
				data: {
					message: $( '#push-message' ).val(),
					title: $( '#push-title' ).val(),
					url: $( '#push-url' ).val(),
					route: $( '#push-route').val()
				}
			}, function( response ){
				me.val( me.data( 'original-text' ) ).removeAttr( 'disabled' );
				me.after( '<span class="message"> </span>' );
				var message = me.next( '.message' );
				try{
					var result = $.parseJSON( response );
					$.each( targets, function( index, target){
						if ( result[ target ].status == 200 ){
							message.append( PUSHPLUGIN.success_msg.replace( /%1/, result[ target ].count ).replace( /%2/, PUSHPLUGIN.os[ target ] ) );
						}
						else{
							message.append( PUSHPLUGIN.error_msg.replace( /%1/, PUSHPLUGIN.os[ target ] ).replace( /%2/, result[ target ].status ).replace( /%3/, result[ target ].message ) );
						}
					});
				}
				catch(e){
					console.log( e );
					console.log( response );
					message.append( PUSHPLUGIN.general_error_msg );
				}
			});
		});
		$( '#push-notification-sender' ).on( 'change', '.os[value="ios"]', function(){
			if ( $(this).is( ':checked' ) ){
				$( '#extra-ios-settings' ).show();
			}
			else{
				$( '#extra-ios-settings' ).hide();
			}
		});
	});
</script>
		
		<?php
	}
	
}

add_action( 'admin_init', 'PushPluginAdmin::maybeAddMetaBox' );
