Ext.define('Ext.ux.IncrementalUpdateStore', {

	extend: 'Ext.data.Store',

	config: {

		/**
		 * @cfg {Object} localProxy A Proxy instance that will be used when storing the store's contents locally. Generally a LocalStorage proxy.
		 * @accessor
		 */
		localProxy: null,

		/**
		 * @cfg {Object} serverProxy A Proxy instance that will be used when syncing the store's contents to the server. Generally a Ajax proxy.
		 * @accessor
		 */
		serverProxy: null,

		/**
		 * @cfg {Object} ajaxProxyu A Proxy instance that will be used when loading the store's contents via ajax (e.g. in a packaged app)
		 * @accessor
		 */
		ajaxProxy: null,
		
		storeTimestamp: 0,
		
		snapshot: {
			records: [],
			map: {}
		},
		
		localLoaded: false,
		ajaxLoaded: false,
		serverLoaded: false
	},

	statics: {
		CREATED: 'created',
		UPDATED: 'updated',
		REMOVED: 'removed'
	},

	constructor: function(config){
		config = config || {};

		this.callParent([config]);
		
		this.on( 'beforeload', this.maskViewport, this );
		this.on( 'beforeload', this.determineProxyOnce, this, {single: true} );
		
		this.on( 'load', this.onLoad, this );
		this.on( 'load', this.unmaskViewport, this, { order: 'after' } );
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
		this.setupLocalProxy();
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
		var records = this.getSnapshot();
			
		Ext.each( this.getRange(), function( record ){
			if ( Ext.isDefined( records[ record.get( 'id' ) ] ) ){
				// The record exists, update it
				records[ record.get( 'id' ) ] = record;
			}
			else{
				// The record doesn't exist, just push it on
				records[ record.get( 'id' ) ] = record;
			}
		});
		
		var me = this;
		Ext.each( this.getDeleted(), function( deleted ){
			if ( Ext.isDefined( records[ deleted ] ) ){
				delete records[ deleted ];
			}
		});
		
		var new_data = [];
		Ext.Object.each( records, function( key, record ){
			record.stores = [];
			new_data.push( record );
		});		
		
		this.setData( new_data );		

		this.fireEvent( 'localmerged' );
	},
	
	doServerMerge: function(){
		var records = this.getSnapshot();

		Ext.each( this.getData().all, function( record ){
			if ( Ext.isDefined( records[ record.get( 'id' ) ] ) ){
				// the record exists in the snapshot, let's see if it has changed
				var data = {
					record: record.getData(),
					snapshot: records[ record.get( 'id' ) ].getData()
				}
				
				if ( Ext.Object.toQueryString( data.record ) != Ext.Object.toQueryString( data.snapshot ) ){
					record.dirty = true;
				}
				
				// Delete them from the snapshot - this will allow us to know which records from the snapshot need to be delete from the final dataset
				delete records[ record.get( 'id' ) ];
			}
			else{
				// It's a new record - one would think that setting record.phantom = true would be the right way, but
				// doing that seems to muck things up with unique identifiers, etc. , causing the warning "Your identifier generation strategy for the model does not ensure unique id\'s. Please use the UUID strategy, or implement your own identifier strategy with the flag isUnique." to get output
				record.dirty = true;
			}
		});
		this.setDeleted( records );
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
	},
	
	setupLocalProxy: function(){
		this.setProxy( this.getLocalProxy() );
		if ( this.getLocalProxy().type == 'localstorage' ){
			// The getIds() function in the WebStorage proxy returns an array of strings, which do not work
			// when trying to find indexOf integers.  The result was that the index item getting written out
			// was having duplicate ids (one a string, one an integer).  Lame.
			this._proxy.getIds = function(){
		        var str    = (this.getStorageObject().getItem(this.getId()) || "").split(","),
		            length = str.length,
		            i, ids = [];

		        if (length == 1 && str[0] === "") {
		            str = [];
		        }
				Ext.Array.each( str, function( id ){
					ids.push( parseInt( id ) );
				});
		        return ids;
			}
		}
	}
	
});