Ext.define('the_app.controller.PushWooshController', {
    extend: 'Ext.app.Controller',
    
	requires: [],
	
    init: function(){
		if ( typeof PACKAGED_APP == 'undefined' || typeof window.plugins == 'undefined' || typeof window.plugins.pushNotification == 'undefined' ){
			// Only valid for packaged apps with the PushNotification plugin loaded
			return;
		}
		
		var pushNotification = window.plugins.pushNotification;

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
});

