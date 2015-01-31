Ext.define('the_app.controller.FormsController', {
    extend: 'Ext.app.Controller',
    
	requires: [ 'Ext.form.Panel', 'Ext.form.FieldSet' ],
	
    config: {
        refs: {
			mainPanel: 'mainpanel',
        },
        control: {
			'mainpanel': {
				activate: 'onMainPanelActivate',
			},
        },
    },
	
	onMainPanelActivate: function( panel ){
		console.log( 'hello forms world' );
	}
});

