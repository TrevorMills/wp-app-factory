<?php 
	// Please note: if you want to define a helper object, the object of that name will be 
	// created.  For example, the file app/helper/WP.js instantiates a helper object called WP
	// Use the filter TheAppFactory_helpers to define helpers
	$the_app = & TheAppFactory::getInstance(); 
	$helpers = $the_app->get('helpers'); 
	$key = basename(get_query_var(APP_APP_VAR),'.js');
	$helper = $helpers[$key];
	header('Content-type: text/javascript');
?>
Ext.define('the_app.helper.<?php echo $key; ?>', {
    config: {
		<?php foreach ($helper as $what => $details) : ?>
		<?php echo $what; ?>: <?php echo TheAppFactory::anti_escape(json_encode($details)); ?>,
		<?php endforeach; ?>
    },

	singleton: true,
	alternateClassName: ['<?php echo $key; ?>'],

    constructor: function(config) {
        this.initConfig(config);
    }
});
