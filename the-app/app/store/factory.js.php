<?php 
	$the_app = & TheAppFactory::getInstance(); 
	$stores = $the_app->get('stores'); 
	$key = basename(get_query_var(APP_APP_VAR),'.js');
	$store = $stores[$key];
	if (!isset($store['storeId'])){
		$store['storeId'] = $key;
	}
	if(isset($store['model']) and strpos($store['model'],'the_app.model') === false){
		$store['model'] = 'the_app.model.'.$store['model'];
	}
	
	// Setup offline versino of the store
	if ($store['useLocalStorage']){
		$store['serverProxy'] = $store['proxy'];
		$store['localProxy'] = $store['proxy'] = array(
			'type' => 'localstorage',
			'id' => apply_filters('the_app_factory_localstorage_id',"{$store['storeId']}_{$the_app->get('app_id')}",$store)
			/*	 Come back to it.  This is the way to catch that allowed storage has been exhausted.
			'listeners' => array(
				'exception' => $the_app->do_not_escape('function(proxy,e){console.log([\'error\',e]);}')
			)
			*/
		);
		$extend = 'Ext.ux.OfflineSyncStore';
	}
	else{
		$extend = 'Ext.data.Store';
	}
	if ( !$the_app->is('building') and !in_array($store['storeId'],apply_filters('the_app_factory_autoload_stores',array('StoreStatusStore')) ) ){ // @dev
		$store['autoLoad'] = $the_app->do_not_escape('false');
	}
	else{
		$store['autoLoad'] = $the_app->do_not_escape('true');
	}
	header('Content-type: text/javascript');
?>
Ext.define('the_app.store.<?php echo $key; ?>',
	{
		extend: '<?php echo $extend; ?>',
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
			<?php if ($store['useLocalStorage']) : ?>
			storeTimestamp: 0,
			localExists: false,
			listeners: {
				load: function(store, records, successful, operation, eOpts){
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
									Ext.getStore(storeId).loadServer();
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

					if (this.getProxy().config.type == 'localstorage' && !(successful && records.length)){
						// Tried to load from localstorage, but there's nothing there.  Try and load from the server
						if (typeof PACKAGED_APP != 'undefined'){
							// We are packaging for native, we're going to update the URL for the proxy to
							// load from the initial data in the resources/data directory 
							<?php 
								$true_proxy = $data_proxy = $store['serverProxy']; 
								$parts = explode('.', $store['model']);
								$data_proxy['type'] = 'ajax';
								$data_proxy['url'] = 'resources/data/'.end($parts).'.json';
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
			<?php endif; ?>
		}
		
	}
);