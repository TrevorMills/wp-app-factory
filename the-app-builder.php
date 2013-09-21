<?php 
/**********
* A note about the build.....
* 
* As part of the build, I wanted to pack the Javascript and CSS files as best as possible.  
* I started using JSMin, which did a decent job, but seemed to take too long.  I don't
* want to put something out there that's going to put undue strain on a server, so I started
* looking into other options.  I came across the JavaScriptPacker class (http://joliclic.free.fr/php/javascript-packer/en/).
* Things looked good - it reduced the file size by another 30% - 40%, but unfortunately it
* also introduced some development hurdles.  For example, the packer is unforgiving for missing 
* semicolons; and app was failing, due to missing ; in both my own code and also in the 
* Sencha code.  I needed a way to try and track them down, so I ended up creating an 
* Unpack+Beautify step that created *.unpacked.js files and a test.html file to see if they 
* load properly.  That helped me track down a few bugs, but then came to a grinding halt
* when I learned that the packer didn't pack everything properly.  In particular, it failed on 
* sdk/src/core/lang/JSON.js.  
*
* I spent more time trying to figure this out than I am proud of.  It's time to punt and move 
* on.  I've gone back to JSMin, though I've left the Packer code (commented by // Packer Code)
* in there in case I ever come back.  The piece that created the Unpacked+Beautified versions
* might come in handy down the road.
**********/

add_action('wp_ajax_build_app','the_app_factory_build_app');
function the_app_factory_build_app(){
	
	global $post;
	$post = get_post($_POST['id']);
	
	ob_start();
	
	setup_the_app_factory();
	include_once('extras/JSMin.php');
	include_once('extras/JSMinPlus.php');
	//include_once('extras/class.JavaScriptPacker.php');  // Packer Code
	
	switch($_POST['command']){
	case 'deploy':
		// Step 1 - Deploy.  This creates the production directory structure
		// and copies over sencha-touch.js plus other resources
		the_app_factory_deploy();
		break;
	case 'dependencies':
		the_app_factory_concat_dependencies();
		break;
	case 'index':
		the_app_factory_generate_index();
		break;
	case 'get_production_url': // Packer Code
		the_app_factory_get_production_url();
		break;
	case 'beautify': // Packer Code
		the_app_factory_beautify();
		break;
	case 'wrapup':
		the_app_factory_wrapup();
	}
	build_succeed(ob_get_clean());
	die();
}

function build_fail($message=''){
	header('Content-type: text/javascript');
	echo json_encode(array(
		'success' => false,
		'message' => ob_get_clean()."\n[FAIL] ".$message
	));
	die();
}

function build_succeed($message='',$data = ''){
	header('Content-type: text/javascript');
	echo json_encode(array(
		'success' => true,
		'message' => $message,
		'data' => $data
	));
	die();
}

function build_mkdir($target){	
	if (!wp_mkdir_p($target)){
		build_fail('Could not create build directory at '.$target);
	}
}

function setup_production_environment(){
	// We are going to create a directory within wp-content that will
	// contain all of the build files for our apps.
	
	/*
	$uploads = wp_upload_dir();
	define('APP_BUILD_ROOT',trailingslashit($uploads['basedir'])); // multisite safe - uploads or files directory
	define('APP_BUILD_ROOT_URL',trailingslashit($uploads['baseurl'])); // multisite safe - uploads or files directory

	global $post;
	$relative = trailingslashit('wp-app-factory/build/production/'.$post->post_name);
	$target = APP_BUILD_ROOT.$relative; //.'/build/production';
	
	define('APP_PRODUCTION_ROOT',$target);
	define('APP_PRODUCTION_ROOT_URL',APP_BUILD_ROOT_URL.$relative);
	
	if (!wp_mkdir_p($target)){
		build_fail('Could not create build directory at '.$target);
	}
	*/
}

function build_cp($url,$dest,$minify = false, $silent = false){
	$fp = fopen($dest,'w');
	$content = get_by_curl($url);
	
	if ($minify){
		if (!$silent){
			echo "[COPY - MINIFIED] ".basename($url)."\n";
		}
		
		switch(true){
		case ($minify === 'css'):
			$packed = compressCSS($content); 
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
		//$packed = JSMinPlus::minify($content);
		//$packer = new JavaScriptPacker($content,'None',false); // Packer Code
		//$packed = $packer->pack(); // Packer Code
		
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

function compressCSS($css) {
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

function build_cp_deep($src,$dest,$ignores=null,$quiet = false){
	if (!isset($ignores)){
		$ignores = array('^.DS_STORE$','^.svn');
	}
	if (is_dir($src)){
		build_mkdir($dest);
		$contents = glob(trailingslashit($src).'*');
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
					build_cp_deep($file,$target,$ignores,$quiet);
				}
				else{
					if (!$quiet){
						echo "[COPY] ".str_replace(ABSPATH,'',$file)." to ".str_replace(ABSPATH,'',$target)."\n";
					}
					copy($file,$target);
				}
			}
		}
	}
}

function get_by_curl($url,$ch=null){
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
	curl_setopt($_ch, CURLOPT_URL, $url.$sep.'building=true');
	
	// Access this hook if you need to add anything like user authentication
	do_action_ref_array('the_app_factory_get_by_curl',array(&$_ch));

	$result = curl_exec($_ch);
	
	if (!isset($ch)){
		curl_close($_ch);
	}
	return $result;
}

function the_app_factory_deploy(){
	$the_app = & TheAppFactory::getInstance();
	$permalink = get_permalink();

	// For this step we need to read app.json
	$json = the_app_factory_get_app_json('development');
	
	$minify = $the_app->is('minifying');	

	// Let's do the JS files
	if (is_array($json->js)){
		foreach ($json->js as $key => $js){
			if ($js->path != 'app.js'){ // app.js is handled later
				if (isset($js->remote)){
					$target = $the_app->get('production_root').'resources/js/'.basename($js->path);
					build_mkdir(dirname($target));
					build_cp($js->path,$target);			
				}
				else{
					$target = $the_app->get('production_root').$js->path;
					build_mkdir(dirname($target));
					build_cp($permalink.$js->path,$target,$minify);			
				}
			}
		}
	}

	// Now the CSS
	if (is_array($json->css)){
		foreach ($json->css as $key => $css){
			if (isset($css->remote)){
				$target = $the_app->get('production_root').'resources/css/'.basename($css->path);
				build_mkdir(dirname($target));
				build_cp($css->path,$target,$minify ? 'css' : false);			
			}
			else{
				$target = $the_app->get('production_root').$css->path;
				build_mkdir(dirname($target));
				build_cp($permalink.$css->path,$target,$minify ? 'css' : false);			
			}
		}
	}
	
	// Now the Resources
	// Actually, I don't think I need the resources
	if (false and is_array($json->resources)){
		foreach ($json->resources as $key => $resource){
			// Resources are simpler as they are always relative to app.json, which in the App Factory
			// context is relative to wp-content/plugins/wp-app-factory/the-app
			$target = $the_app->get('production_root').$resource;
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
			$js->path = $the_app->get('production_root_url').'resources/js/'.basename($js->path);
		}
		else{
			$js->path = $the_app->get('production_root_url').$js->path;
		}
		$jsonout->js[$key] = $js;
	}
	foreach ($jsonout->css as $key => $css){
		if (isset($css->remote)){
			$css->path = $the_app->get('production_root_url').'resources/css/'.basename($css->path);
		}
		else{
			$css->path = $the_app->get('production_root_url').$css->path;
		}
		$jsonout->css[$key] = $css;
	}
	echo '[GENERATE] app.json file'."\n";
	the_app_factory_set_app_json($jsonout);
	
	do_action('the_app_factory_build_resources');
}

function the_app_factory_concat_dependencies( $target_root = null ){
	$the_app = & TheAppFactory::getInstance();
	if ( !isset($target_root) ){
		$target_root = $the_app->get('production_root');
	}
	set_time_limit(0);
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
			if ($the_app->is('packaging')){
				$path.= '?packaging=true&target='.$the_app->get('package_target');
			}
			$content = get_by_curl($path,$ch);
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
	if ($the_app->is('packaging')){
		$path.= '?packaging=true&target='.$the_app->get('package_target');
	}
	$content = get_by_curl($path,$ch);
	fwrite($fp, $minify ? JSMin::minify($content) : $content );

	curl_close($ch);
	fclose($fp);
}

function the_app_factory_generate_index(){
	$the_app = & TheAppFactory::getInstance();
	$permalink = get_permalink();
	
	$index = get_by_curl($permalink);
	
	// For this step we need to read app.json
	$json = the_app_factory_get_app_json('development');
	$jsonout = new stdClass;
	$jsonout->id = $json->id;
	
	$microloader = JSMin::minify(file_get_contents(APP_FACTORY_PATH."the-app/sdk{$the_app->get('sdk')}/microloader/production.js"));
	
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

function the_app_factory_get_production_url(){
	$the_app = & TheAppFactory::getInstance();
	$json = the_app_factory_get_app_json();
	build_succeed('',array(
		'root' => $the_app->get('production_root_url'),
		'js' => $json->js
	));
}

function the_app_factory_get_app_json($which = 'production'){
	$the_app = & TheAppFactory::getInstance();
	$permalink = get_permalink();
	switch($which){
	case 'development':
		$json = json_decode(get_by_curl($permalink.'app.json'));
		break;
	case 'packaged':
		$json = json_decode(file_get_contents($the_app->get('package_native_www').'app.json'));
		break;
	case 'production':
	default:
		$json = json_decode(file_get_contents($the_app->get('production_root').'app.json'));
		break;
	}
	
	if (!$json){
		$json = new stdClass;
	}
	
	if (!is_array($json->js)){
		$json->js = array();
	}
	if (!is_array($json->css)){
		$json->css = array();
	}
	return $json;
}

function the_app_factory_set_app_json($json){
	$the_app = & TheAppFactory::getInstance();
	$dest = $the_app->get('production_root').'app.json';
	$fh = fopen($dest,'w');
	fwrite($fh,json_encode($json));
	fclose($fh);
}


// Packer Code (see note at beginning)
function the_app_factory_beautify(){
	$the_app = & TheAppFactory::getInstance();
	require_once('extras/jsbeautifier.php');
	$jsb = new JSBeautifier();
	$_POST = stripslashes_deep($_POST);
	$result = $jsb->beautify($_POST['packed']);
	$target = str_replace('.js','.unpacked.js',$_POST['path']);
	$fp = fopen(str_replace($the_app->get('production_root_url'),$the_app->get('production_root_url'),$target),'w');
	fwrite($fp,$result);
	fclose($fp);
	echo '[GENERATE] Unpacked '.$_POST['path'].' file.  Access '.$the_app->get('production_root_url').'test.html to track down any problems.';
	$fp = fopen($the_app->get('production_root').'test.html','w');
	// For this step we need to read app.json
	$json = the_app_factory_get_app_json();
	fwrite($fp,'<!DOCTYPE HTML>
<html>
<head>
');
	foreach ($json->js as $js){
		if (!$js->remote){
			fwrite($fp,'<script type="text/javascript" src="'.str_replace('.js','.unpacked.js',$js->path).'"></script>'."\n");
		}
	}
	fwrite($fp,'
</head>
<body>
</body>
</html>');
	fclose($fp);
}

function the_app_factory_wrapup(){
	$the_app = & TheAppFactory::getInstance();
	$json = the_app_factory_get_app_json();
	
	// Need to generate the versions and make sure that updates are set to full
	// I decided to move this here to the wrapup section so that I could be sure to 
	// get it all one in one fell swoop
	$these = array('js','css');
	foreach ($these as $that){
		foreach ($json->$that as $key => $thing){
			$path = str_replace($the_app->get('production_root_url'),$the_app->get('production_root'),$thing->path);
			$thing->update = 'full';
			if (!file_exists($path)){
				build_fail(sprintf(__('could not find %s','app-factory'),$thing->path));
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
	the_app_factory_set_app_json($json);
	echo "[PREPARED] app.json file with file versions";
}

?>