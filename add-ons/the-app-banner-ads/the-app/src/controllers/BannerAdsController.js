Ext.define('the_app.controller.BannerAdsController', {
    extend: 'Ext.app.Controller',
    
	requires: [],
	
    config: {
        refs: {
			mainPanel: 'mainpanel',
			bannerView: '#banner_ads_ad'
        },
        control: {
			'mainpanel': {
				activate: 'onMainPanelActivate',
			},
			'navigationview':{
				push: 'onNavigationViewActiveItemChange',
				pop: 'onNavigationViewActiveItemChange',
			},
			'htmlpage': {
				initialize: 'onHtmlPageInitialize'
			},
        },
		routes: {
		},
		
		timeout: false,
		alreadySwitching: false,
		queued: 0
		
    },

	init: function(){
		BANNER_ADS._phoneHome(); // See if there are any new ads to serve up
	},
	
	onActiveItemChange: function( panel, item ){
		if ( Ext.isFunction( item.getActiveItem().getData ) ){
			this.setupBannerAdsForPage( item.getItemId(), item.getActiveItem().getData() );
		}
		else{
			this.setupBannerAdsForPage( item.getItemId() );
		}
	},
	
	onHtmlPageInitialize: function(panel){
		var store = Ext.getStore('HtmlPagesStore'),
			me = this,
			doit = function(){
				me.setupBannerAdsForPage( panel.getData().key, panel.getData() );
			}
		;
		
		if (store.isLoaded() && store.getCount()){
			doit();
		}
		else{
			store.on('load',doit);
		}		
	},
	
	onNavigationViewActiveItemChange: function( panel ){
		if ( panel.getInnerItems().length == 1 ){
			this.setupBannerAdsForPage( panel.getItemId() );
		}
		else{
			var data = panel.getActiveItem().getData();
			if ( data ) {
				this.setupBannerAdsForPage( panel.getItemId() + '/' + data.id, data );
			}
		}
	},
	
	setupBannerAdsForPage: function( route, data ){
		var activeId = this.getMainPanel().getActiveItem().getItemId(),
			routeId = route.match( /^[^\/]+/ )[0];
			
		if ( activeId != routeId ){
			return;
		}
		
		var found = false,
			wasSpecific = BANNER_ADS.getSpecificBannerAds() !== false;
			
		if ( data ){
			var regExp = /<!-- banner-ads:(.+)-->/;

			Ext.each( BANNER_ADS.getFieldKeys(), function( key ){
				if ( !found && data[ key ] ){
					var matches = data[ key ].match( regExp );
					if ( matches ){
						var ads = Ext.JSON.decode( matches[1] );
						if ( ads ){
							BANNER_ADS.setSpecificBannerAds( ads );
							this.switchAds();
							found = true;
						}
					}
				}
			}, this);
		}
		if ( !found ){
			var ads = [],
				base_route = ( route.indexOf( '/' ) == -1 ) ? false : route.split( '/' )[0];
			Ext.each( BANNER_ADS.getBannerAds(), function( ad ){
				if ( !ad.route ){
					return;
				}
				var routes = ad.route.split(',');
				if ( routes.indexOf( route ) != -1 || ( base_route && routes.indexOf( base_route ) != -1 ) ){
					found = true;
					ads.push( ad );
				}
			});
			if ( found ){
				BANNER_ADS.setSpecificBannerAds( ads );
			}
		}
		if ( !found && wasSpecific ){
			BANNER_ADS.setSpecificBannerAds( false );
		}
		this.switchAds();
	},

	onMainPanelActivate: function( panel ){
		panel.add([{
			xtype: 'panel',
			docked: BANNER_ADS.getDocked(),
			id: 'banner_ads_ad',
			tpl: '<tpl if="href"><a href="{href}" target="_blank"></tpl><img style="max-width:{width};max-height:{height}" src="{src}"/><tpl if="href"></a></tpl>',
			width: BANNER_ADS.width,
			height: BANNER_ADS.height,
			data: this.randomAd(),
			hidden: true,
			showAnimation: BANNER_ADS.getShowAnimation(),
			hideAnimation: BANNER_ADS.getHideAnimation()
		}]);
		
		panel.on( 'activeitemchange', this.onActiveItemChange, this, { order: 'after' } );
		
		Ext.defer( function(){
			this.getBannerView().show();
			this.setTimeout(Ext.defer( this.switchAds, BANNER_ADS.getDelay() , this ));
		},BANNER_ADS.getInitialDelay(), this); // Slight delay so that there's time to see the animation

	},
	
	randomAd: function(){
		var ads = BANNER_ADS.getSpecificBannerAds() || BANNER_ADS.getBannerAds().filter( this.nonRouteAds ),
			index = Math.floor(Math.random()*ads.length);
			
		return Ext.apply({},ads[index],{width:BANNER_ADS.getWidth(),height:BANNER_ADS.getHeight()});
	},
	
	nonRouteAds: function( ad ){
		return !ad.route;
	},
	
	switchAds: function(){
		// this function may get called quickly in succession.  But, we only want to switch once.  That's the purpose of the getAlreadySwitching()
		if ( this.getAlreadySwitching() ){
			return;
		}
		this.setAlreadySwitching( true );
		Ext.defer( function(){
			this.setAlreadySwitching( false );
		}, BANNER_ADS.getDelay() / 2, this );
		var view = this.getBannerView();
		if ( BANNER_ADS.getBannerAds().filter( this.nonRouteAds ).length || (BANNER_ADS.getSpecificBannerAds() && BANNER_ADS.getSpecificBannerAds().length) ){
			var current = view.getData(),
				next = this.randomAd(),
				safety = 0;
				
			while( next.src == current.src && safety < 10 ){
				safety++;
				next = this.randomAd();
			}
			
			if ( next.src != current.src ){
				if ( view.isHidden() ){
					view.setData( next );
					view.show();
				}
				else{
					// If the app is inactive for a while, then it's possible that a bunch of ad switches will get queued and then
					// run in quick succession.  this.getQueued() is to prevent that
					if ( !this.getQueued() ){
						this.setQueued( true );
						view.hide( {
							type: BANNER_ADS.getHideAnimation(),
							listeners: {
								animationend: {
									fn: function(){
										view.setData( next );
										view.show();
										this.setQueued( false );
									},
									single: true,
									scope: this
								}
							}
						});
					}
				}
			}
		}
		else if ( !view.isHidden() ){
			view.hide();
			view.setData( {} );
		}
		if ( this.getTimeout() ){
			clearTimeout( this.getTimeout() );
		}
		this.setTimeout(Ext.defer( this.switchAds, BANNER_ADS.getDelay(), this ));
		
	}
});