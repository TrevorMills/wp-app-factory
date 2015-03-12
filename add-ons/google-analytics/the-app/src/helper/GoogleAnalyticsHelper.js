Ext.define('the_app.helper.GoogleAnalyticsHelper', {

    mixins: ['Ext.mixin.Observable'],

    singleton: true,

	alternateClassName: ['GoogleAnalyticsHelper'],

    /**
     * @constructor
     * Load the Google Analytics SDK asynchronously
     */
    initialize: function() {
		if ( this.isStandalone() ) {
			// It's a native app, 
			this.initializeNative()
		} else {
			this.initializeWeb();
		}
	},
	initializeNative: function(){
		window.analytics.startTrackerWithId( GoogleAnalyticsConfig.getAccount() );
		if ( GoogleAnalyticsConfig.getDebug() ) {
			window.analytics.debugMode();
		}
	},
	initializeWeb: function(){
		(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		})(window,document,'script','//www.google-analytics.com/analytics' + ( GoogleAnalyticsConfig.getDebug() ? '_debug' : '' ) + '.js','ga');
		
		var config = 'auto';
		if (document.domain == 'localhost'){
			config = {
				'cookieDomain': 'none'
			}
		}
		
		ga('create', GoogleAnalyticsConfig.getAccount(), config);
		ga('set','appName',WP.getAppName());

    },
	isStandalone: function(){
		return Ext.browser.is.WebView && Ext.isDefined( window.analytics );
	},
	send: function( event ){
		if ( this.isStandalone() ) {
			// It's a native app, 
			this.sendNative( event )
		} else {
			this.sendWeb( event );
		}
	},
	sendWeb: function( event ){	
		switch ( event.category ) {
		case 'screenview':
		case 'pageview':
			// For some testing, I'm going to send both screenview and pageview events, 
			ga( 'send', 'screenview', { screenName: event.label } );
			ga( 'send', 'pageview', { title: event.label } );
			break;
		}
		
		// for every event, I'm going to send an 'event' event
		var args = {
			hitType: 'event',
			eventCategory: event.category, // required
			eventAction: event.action, // required
		}
		
		if ( Ext.isDefined( event.label ) ) {
			args.eventLabel = event.label;
		}
		if ( Ext.isDefined( event.value ) ) {
			args.eventValue = event.value;
		}
		
		ga( 'send', args )
	},
	sendNative: function( event ) {
		switch ( event.category ) {
		case 'screenview':
		case 'pageview':
			window.analytics.trackView( event.label );
			break;
		}

		window.analytics.trackEvent( 
			event.category, 
			event.action, 
			Ext.isDefined( event.label ) ? event.label : undefined, 
			Ext.isDefined( event.value ) ? event.value : undefined
		);
		
	}

});