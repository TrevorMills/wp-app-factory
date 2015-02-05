Ext.define('the_app.controller.FormsController', {
    extend: 'Ext.app.Controller',
    
	requires: [ 'Ext.form.Panel', 'Ext.form.FieldSet' ],
	
    config: {
        control: {
			'formpanel': {
				activate: 'onFormActivate',
			},
        },
    },
	
	onFormActivate: function( form ) {
		Ext.each( form.up( 'panel' ).query( 'button' ), function( button ){
			if ( button.getCls().indexOf( 'submit' ) !== -1 ) {
				// It's our submit button
				button.on( 'tap', function(){
					this.validateAndSubmit( form );
				}, this );
			}
		}, this );
	},
	
	validateAndSubmit: function( form ) {
		var errors = [];
		Ext.each( form.query( 'field' ), function ( field ){
			if ( field.getRequired() && field.getValue() == '' ) {
				errors.push( WP.__('%s is a required field.').replace( '%s', field.getLabel() ) );
			}
		});
		
		if ( errors.length ) {
			the_app.app.alert({
				html: errors.join('<br/>'),
			});
		} else {
			form.submit({
				url: form.getUrl(),
				method: form.getMethod(),
				waitMsg: WP.__( 'Submitting' ),
				success: function( form, result, responseText ){
					if ( form.fireEvent('submitsuccess', form, arguments) !== false ) {
						if ( typeof result.message != 'undefined' ) {
							the_app.app.alert({
								html: result.message,
							});
						}
						form.reset();
					}
				},
				failure: function( form, result ){
					if ( form.fireEvent('submitfailure', form, arguments) !== false ) {
						if ( typeof result.message != 'undefined' ) {
							the_app.app.alert({
								html: result.message
							});
						} else {
							the_app.app.alert({
								html: WP.__( 'There was a communication error while trying to submit your form.  Are you sure you are connected to the internet?' )
							});
							console.log( result );
						}
					}
				}
			})
		}
	}
});

