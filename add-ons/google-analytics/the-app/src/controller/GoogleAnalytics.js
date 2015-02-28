Ext.define('the_app.controller.GoogleAnalytics', {
	extend: 'Ext.app.Controller',

	requires: [],

	config: {
		refs: {
			mainPanel: 'mainpanel'
		},

		lastPath: null
	},

	init: function() {
		GoogleAnalytics.initialize();
		the_app.app.getController('Main').on({
			afterrouting: this.afterRouting,
			trackevent: this.trackEvent
		});
	},
	
	getTopXType: function(component){
		var xtypes = component.getXTypes().split('/');
		return xtypes[xtypes.length - 1];
	},
	
	getTitle: function(component){
		if (typeof component.getTitle == 'function' && component.getTitle() != ''){
			return component.getTitle();
		}
		else{
			return this.getTopXType(component);
		}
	},
	
	getCurrentTitle: function(){
		var active = this.getMainPanel().getActiveItem(),
			title;
		
		if ( active.isXType( 'lazypanel' ) ) {
			var original = active.getOriginalItem();
			
			title = original.title;
			switch( original.xtype ) {
			case 'itemlist':
				var items = active.getActiveItem().getItems(),
					last = items.getAt( items.length - 1 );
				
				if ( items.length > 2 && typeof last.getTitle == 'function' ) {
					title += ' - ' + last.getTitle();
				}
				break;
			}
		}
		
		return title;
	},
	
	afterRouting: function(action){
		// the this reference is to the Main controller
		var me = the_app.app.getController('GoogleAnalytics');
		var path = action.action.getUrl(); // this is the tab/1/record/123 action
		
		if (path == me.getLastPath()){
			//  already tracked this event.  This is a safety.
			//  It seems that this function can get hit twice.
			return;
		}
		
		if (me.getLastPath() && me.getLastPath().substr(0,path.length) == path){
			// if the last path starts with the current path,
			// it's an indication that they've just hit the back button
			// I'm making a call to not track that event
			// Example, getLastPath() == 'tab/1/record/3', path == 'tab/1'
			me.setLastPath(path);
			return;
		}
		me.setLastPath(path);
		
		me.trackEvent({
			category: 'screenview',
			action: 'routing',
			label: me.getCurrentTitle(),
			value: path
		});
	},
	
	trackEvent: function( event ){
		GoogleAnalytics.send( event );
	}
	
});
