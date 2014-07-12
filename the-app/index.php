<!DOCTYPE HTML>
<html manifest="" lang="en-US">
<head>
    <meta charset="UTF-8">
    <title><?php $post = $the_app->get('post'); echo $post->post_title; ?></title >

	<?php do_action('the_app_factory_print_stylesheets'); ?>
	<?php do_action('the_app_factory_print_scripts'); ?>
	
<?php if ($the_app->is('doing_package_command') || $the_app->is('doing_build_command')) : ?>
<script type="text/javascript">
	// When building or packaging, clear the localStorage off the top. 
	var FORCE_CLEAR_LOCALSTORAGE = true;
</script>
<?php endif; ?>
<?php if ($the_app->is('packaging')) : ?>
<script type="text/javascript">
	var PACKAGED_APP = {
		target: '<?php echo $the_app->get('package_target'); ?>'
	}
</script>
<?php else : 
	// If packaging the app, we don't need to worry about checking for unacceptable browsers
	?>
	<script type="text/javascript">
	<?php 
		// I had a lot of trouble getting the "Unacceptable Browser" message to show up in all browsers.
		// Ancient ones like IE7 just failed when running the Ext.application() command, and in production
		// it failed in the microloader (so NOTHING happened).  I need to ship this thing.  So, I'm going to 
		// add this timeout in here. On a successful load, the app.js script will remove this timeout.
		$meta = $the_app->get('meta');
		$message = $meta['unacceptable_browser']['content'];
		$checks = array(
			"typeof JSON == 'undefined'",	// True for IE8
			"!('defineProperty' in Object || '__defineGetter__' in Object)"  // True for IE7
		);
		if ($_GET['building'] == 'true'){
			$checks[] = 'typeof window.applicationCache == "undefined"'; 	// True for all IE
		}
		$checks = apply_filters('TheAppFactory_unacceptable_browser_check_js',$checks);
		
		$markup = '<div id="unacceptable-browser" style="padding:2em;text-align:center"><div class="message">{message}</div><img src="http://qrickit.com/api/qr?d={url}"/><br/><a href="{url}" class="full-link">{url}</a>';
		$markup = apply_filters('TheAppFactory_unacceptable_browser_check_markup',$markup);
		$markup = preg_replace('/\{message\}/',$message,$markup);
	?>
	var isUnsupportedBrowser = false;
	unsupportedBrowser = function(){
		var url = window.location.href;
		var content = "<?php echo addslashes(preg_replace('/[\n\r]/','',$markup)); ?>";
		content = content.replace(/\{url\}/g,url);
		document.body.innerHTML = content;
	};
	checkSupportedBrowser = function(){
		if (<?php echo implode(' || ',$checks); ?>){
			isUnsupportedBrowser = true;
			unsupportedBrowser();
		}
	}
	if (typeof window.addEventListener != 'undefined'){
		window.addEventListener('load',checkSupportedBrowser);
	}
	else if (typeof attachEvent != 'undefined'){
		attachEvent('onload',checkSupportedBrowser);
	}
	</script>
<?php endif; ?>
	
    <!-- The line below must be kept intact for Sencha Command to build your application -->
    <script id="microloader" type="text/javascript" src="<?php echo APP_FACTORY_URL;  ?>the-app/sdk<?php echo $the_app->get('sdk'); ?>/microloader/development.js"></script>
</head>
<body class="<?php echo implode(' ', apply_filters( 'app_body_class', array( 'theme-' . $the_app->get( 'theme' ) ) ) ); ?>">
<div id="app-loading"></div>
</body>
</html>