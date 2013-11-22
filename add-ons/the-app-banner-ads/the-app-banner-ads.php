<?php


class TheAppBannerAds{	
	private $ads;
	private $enqueued = false;
	private $delay;
	
	public function __construct(){
		add_shortcode( 'app_banner_adds', create_function('', 'return "";')); // a void shortcode for no output - only use this shortcode in apps
		add_filter('TheAppFactory_init', array( &$this,'init') );
	}
	
	public function init( & $the_app ){
		remove_shortcode( 'app_banner_ads' );
		add_shortcode('app_banner_ads', array( &$this, 'shortcodes') );
		add_shortcode('banner_ad', array( &$this, 'shortcodes') );
		
		// Add in a couple of filters.  Which one gets hit will help determine the context for the supplied ads
		add_filter( 'the_app_post_output', array( &$this, 'banner_in_post' ), 10, 2); // When a banner ad is included in a WordPress post
		add_action( 'TheAppFactory_parsePost', array( &$this, 'parse_post' ) ); // When banner ads are included in the app post itself
		
		$the_app->register('controller','BannerAdsController', __DIR__ .'/the-app/src/controllers/BannerAdsController.js');
		$this->reset_ads();
	} 
	
	public function reset_ads(){
		$this->ads = array();
	}
	
	public function shortcodes( $atts=array(), $content=null, $code=''){
		$the_app = & TheAppFactory::getInstance();
		
		switch( $code ){
		case 'app_banner_ads':
			$this->reset_ads();
			do_shortcode( $content );
			
			$defaults = array( 
				'delay' => 15, // seconds between switching
				'initial_delay' => 2, // seconds before first ad appears
				'show_animation' => 'slideIn',
				'hide_animation' => 'slideOut',
				'docked' => 'bottom', // could also be 'top', 'left' or 'right'
				'height' => '75px',
				'width' => '100%'
			);
			$this->atts = shortcode_atts( $defaults, $atts );
			
			if ( !$this->enqueued ){
				$this->enqueue();
			}
			break;
		case 'banner_ad':
			$this->ads[] = array(
				'src' => $atts['src'],
				'href' => $atts['href']
			);
			break;
		}
	}
	
	public function banner_in_post( $post_output, $post ){
		if ( !empty( $this->ads ) ){
			$post_output['content'] = '<!-- banner-ads:' . json_encode($this->ads) . '--> ' . $post_output['content'];
		}
		$this->reset_ads();
		return $post_output;
	}
	
	public function parse_post( &$the_app ){
		if ( $this->enqueued ){
			// Setup the Banner Ad Helper.  
			add_filter('TheAppFactory_helpers', array( &$this, 'helper' ), 10, 2);
			$the_app->set( 'banner_ads', $this->ads );
			$this->reset_ads();
		}
	}
	
	public function helper( $helpers, $args ){
		$the_app = & $args[0];

		$helper = array(
			'bannerAds' => $the_app->get('banner_ads'),
			'specificBannerAds' => false,
			'fieldKeys' => array( 'content', 'description' ) // Fields that _could_ contain the markup for banner ads
		);
		foreach ( (array)$this->atts as $key => $value ){
			$key = preg_replace_callback( '/_([a-z])/', create_function( '$a', 'return strtoupper($a[1]);'), $key );
			if ( $key === 'delay' || $key === 'initialDelay' ){
				$value = intval( $value ) * 1000; // convert seconds to milliseconds
			}
			$helper[$key] = $value;
		}
		
		
		$helpers['BANNER_ADS'] = apply_filters('the_app_banner_ads_helper',$helper);

		$the_app->enqueue('require','the_app.helper.BANNER_ADS');

		return $helpers;
	}
	
	public function enqueue(){
		$the_app = & TheAppFactory::getInstance();
		$the_app->enqueue( 'controller', 'BannerAdsController' );
		add_filter('TheAppFactory_app_json', array( &$this, 'app_json' ) );
		$this->enqueued = true;
	}
	
	function app_json( $json ){
		$json['css'][] = array(
	        "path" => APP_FACTORY_URL.'add-ons/the-app-banner-ads/the-app/resources/css/banner-ads.css',
			"remote" => true,
	        "update" => "full"
		);

		return $json;
	}
}

new TheAppBannerAds();