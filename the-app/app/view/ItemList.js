Ext.define('the_app.view.ItemList',{
	extend: 'Ext.navigation.View',
	xtype: 'itemlist',
	
	requires: [
		'Ext.dataview.List',
		'Ext.data.proxy.JsonP',
		'Ext.data.Store' /* This is vital */
	],

	config: {
		title: 'Item List',
		iconCls: 'star',
		items: [],
		initialItem: {
			xtype: 'list',
			title: '',
			itemTpl: '{title}',
			itemId: 'list', 
			infinite: true,
			variableHeights: true,
		},
		
		queryInstance: null,
		store: null,
		meta: null,
	}
});