Ext.define('Ext.ux.OfflineSyncStatusStore', {

	extend: 'Ext.ux.OfflineSyncStore',
	
	config: {
		storesToUpdate: [],
		askBeforeUpdating: true
	},

	maybeDoInitialSync: function(store, records, successful, operation, eOpts){
		// The StoreStatusStore is a way to tell if any of the stores
		// have been updated on the server.  It contains records for each of the stores
		// loaded into the app along with a modified timestamp.  Apps that implement
		// their own stores using the data_callback functionality will also need
		// to implement a timestamp_callback that returns the latest timestamp (
		// in seconds since the epoch, a la PHP's time() function)
	
		// If we're loading in the Store Status from localstorage, and were successful
		// then we'll save off the timestamps for each of the stores and trigger a server
		// load.  Note, I'm not using the loadServer() function because I want to control
		// syncing the StoreStatusStore myself, and loadServer automatically syncs
		if ( !this.getLocalExists() ){
			if (successful && records.length){
				this.setLocalExists(true);
				Ext.each(records,function(record){
					var the_store = Ext.getStore(record.get('store'));
					if (the_store){
						the_store.setStoreTimestamp(record.get('timestamp'));
					}
				});
			
				// Now, trigger a server load
				this._proxy = this.getServerProxy();
				this.load();
			}
		}
		else{
			// This is from the server load triggered above
			var stores_to_update = [];
			Ext.each(records,function(record){
				var the_store = Ext.getStore(record.get('store'));
				// Note, I'm doing a != as opposed to < to allow for the case where the 
				// most recent record has actaully been deleted on the server and so therefore
				// the latest timestamp has gotten smaller.  It also allows for apps to 
				// use a hash instead of a timestamp
				if (the_store && the_store.getStoreTimestamp() != record.get('timestamp')){
					stores_to_update.push(the_store.getStoreId());
				}
			});
		
		
			// If there are stores to be updated, then we will trigger .loadServer on each of 
			// them.  Once all stores_to_update have been updated, (handled by the interval)
			// then finally we sync the StoreStatusStore to localstorage (by calling me.onServerLoad)
			this.setStoresToUpdate( stores_to_update );
			this.fireEvent( 'synccheck' );
			
			var me = this;
			if ( this.getStoresToUpdate().length ){
				if ( false && this.getAskBeforeUpdating() ){
					the_app.app.confirm({
						id: 'download-zip', 
						title: WP.__("Updates Available"),
						html: WP.__("There are data updates available from the server.  Load them now?"),
						hideOnMaskTap: false,
						handler: function(){
							me.fireEvent( 'syncdecision', true );
							me.performUpdates(records,successful);
						},
						handlerNo: function(){
							me.fireEvent( 'syncdecision', false );
						}
					});
				}
				else{
					me.fireEvent( 'syncdecision', true );
					me.performUpdates( records, successful );
				}
			}
		}
		
		this.callParent( arguments );
	},
	
	performUpdates: function(records,successful){
		var me = this;
		Ext.each( this.getStoresToUpdate(),function(storeId, index){
			var store = Ext.getStore(storeId);
			if ( !Ext.isDefined( storeId ) ){
				return;
			}
			if ( store && Ext.isFunction( store.loadServer ) ){
				store.on({
					sync_complete: {
						fn: function(){
							stores_to_update = me.getStoresToUpdate();
							stores_to_update.splice( stores_to_update.indexOf( storeId ),1);
							me.setStoresToUpdate( stores_to_update );
							me.maybeTriggerAllComplete();
							Ext.each( records, function( record ){
								if ( record.get( 'store' ) == storeId && Ext.isFunction( store.setStoreTimestamp ) ){
									store.setStoreTimestamp( record.get( 'timestamp' ) );
								}
							});
						},
						single: true
					}
				});
				store.loadServer();
			}
			else{
				stores_to_update = me.getStoresToUpdate();
				stores_to_update.splice(index,1);
				me.setStoresToUpdate( stores_to_update );
				me.maybeTriggerAllComplete();
			}
		});
	
		var interval = setInterval(function(){
			if ( !me.getStoresToUpdate().length ){
				clearInterval(interval);
				me.onServerLoad(me,records,successful); // will sync to localstorage
			}
		},500);
	},
	
	maybeTriggerAllComplete: function(){
		if ( !this.getStoresToUpdate().length ){
			this.fireEvent( 'all_syncs_complete' );
		}
	}
	
});