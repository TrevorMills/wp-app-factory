Ext.define('the_app.controller.Search', {
    extend: 'Ext.app.Controller',
    
	requires: ['Ext.field.Search'],
	
    config: {
        refs: {
			mainPanel: 'mainpanel',
        },
        control: {
			'itemlist': {
				initialize: 'onItemListInitialize',
			}
        },
		lastSearch: {},
    },

	onItemListInitialize: function( panel ){
		if ( panel.getMeta().searchable ){
			this.addSearchField( panel );
		}
		var list = panel.down( 'list' ),
			store = list.getStore();
		if ( !store.getCount() ){
			panel.getInnerItems()[0].add({
				xtype: 'component',
				styleHtmlContent: true,
				html: WP.__( "There is currently no information for %s.  Please check back later for updates." ).replace(/\%s/,panel.getNavigationBar().getTitle()),
				docked: 'top',
				//cls: 'my_schedule_no_results',
				ui: 'plain'
			});
			if ( list.getIndexBar() ){
				list.getIndexBar().hide();
			}
		}
	},
	
	getSearchableFields: function( panel ){
		var searchableFields;
		if ( Ext.isDefined( panel.getMeta().searchableFields ) ){
			if ( Ext.isArray( panel.getMeta().searchableFields ) ){
				searchableFields = panel.getMeta().searchableFields;
			}
			else{
				searchableFields = panel.getMeta().searchableFields.split(',');
			}
		}
		else{
			searchableFields = ['title'];
		}
		return searchableFields;
	},
	
	addSearchField: function ( panel ){
		// Doing this greatly improves performance. 
		//store.data.setAutoSort(false);
		var searchListeners = {
			scope: this,
			clearicontap: this.onSearchClearIconTap,
			clearcache: this.clearSearchCache
		}
		
		searchListeners[ Ext.os.deviceType == 'Phone' ? 'change' : 'keyup' ] = this.doSearch;
		searchListeners[ (Ext.os.deviceType == 'Phone' ? 'change' : 'keyup') + 'nomask' ] = this.doSearchNoMask;
		var searchfield = panel.down('list').add([{
			xtype: 'toolbar',
			docked: 'top',
			ui: 'search',
			layout: {
			        type: 'hbox',
			        align: 'center',
					pack: 'center'
			    },
			items: [{
				xtype:'searchfield',
				placeHolder: WP.__('Search...'),
				id: panel.getItemId()+'-search',
				searchableFields: this.getSearchableFields( panel ),
				listeners: searchListeners
			}]
		}]);
		panel.down('list').getScrollable().getScroller().on('scroll',function(scroller,x,y,eOpts){
			if (y > 0 && !searchfield.isHidden()){
				searchfield.hide();
			}
			if (y <= 0 && searchfield.isHidden()){
				searchfield.show();
			}
		});
		panel.setNavigationBar({
		    docked: 'top',
		    listeners: {
		        tap: {
		            fn: function(e) {
						if ( panel.getActiveItem().isXType('list') ){
							// If we're on a list, then we'll take tapping the top nav bar as signal to scroll to top
							if (e.target.classList == undefined || !(e.target.classList.contains('x-button') || e.target.classList.contains('x-button-label'))){
								// do not scroll to the top if the original target of the event is the button (or its label)
								panel.getActiveItem().getScrollable().getScroller().scrollTo(0,0);
							}
						}
		            },
		            element: 'element'
		        }
		    }
		});
	},
	
	clearSearchCache: function( panel_id ){
		if ( typeof panel_id == 'undefined' ){
			this.setLastSearch( {} );
		}
		else{
			this._lastSearch[ panel_id ] = '';
		}
	},
	
	doSearchNoMask: function( field ){
		this.doSearch( field, false );
	},
	
	doSearch: function(field, useMask ){
		if ( typeof useMask == 'undefined' ){
			useMask = true;
		}
        //get the store and the value of the field
        var value = field.getValue(),
			panel = field.up('itemlist'),
            store = Ext.isFunction( panel.getActiveItem().getStore ) ? panel.getActiveItem().getStore() : null
		;
		
		if ( !store ){
			return;
		}

        //first clear any current filters on thes tore
        //store.clearFilter();

        //check if a value is set first, as if it isnt we dont have to do anything
		store.suspendEvents();
		panel.suspendEvents();
		panel.down('list').suspendEvents();
		
		if ( useMask ){
			panel.mask( {
				xtype: 'loadmask',
				indicator: true,
				message: null
			});
		}
		
		Ext.defer( function(){
	        if (!value) {
				panel.resumeEvents( true );
				this.onSearchClearIconTap( field );
				store.resumeEvents(true);
			}
			else{
				// Only clear the filter if the current value is not simply an extension of the last value (as will be true)
				// when they're typing.  This is a performance optimization.  
				var stillTyping = !( this._lastSearch[panel.getItemId()] == '' || !value.match(new RegExp('^'+this._lastSearch[panel.getItemId()])) );
				if ( !stillTyping ){
			        store.clearFilter(true);
				}
	            //the user could have entered spaces, so we must split them so we can loop through them all
	            var searches = value.split(' '),
	                regexps = [],
	                i;

	            //loop them all
	            for (i = 0; i < searches.length; i++) {
	                //if it is nothing, continue
	                if (!searches[i]) continue;

	                //if found, create a new regular expression which is case insenstive
	                regexps.push(new RegExp(searches[i], 'i'));
	            }

	            //now filter the store by passing a method
	            //the passed method will be called for each record in the store
	            store.filter(function(record) {
	                var matched = [];

	                //loop through each of the regular expressions
	                for (i = 0; i < regexps.length; i++) {
	                    var search = regexps[i],
							didMatch = false;
					
						Ext.each( field.config.searchableFields, function( searchable ){
							var recordValue = record.get( searchable );
							if ( recordValue && recordValue.match( search ) ){
								didMatch = true;
							}
						});

	                    //if it matched the title, push it into the matches array
	                    matched.push(didMatch);
	                }

	                //if nothing was found, return false (dont so in the store)
	                if (matched.length > 1 && matched.indexOf(false) != -1) {
	                    return false;
	                } else {
	                    //else true true (show in the store)
	                    return matched[0];
	                }
	            });
						
				store.resumeEvents(true);
				store.fireAction( 'searchFilter', [ panel, stillTyping ] , function(){
					store.sort();
				});
			}
			this._lastSearch[panel.getItemId()] = value;
		
			if ( useMask ){
				panel.unmask();
			}
			panel.resumeEvents( true );
			panel.down('list').resumeEvents(true);
			store.fireEvent('refresh');
		}, useMask ? 50 : 0, this );

	},
	
	onSearchClearIconTap: function(field){
		var panel = field.up('itemlist'),
			store = field.up('itemlist').down('list').getStore();

		this.clearSearchCache( panel.getItemId() );
	
		// @DEBUG
		panel.fireAction( 'searchClear', [store], function(s){
			s.clearFilter(true);
			s.fireEvent('refresh');
		});
	},
	
});