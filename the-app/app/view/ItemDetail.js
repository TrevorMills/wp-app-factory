Ext.define('the_app.view.ItemDetail',{
	extend: 'Ext.Panel',
	xtype: 'itemdetail',
	
	config: {
		title: 'Item Wrapper',
		scrollable: true,
		styleHtmlContent: true,
		tpl: '{content}',
		data: null
	}
});