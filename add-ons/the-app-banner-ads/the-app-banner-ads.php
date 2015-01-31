<?php


class TheAppBannerAds{	
	private $ads;
	private $enqueued = false;
	private $delay;
	
	public function __construct(){
		add_filter('TheAppFactory_init', array( &$this,'init') );
	}
	
	public function init( & $the_app ){
		remove_shortcode( 'app_banner_ads' );
		add_shortcode('app_banner_ads', array( &$this, 'shortcodes') );
		add_shortcode('banner_ad', array( &$this, 'shortcodes') );
		
		// Add in a couple of filters.  Which one gets hit will help determine the context for the supplied ads
		add_filter( 'the_app_post_output', array( &$this, 'banner_in_post' ), 10, 2); // When a banner ad is included in a WordPress post
		add_action( 'TheAppFactory_parsePost', array( &$this, 'parse_post' ) ); // When banner ads are included in the app post itself
		
		$the_app->register('controller','BannerAdsController', dirname(__FILE__) .'/the-app/src/controllers/BannerAdsController.js');
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
			if ( $the_app->is('packaging') ){ 
				$relative_dest = 'resources/images/ads/'.basename( $atts['src'] );
				$dest = $the_app->get( 'package_native_www' ) . $relative_dest;
				TheAppBuilder::build_mkdir(dirname($dest));
				TheAppBuilder::build_cp( $atts['src'], $dest, false, true ); // no minify, silent
				$atts['src'] = $relative_dest;
			}
			$this->ads[] = array(
				'src' => $atts['src'],
				'href' => $atts['href'],
				'route' => empty( $atts['route'] ) ? false : $atts['route']
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
			if ( 'banner_ads' == get_query_var( APP_DATA_VAR ) ){
				$registered_post_queries = $the_app->get('registered_post_queries');
				$registered_post_queries['banner_ads'] = array(
					array(
						'query_vars' => array(
							'data_callback' => array( &$this, 'ads_callback' ),
						)
					)
				);
				$the_app->set('registered_post_queries',$registered_post_queries);
			}
			$this->reset_ads();
		}
	}
	
	public function ads_callback( $query ){
		$the_app = & TheAppFactory::getInstance();
		return $the_app->get( 'banner_ads' );
	}
	
	public function helper( $helpers, $args ){
		$the_app = & $args[0];

		$helper = array(
			'bannerAds' => $the_app->get('banner_ads'),
			'specificBannerAds' => false,
			'fieldKeys' => array( 'content', 'description' ), // Fields that _could_ contain the markup for banner ads
			'homeUrl' => get_permalink() . 'data/banner_ads'
		);
		foreach ( (array)$this->atts as $key => $value ){
			$key = preg_replace_callback( '/_([a-z])/', create_function( '$a', 'return strtoupper($a[1]);'), $key );
			if ( $key === 'delay' || $key === 'initialDelay' ){
				$value = intval( $value ) * 1000; // convert seconds to milliseconds
			}
			$helper[$key] = $value;
		}
		
		$helper['phoneHome'] = $the_app->do_not_escape( "
			function(){
				Ext.data.JsonP.request({
					url: this.getHomeUrl(),
					callbackKey: 'callback',
					scope: this,
					success: function( result, request ){
						var ads = this.getBannerAds(), keepers = [];
						
						Ext.each( result.banner_ads, function( ad ){
							var base = ad.src.match( /[^\/]*$/ )[0],
								link = ad.href,
								found = false
							;
								
							Ext.each( ads, function( tester, index ){
								if ( !found && tester.src.match( /[^\/]*$/ )[0] == base ){
									tester.href = ad.href; // just in case the href has changed
									found = true;
									keepers.push( index );
								}
							});
							if ( !found ){
								// New ad
								keepers.push( ads.length );
								delete ad.query_num;
								ads.push( ad );
							}
						});
						
						// Now, get rid of any ads that aren't keepers
						var new_ads = [];
						Ext.each( keepers, function( index ){
							new_ads.push( ads[ index ] );
						});
						
						this.setBannerAds( new_ads );
					},
				});
			}
		");		
		
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