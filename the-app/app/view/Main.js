Ext.define("the_app.view.Main", {
    extend: 'Ext.tab.Panel',
	xtype: 'mainpanel',
	id: 'mainpanel',
    requires: [
    ],
    config: {
		id: 'mainpanel',
        tabBarPosition: 'bottom',
/*	Causes problems with 0-height tabbar in ST2.2.1.  Silly feature anyway.
	    tabBar: {
			scrollable: 'horizontal', // Just in case there are too many elements.
	    },
*/	
		layout: {
	        type: 'card',
	        animation: {
	            type: false
	        }
	    },
	
		title: 'The App',

        items: []  // These get added in a run time via app.js.php
    }
});
