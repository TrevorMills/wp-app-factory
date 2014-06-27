<?php 
	$the_app = & TheAppFactory::getInstance(); 
	$stores = $the_app->get('stores'); 
	$key = basename(get_query_var(APP_APP_VAR),'.js');
	$store = $stores[$key];
	if ( $store['storeId'] == 'StoreStatusStore' ){
		$store['autoLoad'] = true;
	}
	if( isset( $store['model'] ) and strpos( $store['model'], 'the_app.model' ) === false){
		$store['model'] = 'the_app.model.' . $store['model'];
	}
	if ( $store['useLocalStorage'] ){
		$store['proxy'] = $store['localProxy'];
	}
	$store['autoLoad'] = false;
	header('Content-type: text/javascript');
?>
Ext.define('the_app.store.<?php echo $key; ?>',
	{
		extend: '<?php echo $store['extend']; ?>',
		maybeLoad: function(){
			if ( !( this.getAutoLoad() || this.isLoading() || this.isLoaded() ) ){
				this.load();
			}
		},
		config: {
			<?php foreach ($store as $what => $details) : ?>
			<?php echo $what; ?>: <?php echo TheAppFactory::anti_escape(json_encode($details)); ?>,
			<?php endforeach; ?>
			queryInstance: undefined,
			grouped: undefined,
			storeTimestamp: 0,
			<?php if ($store['useLocalStorage']) : ?>
			localExists: false,
			listeners: {
				beforeload: function( store, operation, eOpts ){
					// There seems to be an issue on the actual devices when this proxy is used in conjunction with the Cordova SQLite Native plugin
					// If we set the dbConn to SqliteDemo.util.InitSQLite.getConnection() during configuration, the Codrova SQLite Native plugin
					// hasn't necessarily had a chance to load (seems to occur when there is data in it).  So, we hold off setting the connection 
					// until the first time it is needed.
					if ( this.getLocalProxy().config.type == 'sqlitestorage' && this.getLocalProxy().getDbConfig().dbConn == null ){
						var dbConfig = {
							tablename: "<?php echo preg_replace( '/[^A-Z0-9_]/i', '_', preg_replace( '/Store$/', '', $key ) ); ?>",
							dbConn: SqliteDemo.util.InitSQLite.getConnection()
						}
						this.getLocalProxy().setDbConfig( dbConfig );
						this.getLocalProxy().setTable();
						if ( this.getProxy().config.type == 'sqlitestorage' ){
							this.getProxy().setDbConfig( dbConfig );
							this.getProxy().setTable();
						}
					}
				},
				load: function(store, records, successful, operation, eOpts){
					if ( Ext.Viewport.getMasked() ){
						Ext.Viewport.unmask();
					}
					<?php if ($key == 'StoreStatusStore') : ?>
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
							if (stores_to_update.length){
								var stores_updated = 0;
								Ext.each(stores_to_update,function(storeId){
									var store = Ext.getStore(storeId);
									store.on({
										load: {
											fn: function(){
												stores_updated++;
											},
											single: true
										}
									});
									if ( Ext.isFunction( Ext.getStore(storeId).loadServer ) ){
										Ext.getStore(storeId).loadServer();
									}
									else{
										stores_updated++;
									}
								});
								
								var me = this;
								var interval = setInterval(function(){
									if (stores_updated >= stores_to_update.length){
										clearInterval(interval);
										me.onServerLoad(me,records,successful); // will sync to localstorage
										
										// I don't think I need to strictly do a full app reload, but it seems the best way to 
										// go, in case any views would be updated based on new data. 
										// I know I definitely don't need to do one for HtmlPagesStore updates
										if (stores_to_update.length > 1 || stores_to_update[0] != 'HtmlPagesStore') {
											the_app.app.confirm(
												{
													id: 'update', 
													title: WP.__("Data Update"),
													html: WP.__("New data has just been loaded for the app.  Reload now to see that data?"),
													hideOnMaskTap: false,
													handler: function(){
														window.location.reload();
													}
												}
											);
										}
									}
								},500);
							}
							
							Ext.defer(function(){
								// Now, trigger a server load
								this._proxy = this.getServerProxy();
								this.load();				
							},60 * 60 * 24 * 1000,this); // check again in a day - this is mostly only pertinant to packaged apps
						}
					<?php endif; ?>
					
					if (this.getProxy().config.type == "<?php echo $store[ 'storageEngine' ]; // 'localstorage' ?>" && !(successful && records.length)){
						// Tried to load from localstorage, but there's nothing there.  Try and load from the server
						/*
						Ext.Viewport.setMasked( {
							xtype: 'loadmask',
							message: WP.__( 'Initializing Data' )
						});
						*/
						if (typeof PACKAGED_APP != 'undefined'){
							// We are packaging for native, we're going to update the URL for the proxy to
							// load from the initial data in the resources/data directory 
							<?php 
								$true_proxy = $data_proxy = $store['serverProxy']; 
								$data_proxy['type'] = 'ajax';
								$data_proxy['url'] = 'resources/data/'.preg_replace( '/Store$/', '', $key ).'.json';
							?>
							this.setServerProxy(<?php echo json_encode($data_proxy); ?>);
							this.loadServer();
							
							// Reset the Proxy
							this.setServerProxy(<?php echo json_encode($true_proxy); ?>);
						}
						else{
							this.loadServer();
						}
					}
					
				}
			}
			<?php else : ?>
			listeners: {
				beforeload: function(store, operation, eOpts){
					return;
					Ext.Viewport.setMasked( {
						xtype: 'loadmask',
						message: WP.__( 'Initializing Data' )
					});
				},
				load: function(store, records, successful, operation, eOpts){
					if ( Ext.Viewport.getMasked() ){
						Ext.Viewport.unmask();
					}
				}
			}	
			<?php endif; ?>
		}
		
	}
);