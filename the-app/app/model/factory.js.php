<?php 
	$the_app = & TheAppFactory::getInstance(); 
	$models = $the_app->get('models'); 
	$key = basename(get_query_var(APP_APP_VAR),'.js');
	$model = $models[$key];
	header('Content-type: text/javascript');
?>
Ext.define('the_app.model.<?php echo $key; ?>',
	{
		extend: 'Ext.data.ModelFaster',
		config: {
			<?php foreach ($model as $what => $details) : ?>
			<?php echo $what; ?>: <?php echo TheAppFactory::anti_escape(json_encode($details)); ?>,
			<?php endforeach; ?>
		}
		
	}
);