Ext.define('Ext.ux.IncrementalUpdateMetaStore', {

	extend: 'Ext.ux.IncrementalUpdateStore',
	
	config: {
		pauseBeforeLoad: 10000
	},

	constructor: function(config){
		this.callParent( config );

		this.removeListener( 'beforeload', this.maskViewport );
		
		// We're going to load in the store meta, but not for a little while.  Give the app a chance to breathe first.
		Ext.defer( this.maybeLoad, this.getPauseBeforeLoad(), this );
	},
	
	maybeLoad: function(){
		if ( !( this.getAutoLoad() || this.isLoading() || this.isLoaded() ) ){
			this.load();
		}
	},

	determineProxyOnce: function( store ){
		if ( this.getAjaxProxy() ){
			this.setProxy( this.getAjaxProxy() );
		}
	},

	maskViewport: function(){
		Ext.Viewport.setMasked( {
			xtype: 'loadmask',
			message: WP.__( 'Initializing Data' )
		});
	},
	
	unmaskViewport: function(){
		if ( Ext.Viewport.getMasked() ){
			Ext.Viewport.unmask();
		}
	},
	
	loadLocal: function(){
		this.takeSnapshot();
		this.on( 'load', this.doLocalMerge, this, { single: true } );
		this.setProxy( this.getLocalProxy() );
		this.load();
	},
	
	loadServer: function(){
		this.takeSnapshot();
		this.on( 'load', this.doServerMerge, this, { single: true } );
		this.setProxy( this.getServerProxy() );
		this.load();
	},
	
	loadAjax: function(){
		this.setProxy( this.getAjaxProxy() );
		this.load();
	},
	
	takeSnapshot: function(){
		// This seems to actually keep references around actually
		//this.setSnapshot( Ext.Object.merge( {}, this.getData().map ) );
		var map = {}
		Ext.Object.each( this.getData().map, function( index, record ){
			map[ index ] = Ext.Object.merge( {}, record );
		})
		this.setSnapshot( map );
	},
	
	onLoad: function( store, records, successful ){
		switch( this.getProxy().config.type ){
		case this.getLocalProxy().type:
			console.log( 'loaded local ' + this.getStoreId() );
			this.setLocalLoaded( true );
			break;
		case this.getServerProxy().type:
			console.log( 'loaded server ' + this.getStoreId() );
			this.setServerLoaded( true );
			break;
		case this.getAjaxProxy().type:
			console.log( 'loaded ajax ' + this.getStoreId() );
			this.setAjaxLoaded( true );
			this.loadLocal();
			break;
		default:
			console.log( ['nada',this.getProxy().config.type] );
			break;
		}
	},
	
	doLocalMerge: function(){
		this.callParent();

		this.each( function( item, index ){
			var store = Ext.getStore( item.get( 'store' ) );
			if ( store && Ext.isFunction( store.setStoreTimestamp ) ){
				store.setStoreTimestamp( item.get( 'timestamp' ) );
			}
		}, this );			
		this.loadServer();
	},
	
	doServerMerge: function(){
		Ext.each( this.getData().all, function( record ){
			var store = Ext.getStore( record.get( 'store' ) );
			if ( store && record.get( 'timestamp' ) != store.getStoreTimestamp() ){
				// The data has changed on the server, need to reload from server.  
				if ( !store.getAjaxLoaded() ){
					store.on( 'localmerged', store.loadServer, store );
					store.loadAjax();
				}
				else{
					store.loadServer();
				}
				record.dirty = true;
			}
		});
		
		this.setupLocalProxy();
		this.sync();
	},
	
	getDeletedKey: function(){
		return this.getStoreId() + '_' + WP.getAppId() + '_deleted';
	},
	
	getStorageObject: function(){
		return window.localStorage;
	},
	
	setDeleted: function( records ){
		var deleted = [];

		Ext.Object.each( records, function( key, record ){
			deleted.push( record.get( 'id' ) );
		});

        this.getStorageObject().removeItem( this.getDeletedKey() );

		this.getStorageObject().setItem( this.getDeletedKey(), deleted.join(',') );
	},
	
	getDeleted: function(){
		var deleted_str = this.getStorageObject().getItem( this.getDeletedKey() ),
			deleted = [];
		if ( deleted_str ){
			Ext.each( deleted_str.split(','), function( str ){
				deleted.push( parseInt( str ) );
			});
		}
		
		return deleted;
	}
	
});