<?php if (!defined('APP_IS_VALID')) die('// Move along...'); ?>

the_app.views.LocationMap = Ext.extend(Ext.Panel, {
    initComponent: function(){ 
        var toolbarBase = {
            xtype: 'toolbar',
            title: this.title
        };
        
        if (this.prevCard !== undefined) {
            toolbarBase.items = {
                ui: 'back',
                text: this.prevCard.title,
                scope: this,
                handler: function(){
                    this.ownerCt.setActiveItem(this.prevCard, { type: get_option('transition'), reverse: true });
					this.destroy();
                }
            }
        }
       
 		try{
			this.infoBubble = new google.maps.InfoWindow();

	        var map_options = {
	            mapOptions : {
	                navigationControlOptions: {
	                    style: google.maps.NavigationControlStyle.DEFAULT
	                }
	            },
	            listeners: {
	                maprender : {
						fn : function(comp,map){
							this.map = map;
							google.maps.event.addListenerOnce(this.map,'zoom_changed',function(){
								// If there's only one point on the map, map.fitBounds() is going to zoom
								// too far in.  This only needs to be run once - we'll let the user zoom 
								// in further if they wish
								if (map.getZoom() > 17){
									map.setZoom(17);
								}
							});
							for (var p = 0; p < this.points.length; p++){
								this.addMarker(this.points[p]);
							}
							this.fitBounds();
							if (this.use_current_location){
								this.addCurrentLocation();
							}
	                	},
						scope: this
	            	}
				}
			};


	        this.items = [toolbarBase,new Ext.Map(map_options)];
		}
		catch(e){
			Ext.Msg.alert(
				'<?php echo the_app_gettext('Offline'); ?>', 
				'<?php echo the_app_gettext('__offline_maps'); ?>', 
				function(button){
                    //this.ownerCt.setActiveItem(this.prevCard, { type: get_option('transition'), reverse: true });
					this.destroy();
				},
				this
			);
		}

        the_app.views.LocationMap.superclass.initComponent.call(this);
    },

	addMarker:  function(point,config) {
		if (Ext.isEmpty(this.latlngbounds)){
			this.latlngbounds = new google.maps.LatLngBounds();
		}
		var latLng = new google.maps.LatLng(point.lat,point.long);
		this.latlngbounds.extend(latLng);

		var marker = new google.maps.Marker(Ext.applyIf({
			map: this.map,
			position: latLng,
		},config));
		
		var infoBubble = this.infoBubble;

		google.maps.event.addListener(marker, 'mousedown', function() {
			infoBubble.setContent('<div class="google-map-bubble"><div class="bubble-title">'+point.title+'</div>' + (point.text != '' ? ' ' + '<div class="bubble-text">'+point.text+'</div>' : '') + '</div>');
			infoBubble.open(this.map,marker);
		});
		
		return marker;
	},
	
	fitBounds: function(){
		this.map.fitBounds( this.latlngbounds );
	},
	
	addCurrentLocation: function(){
		this.GeoLocation = new Ext.util.GeoLocation({
		    autoUpdate: false,
		    listeners: {
		        locationupdate: function (geo) {
					var point = {
						lat:geo.latitude,
						long:geo.longitude,
						title:'<?php echo the_app_gettext('You are here'); ?>',
						text:''
					};
					this.addMarker(point,{
						animation:google.maps.Animation.DROP,
						icon:'http://maps.google.com/mapfiles/arrow.png', // Thanks http://mabp.kiev.ua/2010/01/12/google-map-markers/
						shadow:'http://maps.google.com/mapfiles/arrowshadow.png'
					});
					this.fitBounds();
		        },
		        locationerror: function (   geo,
		                                    bTimeout, 
		                                    bPermissionDenied, 
		                                    bLocationUnavailable, 
		                                    message) {
		            if(bTimeout){
		                console.log('Geolocation Timeout occurred.');
		            }
		            else{
		                console.log('Geolocation Error occurred.');
		            }
		        },
				scope: this
		    }
		});
		this.GeoLocation.updateLocation();
	}

	
	
});

Ext.reg('location', the_app.views.LocationMap);