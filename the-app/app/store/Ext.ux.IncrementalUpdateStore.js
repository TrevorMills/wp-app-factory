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
		
		isStoreMeta: false,
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
	}
});
