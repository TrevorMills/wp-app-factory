<?php 

add_action('wp_ajax_package_app','the_app_factory_package_app');
function the_app_factory_package_app(){
	
	global $post;
	$post = get_post($_POST['id']);
	
	ob_start();
	
	setup_the_app_factory();
	include_once('extras/JSMin.php');
	include_once('extras/JSMinPlus.php');
	//include_once('extras/class.JavaScriptPacker.php');  // Packer Code
	
	$the_app = & TheAppFactory::getInstance();
	switch($_POST['command']){
	case 'cordova':
		the_app_factory_package_cordova();
		break;
	case 'deploy':
		// Step 1 - Deploy.  This creates the package directory structure
		// and copies over sencha-touch.js plus other resources
		the_app_factory_package_deploy();
		break;
	case 'resources':
		the_app_factory_package_resources();
		break;
	case 'dependencies':
		the_app_factory_concat_dependencies( $the_app->get('package_native_www') ); // defined in the-app-builder.php
		break;
	case 'index':
		the_app_factory_generate_package_index();
		break;
	case 'get_package_url': // Packer Code
		the_app_factory_get_package_url();
		break;
	case 'wrapup':
		the_app_factory_package_wrapup();
		break;
	case 'zip':
		echo admin_url('admin-ajax.php')."?post=$post->ID&action=download_".$the_app->get('package_target')."_zip";
		break;
	}
	build_succeed(ob_get_clean());
	die();
}

function setup_package_environment( $target = null ){
	// We are going to create a directory within wp-content that will
	// contain all of the package files for our apps.
	
	/*
	define('APP_PACKAGE_ROOT',trailingslashit($uploads['basedir'])); // multisite safe - uploads or files directory
	define('APP_PACKAGE_ROOT_URL',trailingslashit($uploads['baseurl'])); // multisite safe - uploads or files directory
	if ( isset( $_REQUEST['target'] ) ){
		define('APP_PACKAGE_TARGET',$_REQUEST['target']);
	}
	elseif(get_query_var(APP_COMMAND_VAR) != '' and preg_match( '/package_(ios|android)/', get_query_var(APP_COMMAND_VAR), $matches)){
		define('APP_PACKAGE_TARGET',$matches[1]);
	}
	if (isset($_REQUEST['minify']) and $_REQUEST['minify'] == 'false'){
		define('APP_PACKAGE_MINIFY',false);
	}
	else{
		define('APP_PACKAGE_MINIFY',true);
	}
	
	if (isset($_REQUEST['action']) and $_REQUEST['action'] == 'package_app'){
		define('APP_IS_PACKAGING',true);
	}
	else{
		define('APP_IS_PACKAGING',false);
	}
	
	
	global $post;
	$relative = trailingslashit('wp-app-factory/build/native/'. ( defined('APP_PACKAGE_TARGET') ? APP_PACKAGE_TARGET : '%s' ) . '/'.$post->post_name);

	$target = APP_PACKAGE_ROOT.$relative; //.'/package/production';
	
	define('APP_PACKAGE_NAME',preg_replace('/[^a-zA-Z0-9]/','',$post->post_title));
	
	define('APP_NATIVE_ROOT_SPRINTF', APP_PACKAGE_ROOT . trailingslashit('wp-app-factory/build/native/%s/'.$post->post_name) );
	
	define('APP_NATIVE_ROOT',$target);
	if (defined('APP_PACKAGE_TARGET')){
		switch(APP_PACKAGE_TARGET){
		case 'ios':
			define('APP_NATIVE_WWW',$target.'www/');
			break;
		case 'android':
			define('APP_NATIVE_WWW',$target.'assets/www/');
			break;
		}
	}
	//define('APP_NATIVE_WWW',$target.'www/');

	define('APP_NATIVE_ROOT_URL',APP_PACKAGE_ROOT_URL.$relative);
	
	if (defined('APP_PACKAGE_TARGET') and !wp_mkdir_p($target)){
		build_fail('Could not create package directory at '.$target);
	}
	*/
}

function the_app_factory_package_cordova(){
	// Start by deleting the existing directory
	$the_app = & TheAppFactory::getInstance();
	if (is_dir($the_app->get( 'package_native_root' ))){
		rrmdir($the_app->get( 'package_native_root' ));
	}
	
	// Copy over the shell Cordova project into the NATIVE directory.  
	// This contains the Cordova lib and a shell app (for iOS or Android, depending)
	$the_app = & TheAppFactory::getInstance();
	build_cp_deep( APP_FACTORY_PATH.'extras/cordova/'.$the_app->get('package_target'), $the_app->get( 'package_native_root' ), null, true );
	
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
function rrmdir($dir) { 
  if (is_dir($dir)) { 
    $objects = scandir($dir); 
    foreach ($objects as $object) { 
      if ($object != "." && $object != "..") { 
        if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object); 
      } 
    } 
    reset($objects); 
    rmdir($dir); 
  } 
}

function the_app_factory_package_deploy(){

	$the_app = & TheAppFactory::getInstance();

	$permalink = get_permalink();

	// For this step we need to read app.json
	$json = the_app_factory_get_app_json('development');	
	
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

	// Let's do the JS files
	if (is_array($json->js)){
		foreach ($json->js as $key => $js){
			if ($js->path != 'app.js'){ // app.js is handled later
				if (isset($js->remote)){
					$target = $the_app->get('package_native_www').'resources/js/'.basename($js->path);
					build_mkdir(dirname($target));
					build_cp($js->path,$target, isset($js->minify) ? $js->minify : false);			
				}
				else{
					$target = $the_app->get('package_native_www').$js->path;
					build_mkdir(dirname($target));
					build_cp($permalink.$js->path,$target, $the_app->is('minifying') );			
				}
			}
		}
	}

	// Now the CSS
	if (is_array($json->css)){
		foreach ($json->css as $key => $css){
			if (isset($css->remote)){
				$target = $the_app->get('package_native_www').'resources/css/'.basename($css->path);
				build_mkdir(dirname($target));
				build_cp($css->path,$target,$the_app->is('minifying') ? 'css' : false);			
			}
			else{
				$target = $the_app->get('package_native_www').$css->path;
				build_mkdir(dirname($target));
				build_cp($permalink.$css->path,$target,$the_app->is('minifying') ? 'css' : false);			
			}
		}
	}
	
	// Now the Resources
	// Actually, I don't think I need the resources
	if (false and is_array($json->resources)){
		foreach ($json->resources as $key => $resource){
			// Resources are simpler as they are always relative to app.json, which in the App Factory
			// context is relative to wp-content/plugins/wp-app-factory/the-app
			$target = $the_app->get('package_native_www').$resource;
			$src = APP_FACTORY_PATH.'the-app/'.$resource;
			build_cp_deep($src,$target,$json->ignore);
		}
	}
	
	// Finally, we'll create our app.json file
	$jsonout = new stdClass();
	$jsonout->id = $json->id;
	$jsonout->js = $json->js;
	$jsonout->css = $json->css;
	
	foreach ($jsonout->js as $key => $js){
		if (isset($js->remote)){
			$js->path = 'resources/js/'.basename($js->path);
		}
		else{
			$js->path = $js->path;
		}
		$jsonout->js[$key] = $js;
	}
	foreach ($jsonout->css as $key => $css){
		if (isset($css->remote)){
			$css->path = 'resources/css/'.basename($css->path);
		}
		else{
			$css->path = $css->path;
		}
		$jsonout->css[$key] = $css;
	}
	$jsonout = apply_filters('the_app_factory_package_app_json',$jsonout);

	echo '[GENERATE] app.json file';
	the_app_factory_set_package_app_json($jsonout);
	
}

function the_app_factory_package_resources(){
	// Maybe deploy initial data
	the_app_factory_deploy_data();
	
	the_app_factory_deploy_app_images();

	do_action('the_app_factory_package_resources');
}

function the_app_factory_deploy_data(){
	$the_app = & TheAppFactory::getInstance();

	if ($the_app->is('using_manifest')){
		// They are using the manifest, meaning they want the data to be available
		// offline.  So, we're going to get all of the initial data and store it
		// with the packaged app.
		build_mkdir( $the_app->get('package_native_www').'resources/data');
		foreach ($the_app->get('stores') as $store){
			if (isset($store['proxy']) and $store['useLocalStorage']){
				build_cp($store['proxy']['url'], $the_app->get('package_native_www')."resources/data/{$store['model']}.json");
			}
		}
	}	
}

function the_app_factory_deploy_app_images(){
	$the_app = & TheAppFactory::getInstance();
	
	switch( $the_app->get('package_target') ){
	case 'ios':
		if ($the_app->get('icon')){
			the_app_factory_deploy_ios_icons($the_app->get('icon'));
		}
		if ($the_app->get('startup_phone')){
			the_app_factory_deploy_ios_startup_phone($the_app->get('startup_phone'));
		}
		if ($the_app->get('startup_tablet')){
			the_app_factory_deploy_ios_startup_tablet($the_app->get('startup_tablet'));
		}
		if ($the_app->get('startup_landscape_tablet')){
			the_app_factory_deploy_ios_startup_landscape_tablet($the_app->get('startup_landscape_tablet'));
		}
		break;
	case 'android':
		if ($the_app->get('icon')){
			the_app_factory_deploy_android_icons($the_app->get('icon'));
		}
		break;
	}
}

function the_app_factory_deploy_ios_icons( $url ){
	$sizes = array(
		72 => 'icon-72.png',
		144 => 'icon-72@2x.png',
		57 => 'icon.png',
		114 => 'icon@2x.png'
	);
	
	the_app_factory_create_images( $url, 'icons', $sizes );
}

function the_app_factory_deploy_android_icons( $url ){
	$sizes = array(
		96 => array('drawable/icon.png','drawable-xhdpi/icon.png'),
		72 => 'drawable-hdpi/icon.png',
		36 => 'drawable-ldpi/icon.png',
		48 => 'drawable-mdpi/icon.png',
		
	);
	
	the_app_factory_create_images( $url, 'icons', $sizes );
}

function the_app_factory_create_images( $url, $type, $sizes ){
	// Returns an object with:
	//	->image = image resource
	//  ->size = the result from getimagesize()
	$image = the_app_factory_curl_image( $url );
	
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
				switch( $the_app->get('package_target') ){
				case 'ios':
					imagepng( $dest, $the_app->get('package_native_root') . $the_app->get('package_name') . "/Resources/$type/$filename" );
					break;
				case 'android':
					if (is_array($filename)){
						foreach ($filename as $name){
							imagepng( $dest, $the_app->get('package_native_root') . "res/$name" );
						}
					}
					else{
						imagepng( $dest, $the_app->get('package_native_root') . "res/$filename" );
					}
					break;
				}


				imagedestroy( $dest );
			}
			else{
				switch( $the_app->get('package_target') ){
				case 'ios':
					imagepng( $image->image, $the_app->get('package_native_root') . $the_app->get('package_name') . "/Resources/$type/$filename" );
					break;
				case 'android':
					if (is_array($filename)){
						foreach ($filename as $name){
							imagepng( $image->image, $the_app->get('package_native_root') . "res/$name" );
						}
					}
					else{
						imagepng( $dest, $the_app->get('package_native_root') . "res/$filename" );
					}
					break;
				}
				
			}
			if (is_array($filename)){
				foreach ($filename as $name){
					echo "[CREATED] $name\n";
				}
			}
			else{
				echo "[CREATED] $filename\n";
			}
		}
	}
}

function the_app_factory_deploy_ios_startup_phone( $url ){
	$sizes = array(
		'320x480' => 'Default~iphone.png',
		'640x960' => 'Default@2x~iphone.png',
		'640x1136' => 'Default-568h@2x~iphone.png'
	);
	
	the_app_factory_create_images( $url, 'splash', $sizes );
	
}

function the_app_factory_deploy_ios_startup_tablet( $url ){
	$sizes = array(
		'736x1004' => 'Default-Portrait~ipad.png',
		'1536x2008' => 'Default-Portrait@2x~ipad.png',
	);

	the_app_factory_create_images( $url, 'splash', $sizes );
}

function the_app_factory_deploy_ios_startup_landscape_tablet( $url ){
	$sizes = array(
		'1024x748' => 'Default-Landscape~ipad.png',
		'2048x1496' => 'Default-Landscape@2x~ipad.png',
	);

	the_app_factory_create_images( $url, 'splash', $sizes );
}

function the_app_factory_curl_image( $url ){
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

function the_app_factory_generate_package_index(){
	$the_app = & TheAppFactory::getInstance();
	$permalink = get_permalink();
	$index = get_by_curl($permalink.'?packaging=true&target='.$the_app->get('package_target'));
	
	// For this step we need to read app.json
	$json = the_app_factory_get_app_json('packaged');
	$jsonout = new stdClass;
	$jsonout->id = $json->id;
	$jsonout->js = $json->js;
	$jsonout->css = $json->css;
	
	$microloader = JSMin::minify(file_get_contents(APP_FACTORY_PATH."the-app/sdk{$the_app->get('sdk')}/microloader/development.js"));
	
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

function the_app_factory_get_package_url(){
	$the_app = TheAppFactory::getInstance();
	$json = the_app_factory_get_app_json('packaged'); // the_app_factory_get_app_json() is defined in the-app-builder.php
	build_succeed('',array(
		'root' => $the_app->get('package_native_root_url'),
		'js' => $json->js
	));
}

function the_app_factory_set_package_app_json($json){
	$the_app = TheAppFactory::getInstance();
	$dest = $the_app->get('package_native_www').'app.json';
	$fh = fopen($dest,'w');
	fwrite($fh,json_encode($json));
	fclose($fh);
}

function the_app_factory_package_wrapup(){
	$the_app = TheAppFactory::getInstance();
	$json = the_app_factory_get_app_json('packaged');
	
	// Need to generate the versions and make sure that updates are set to full
	// I decided to move this here to the wrapup section so that I could be sure to 
	// get it all one in one fell swoop
	if (false){ // actually, don't need file versions on the packaged versions
		$these = array('js','css');
		foreach ($these as $that){
			foreach ($json->$that as $key => $thing){
				$path = $the_app->get('package_native_www').$thing->path;
				$thing->update = 'full';
				if (!file_exists($path)){
					build_fail(sprintf(__('could not find %s','app-factory'),$thing->path));
				}
				$content = file_get_contents($path);

				while(preg_match('/^\/\*/',$content)){
					$content = preg_replace('/\/\*[^\/]*\*\//','',$content);
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
	}
	the_app_factory_set_package_app_json($json);
	echo "[PREPARED] app.json file";
	
}

add_action('wp_ajax_download_ios_zip','the_app_factory_package_download');
add_action('wp_ajax_download_android_zip','the_app_factory_package_download');
function the_app_factory_package_download(){
	preg_match('/download_([^_]+)_zip/',$_GET['action'],$matches);
	$_REQUEST['target'] = $matches[1];
	$_REQUEST['packaging'] = 'true';
	
	global $post;
	$post = get_post( $_GET['post'] );
	setup_the_app_factory();
	$the_app = & TheAppFactory::getInstance();
	
	$tmp = tempnam('/tmp','afoy_package_');
	
	$zip = the_app_factory_zip( $the_app->get('package_native_root'), $tmp );
	
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

function the_app_factory_zip($source, $destination)
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

add_shortcode('app_package_image','app_package_image');
function app_package_image( $atts=array(), $content=null, $code='' ){
	$the_app = & TheAppFactory::getInstance();
	$image_url = $content;
	if ( $the_app->is('packaging') ){ 
		//setup_package_environment();
		$relative_dest = 'resources/images/'.basename($image_url);
		$dest = $the_app->get( 'package_native_www' ) . $relative_dest;
		build_mkdir(dirname($dest));
		build_cp( $image_url, $dest, false, true ); // no minify, silent
		return $relative_dest;
	}
	return $image_url;
}

add_filter('TheAppFactory_stores','TheAppFactory_package_stores',100,2);
function TheAppFactory_package_stores( $stores, $args ){
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

?>