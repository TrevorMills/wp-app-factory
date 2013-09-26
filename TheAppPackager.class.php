<?php
class TheAppPackager extends TheAppBuilder {
	
	public function setup(){
		add_filter('TheAppFactory_stores',array(&$this,'package_stores'),100,2);
		add_filter('get_dependency_for_concatenation_url',array(&$this,'maybe_append_url_parms'));
		
		self::setup_environment();
	}
	
	public function setup_environment(){
		$the_app = & TheAppFactory::getInstance();
		
		global $post;
		$uploads = wp_upload_dir();

		// Some vars for packaging
		$the_app->set('package_root',trailingslashit($uploads['basedir'])); // multisite safe - uploads or files directory
		$the_app->set('package_root_url',trailingslashit($uploads['baseurl']));
		$the_app->set('package_name', preg_replace('/[^a-zA-Z0-9]/','',$post->post_title));
		
		// Are we actually packaging and is there a target set
		$targets = self::get_available_targets();
		if( preg_match('/^package_('.implode('|',array_keys($targets)).')/',get_query_var(APP_COMMAND_VAR),$matches) ){
			$the_app->set('package_target',$matches[1]);		
			$the_app->is('minifying',strpos(get_query_var(APP_COMMAND_VAR),'no_minify') === false);
		}
		elseif( $_REQUEST['action'] == 'package_app' and isset($_REQUEST['target']) ){
			$the_app->set('package_target',$_REQUEST['target']);
			$the_app->is('packaging',true);
		}
		elseif( isset($_REQUEST['packaging']) and $_REQUEST['packaging'] == 'true' and isset($_REQUEST['target'])){
			$the_app->set('package_target',$_REQUEST['target']);
			$the_app->is('packaging',true);
		}

		$relative = array();
		foreach ( array_keys($targets) as $target ){
			$relative[$target] = trailingslashit( "wp-app-factory/build/native/$target/$post->post_name" );
			$the_app->set("package_native_root_$target",$the_app->get('package_root') . $relative[$target] );
			$the_app->set("package_native_root_url_$target", $the_app->get('package_root_url') . $relative[$target] );
			if ($target == 'android'){
				$the_app->set("package_native_www_$target", $the_app->get("package_native_root_$target") . 'assets/www/' );
			}
			else{
				$the_app->set("package_native_www_$target", $the_app->get("package_native_root_$target") . 'www/' );
			}
		}

		if ( $the_app->get('package_target') ){
			$the_app->set( 'package_native_root', $the_app->get( 'package_native_root_' . $the_app->get( 'package_target' ) ) );
			$the_app->set( 'package_native_root_url', $the_app->get( 'package_native_root_url_' . $the_app->get( 'package_target' ) ) );
			$the_app->set( 'package_native_www', $the_app->get( 'package_native_www_' . $the_app->get( 'package_target' ) ) );
		}

		if ( isset($_REQUEST['minify'])){
			$the_app->is('minifying',($_REQUEST['minify'] == 'true'));
		}

		// Additional vars for administrators who are doing the actual packaging
		if (current_user_can('administrator') && substr(get_query_var(APP_COMMAND_VAR),0,7) == 'package'){
			if (array_key_exists($the_app->get('package_target'),$targets)){
				$the_app->is('doing_package_command',true);

				// If packaging, we want to make sure that the Desktop is an allowed browser
				$the_app->apply('meta',array(
					'unacceptable_browser' => array(
						'desktop' => false
					)
				));
				$the_app->enqueue('controller','Package');
				add_filter('TheAppFactory_helpers',array(&$this,'target_helper'),10,2);
			}
		}

		if ( current_user_can('administrator') and $the_app->is('doing_package_command')){
			// We are building or packaging the app.  Set the environment to development so as to load the dev files
			$the_app->set('environment', 'development');
		}
		elseif ($the_app->is('packaging') ){
			$the_app->set('environment', 'native');
		}
		else{
			TheAppFactory::set_environment();
		}
		$the_app->is('native',substr($the_app->get('environment'),0,6) == 'native');	

		if (substr(get_query_var(APP_COMMAND_VAR),0,7) == 'package' and get_query_var( APP_DATA_VAR ) != ''){
			$the_app->is('packaging',true);
		}		
	}
	
	public function get_available_targets(){
		$targets = array(
			'ios' => __('iOS','app-factory'),
			'android' => __('Android','app-factory'),
			'pb' => __('Phonegap Build','app-factory')
		);
		return $targets;
	}
	
	public function package_app(){

		global $post;
		$post = get_post($_POST['id']);

		ob_start();

		$the_app = TheAppFactory::getInstance( 'TheAppPackager' );
		
		include_once('extras/JSMin.php');
		include_once('extras/JSMinPlus.php');
		//include_once('extras/class.JavaScriptPacker.php');  // Packer Code

		$the_app = & TheAppFactory::getInstance();
		switch($_POST['command']){
		case 'cordova':
			self::package_cordova();
			break;
		case 'deploy':
			// Step 1 - Deploy.  This creates the package directory structure
			// and copies over sencha-touch.js plus other resources
			self::deploy();
			break;
		case 'resources':
			self::package_resources();
			break;
		case 'dependencies':
			parent::concat_dependencies( $the_app->get('package_native_www') ); 
			break;
		case 'index':
			self::generate_package_index();
			break;
		case 'get_package_url': // Packer Code
			self::get_package_url();
			break;
		case 'wrapup':
			self::wrapup();
			break;
		case 'zip':
			echo admin_url('admin-ajax.php')."?post=$post->ID&action=download_package_zip&target={$the_app->get('package_target')}";
			break;
		}
		parent::build_succeed(ob_get_clean());
		die();
	}

	public function package_cordova(){
		// Start by deleting the existing directory
		$the_app = & TheAppFactory::getInstance();
		if (is_dir($the_app->get( 'package_native_root' ))){
			self::rrmdir($the_app->get( 'package_native_root' ));
		}

		// Copy over the shell Cordova project into the NATIVE directory.  
		// This contains the Cordova lib and a shell app (for iOS or Android, depending)
		$the_app = & TheAppFactory::getInstance();
		if ($the_app->get('package_target') == 'pb' ){ // Phonegap Build doesn't need Cordova, just a www directory
			parent::build_mkdir( $the_app->get( 'package_native_root' ).'www' );
		}
		else{
			parent::build_cp_deep( APP_FACTORY_PATH.'extras/cordova/'.$the_app->get('package_target'), $the_app->get( 'package_native_root' ), null, true );
		}

		// There are a handful of places that we need to insert the actual name of our App
		// Here is where we do that.

		// Some Shorthands
		$name = $the_app->get('package_name');
		$target = $the_app->get('package_target');
		$native_root = $the_app->get('package_native_root');
		$native_www = $the_app->get('package_native_www');


		$app_meta = the_app_get_app_meta( $the_app->get('post')->ID );
		$app_identifier = $app_meta['bundle_id'].'.'.$name;	

		// First, we'll rename some files and directories
		switch( $target ){
		case 'ios':
			// The MyApp directory
			rename( "{$native_root}MyApp/MyApp-Info.plist", "{$native_root}MyApp/$name-Info.plist" );
			rename( "{$native_root}MyApp/MyApp-Prefix.pch", "{$native_root}MyApp/$name-Prefix.pch" );
			rename( "{$native_root}MyApp", "{$native_root}{$name}" ); // rename the directory itself

			// The MyApp.xcodeproj directory/package
			rename( "{$native_root}MyApp.xcodeproj", "{$native_root}{$name}.xcodeproj" ); 

			// Now, we have to go through some files and replace occurrences of MyApp with $name
			$files = array(
				"$name/Classes/AppDelegate.h",
				"$name/Classes/AppDelegate.m",
				"$name/Classes/MainViewController.h",
				"$name/config.xml",
				"$name/main.m",
				"$name/$name-Prefix.pch",
				"$name.xcodeproj/project.pbxproj",
			);

			foreach ($files as $file){
				$contents = file_get_contents( $native_root . $file );
				$contents = str_replace( 'MyApp', $name, $contents );
				file_put_contents( $native_root . $file, $contents );
			}

			// Update the Bundle Identifier in the .plist file
			$contents = file_get_contents( "{$native_root}{$name}/$name-Info.plist" );
			$contents = str_replace( '__APP_BUNDLE_IDENTIFIER__', $app_identifier, $contents);
			file_put_contents( "{$native_root}{$name}/$name-Info.plist", $contents );
			break;
		case 'android':
			rename( "{$native_root}src/MyApp.java", "{$native_root}src/$name.java" );

			// Now, we have to go through some files and replace occurrences of MyApp with $name
			$files = array(
				'AndroidManifest.xml',
				'build.xml',
				'res/layout/main.xml',
				'res/xml/config.xml',
				'res/values/strings.xml',
				"src/$name.java"
			);

			foreach ($files as $file){
				$contents = file_get_contents( $native_root . $file );
				$contents = str_replace( 'MyApp', $name, $contents );
				$contents = str_replace( '__APP_BUNDLE_IDENTIFIER__', $app_identifier, $contents );
				file_put_contents( $native_root . $file, $contents );
			}

			// create the proper directory structure in src
			$target_dest = $native_root.'src/'.str_replace('.','/',$app_meta['bundle_id']).'/'.$name;
			wp_mkdir_p($target_dest);

			rename( $native_root.'src/'.$name.'.java',$target_dest.'/'.$name.'.java');
			break;
		}

		echo "[SETUP] A shell CORDOVA project has been setup";
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

	public function deploy(){

		$the_app = & TheAppFactory::getInstance();

		$permalink = get_permalink();

		// For this step we need to read app.json
		$json = self::get_app_json('development');	

		if ($the_app->get('package_target') != 'pb'){ // Phonegap Build puts Cordova in itself (I think)
			array_unshift( $json->js, (object)array(
				'path' => APP_FACTORY_URL . 'extras/cordova/js/cordova-'.$the_app->get('package_target').'.js',
				'remote' => true,
				'minify' => $the_app->is('minifying'),
				'update' => 'full'
			) );
			array_unshift( $json->js, (object)array(
				'path' => APP_FACTORY_URL . 'extras/cordova/js/cordova_plugins.json',
				'remote' => true,
				'minify' => $the_app->is('minifying'),
				'update' => 'full'
			) );
		}
		
		$jsonout = parent::deploy( $json, $the_app->get('package_native_www') );
		
		$jsonout = apply_filters('the_app_factory_package_app_json',$jsonout);

		$the_app->set_app_json($jsonout);

	}

	public function package_resources(){
		// Maybe deploy initial data
		self::deploy_data();

		self::deploy_app_images();

		do_action('the_app_factory_package_resources');
	}

	public function deploy_data(){
		$the_app = & TheAppFactory::getInstance();

		if ($the_app->is('using_manifest')){
			// They are using the manifest, meaning they want the data to be available
			// offline.  So, we're going to get all of the initial data and store it
			// with the packaged app.
			parent::build_mkdir( $the_app->get('package_native_www').'resources/data');
			foreach ($the_app->get('stores') as $store){
				if (isset($store['proxy']) and $store['useLocalStorage']){
					parent::build_cp($store['proxy']['url'], $the_app->get('package_native_www')."resources/data/{$store['model']}.json");
				}
			}
		}	
	}

	public function deploy_app_images(){
		$the_app = & TheAppFactory::getInstance();

		switch( $the_app->get('package_target') ){
		case 'ios':
			if ($the_app->get('icon')){
				self::deploy_ios_icons($the_app->get('icon'));
			}
			if ($the_app->get('startup_phone')){
				self::deploy_ios_startup_phone($the_app->get('startup_phone'));
			}
			if ($the_app->get('startup_tablet')){
				self::deploy_ios_startup_tablet($the_app->get('startup_tablet'));
			}
			if ($the_app->get('startup_landscape_tablet')){
				self::deploy_ios_startup_landscape_tablet($the_app->get('startup_landscape_tablet'));
			}
			break;
		case 'android':
			if ($the_app->get('icon')){
				self::deploy_android_icons($the_app->get('icon'));
			}
			break;
		case 'pb':
			if ($the_app->get('icon')){
				// Store both, let the user decide if they're going to upload them.
				self::deploy_ios_icons($the_app->get('icon'));
				self::deploy_android_icons($the_app->get('icon'));
			}
			break;
		}
	}

	public function deploy_ios_icons( $url ){
		$sizes = array(
			72 => 'icon-72.png',
			144 => 'icon-72@2x.png',
			57 => 'icon.png',
			114 => 'icon@2x.png',
			60 => 'icon-60.png',
			120 => 'icon-60@2x.png',
			76 => 'icon-76.png',
			114 => array('icon@2x.png','icon-76@2x.png')
		);

		self::create_images( $url, 'icons', $sizes );
	}

	public function deploy_android_icons( $url ){
		$sizes = array(
			96 => array('drawable/icon.png','drawable-xhdpi/icon.png'),
			72 => 'drawable-hdpi/icon.png',
			36 => 'drawable-ldpi/icon.png',
			48 => 'drawable-mdpi/icon.png',

		);

		self::create_images( $url, 'icons', $sizes );
	}

	public function create_images( $url, $type, $sizes ){
		// Returns an object with:
		//	->image = image resource
		//  ->size = the result from getimagesize()
		$image = self::curl_image( $url );

		$the_app = & TheAppFactory::getInstance();

		if ($image){
			foreach ($sizes as $pixels => $filename){
				switch ($type){
				case 'icons':
					$width = $pixels;
					$height = $pixels;
					break;
				case 'splash':
					list($width,$height) = explode('x',$pixels);
					break;
				}
				if ($width != $image->width or $height != $image->height){
					$dest = imagecreatetruecolor( $width, $height );
					imagealphablending($dest, false);
			        $color = imagecolortransparent($dest, imagecolorallocatealpha($dest, 0, 0, 0, 127));
			        imagefill($dest, 0, 0, $color);
			        imagesavealpha($dest, true);

					imagecopyresampled( $dest, $image->image, 0, 0, 0, 0, $width, $height, $image->width, $image->height );
				}
				else{
					$dest = & $image->image;
				}
				
				
				switch( $the_app->get('package_target') ){
				case 'ios':
					$relative_path = $the_app->get('package_name') . "/Resources/$type/";
					break;
				case 'android':
					$relative_path = "res/";
					break;
				case 'pb':
				default:
					$relative_path = "$type/";
					break;
				}
				

				if (is_array($filename)){
					foreach ($filename as $name){
						parent::build_mkdir( dirname($the_app->get('package_native_root') . "$relative_path/$name") );
						imagepng( $dest, $the_app->get('package_native_root') . "$relative_path/$name" );
						echo "[CREATED] $name\n";
					}
				}
				else{
					parent::build_mkdir( dirname($the_app->get('package_native_root') . "$relative_path/$filename") );
					imagepng( $dest, $the_app->get('package_native_root') . "$relative_path/$filename" );
					echo "[CREATED] $filename\n";
				}


				imagedestroy( $dest );
			}
		}
	}

	public function deploy_ios_startup_phone( $url ){
		$sizes = array(
			'320x480' => 'Default~iphone.png',
			'640x960' => 'Default@2x~iphone.png',
			'640x1136' => 'Default-568h@2x~iphone.png'
		);

		self::create_images( $url, 'splash', $sizes );

	}

	public function deploy_ios_startup_tablet( $url ){
		$sizes = array(
			'736x1004' => 'Default-Portrait~ipad.png',
			'1536x2008' => 'Default-Portrait@2x~ipad.png',
		);

		self::create_images( $url, 'splash', $sizes );
	}

	public function deploy_ios_startup_landscape_tablet( $url ){
		$sizes = array(
			'1024x748' => 'Default-Landscape~ipad.png',
			'2048x1496' => 'Default-Landscape@2x~ipad.png',
		);

		self::create_images( $url, 'splash', $sizes );
	}

	public function curl_image( $url ){
		$ch = curl_init(str_replace(array(' '),array('%20'),$url));
		$filename = tempnam( '/tmp', 'afoy_image_');
		$fp = fopen($filename,'wb');

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		$result = curl_exec($ch);
		$mime = curl_getinfo($ch,CURLINFO_CONTENT_TYPE);
		fclose($fp);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($result and $code != 404){		
			switch($mime){
			case 'image/png':
				$image = imagecreatefrompng( $filename );
				//imagealphablending($image, true);
				//imagesavealpha($image, true);
				break;
			case 'image/jpeg':
				$image = imagecreatefromjpeg( $filename );
				break;
			case 'image/gif':
				$image = imagecreatefromgif( $filename );
				break;
			}

			$size = getimagesize( $filename );

			@unlink( $filename );
			return (object)array(
				'image' => $image,
				'width' => $size[0],
				'height' => $size[1]
			);
		}
		@unlink($path . $filename);
		return false;
	}

	public function generate_package_index(){
		$the_app = & TheAppFactory::getInstance();
		$permalink = get_permalink();
		ob_start();
		include(APP_FACTORY_PATH.'the-app/index.php');
		$index = ob_get_clean(); //self::get_by_curl($permalink);

		// For this step we need to read app.json
		$json = self::get_app_json();
		$jsonout = new stdClass;
		$jsonout->id = $json->id;
		$jsonout->js = $json->js;
		$jsonout->css = $json->css;

		//$microloader = JSMin::minify(file_get_contents(APP_FACTORY_PATH."the-app/sdk{$the_app->get('sdk')}/microloader/development.js"));

		$target = $the_app->get('package_native_www').'index.html';

		// Don't need a cache manifest (I don't think) in the packaged build
		//$index = preg_replace('/<html manifest=""/', '<html manifest="'.$permalink.'manifest"',$index);

		// Here's the microloader script for packaging.  This was copied directly from a manually (i.e. using Sencha Cmd) built app.  
		ob_start(); ?>
	<script type="text/javascript">
		(function(h){
			function f(c,d){
				document.write('<meta name="'+c+'" content="'+d+'">')
			}
			if("undefined"===typeof g)
				var g=h.Ext={};

			g.blink=function(c){
				var d=c.js||[],
					c=c.css||[],
					b,e,a;
				f("viewport","width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no");
				f("apple-mobile-web-app-capable","yes");
				f("apple-touch-fullscreen","yes");
				b=0;
				for(e=c.length;b<e;b++)
					a=c[b],
					"string"!=typeof a&&(a=a.path),
					document.write('<link rel="stylesheet" href="'+a+'">');
				b=0;
				for(e=d.length;b<e;b++)
					a=d[b],
					"string"!=typeof a&&(a=a.path),
					document.write('<script src="'+a+'"><\/script>')
			}
		})(this);
		var blink_config = <?php echo json_encode($jsonout); ?>;
		if (location.search.indexOf('no_cordova') != -1){
			blink_config.js = blink_config.js.filter(function(item){
				return item.path.indexOf('cordova') == -1;
			});
		}
		Ext.blink(blink_config)
	</script>
		<?php 

		$script = $the_app->is('minifying') ? JSMin::minify(ob_get_clean()) : ob_get_clean();
		$index = preg_replace('/<script id="microloader"([^<]+)<\/script>/',$script,$index);

		$fh = fopen($target,'w');
		fwrite($fh,$index);
		fclose($fh);

		echo '[GENERATE] index.html';
	}

	public function get_package_url(){
		$the_app = TheAppFactory::getInstance();
		$json = self::get_app_json(); 
		parent::build_succeed('',array(
			'root' => $the_app->get('package_native_root_url'),
			'js' => $json->js
		));
	}

	public function get_app_json($which = 'packaged'){
		$the_app = & TheAppFactory::getInstance();
		$permalink = get_permalink();
		switch($which){
		case 'development':
			$json = self::get_by_curl($permalink.'app.json');
			break;
		case 'packaged':
		default:
			$json = file_get_contents($the_app->get('package_native_www').'app.json');
			break;
		}
		
		return TheAppFactory::sanitize_json( $json );
	}

	public function set_app_json($json){
		$the_app = TheAppFactory::getInstance();
		$dest = $the_app->get('package_native_www').'app.json';
		$fh = fopen($dest,'w');
		fwrite($fh,json_encode($json));
		fclose($fh);
	}

	public function wrapup(){
		$json = self::get_app_json();

		self::set_app_json($json);
		echo "[PREPARED] app.json file";

	}

	public function package_download(){
		//preg_match('/download_([^_]+)_zip/',$_GET['action'],$matches);
		$_REQUEST['target'] = $_GET['target'];
		$_REQUEST['packaging'] = 'true';

		global $post;
		$post = get_post( $_GET['post'] );
		
		$the_app = & TheAppFactory::getInstance( 'TheAppPackager' );

		$tmp = tempnam('/tmp','afoy_package_');

		$zip = self::zip( $the_app->get('package_native_root'), $tmp );

		if ($zip){
			header('Content-type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.$the_app->get('package_name').'.zip"');
			readfile($tmp);
		}
		else{
			echo "There were problems creating the ZIP file.  Get in there and figure out why.";
		}

		if (file_exists($tmp)){
			unlink($tmp);
		}
		die();
	}

	public function zip($source, $destination)
	{
		// Thanks http://stackoverflow.com/questions/1334613/how-to-recursively-zip-a-directory-in-php
	    if (!extension_loaded('zip') || !file_exists($source)) {
	        return false;
	    }

	    $zip = new ZipArchive();
	    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
	        return false;
	    }

	    $source = str_replace('\\', '/', realpath($source));

	    if (is_dir($source) === true)
	    {
	        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

	        foreach ($files as $file)
	        {
	            $file = str_replace('\\', '/', $file);

	            // Ignore "." and ".." folders
	            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
	                continue;

	            $file = realpath($file);

	            if (is_dir($file) === true)
	            {
	                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
	            }
	            else if (is_file($file) === true)
	            {
	                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
	            }
	        }
	    }
	    else if (is_file($source) === true)
	    {
	        $zip->addFromString(basename($source), file_get_contents($source));
	    }

	    return $zip->close();
	}

	public function package_image( $atts=array(), $content=null, $code='' ){
		$the_app = & TheAppFactory::getInstance();
		$image_url = $content;
		if ( $the_app->is('packaging') ){ 
			$relative_dest = 'resources/images/'.basename($image_url);
			$dest = $the_app->get( 'package_native_www' ) . $relative_dest;
			parent::build_mkdir(dirname($dest));
			parent::build_cp( $image_url, $dest, false, true ); // no minify, silent
			return $relative_dest;
		}
		return $image_url;
	}

	public function package_stores( $stores, $args ){
		$the_app = & $args[0];
		// For the packaged app we're going to (maybe) reset the URLs with 
		// the packaging url, to allow us to massage data as need be
		if ( $the_app->is('packaging') ){ 
			$permalink = get_permalink();
			global $post;
			$packaged_permalink = dirname($permalink)."/$post->post_name::package_".$the_app->get('package_target').'/';
			foreach ($stores as $key => $store){
				if (isset($store['proxy']) and isset($store['proxy']['url'])){
					$stores[$key]['proxy']['url'] = str_replace( $permalink, $packaged_permalink, $store['proxy']['url']);
				}
			}
		}

		return $stores;
	}
	
	public function maybe_append_url_parms( $path ){
		$the_app = & TheAppFactory::getInstance();
		if ($the_app->is('packaging')){
			$path.= '?packaging=true&target='.$the_app->get('package_target');
		}
		return $path;
	}
	
	public function target_helper( $helpers, $args ){
		$the_app = & $args[0];
		$helpers['WP']['packageTarget'] = $the_app->get('package_target');
		$helpers['WP']['isMinifying'] = $the_app->is('minifying');
		return $helpers;
	}

	public static function package_metabox( $app ){
		$the_app = & TheAppFactory::getInstance( 'TheAppPackager' ); 

		$app_meta = the_app_get_app_meta( $app->ID );

		?>
		<p><?php echo __('Packaging your app prepares it for submission into the App Store (iOS) or the Android Marketplace (Android).','app-factory'); ?></p>
		<p><?php echo __('The packaged app is delivered as a .zip file that contains a project that you can open and run in XCode (iOS) or build using the Android SDK (Android).','app-factory'); ?></p>
			
		<p>
			<strong><?php printf(__('You have to give your app a bundle identifier.  This is usually your domain name backwards, followed by the app name (i.e. com.example.%s).','app-factory'),$the_app->get('package_name')); ?></strong><br/>
			<?php echo __('Bundle Identifier','app-factory'); ?>: <input type="text" name="app_meta[bundle_id]" value="<?php echo esc_attr($app_meta['bundle_id']); ?>" />.<?php echo $the_app->get('package_name'); ?>
		</p>
		
		<p>
			<?php printf( __( 'If you wish to serve data from a live server (making it available for beta testers), enter the app url on the live server:','app-factory' ) ); ?><br/>
			<?php echo __('Live Data URL','app-factory'); ?>: <input type="text" name="app_meta[data_url]" value="<?php echo esc_attr($app_meta['data_url']); ?>" />
		</p>

		<?php if (!empty($app_meta['bundle_id'])) : 
			$targets = self::get_available_targets();
			foreach ($targets as $target => $label) : ?>
				<a href="<?php bloginfo('url'); ?>/<?php echo APP_POST_TYPE; ?>/<?php echo $app->post_name; ?>::package_<?php echo $target; ?>" class="button-primary" target="_blank"><?php printf(__( 'Package %s', 'app-factory' ),$label); ?></a>
				<a href="<?php bloginfo('url'); ?>/<?php echo APP_POST_TYPE; ?>/<?php echo $app->post_name; ?>::package_<?php echo $target; ?>_no_minify" class="button-primary" target="_blank"><?php printf(__( 'Package %s (no minify)', 'app-factory' ),$label); ?></a>
				<?php	
				if (file_exists( $the_app->get("package_native_www_$target") ) ){
					echo '<p>'.sprintf(__('You have a packaged %s app ready to go.  %sDownload as ZIP%s'),$label,'<a href="'.admin_url('admin-ajax.php')."?post=$app->ID&action=download_package_zip&target=$target" . '" target="_blank">','</a>').'</p>';
				}
			endforeach; 
		endif;
	}
	
	public function get_by_curl($url,$ch=null,$url_parms = null){
		return parent::get_by_curl( $url, $ch, 'packaging=true&target='.self::$instance->get('package_target'));
	}
	
	
}
?>