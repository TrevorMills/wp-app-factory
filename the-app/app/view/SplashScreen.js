Ext.define('the_app.view.SplashScreen',{
	extend: 'Ext.Container',
	xtype: 'splashscreen',
	
	requires: ['Ext.Animation.Fade'],
	
	config: {
		fullscreen: true,
		cls: 'loading',
		hidden: true,
		id: 'splashscreen',
		showAnimation: {type: 'fade'},
		hideAnimation: {type: 'fadeOut'},
		listeners:{
			render: function(){
	            this.el.mask('<span class="top"></span><span class="right"></span><span class="bottom"></span><span class="left"></span>', 'x-spinner', false);
			}
		}
	},
	
	splashIn: function(){
		this.show('fade');
	},
	splashOut: function(e){
		this.el.unmask();
		var that = this;
		setTimeout(function(){
			that.destroy();
			Ext.get(document.body).addCls('loaded');
		},2000); 
	}
});