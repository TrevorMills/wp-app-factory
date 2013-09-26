Ext.define('the_app.view.LazyPanel', {
    extend: 'Ext.Panel',
    xtype: 'lazypanel',

	config: {
		layout: {
			type: 'fit'
		},
		lazyItem: null,
		originalItem: null,
		title: 'foo',
		iconCls: 'home',
		items: [],
		meta: null,
		queryInstance: null,
		destroyOnDeactivate: true
	},
	
});
/*
Ext.define('the_app.view.Placeholder',{
	extend: 'Ext.Panel',
	xtype: 'placeholder',
	
	config: {
		html: 'Hello World',
		alreadyInitialized: false,
		savedConfig: {}
	}
});
*/