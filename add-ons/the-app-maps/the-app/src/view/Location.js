Ext.define('the_app.view.Location',{
	extend: 'Ext.Panel',
	
	requires: ['Ext.Map','Ext.device.Geolocation'],

	xtype: 'location',
	
	config: {
		layout: 'fit',
		points: [],
		infoBubble: null,
		use_current_location: false,
		items: [],
		
	},
	
	initialize: function(){
		this.setInfoBubble(new google.maps.InfoWindow());
		
        var map_options = {
			xtype: 'map',
            mapOptions : {
                navigationControlOptions: {
                    style: google.maps.NavigationControlStyle.DEFAULT
                }
            },
            listeners: {
                maprender : {
					fn : function(comp,map){
						this.map = map;
						/*  See note under show: function below
						google.maps.event.addListenerOnce(map,'zoom_changed',function(){
							// If there's only one point on the map, map.fitBounds() is going to zoom
							// too far in.  This only needs to be run once - we'll let the user zoom 
							// in further if they wish
							if (map.getZoom() > 17){
								map.setZoom(17);
							}
						});
						*/
						var points = this.getPoints();
						for (var p = 0; p < points.length; p++){
							this.addMarker(points[p]);
						}
						this.fitBounds();
						if (this.getUse_current_location()){
							this.addCurrentLocation();
						}
                	},
					scope: this
            	}
			}
		};
		
		var map = this.add(map_options);
	},
	
	show: function(){
		this.callParent(arguments);
		
		// I don't know what changd, and why this became so difficult, but 
		// with Sencha 2, the fit bounds and set center weren't happening properly
		// depending on if I started the app on the map tab or not.  I tried every 
		// combination of the 'zoom_changed' function above and couldn't get it 
		// to work.  Finally, I got it to work by putting this function in the
		// show: event.  There's a timeout of 1 second on it to give the map a 
		// second to initialize.  I don't love this solution, but I've spent
		// enough time on it by now to move on.  
		var me = this;
		setTimeout(function(){
			if (Ext.isEmpty(me.map_initialized)){
				me.fitBounds();
				if (me.map.getZoom() > 17){
					me.map.setZoom(17);
				}
				me.map_initialized = true;
			}
		},1000);
	},

	addMarker:  function(point,config) {
		if (Ext.isEmpty(this.bounds)){
			this.bounds = new google.maps.LatLngBounds();
		}
		var latLng = new google.maps.LatLng(point.lat,point.long);
		this.bounds.extend(latLng);

		var marker = new google.maps.Marker(Ext.applyIf({
			map: this.map,
			position: latLng,
		},config));
		
		var infoBubble = this.getInfoBubble();

		google.maps.event.addListener(marker, 'mousedown', function() {
			infoBubble.setContent('<div class="google-map-bubble"><div class="bubble-title">'+point.title+'</div>' + (point.text != '' ? ' ' + '<div class="bubble-text">'+point.text+'</div>' : '') + '</div>');
			infoBubble.open(this.map,marker);
		});
		
		return marker;
	},
	
	fitBounds: function(){
		this.map.fitBounds( this.bounds );
		this.map.setCenter( this.bounds.getCenter() );
	},
	
	addCurrentLocation: function(){
		// I'm having troubles getting this to consistently get the current position
		//  I was in Victoria and testing it and it kept showing me a cached position
		// from when I was in Toronto.  Investigating, I went to http://www.w3schools.com/html5/tryit.asp?filename=tryhtml5_geolocation
		// and ran it and then all of a sudden the cached position updated to me in 
		// Victoria.  WTF.  This is all on Chrome, BTW.  
				
		Ext.device.Geolocation.setMaximumAge(3000);
		Ext.device.Geolocation.setTimeout(3000);
		
        Ext.device.Geolocation.getCurrentPosition({
            scope: this,
			maximumAge: 0,
			autoUpdate: true,
            success: function(position) {
				var point = {
					lat: position.coords.latitude,
					long: position.coords.longitude,
					title: WP.__('You are here'),
					text: ''
				}
				this.addMarker(point,{
					animation:google.maps.Animation.DROP,
					icon:'http://maps.google.com/mapfiles/arrow.png', // Thanks http://mabp.kiev.ua/2010/01/12/google-map-markers/
					shadow:'http://maps.google.com/mapfiles/arrowshadow.png'
				});
				this.fitBounds();
            },
            failure: function() {
				return;
                Ext.Msg.alert(WP.__('Geolocation'),
                    WP.__('Unable to retrieve current location.')
                );
            }
        });
	}
});