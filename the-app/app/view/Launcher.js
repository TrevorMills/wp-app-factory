Ext.define("the_app.view.Launcher", {
    extend: 'Ext.Panel',
	xtype: 'launcher',
	id: 'launcher',
    requires: [],
    config: {
		id: 'launcher',
		layout: {
			type: 'card'
		},
        items: [
			{
				xtype: 'carousel',
				layout: 'card',
				defaults: {
			        styleHtmlContent: true
			    },
				indicator: false,
				items: [
					{
						html: 'Hello World'
					},
					{
						html: 'What a nice world'
					},
					{
						html: 'Goodbye World'
					}
				]
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
					text: 'Foo Bar'
				}
			},
			
		]
    }
});
