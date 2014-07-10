Ext.define("the_app.view.Launcher", {
    extend: 'Ext.Panel',
	xtype: 'launcher',
	id: 'launcher',
    requires: ['Ext.carousel.Carousel'],
    config: {
		id: 'launcher',
		layout: {
			type: 'card'
		},
		title: '',
		mainItems: [],
		installApp: false,
        items: [
			{
				xtype: 'carousel',
				layout: 'card',
				defaults: {
			        //styleHtmlContent: true
			    },
				indicator: false,
				top:0,
				style:"height:100%;width:100%",
				fullscreen:true,
				items: []
	        },
			{
				xtype: 'panel',
				id: 'launchertext',
				tpl: '{text}',
				top: '80%',
				width: '100%',
				floatingCls: 'x-floating-no-border',
				style: 'text-align:center',
				data: {
					text: null
				}
			},
			
		]
    }
});