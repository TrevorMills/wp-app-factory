<?php
/*
Plugin Name: The App Maps
*/

add_filter('TheAppFactory_init','TheAppFactoryMaps_init');
function TheAppFactoryMaps_init(& $the_app){
	add_shortcode('app_map','app_maps_shortcode');
	add_shortcode('app_map_point','app_maps_shortcode');
	
	$the_app->register('view','Location',dirname(__FILE__).'/the-app/src/view/Location.js');
}

function app_maps_shortcode($atts = array(),$content=null,$code=''){
	$the_app = & TheAppFactory::getInstance();

	$atts = $the_app->sanitize_atts( $atts, $code );
	switch($code){
	case 'app_map':
		$item_defaults = array(
			'xtype' => 'location',
			'icon' => 'locate',
			'title' => __('Map','app-factory'),
			'use_current_location' => false
		);
		$meta_defaults = array(
			'_is_default' => false,
			'kml' => ''
		);
		$item_atts = shortcode_atts($item_defaults,$atts);
		$meta_atts = shortcode_atts($meta_defaults,$atts);
		
		// This will add in whatever points are there
		$the_app->set('GoogleMapPoints',array()); // reset
		if ( !empty( $meta_atts['kml'] ) ){
			// There's a kml file defined, let's get points from that.
			app_maps_parse_kml( $meta_atts['kml'] );
		}
		unset( $meta['kml'] );
		
		do_shortcode($content);
		$item_atts['points'] = $the_app->get('GoogleMapPoints');
		
		$the_app->addItem($item_atts,$meta_atts);

		app_maps_setup_actions();
		break;
	case 'app_map_point':
		$current_points = $the_app->get('GoogleMapPoints');
		if (!is_array($current_points)){
			$current_points = array();
		}
		$item_defaults = array(
			'title' => '',
			'lat' => '',
			'long' => ''
		);
		$atts = shortcode_atts($item_defaults,$atts);
		if ($atts['title'] != '' and $atts['lat'] != '' and $atts['long'] != ''){
			// Must have a title, lat & long
			$point = array(
				'title' => $atts['title'],
				'lat' => $atts['lat'],
				'long' => $atts['long'],
				'text' => apply_filters('the_content',$content)
			);
			$current_points[] = $point;
		}
		$the_app->set('GoogleMapPoints',$current_points);
		break;
	}
	
}

function app_maps_parse_kml( $url ){
	$result = wp_remote_get( $url );
	if ( $result and $result['response']['code'] == 200 ){
		$kml = @simplexml_load_string( $result['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
		$namespaces = $kml->getDocNamespaces();
		$kml->registerXPathNamespace( 'ns', current( $namespaces ) );
		$placemarks = $kml->xpath( '//ns:Placemark');
		if ( $placemarks ){
			$the_app = & TheAppFactory::getInstance();
			$points = $the_app->get('GoogleMapPoints');
			foreach ( $placemarks as $placemark ){
				$placemark->registerXPathNamespace( 'ns', current( $namespaces ) );
				list( $longitude, $latitude, $altitude ) = explode(',',(string)$placemark->Point->coordinates);
				
				$points[] = array(
					'title' => (string)$placemark->name,
					'lat' => $latitude,
					'long' => $longitude,
					'text' => (string)$placemark->description 
				);
			}
			$the_app->set( 'GoogleMapPoints', $points );
		}
	}
}

add_filter('upload_mimes', 'the_app_maps_upload_mimes');
function the_app_maps_upload_mimes ( $existing_mimes=array() ) {
    // add the file extension to the array
    $existing_mimes['kml'] = 'application/vnd.google-earth.kml+xml';
    // call the modified list of extensions 
    return $existing_mimes;
 
}

function app_maps_setup_actions(){
	if (!has_filter('TheAppFactory_models','app_maps_models')){
		add_filter('TheAppFactory_models','app_maps_models',10,2);
		add_action('the_app_factory_print_stylesheets','app_maps_print_stylesheets');
		add_action('the_app_factory_print_scripts','app_maps_print_scripts');
		add_action('the_app_factory_print_manifest','app_maps_print_manifest');
		add_filter('the_app_gettext','app_maps_textblocks');

		$the_app = & TheAppFactory::getInstance();
		$the_app->enqueue('view','Location');

		add_filter('TheAppFactory_app_json','app_maps_add_stylesheet');
	}
}

function app_maps_models($Models,$args){
	return $Models;
}

function app_maps_print_stylesheets(){
}

function app_maps_print_scripts(){
	echo '<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=true"></script>'."\n";
}

function app_maps_print_manifest(){
	echo 'http://maps.google.com/maps/api/js?sensor=true'."\n";
}

function app_maps_textblocks($text){
	switch($text){
	case '__offline_maps':
		$text = sprintf('It appears your devices is not connected to the internet.  Google Maps requires an internet connection.  To use the maps in this app, connect to the internet and %srestart the app%s.','<a href="javascript:window.location.reload();">','</a>');
		break;	
	}
	return $text;
}

function app_maps_add_stylesheet($json){
	
	$json['css'][] = array(
        "path" => APP_FACTORY_URL.'add-ons/the-app-maps/the-app/resources/css/app-map.css',
		"remote" => true,
        "update" => "full"
	);
	
	return $json;
	
}


?>