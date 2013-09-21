Ext.define('the_app.view.UnsupportedBrowser',{
	extend: 'Ext.Container',
	xtype: 'unsupportedbrowser',
	
	config: {
		styleHtmlContent: true,
		fullscreen: true,
		tpl: [
			'<div class="message">{message}</div><img src="http://qrickit.com/api/qr?d={url}"/><br/><a href="{url}" class="full-link">{url}</a>'
		].join(''),
		data: null
	}
});