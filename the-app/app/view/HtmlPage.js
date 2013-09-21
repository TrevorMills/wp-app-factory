Ext.define('the_app.view.HtmlPage',{
	extend: 'Ext.Panel',
	xtype: 'htmlpage',
	
	config: {
		title: 'Html Page',
		useTitleBar: true,
		iconCls: 'home',
		scrollable: true,
		styleHtmlContent: true,
		data: null,
		tpl: '{content}',
		html: ' '
	}
});