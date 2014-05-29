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
	},
	
	addSearchField: function ( panel ){
		// Doing this greatly improves performance. 
		//store.data.setAutoSort(false);
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
				listeners: {
					scope: this,
					clearicontap: this.onSearchClearIconTap,
					keyup: this.onSearchKeyUp
				}
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
	
	onSearchKeyUp: function(field){
        //get the store and the value of the field
        var value = field.getValue(),
			panel = field.up('itemlist'),
            store = panel.getActiveItem().getStore()
			
		;
		
        //first clear any current filters on thes tore
        //store.clearFilter();

        //check if a value is set first, as if it isnt we dont have to do anything
		store.suspendEvents();
		panel.down('list').suspendEvents();
		
        if (!value) {
			this.onSearchClearIconTap( field );
		}
		else{
			// Only clear the filter if the current value is not simply an extension of the last value (as will be true)
			// when they're typing.  This is a performance optimization.  
			var stillTyping = !( this._lastSearch[panel.getItemId()] == '' || !value.match(new RegExp('^'+this._lastSearch[panel.getItemId()])) );
			if ( !stillTyping ){
		        store.clearFilter();
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
						didMatch;
					
					didMatch = record.get('title');
					if ( didMatch ){
						didMatch = didMatch.match(search);
					}

                    //if it matched the title, push it into the matches array
                    matched.push(didMatch);
                }

                //if nothing was found, return false (dont so in the store)
                if (matched.length > 1 && matched.indexOf(null) != -1) {
                    return false;
                } else {
                    //else true true (show in the store)
                    return matched[0];
                }
            });
			
			store.fireAction( 'searchFilter', [ panel, stillTyping ] , function(){
				store.sort();
			});
			
		}
		this._lastSearch[panel.getItemId()] = value;
		store.resumeEvents();
		panel.down('list').resumeEvents(false);
		store.fireEvent('refresh');
	},
	
	onSearchClearIconTap: function(field){
		var panel = field.up('itemlist'),
			store = field.up('itemlist').down('list').getStore();

		this._lastSearch[panel.getItemId()] = '';
		
		// @DEBUG
		panel.fireAction( 'searchClear', [], function(){
			store.clearFilter();
		});
	},
	
});