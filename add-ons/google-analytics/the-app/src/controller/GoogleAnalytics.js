Ext.define('the_app.controller.GoogleAnalytics', {
	extend: 'Ext.app.Controller',

	requires: [],

	config: {
		refs: {
			mainPanel: 'mainpanel'
		},

		control: {
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
		var activeItem = me.getMainPanel().getActiveItem();
		var top = me.getTopXType(activeItem);
		
		switch(top){
		case 'itemlist':
		case 'itemwrapper':
			// These xtypes are index pages, let's see if there's an active item
			if (me.getTopXType(activeItem.getActiveItem()) == 'list'){
				// It's the index page of either itemlist or itemwrapper
				GoogleAnalytics.push(['_trackEvent',me.getTitle(activeItem),'pageview',WP.__('Index')]);
			}
			else{
				// It's a list item we're looking at...
				GoogleAnalytics.push(['_trackEvent',me.getTitle(activeItem),'itemview',me.getTitle(activeItem.getActiveItem())]);
			}
			break;
		default: 
			GoogleAnalytics.push(['_trackEvent',me.getTitle(activeItem),'pageview','']);
			break;
		}
	},
	
	trackEvent: function(args){
		if (args.label == undefined) args.label = '';
		var commandArray = ['_trackEvent',args.category,args.action,args.label];
		if (args.value != undefined){
			commandArray.push(args.value);
		}
		if (args.noninteraction != undefined){
			commandArray.push(args.noninteraction);
		}
		GoogleAnalytics.push(commandArray);
	}
	
});
