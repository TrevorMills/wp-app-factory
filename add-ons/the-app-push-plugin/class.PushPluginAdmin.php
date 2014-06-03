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
		if ( !has_shortcode( $app->post_content, 'app_push_plugin' ) ){
			// No shortcode present, put out instructions 
			?>
			<p class="description"><?php printf( __('To enable Push Notifications for this app, add a shortcode of the following form: %s', 'app-factory'), '<pre>[app_push_plugin google_project_number="your_google_project_number" google_api_key="your_google_api_key"]</pre>' ); ?></p>
			<p class="description"><?php printf( __('You obtain your Google credentials at %s', 'app-factory' ), '<a href="https://code.google.com/apis/console" target="_blank">Google API Console</a>' ); ?></p>
			<?php return;
		}
		
		$the_app = & TheAppFactory::getInstance();
		
		$registered_devices = array();
		foreach ( PushPluginApi::getAvailableTargets() as $os => $label ){
			$registered_devices[ $label ] = get_post_meta( $app->ID, "{$os}_devices" );
		}
	
		?>
		
		<p class="description"><?php echo __( 'Push notifications are an easy and effective way to communicate with your app users.', 'app-factory' ); ?></p>
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
	}
}

add_action( 'admin_init', 'PushPluginAdmin::maybeAddMetaBox' );
