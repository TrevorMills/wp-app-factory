<?php
	
	if (!isset($the_app)){ die ('Move along...'); }
	
	switch(get_query_var(APP_DATA_VAR)){
	case 'storemeta':
		// A fairly brute force method.  To see if a store needs to be updated,
		// we'll just look at the last_modifed date of all of the posts in the store.
		$all_registered_queries = $the_app->get('registered_post_queries');

		$output['stores'] = array();
		$latest = array();
		foreach ($all_registered_queries as $type => $registered_queries){
			foreach ($registered_queries as $queryInstance => $registered_query){
				
				if (isset($registered_query['useLocalStorage']) and !$registered_query['useLocalStorage']){
					continue;
				}
				if (isset($registered_query['query_vars']['timestamp_callback']) and function_exists($registered_query['query_vars']['timestamp_callback'])){
					// There is a timestamp_callback.  Call it to retreive the latest timestamp for the registered query
					$latest[$type] = call_user_func($registered_query['query_vars']['timestamp_callback'],$registered_query);
				}
				else{
					// There's no callback - treat it as a WordPress post and just find the latest timestamp
					$registered_query['query_vars']['numberposts'] = -1;
					$posts = get_posts($registered_query['query_vars']);
					$latest[$type] = 0;
					foreach ($posts as $post){
						$time = strtotime($post->post_modified);
						if ($time > $latest[$type]){
							$latest[$type] = $time;
						}
					}
				}
			}	
		}
		if ($the_app->get('html_store_contents')){
			$latest['HtmlPages'] = strtotime($the_app->get('post')->post_modified);
		}
		if ($the_app->get('wrapper_store_contents')){
			$latest['Wrapper'] = strtotime($the_app->get('post')->post_modified);
		}
		
		$s = 1;
		foreach ($latest as $type => $time){
			$output['stores'][] = array('id' => $s++, 'store' => "{$type}Store", 'timestamp' => $time);
		}
		break;
	case 'htmlpages':
		$output['htmlpages'] = $the_app->get('html_store_contents');
		break;
	case 'wrapperpages':
		$output['wrapperpages'] = $the_app->get('wrapper_store_contents');
		break;
	default:
		$output = array(get_query_var(APP_DATA_VAR) => array());
		$registered_queries = $the_app->get('registered_post_queries');
		$registered_queries = $registered_queries[get_query_var(APP_DATA_VAR)];
		if (!is_array($registered_queries)){
			die('What are you doing Dave?');
		}
		
		$post_ids = array();
		$other_ids = array();
		// @TODO, there is a bug in here
		// If a post is retreived via query that limits by category, then we need to skip
		// the category spoofing for this query instance.  Similarly, if a later registered_query
		// limits based on category, then we shouldn't add the spoof'd versions outside of that category
		foreach ($registered_queries as $queryInstance => $registered_query){
			if (isset($registered_query['query_vars']['data_callback']) and function_exists($registered_query['query_vars']['data_callback'])){
				$xtype = $registered_query['query_vars']['xtype'];
				if (!array_key_exists($xtype,$other_ids)){
					$other_ids[$xtype] = array();
				}
				$posts = call_user_func($registered_query['query_vars']['data_callback'],$registered_query);
				foreach ($posts as $post){
					if ($queryInstance > 0 and array_key_exists($post->id,$other_ids[$xtype])){
						// Already output it, update it to reflect this query instance as well
						foreach ($other_ids[$xtype][$post->id] as $pos){
							$output[get_query_var(APP_DATA_VAR)][$pos]['query_num'].= ',_'.$queryInstance.'_';
						}
					}
					else{
						$post_output = array();
						foreach ($post as $key => $value){
							$key = $the_app->sanitize_key($key);
							$post_output[$key] = $value;
						}
						$post_output['query_num'] = '_'.$queryInstance.'_';
						$post_output = apply_filters('the_app_callback_post_output',$post_output,$post);
						
						if (is_array($post_output)){
							$other_ids[$xtype][$post->id] = array(count($output[get_query_var(APP_DATA_VAR)])); // an array to keep track of positions within the larger array
							$output[get_query_var(APP_DATA_VAR)][] = $post_output;
						}
					}
				}
				continue; // skip the getting of WordPress posts because this is an external data source
			}
			//$registered_query['query_vars']['numberposts'] = -1;
			$posts = get_posts($registered_query['query_vars']);
			foreach ($posts as $post){
				if (isset($post->password)){
					// don't want to output the password
					unset($post->password);
				}
				if ($queryInstance > 0 and array_key_exists($post->ID,$post_ids)){
					// Already output it, update it to reflect this query instance as well
					foreach ($post_ids[$post->ID] as $pos){
						$output[get_query_var(APP_DATA_VAR)][$pos]['query_num'].= ',_'.$queryInstance.'_';
					}
				}
				else{
					$post_output = array();
					foreach ($post as $key => $value){
						$key = $the_app->sanitize_key($key);
						switch($key){
						case 'date':
							$ts = strtotime($value);
							$post_output[$key] = date("M d Y",$ts);
							break;
						case 'content':
							$post_output[$key] = apply_filters('the_content',$value); //do_shortcode($value);
							break;
						default:
							$post_output[$key] = $value;
							break;
						}
					}
					$image = $the_app->getPostImages($post->ID,0);
					if ($image){
						$post_output['thumbnail'] = $image;
					}
					$categories = wp_get_post_categories($post->ID);
					if (count($categories)){
						$cat = get_category($categories[0]);
						$post_output['category'] = $cat->name;
					}
					
					// Post Custom Values
					$custom = get_post_custom($post->ID);
					if (is_array($custom)){
						foreach($custom as $field => $value){
							$post_output[$field] = $value;
						}
					}
					
					$post_output['query_num'] = '_'.$queryInstance.'_';
					
					$post_output = apply_filters('the_app_post_output',$post_output,$post);
					if (is_array($post_output)){
						$post_ids[$post->ID] = array(count($output[get_query_var(APP_DATA_VAR)])); // an array to keep track of positions within the larger array
						$output[get_query_var(APP_DATA_VAR)][] = $post_output;
						if (count($categories) > 1){
							// Just in case they are wanting to sort by category, I'm going
							// to spoof additional records
							array_shift($categories); // first one is covered already
							foreach ($categories as $category){
								$cat = get_category($category);
								$spoof = array(
									'id' => $post->ID.'999'.$category,
									'category' => $cat->name,
									'spoof_id' => $post->ID
								);
								$additional_spoof_fields = apply_filters('additional_spoof_fields',array('title'),$registered_query);
								if (count($additional_spoof_fields)){
									foreach ($additional_spoof_fields as $f){
										$spoof[$f] = $post_output[$f];
									}
								}
								$spoof['query_num'] = '_'.$queryInstance.'_';
								$post_ids[$post->ID][] = count($output[get_query_var(APP_DATA_VAR)]);
								$output[get_query_var(APP_DATA_VAR)][] = $spoof;
							}
						}
					}
				}
			}
		}
	}
	
	header("Content-type: text/javascript");
	ob_start();
	if (isset($_GET['jsonp'])){
		echo $_GET['jsonp'].'=';
	}
	elseif(isset($_GET['callback'])){
		echo $_GET['callback']."(";
	}
	elseif(isset($_GET['what'])){
		echo $_GET['what'].'=';
	}
	echo json_encode($output);
	if(isset($_GET['callback'])){
		echo ");";
	}
	exit();
?>