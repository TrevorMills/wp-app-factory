<?php
class TheAppBuilder extends TheAppFactory {
	
	/**
	 * Allow this flavour of the Factory to setup what it wants
	 *
	 * @return void
	 */
	public function setup() {
		self::setup_environment();
		return void;
	}
	
	public function setup_environment(){
		$the_app = & TheAppFactory::getInstance();

		global $post;
		$uploads = wp_upload_dir();

		// Some vars for building
		$the_app->set('build_root',trailingslashit($uploads['basedir'])); // multisite safe - uploads or files directory
		$the_app->set('build_root_url',trailingslashit($uploads['baseurl']));
		$the_app->set('production_root',$the_app->get('build_root') . trailingslashit( "wp-app-factory/build/production/$post->post_name" ));
		$the_app->set('production_root_url',$the_app->get('build_root_url') . trailingslashit( "wp-app-factory/build/production/$post->post_name" ));

		// Additional vars for administrators who are doing the actual building
		if (current_user_can('administrator') && (get_query_var(APP_COMMAND_VAR) == 'build' || get_query_var(APP_COMMAND_VAR) == 'build_no_minify')){
			$the_app->is('building',true);
			$the_app->is('doing_build_command',true);

			// If building, we want to make sure that the Desktop is an allowed browser
			$the_app->apply('meta',array(
				'unacceptable_browser' => array(
					'desktop' => false
				)
			));
			$the_app->enqueue('controller','Build');
			$the_app->is('minifying',strpos(get_query_var(APP_COMMAND_VAR),'no_minify') === false);
			add_filter('TheAppFactory_helpers',array(&$this,'build_helper'),10,2);
		}
		elseif( isset($_REQUEST['building']) && 'true' === $_REQUEST['building'] ){
			$the_app->is('building',true);
		}

		if ( isset($_REQUEST['minify'])){
			$the_app->is('minifying',($_REQUEST['minify'] == 'true'));
		}

		$app_meta = get_post_meta(get_the_ID(),'app_meta',true);
		$key = (current_user_can('administrator') ? 'admin' : 'regular');
		if ( current_user_can('administrator') and $the_app->is('doing_build_command')){
			// We are building or packaging the app.  Set the environment to development so as to load the dev files
			$the_app->set('environment', 'development');
		}
		elseif (isset($_GET['building']) and $_GET['building'] == 'true'){
			// True for anything called via build_cp();
			$the_app->set('environment', 'development');
		}
		else{
			TheAppFactory::set_environment();
		}
		
	}
	
	/**
	 * Ajax endpoint for when the app is building
	 *
	 * @return dead
	 */
	public function build_app(){

		global $post;
		$post = get_post($_POST['id']);

		ob_start();

		$the_app = & TheAppFactory::getInstance( 'TheAppBuilder' );
		
		include_once('extras/JSMin.php');
		include_once('extras/JSMinPlus.php');
		//include_once('extras/class.JavaScriptPacker.php');  // Packer Code

		switch($_POST['command']){
		case 'deploy':
			// Step 1 - Deploy.  This creates the production directory structure
			// and copies over sencha-touch.js plus other resources
			self::deploy();
			do_action('the_app_factory_build_resources');
			break;
		case 'dependencies':
			self::concat_dependencies();
			break;
		case 'index':
			self::generate_index();
			break;
		case 'get_production_url': // Packer Code
			self::get_production_url();
			break;
		case 'wrapup':
			self::wrapup();
		}
		self::build_succeed(ob_get_clean());
		die();
	}
	
	/**
	 * Deploy the Javascript and CSS for the app
	 *
	 * @action the_app_factory_build_resources
	 */
	public function deploy( $json = null, $target_root = null ){
		$the_app = & TheAppFactory::getInstance();
		$permalink = get_permalink();

		// For this step we need to read app.json
		if (!isset($json)){
			$json = self::get_app_json('development');
		}
		if (!isset($target_root)){
			$target_root = $the_app->get('production_root');
			$relative_target_root = $the_app->get('production_root_url');
		}
		else{
			$relative_target_root = '';
		}
		
		if ( is_dir($the_app->get( 'production_root' ) ) ){
			self::rrmdir($the_app->get( 'production_root' ));
		}

		$minify = $the_app->is('minifying');	

		// Let's do the JS files
		if (is_array($json->js)){
			foreach ($json->js as $key => $js){
				if ($js->path != 'app.js'){ // app.js is handled later
					if (isset($js->remote)){
						$target = $target_root.'resources/js/'.basename($js->path);
						self::build_mkdir(dirname($target));
						self::build_cp($js->path,$target);			
					}
					else{
						$target = $target_root.$js->path;
						self::build_mkdir(dirname($target));
						self::build_cp($permalink.$js->path,$target,$minify);			
					}
				}
			}
		}

		// Now the CSS
		if (is_array($json->css)){
			foreach ($json->css as $key => $css){
				if (isset($css->remote)){
					$target = $target_root.'resources/css/'.basename($css->path);
					self::build_mkdir(dirname($target));
					self::build_cp($css->path,$target,$minify ? 'css' : false);			
				}
				else{
					$target = $target_root.$css->path;
					self::build_mkdir(dirname($target));
					self::build_cp($permalink.$css->path,$target,$minify ? 'css' : false);			
				}
			}
		}

		// Finally, we'll create our app.json file
		$jsonout = new stdClass();
		$jsonout->id = $json->id;
		$jsonout->js = $json->js;
		$jsonout->css = $json->css;

		foreach ($jsonout->js as $key => $js){
			if (isset($js->remote)){
				$js->path = $relative_target_root.'resources/js/'.basename($js->path);
			}
			else{
				$js->path = $relative_target_root.$js->path;
			}
			$jsonout->js[$key] = $js;
		}
		foreach ($jsonout->css as $key => $css){
			if (isset($css->remote)){
				$css->path = $relative_target_root.'resources/css/'.basename($css->path);
			}
			else{
				$css->path = $relative_target_root.$css->path;
			}
			$jsonout->css[$key] = $css;
		}
		echo '[GENERATE] app.json file'."\n";
		$the_app->set_app_json($jsonout);

		return $jsonout;
	}
	
	/**
	 * Returns a message to the front end that the build step failed
	 *
	 * @return dead
	 */
	public function build_fail($message=''){
		header('Content-type: text/javascript');
		echo json_encode(array(
			'success' => false,
			'message' => ob_get_clean()."\n[FAIL] ".$message
		));
		die();
	}

	/**
	 * Returns a message to the front end that the build step succeeded
	 *
	 * @return dead
	 */
	public function build_succeed($message='',$data = ''){
		header('Content-type: text/javascript');
		echo json_encode(array(
			'success' => true,
			'message' => $message,
			'data' => $data
		));
		die();
	}

	/**
	 * Attempts to mkdir (deep) the $target directory
	 *
	 * @return void
	 */
	public function build_mkdir($target){	
		if (!wp_mkdir_p($target)){
			self::build_fail('Could not create build directory at '.$target);
		}
	}

	/**
	 * Fetches (by cURL) the $url and copies it to the $dest
	 *
	 * @return void
	 */
	public function build_cp($url,$dest,$minify = false, $silent = false){
		$fp = fopen($dest,'w');
		$content = self::get_by_curl($url);

		if ($minify){
			if (!$silent){
				echo "[COPY - MINIFIED] ".basename($url)."\n";
			}

			switch(true){
			case ($minify === 'css'):
				$packed = self::compressCSS($content); 
				break;
			default:
				try{
					$packed = JSMin::minify($content);
				}
				catch(Exception $e){
					die('Could not minify '.$url.'.  JSMin returned '.$e->getMessage());
				}
				break;
			}

			fwrite($fp,$packed);
		}
		else{
			if (!$silent){
				echo "[COPY] ".basename($url)."\n";
			}
			fwrite($fp,$content);
		}
		fclose($fp);
	}

	// Thanks http://php.net/manual/en/function.rmdir.php
	// Recursively remove a directory
	public function rrmdir($dir) { 
	  if (is_dir($dir)) { 
	    $objects = scandir($dir); 
	    foreach ($objects as $object) { 
	      if ($object != "." && $object != "..") { 
	        if (filetype($dir."/".$object) == "dir") self::rrmdir($dir."/".$object); else unlink($dir."/".$object); 
	      } 
	    } 
	    reset($objects); 
	    rmdir($dir); 
	  } 
	}

	/**
	 * Compresses CSS.  Which I could remember the site I got this from to give credit.  I sure didn't write it.
	 *
	 * @return void
	 */
	public function compressCSS($css) {
	    return
	        preg_replace(
	            array('@\s\s+@','@(\w+:)\s*([\w\s,#]+;?)@'),
	            array(' ','$1$2'),
	            str_replace(
	                array("\r","\n","\t",' {','} ',';}'),
	                array('','','','{','}','}'),
	                preg_replace('@/\*[^*]*\*+([^/][^*]*\*+)*/@', '', $css)
	            )
	        )
	    ;
	}

	/**
	 * Attempt to cp (deep) $src to $dest
	 *
	 * @return dead
	 */
	public function build_cp_deep($src,$dest,$ignores=null,$quiet = false){
		if (!isset($ignores)){
			$ignores = array('^.DS_Store$','^.svn','^.git');
		}
		$ignores[] = '^\.$';
		$ignores[] = '^\.\.$';
		if (is_dir($src)){
			self::build_mkdir($dest);
			$contents = glob(trailingslashit($src).'{,.}*', GLOB_BRACE);
			foreach ($contents as $file){
				$basename = basename($file);
				$continue = true;
				$target = str_replace($src,$dest,$file);
				foreach ($ignores as $ignore){
					if (preg_match('/'.$ignore.'/',$basename)){
						$continue = false;
					}
				}
				if ($continue){
					if (is_dir($file)){
						self::build_cp_deep($file,$target,$ignores,$quiet);
					}
					else{
						if (!$quiet){
							echo "[COPY] ".str_replace(ABSPATH,'',$file)." to ".str_replace(ABSPATH,'',$target)."\n";
						}
						copy($file,$target);
						
						if (fileperms($file) != fileperms($target)){
							// Makes sure scripts are executable
							chmod($target,fileperms($file));
						}
					}
				}
			}
		}
	}
	
	public function recurse_copy($src,$dst) { 
	    $dir = opendir($src); 
	    @mkdir($dst); 
	    while(false !== ( $file = readdir($dir)) ) { 
	        if (( $file != '.' ) && ( $file != '..' )) { 
	            if ( is_dir($src . '/' . $file) ) { 
	                self::recurse_copy($src . '/' . $file,$dst . '/' . $file); 
	            } 
	            else { 
	                copy($src . '/' . $file,$dst . '/' . $file); 
	            } 
	        } 
	    } 
	    closedir($dir); 
	}

	/**
	 * Gets a URL by curl and returts the result
	 *
	 * @return $result result of curl_exec() - should be the contents of the url
	 */
	public function get_by_curl($url,$ch=null,$url_parms = 'building=true'){
		if (isset($ch)){
			$_ch = & $ch;
		}
		else{
			$_ch = curl_init();
		}
		curl_setopt($_ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($_ch, CURLOPT_HEADER, false);
		curl_setopt($_ch, CURLOPT_FOLLOWLOCATION, true);
		$sep = (strpos($url,'?') === false ? '?' : '&');
		curl_setopt($_ch, CURLOPT_URL, $url.$sep.$url_parms);

		// Access this hook if you need to add anything like user authentication
		do_action_ref_array('the_app_factory_get_by_curl',array(&$_ch));

		$result = curl_exec($_ch);

		if (!isset($ch)){
			curl_close($_ch);
		}
		return $result;
	}


	/**
	 * Concatenates all dependencies (as sent in $_POST['dependencies']) into 
	 * a single file
	 *
	 * @return void
	 */
	public function concat_dependencies( $target_root = null ){
		set_time_limit(0);

		$the_app = & TheAppFactory::getInstance();
		if ( !isset($target_root) ){
			$target_root = $the_app->get('production_root');
		}
		$dependencies = json_decode(stripslashes($_POST['dependencies']));
		$permalink = get_permalink();

		$app_js = '';
		$minify = $the_app->is('minifying');

		echo '[FOUND] '.count($dependencies).' dependencies.  Concatenating all into app.js';

		$fp = fopen($target_root.'app.js','w');
		$ch = curl_init();
		foreach ($dependencies as $dependency){
			if (substr($dependency->path,0,3) == 'sdk'){
				// These are safe just to get via file system
				$path = APP_FACTORY_PATH.'the-app/'.$dependency->path;
				$content = file_get_contents($path);
			}
			else{
				// I get these via HTTP, because some might be dynamic
				$path = $permalink.$dependency->path;
				$path = apply_filters('get_dependency_for_concatenation_url', $path );
				$content = self::get_by_curl($path,$ch);
			}
			try{
				fwrite($fp, $minify ? JSMin::minify($content) : $content );
			}
			catch(Exception $e){
				die('Could not minify '.$path.': '.$content.''."\n\n".'  JSMin returned '.$e->getMessage());
			}
		}

		// finally, do app.js itself
		$path = $permalink.'app.js';
		$path = apply_filters('get_dependency_for_concatenation_url', $path );
		$content = self::get_by_curl($path,$ch);
		fwrite($fp, $minify ? JSMin::minify($content) : $content );

		curl_close($ch);
		fclose($fp);
	}

	public function generate_index(){
		$the_app = & TheAppFactory::getInstance();
		$permalink = get_permalink();

		$index = self::get_by_curl($permalink);

		// For this step we need to read app.json
		$json = self::get_app_json('development');
		$jsonout = new stdClass;
		$jsonout->id = $json->id;

		$microloader = file_get_contents(APP_FACTORY_PATH."the-app/sdk{$the_app->get('sdk')}/microloader/production.js");
		if ( $the_app->is( 'minifying' ) ){
			$microloader = JSMin::minify( $microloader );
		}

		$target = $the_app->get('production_root').'index.html';

		$index = preg_replace('/<html manifest=""/', '<html manifest="'.$permalink.'manifest"',$index);
		$index = preg_replace('/<script id="microloader"([^<]+)<\/script>/','
	<script type="text/javascript">
	'.$microloader.';Ext.blink('.json_encode($jsonout).');
	</script>
		',$index);

		$fh = fopen($target,'w');
		fwrite($fh,$index);
		fclose($fh);

		echo '[GENERATE] index.html';
	}

	public function get_production_url(){
		$the_app = & TheAppFactory::getInstance();
		$json = self::get_app_json();
		self::build_succeed('',array(
			'root' => $the_app->get('production_root_url'),
			'js' => $json->js
		));
	}

	public function get_app_json($which = 'production'){
		$the_app = & TheAppFactory::getInstance();
		$permalink = get_permalink();
		switch($which){
		case 'development':
			$json = self::get_by_curl($permalink.'app.json');
			break;
		case 'production':
		default:
			$json = file_get_contents($the_app->get('production_root').'app.json');
			break;
		}
		
		return TheAppFactory::sanitize_json( $json );
	}

	public function set_app_json($json){
		$the_app = & TheAppFactory::getInstance();
		$dest = $the_app->get('production_root').'app.json';
		$fh = fopen($dest,'w');
		fwrite($fh,json_encode($json));
		fclose($fh);
	}

	public function wrapup(){
		$the_app = & TheAppFactory::getInstance();
		$json = self::get_app_json();

		// Need to generate the versions and make sure that updates are set to full
		// I decided to move this here to the wrapup section so that I could be sure to 
		// get it all one in one fell swoop
		$these = array('js','css');
		foreach ($these as $that){
			foreach ($json->$that as $key => $thing){
				if ( strpos( $thing->path, $the_app->get('production_root_url') ) !== false ){
					$thing->remote = false;
				}
				$path = str_replace($the_app->get('production_root_url'),$the_app->get('production_root'),$thing->path);
				$thing->update = 'full';
				if (!file_exists($path)){
					self::build_fail(sprintf(__('could not find %s','app-factory'),$thing->path));
				}
				$content = file_get_contents($path);

				// Remove previous version comments from file
				while( preg_match('#/\*.+\*/#', substr($content,0,36) ) ){
					$content = substr($content,36);
				}
				$version = md5($content);

				$fp = fopen($path,'w');
				fwrite($fp,'/*'.$version.'*/');
				fwrite($fp,$content);
				fclose($fp);

				$thing->version = $version;

				$json->{$that}[$key] = $thing;
			}
		}
		self::set_app_json($json);
		echo "[PREPARED] app.json file with file versions";
	}
	
	public function build_helper( $helpers, $args ){
		$the_app = & $args[0];
		$helpers['WP']['isMinifying'] = $the_app->is('minifying');
		return $helpers;
	}
	
	public static function build_metabox( $app ){	
		// Use nonce for verification
		$the_app = & TheAppFactory::getInstance( 'TheAppBuilder' ); 

		?>
		<p><?php printf(__('Build and test your app and when you are ready to release it to the public, hit this Build button:','app-factory')); ?></p>
		<a href="<?php bloginfo('url'); ?>/<?php echo APP_POST_TYPE; ?>/<?php echo $app->post_name; ?>::build" class="button-primary" target="_blank"><?php echo __('Build','app-factory'); ?></a>
		<a href="<?php bloginfo('url'); ?>/<?php echo APP_POST_TYPE; ?>/<?php echo $app->post_name; ?>::build_no_minify" class="button-primary" target="_blank"><?php echo __('Build (no minify)','app-factory'); ?></a>
		<p><?php printf(__('The Build process optimizes your app such that it loads and runs faster.  It does this by launching your app, gathering the list of file dependencies and then concatenating all javascript files into a single, minimized file. ','app-factory')); ?></p>
		<?php

		if ( file_exists($the_app->get('production_root').'index.html') ){
			echo '<p class="description">'.sprintf(__('When switching from production to development, if you\'re testing in Chrome, you should visit %s and clear the Application Cache for %s','app-factory'),'<code>chrome://appcache-internals</code>','<code>'.get_permalink().'manifest</code>').'</p>';
		}
	}
	
}
?>