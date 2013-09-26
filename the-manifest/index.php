<?php
	if ($_GET['test'] != 'true'){
		header('Content-type: text/cache-manifest');
	}
?>
CACHE MANIFEST
# Using Manifest: <?php echo ($the_app->is('using_manifest') ? 'true' : 'false')."\n"; ?>
# Version <?php echo ($the_app->get('manifest_version') ? $the_app->get('manifest_version') : '1')."\n"; ?>

# The Main App files
		
# The App Images
<?php $attachment_types = apply_filters('TheAppFactory_attachments_types',array('startup_phone','startup_tablet','startup_landscape_tablet','icon','stylesheet','splash'),array(& $the_app));
	foreach ($attachment_types as $type) : 
		if ($the_app->get($type)) : 
			echo $the_app->get($type)."\n"; 
		endif; 
	endforeach;
?>


# The Post Images
<?php
	$all_registered_queries = $the_app->get('registered_post_queries');

	$post_ids = array();
	foreach ($all_registered_queries as $type => $registered_queries){
		foreach ($registered_queries as $queryInstance => $registered_query){
			if (isset($registered_query['query_vars']['data_callback']) and function_exists($registered_query['query_vars']['data_callback'])){
				// This is an outside query, so no need to get post images
				continue;
			}
			$registered_query['query_vars']['numberposts'] = -1;
			$posts = get_posts($registered_query['query_vars']);
			foreach ($posts as $post){
				if ($queryInstance > 0 and array_key_exists($post->ID,$post_ids)){
					// Already output it, no need to again
				}
				else{
					$image = $the_app->getPostImages($post->ID,0);
					if ($image){
						echo $image."\n";
					}
				}
			}
		}	
	}
?>

<?php do_action('the_app_factory_print_manifest'); ?>

#Everything Else
NETWORK:
*

