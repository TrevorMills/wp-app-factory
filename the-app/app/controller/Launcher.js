Ext.define('the_app.controller.Launcher', {
    extend: 'Ext.app.Controller',

	config: {
		queue: new Ext.util.MixedCollection(),
		pauseForHumans: 100,
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
		var me = this;
		this.getQueue().on( 'remove', this.processQueue, this );

		// Start off on the home screen
		// @TODO - eventually it would be good to reinstate deeplinking, but it's not
		// working properly right now.  
		this.redirectTo( 'tab/1' );
		
		if ( typeof FORCE_CLEAR_LOCALSTORAGE != 'undefined' && FORCE_CLEAR_LOCALSTORAGE ){
			Ext.each( Ext.data.StoreManager.getRange(), function( store ){
				if ( store instanceof Ext.ux.OfflineSyncStore ){
					var proxy = Ext.factory( store.getLocalProxy() );					
					store.fireEvent( 'beforeload', store ); // a hacky way to make sure that the DBConn connection for sqlitestorage proxies is setup properly
					proxy.clear();
				}
			});
		}

		Ext.each( Ext.data.StoreManager.getRange(), function( store ){
			if ( !store.getAutoLoad() ){
				this.enqueue( {
					fn: function(){
						store.load();
					},
					complete: {
						object: store,
						event: 'load',
						fn: function( store, records, successful, operation ){
							if ( store.isLoading() ){
								return {
									fn: Ext.emptyFn,
									complete: {
										object: store,
										event: 'load',
									},
									text: 'Loading ' + store.getStoreId().replace( /Store$/, '' )
								};
							}
						}
					},
					text: 'Loading ' + store.getStoreId().replace( /Store$/, '' )
				}); 
			}
		}, this);

		this.processQueue();
	},
	
	enqueue: function( options, uuid ){
		var me = this, queueMethod = 'add';
		
		if ( typeof uuid == 'undefined' ){
			uuid = Ext.create( 'Ext.data.identifier.Uuid' ).generate();
		}
		else{
			queueMethod = 'replace';
		}
			
		options.complete.object.on( options.complete.event, function(){
			var passback = false;
			if ( Ext.isFunction( options.complete.fn ) ){
				passback = options.complete.fn.apply( this, arguments );
			}
			if ( !Ext.isObject( passback ) ){
				me.getQueue().removeAtKey( uuid );
			}
			else{
				me.enqueue( Ext.apply( options, passback ), uuid );
				me.processQueue();
			}
		}, options.complete.object, {
			single: true
		});
		
		me.getQueue()[ queueMethod ]( uuid, options );
	},
	
	processQueue: function(){
		var queue = this.getQueue();
		if ( queue.getCount() ){
			var job = queue.first(); // get the first job
			if ( !Ext.isEmpty( job.text ) ){
				this.setText( job.text );
			}
			if ( job && Ext.isFunction( job.fn ) ){
				Ext.defer( job.fn, this.getPauseForHumans() );
			}
		}
		else{
			this.launchApp();
		}
	},
	
	launchApp: function(){
		this.setText( 'Launching App' );
		Ext.defer( function(){
	        this.getLauncher().add({
				xtype: 'mainpanel',
				title: this.getLauncher().getTitle(),
				items: this.getLauncher().getMainItems(),
				showAnimation: {type: 'fade'},
			});
		}, this.getPauseForHumans() * 2, this );
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