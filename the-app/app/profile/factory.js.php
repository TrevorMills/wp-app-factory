<?php 
	$the_app = & TheAppFactory::getInstance(); 
	$profiles = $the_app->get('profiles'); 
	$key = basename(get_query_var(APP_APP_VAR),'.js');
	$profile = $profiles[$key];
	header('Content-type: text/javascript');
	
	$outside_config = array('isActive','launch');
?>
Ext.define('the_app.profile.<?php echo $key; ?>',
	{
		extend: 'Ext.app.Profile',
		
		config: {
			<?php foreach ($profile as $what => $details) : ?>
			<?php if (in_array($what,$outside_config)) continue; ?>
			<?php echo $what; ?>: <?php echo TheAppFactory::anti_escape(json_encode($details)); ?>,
			<?php endforeach; ?>
		}
		
<?php foreach ($outside_config as $what) : if (isset($profile[$what])) : ?>
,<?php echo $what; ?>: <?php echo TheAppFactory::anti_escape(json_encode($profile[$what] )); ?>
		
<?php endif; endforeach; ?>
	}
);
