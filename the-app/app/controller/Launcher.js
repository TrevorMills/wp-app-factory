Ext.define('the_app.controller.Launcher', {
    extend: 'Ext.app.Controller',

	config: {
		waitFors: new Ext.util.MixedCollection(),
		text: null,
	    refs: {
			launcher: 'launcher',
			carousel: 'launcher carousel',
			textPanel: '#launchertext',
			mainPanel: 'mainpanel',
	    },
	
	    control: {
			launcher: {
				initialize: 'onLauncherInitialize',
			},
			mainpanel: {
				initialize: 'onMainPanelInitialize' 
			}
		},
	},
	
	init: function(){
		this.getWaitFors().on( 'remove', this.monitorWaitFors, this );
		this.getWaitFors().on( 'add', this.monitorWaitFors, this );

		Ext.each( Ext.data.StoreManager.getRange(), function( store ){
			if ( !store.getAutoLoad() ){
				this.waitFor( 'load', store, 'Loaded ' + store.getStoreId().replace( /Store$/, '' ) + ' data...' );
				store.load();
			}
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
		
		var uuid = Ext.create( 'Ext.data.identifier.Uuid' ).generate();
		
		this.getWaitFors().add( uuid, {event: event, object: object} );
		
		object.on( event, function(){
				if ( typeof callback != 'undefined' ){
					callback.apply( this, arguments );
				}
				if ( typeof text != 'undefined' ){
					me.setText( text );
				}
				me.getWaitFors().removeAtKey( uuid );
			}, object, {
				single: true,
			}
		);
	},
	
	monitorWaitFors: function(){
		if ( this.getLauncher() && !this.getWaitFors().getRange().length ){
			// We have reached the end of the WaitFors
			
			console.log( this.getLauncher().getMainItems() );
			Ext.defer( function(){
		        this.getLauncher().add({
					xtype: 'mainpanel',
					title: this.getLauncher().getTitle(),
					items: this.getLauncher().getMainItems(),
					showAnimation: {type: 'fade'},
				});
			}, 2000, this );
		}
		console.log( this.getWaitFors().items );
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
	
	onMainPanelInitialize: function( panel ){
		console.log( 'initialized' );
		this.getTextPanel().destroy();
		this.getLauncher().on( 'activeitemchange', function(){
			this.getCarousel().destroy();
		}, this, {
			single: true
		});
		this.getLauncher().setActiveItem( panel );
	},
	
	switchLaunchImage: function(){
		var carousel = this.getCarousel();
		
		if ( !carousel ){
			return;
		}
		
		var cards = carousel.getItems();
			
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
