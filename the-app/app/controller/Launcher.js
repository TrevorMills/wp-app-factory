Ext.define('the_app.controller.Launcher', {
    extend: 'Ext.app.Controller',

	config: {
		queue: [],
		text: '',
	    refs: {
			launcher: 'launcher',
			carousel: 'launcher carousel',
			textPanel: '#launchertext'
	    },
	
	    control: {
			launcher: {
				initialize: 'onLauncherInitialize',
			},
		},
	},
	
	init: function(){
		console.log( 'hasdf' );
		Ext.each( Ext.data.StoreManager.getRange(), function( store ){
			this.waitFor( 'load', store, 'Loaded ' + store.getStoreId().replace( /Store$/, '' ) + ' data...' );
			store.on( 'load', function(){
				console.log( 'this one' );
			})
			store.load();
		}, this);
	},
	
	waitFor: function( event, object ){
		var me = this, args = arguments, callback, text;
		if ( args.length > 2 ){
			if ( Ext.isFunction( args[2] ) ){
				callback = args[2];
				text = args[3];
			}
			else{
				text = args[2];
			}
		}
		
		object.on( event, function(){
				console.log( 'asdf' );
				if ( typeof callback != 'undefined' ){
					callback.apply( this, arguments );
				}
				if ( typeof text != 'undefined' ){
					console.log( 'setting' );
					me.setText( text );
				}
			}, object, {
				single: true,
			}
		);
	},
	
	applyText: function( text ){
		if ( this.getTextPanel() ){
			this.getTextPanel().setData({
				text: text
			});
		}
	},
	
	onLauncherInitialize: function( carousel ){
		// setup the autoplay
		Ext.defer( this.switchLaunchImage, 3000, this );
	},
	
	switchLaunchImage: function(){
		var carousel = this.getCarousel(),
			cards = carousel.getItems();
			
		if ( cards.length <= 1 ){
			return;
		}
		if ( carousel.getActiveIndex() === carousel.getMaxItemIndex() ){
			// at the end, return to the beginning
			carousel.animateActiveItem( 0, { type: 'fade' } );
		}
		else{
			carousel.next();
		}
		Ext.defer( this.switchLaunchImage, 3000, this );
	},
	
});
