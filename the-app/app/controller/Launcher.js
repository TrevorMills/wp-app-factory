Ext.define('the_app.controller.Launcher', {
    extend: 'Ext.app.Controller',

	config: {
		queue: new Ext.util.MixedCollection(),
		shownAll: false,
		readyToLaunch: false,
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
			if ( Ext.isFunction( store.getLaunchLoad ) && store.getLaunchLoad() ){
				
				var prettyLabel = Ext.isFunction( store.getPrettyLabel ) ? store.getPrettyLabel() : store.getStoreId().replace( /Store$/, '' );
				this.enqueue( {
					fn: function(){
						store.load();
					},
					text: WP.__( 'Loading %1' ).replace( /%1/, prettyLabel ),
					complete: {
						object: store,
						event: 'load',
						fn: function( store, records, successful, operation ){
							if ( store.isLoading() ){
								if ( store instanceof Ext.ux.OfflineSyncStatusStore ){
									// It's the StoreStatusStore, we'll wait for 'load' again
									return {
										fn: Ext.empty,
										text: WP.__( 'Checking for Updates' ),
										complete: {
											object: store,
											event: 'synccheck',
											fn: function(){
												if ( this.getStoresToUpdate().length ){
													return {
														fn: Ext.emptyFn,
														complete: {
															object: this,
															event: 'syncdecision',
															fn: function( updating ){
																if ( updating ){
																	return {
																		fn: Ext.emptyFn,
																		text: WP.__( 'Performing Updates' ),
																		complete: {
																			object: this,
																			event: 'all_syncs_complete'
																		},
																	}
																}
															}
														}
													}
												}
											}
										},
									}
								}
								else{
									// This is true the first time the app runs when a store attempts to 
									// load from persistent storage but nothing exists - a server load will
									// be triggered immediately and store.isLoading() will be true by the time we're here
									return {
										fn: Ext.emptyFn,
										text: WP.__( 'Storing %1' ).replace( /%1/, prettyLabel ),
										complete: {
											object: store,
											event: 'sync_complete'
										},
									}
								}
							}
						}
					},
				})
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
		this.setReadyToLaunch( true );
		if ( LAUNCHER.getShowAll() && !this.getShownAll() ){
			this.on( 'shownall', this.launchApp, this, { single: true } );
			return;
		}
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
	
	onLauncherInitialize: function( view ){
		var carousel = this.getCarousel();
		Ext.each( LAUNCHER.getItems(), function( item ){
			carousel.add({
				xtype: 'panel',
				height:'100%',
				width:'100%',
				tpl: [
					'<div style="position:relative;width:100%;height:100%;<tpl if="image">background-image:url({image});background-size:contain;background-repeat:no-repeat;background-position:center center;</tpl>">',
						'<tpl if="text">',
							'<div class="launch-text" style="top:{text_top};color:{text_color};background:{text_background}">{text}</div>',
						'</tpl>',
					'</div>'
				].join( '' ), 
				data: Ext.Object.mergeIf( {}, item, {
					text_top: LAUNCHER.getTextTop(),
					text_color: LAUNCHER.getTextColor(),
					message_top: LAUNCHER.getMessageTop(),
					message_color: LAUNCHER.getMessageColor(),
					slide_pause: LAUNCHER.getSlidePause(),
					text_background: LAUNCHER.getTextBackground(),
				}),
			});
		}, this );
		// setup the autoplay
		var settings = carousel.getInnerItems()[ 0 ].getData();
		
		this.getTextPanel().setStyle( "color:" + settings.message_color );
		this.getTextPanel().setTop( settings.message_top );
		Ext.defer( this.switchLaunchImage, settings.slide_pause, this );
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
			this.setShownAll( true );
			this.fireEvent( 'shownall' );
			return;
		}
		var current = carousel.getActiveIndex(),
			next = ( current === carousel.getMaxItemIndex() && this.setShownAll( true ) ) ? 0 : current + 1,
			current_settings = carousel.getInnerItems()[ current ].getData(),
			settings = carousel.getInnerItems()[ next ].getData(),
			transition = { type: 'fade', duration: 250  };
			
		if ( Ext.isDefined( current_settings.image ) && Ext.isDefined( settings.image ) && current_settings.image == settings.image ){
			// No image change, so no transition
			transition = { type: false }
		}
		
		if ( this.getShownAll() && this.getReadyToLaunch() ){
			this.fireEvent( 'shownall' );
		}	
		else{
			carousel.animateActiveItem( next, transition ); 
			this.getTextPanel().setTop( settings.message_top );
			this.getTextPanel().setStyle( "color:" + settings.message_color );
		}
		
		Ext.defer( this.switchLaunchImage, settings.slide_pause, this );
	},
	
});