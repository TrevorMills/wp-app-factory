Ext.define('the_app.controller.PushPluginController', {
    extend: 'Ext.app.Controller',
    
	requires: [],
	
    init: function(){
		if ( typeof PACKAGED_APP == 'undefined' || typeof window.plugins == 'undefined' || typeof window.plugins.pushNotification == 'undefined' ){
			// Only valid for packaged apps with the PushNotification plugin loaded
			return;
		}
		
		var pushNotification = window.plugins.pushNotification;
		
		var config;
		switch( Ext.os.name ){
		case 'Android':
			pushNotification.register(
			    this.onSuccess,
			    this.onError,
				config = {
			        "senderID":PUSHPLUGIN.getGoogleProjectNumber(),
			        "ecb": "the_app.app.getController( 'the_app.controller.PushPluginController' ).onNotification" // must be passed as a string that can be called
			    }
			);
			break;
		case 'iOS':
			pushNotification.register(
			    this.tokenHandler,
			    this.onError,
				config = {
			        "badge":"true",
			        "sound":"true",
			        "alert":"true",
			        "ecb": "the_app.app.getController( 'the_app.controller.PushPluginController' ).onNotificationAPNS" // must be passed as a string that can be called
			    }
			);
			break;
		}

		// unregister on shutdown, as per recommendations at https://github.com/phonegap-build/PushPlugin/blob/master/README.md
		window.onbeforeunload = function(){
			var controller = the_app.app.getController( 'the_app.controller.PushPluginController' );
				
			pushNotification.unregister( controller.onSuccess, controller.onError, {} );
		}
    },
	
	callApi: function( endpoint, options ){
		Ext.apply( options, {
			api_key: PUSHPLUGIN.getAppApiKey(),
			os: Ext.os.name.toLowerCase()
		});
		
		Ext.Ajax.request({
			url: WP.getUrl() + 'push/' + endpoint,
			params: options,
			success: function( response ){
				if ( response.responseText != '' ){
					console.log( response.responseText );
				}
			}
		})
	},
	
	onSuccess: function( result ){
		// result contains any message sent from the plugin call
		//alert( 'result = ' + result );
	},

	onError: function( error ){
		// result contains any error description text returned from the plugin call
		console.log( 'Push Plugin Error = ' + error );
	},
	
	tokenHandler: function( token ){
		// just a wrapper to call the_app.app.getController( 'the_app.controller.PushPluginController' ).setDeviceToken with the right scope
		the_app.app.getController( 'the_app.controller.PushPluginController' ).setDeviceToken( token );
	},
	
	getDeviceToken: function(){
		return localStorage.getItem( WP.getAppId() + '_device_token' );
	},
	
	setDeviceToken: function( token ){
		this.callApi( 'register', {
			token: token,
			previous_token: this.getDeviceToken()
		});
		
		localStorage.setItem( WP.getAppId() + '_device_token', token );
	},
	
	onNotificationAPNS: function( e ){
		// Massage the message a little based on the format for iOS notifications vs. Android notifications
		Ext.apply( e, {
			payload: Ext.Object.merge( {}, e, { message: e.alert } ),
			event: 'message'
		});
		this.onNotification( e );
	},
	
	onNotification: function( e ){
		switch( e.event ){
		case 'registered':
			if ( e.regid.length > 0 )
			{
				// Your GCM push server needs to know the regID before it can push to this device
				// here is where you might want to send it the regID for later use.
				this.setDeviceToken( e.regid );
			}
			break;

		case 'message':
			// There are flags - e.foreground ( if app is in the foreground ) & e.coldstart (if not in foreground, was the app running or not)
			// But, I think I actually want to just always show an alert.  I'll put it through an action to allow other
			// code to process the push notification itself
			this.fireAction( 'pushnotification', arguments, function(e){
				var alert_config = {
					id: 'notification', 
					title: '',
					html: e.payload.message,
					hideOnMaskTap: true,
					width:'auto',
					height:'auto'
				}
				
				var nextActions = [];
				if ( e.payload.url && e.payload.url != '' ){
					nextActions.push( function(){
						window.open( e.payload.url, '_blank' );										
					});
				}
				if ( e.payload.route && e.payload.route != '' ){
					nextActions.push( function(){
						the_app.app.redirectTo( e.payload.route.replace( /^#/, '' ) );
					});
				}

				if ( Ext.isDefined( e.payload.message ) && e.payload.message != '' ){
					Ext.apply( alert_config, {
						buttons: [
							{
								xtype: 'button',
								text: WP.__('Ok'),
								handler: function( button ){
									this.hidePopup( button.up( 'panel' ).getId() );
									Ext.each( nextActions, function( action ){
										action();
									});
								},
								scope: the_app.app
							},
						]
					});
					the_app.app.showPopup( alert_config );
				}
				else{
					// No message, just do the actions
					Ext.each( nextActions, function( action ){
						action();
					});
				}
			});
			break;

		case 'error':
			console.log( 'Push Error: ' + e.msg );
			break;

		default:
			console.log( 'Push Unknown: ' + e.event );
			Ext.Object.each( e, function( key, value ){
				console.log( key + ': ' + value );
			})
			break;
		}
 	}
});