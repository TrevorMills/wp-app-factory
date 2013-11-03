Ext.define('the_app.view.ItemWrapper',{
	extend: 'Ext.navigation.View',
	xtype: 'itemwrapper',
	
	requires: [
		'Ext.dataview.List',
		'Ext.data.Store' /* This is vital */
	],
	
	config: {
		title: 'Item Wrapper',
		iconCls: 'star',
		
		items: [
			{
				xtype: 'list',
				itemTpl: '{item.title}',
				ui: 'round',
				itemId: 'list', 
				store: {
					fields: ['item','meta']
				}
			}
		],
		meta: null		
	}
});
