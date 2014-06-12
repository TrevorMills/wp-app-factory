<?php
	
	$the_app = & TheAppFactory::getInstance(); 
	$stores = $the_app->get('stores'); 
	$key = basename(get_query_var(APP_APP_VAR),'.js');
	$store = $stores[$key];
	
	if( isset( $store['model'] ) and strpos( $store['model'], 'the_app.model' ) === false){
		$store['model'] = 'the_app.model.' . $store['model'];
	}

	header('Content-type: text/javascript');
?>
Ext.define('the_app.store.<?php echo $key; ?>',
	{
		extend: '<?php echo $store['extend']; ?>',
		config: {
			<?php foreach ($store as $what => $details) : ?>
			<?php echo $what; ?>: <?php echo TheAppFactory::anti_escape(json_encode($details)); ?>,
			<?php endforeach; ?>
			queryInstance: undefined,
			grouped: undefined,
			storeTimestamp: 0,
			localExists: false,
			<?php if ($store['useLocalStorage'] && $store['storageEngine'] == 'sqlitestorage' ) : ?>
			listeners: {
				beforeload: function( store, operation, eOpts ){
					// There seems to be an issue on the actual devices when this proxy is used in conjunction with the Cordova SQLite Native plugin
					// If we set the dbConn to SqliteDemo.util.InitSQLite.getConnection() during configuration, the Codrova SQLite Native plugin
					// hasn't necessarily had a chance to load (seems to occur when there is data in it).  So, we hold off setting the connection 
					// until the first time it is needed.
					if ( this.getProxy().config.type == 'sqlitestorage' && this.getProxy().getDbConfig().dbConn == null ){
						var dbConfig = {
							tablename: "<?php echo preg_replace( '/[^A-Z0-9_]/i', '_', preg_replace( '/Store$/', '', $key ) ); ?>",
							dbConn: SqliteDemo.util.InitSQLite.getConnection()
						}
						this.getProxy().setDbConfig( dbConfig );
						this.getProxy().setTable();
						if ( this.getLocalProxy().type == 'sqlitestorage' ){
							this.setLocalProxy( Ext.Object.merge( {}, this.getLocalProxy(), {
								dbConfig: dbConfig
							}));
						}
					}
				},
			},
			<?php endif; ?>
		}
	}
);