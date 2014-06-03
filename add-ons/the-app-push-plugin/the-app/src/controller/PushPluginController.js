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
			config = {
		        "senderID":PUSHPLUGIN.getGoogleProjectNumber(),
		        "ecb": "the_app.app.getController( 'the_app.controller.PushPluginController' ).onNotification" // must be passed as a string that can be called
		    }
			break;
		}

		pushNotification.register(
		    this.onSuccess,
		    this.onError,
			config
		);
		
		window.onbeforeunload = function(){
			var controller = the_app.app.getController( 'the_app.controller.PushPluginController' );
				
			pushNotification.unregister(
				controller.onSuccess,
				controller.onError,
				{}
			);
			
		}
		
		return;
		

		//set push notifications handler
	    document.addEventListener('push-notification', function(event) {
	        var title = event.notification.title;
	        var userData = event.notification.userdata;
                             
	        if(typeof(userData) != "undefined") {
	            console.warn('user data: ' + JSON.stringify(userData));
	        }                                 
	    });
						
		switch( Ext.os.name ){
		case 'iOS':
			//initialize the plugin
		    pushNotification.onDeviceReady({ pw_appid:PUSHWOOSH.getApplicationCode() });
			break
		case 'Android':
		    //initialize Pushwoosh with projectid: "GOOGLE_PROJECT_ID", appid : "PUSHWOOSH_APP_ID". This will trigger all pending push notifications on start.
		    pushNotification.onDeviceReady({ projectid: PUSHWOOSH.getGoogleProjectNumber(), appid : PUSHWOOSH.getApplicationCode() });
			break;
		}

	    //register for pushes
	    pushNotification.registerDevice();

	    //reset badges on app start
	    pushNotification.setApplicationIconBadgeNumber(0);
    },
	
	onSuccess: function( result ){
		// result contains any message sent from the plugin call
		//alert( 'result = ' + result );
	},

	onError: function( error ){
		// result contains any error description text returned from the plugin call
		console.log( 'Device Registration Error = ' + error );
	},
	
	onNotification: function( e ){
		switch( e.event ){
		case 'registered':
			if ( e.regid.length > 0 )
			{
				// Your GCM push server needs to know the regID before it can push to this device
				// here is where you might want to send it the regID for later use.
				console.log("regID = " + e.regid);
				console.log( PUSHPLUGIN.getAppApiKey() );
			}
			break;

		case 'message':
			// if this flag is set, this notification happened while we were in the foreground.
			// you might want to play a sound to get the user's attention, throw up a dialog, etc.
			if ( e.foreground )
			{
				the_app.showPopup(
					{
						id: 'notification', 
						title: '',
						html: e.payload.message,
						hideOnMaskTap: true
					}
				)
			}
			else
			{  
				// otherwise we were launched because the user touched a notification in the notification tray.
				if ( e.coldstart )
				{
					console.log( 'coldstart notification' );
				}
				else
				{
					console.log( 'background notification' );
				}
			}
			break;

		case 'error':
			console.log( 'Error: ' + e.msg );
			break;

		default:
			console.log( 'Unknown: ' + e.event );
			Ext.each( e, function( value, key ){
				console.log( key + ': ' + value );
			})
			break;
		}
 	}
});