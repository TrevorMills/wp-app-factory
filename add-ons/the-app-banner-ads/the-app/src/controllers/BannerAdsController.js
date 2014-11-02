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
				activate: 'onMainPanelActivate'
			},
			itemlist: {
				push: 'onItemListPush',
				pop: 'onItemListPop'
			}
        },
		routes: {
		},
		
		timeout: false
		
    },

	init: function(){
		BANNER_ADS._phoneHome(); // See if there are any new ads to serve up
	},

	onItemListPop: function( panel, item ){
		Ext.defer( function( panel ){
			this.onItemListPush( panel, panel.getActiveItem() );
		}, 500, this, [panel] );
	},

	onItemListPush: function( panel, item ){
		var found = false,
			wasSpecific = BANNER_ADS.getSpecificBannerAds() !== false;
		if ( typeof item.getData == 'function' ){
			var data = item.getData();
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
		}
		if ( !found && wasSpecific ){
			BANNER_ADS.setSpecificBannerAds( false );
			this.switchAds();
		}
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
		
		
		Ext.defer( function(){
			this.getBannerView().show();
			this.setTimeout(Ext.defer( this.switchAds, BANNER_ADS.getDelay() , this ));
		},BANNER_ADS.getInitialDelay(), this); // Slight delay so that there's time to see the animation

	},
	
	randomAd: function(){
		var ads = BANNER_ADS.getSpecificBannerAds() || BANNER_ADS.getBannerAds(),
			index = Math.floor(Math.random()*ads.length);
			
		return Ext.apply({},ads[index],{width:BANNER_ADS.getWidth(),height:BANNER_ADS.getHeight()});
	},
	
	switchAds: function(){
		var view = this.getBannerView();
		if ( BANNER_ADS.getBannerAds().length || (BANNER_ADS.getSpecificBannerAds() && BANNER_ADS.getSpecificBannerAds().length) ){
			var current = view.getData(),
				next = this.randomAd(),
				safety = 0;
				
			while( next.src == current.src && safety < 10 ){
				safety++;
				next = this.randomAd();
			}
			
			if ( next.src == current.src ){
				// There is not a new ad to display, just return
				return;
			}

			if ( view.isHidden() ){
				view.setData( next );
				view.show();
			}
			else{
				view.hide( {
					type: BANNER_ADS.getHideAnimation(),
					listeners: {
						animationend: {
							fn: function(){
								view.setData( next );
								view.show();
							},
							single: true
						}
					}
				});
			}
			if ( this.getTimeout() ){
				clearTimeout( this.getTimeout() );
			}
			this.setTimeout(Ext.defer( this.switchAds, BANNER_ADS.getDelay(), this ));
		}
		else if ( !view.isHidden() ){
			view.hide();
			view.setData( {} );
		}
		
	}
});