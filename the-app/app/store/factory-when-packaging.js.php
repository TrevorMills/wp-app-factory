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
		}
	}
);