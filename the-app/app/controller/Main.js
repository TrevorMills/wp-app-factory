Ext.define('the_app.controller.Main', {
    extend: 'Ext.app.Controller',

	requires: ['Ext.log.Logger'],
    
    config: {
		cardJustSwitched: null,
		isFirstView: true,
        refs: {
			mainPanel: 'mainpanel'
        },
		before: {
			goToTabAndReset: 'beforeMainRouting',
			goToTabThenItem: 'beforeMainRouting',
			goToTabThenRecord: 'beforeMainRouting'
		},
        control: {
			'mainpanel': {
				initialize: 'onMainPanelInitialize',
				show: 'onMainPanelShow',
				activeitemchange: 'onMainPanelActiveItemChange'
			},
			'mainpanel tabbar tab': {
				tap: 'onMainPanelTabBarTabTap'
			},
            'itemwrapper list': {
				itemtap: 'onItemWrapperListItemTap' 
			},
			'itemwrapper': {
				initialize: 'onItemWrapperInitialize',
				show: 'onItemWrapperShow',
				back: 'onItemWrapperBack'
			},
			'itemlist': {
				initialize: 'onItemListInitialize',
				activate: 'onItemListActivate',
				back: 'onItemListBack'
			},
			'navigationview':{
				push: 'onNavigationViewActiveItemChange',
				pop: 'onNavigationViewActiveItemChange',
			},
			'itemlist list': {
				itemtap: 'onItemListListItemTap'
			},
			'htmlpage': {
				initialize: 'onHtmlPageInitialize'
			},
			'lazypanel': {
				initialize: 'onLazyPanelInitialize',
				//activate: 'onLazyPanelActivate',
				order: 'before'
			},			
        },
		routes: {
			'tab/:id': 'goToTabAndReset',
			'tab/:id/item/:index': 'goToTabThenItem',
			'tab/:id/record/:record': 'goToTabThenRecord'
		}
    },

	redirectTo: function(place){
		// Convenient way to stop redirection altogether.  
		this.fireAction('beforeredirectto',[{place: place}],function(){
			the_app.controller.Main.superclass.redirectTo.call(this,place);
		});
	},
	
	redirectById: function( path ){
		var parts = path.split( '/' ),
			id = parts[0],
			record_id = Ext.isDefined( parts[1] ) ? parts[1] : undefined
		;
		
		var mainpanel = this.getMainPanel();
		Ext.each( mainpanel.getInnerItems(), ( function( item, index ){
			if ( Ext.isFunction( item.getOriginalItem ) ){
				if ( [item.getOriginalItem().id,item.getItemId()].indexOf( id ) != -1 ){
					var redirect = 'tab/' + ( index + 1 );
					if ( !Ext.isDefined( record_id ) ){
						this.redirectTo( redirect );
					}
					else{
						if ( Ext.get( id ) ){
							// Component already exists
							redirect+= '/record/' + record_id;
							this.redirectTo( redirect );
						}
						else{
							mainpanel.on( 'activeitemchange', function( mainpanel, panel ){
								panel.on( 'add', function( paenl ){
									panel.down( 'list' ).on( 'painted', function( list ){
										redirect+= '/record/' + record_id;
										this.redirectTo( redirect );
									}, this, { single: true } );
								}, this );
							}, this );
							this.redirectTo( redirect );
						}
					}
				}
			}
		}), this );
		
		
	},

	onMainPanelInitialize: function(panel){	
		// If this is a native APP, open any target="_blank" links in the native browser
		if (typeof PACKAGED_APP != 'undefined'){
			// The solution to http://www.sencha.com/forum/showthread.php?284954-Links-in-HTML-Page-in-Native-App
			// Need to make sure we're only responding to a tap, not a drag.  
			Ext.getBody().dom.addEventListener( 'mousedown', function(e){
				var el = Ext.fly( e.target );
				if ( el.is( 'a' ) || el.parent( 'a' ) ) { 
					this.startPoint = { x : e.screenX, y: e.screenY }
				}
			});
			Ext.getBody().dom.addEventListener( 'click', function(e){
				var el = Ext.fly( e.target );
				if ( this.startPoint ){
					var handled = false;
					if ( ( Math.abs( this.startPoint.x - e.screenX ) < 8 )
					&&   ( Math.abs( this.startPoint.y - e.screenY ) < 8 ) ){
						var a = el.is( 'a' ) ? el.dom : el.parent().dom;
						if ( a.href.match( /:\/\// ) ){
							// It's a link with a protocol ( i.e. http://, https:// )
							// We do NOT want to open it within the app.  If there is no 
							// target, then open in _blank ( in app browser ), otherwise, 
							// open it in _system ( native browser );
							var target = a.target == '' ? '_blank' : '_system';
							handled = true;
						  	window.open( a.href, target );
						}
					}
					delete this.startPoint;
					if ( handled ){
						e.preventDefault();
						return false;
					}
				}
			});
		}
		else{
			// Only for non-packaged apps
			if (Ext.os.name == 'iOS' && Ext.os.version.major >= 7){
				Ext.Viewport.addCls('ios7');
			}
		}
		
		var tabBar = panel.getTabBar();
		if ( panel.getSheetMenuItems() ){
			panel.getTabBar().hide();
		}
	},
	
	goToTab: function(id){
		this.getMainPanel().setActiveItem(parseInt(id)-1);
	},
	
	beforeMainRouting: function(action){
		if (!this.getMainPanel()){
			// If the main panel isn't instantiated yet (i.e. if we're waiting for the splash screen
			// to finish), then just set a little callback timeout waiting for it to be done
			var me = this;
			setTimeout(function(){
				me.beforeMainRouting(action);
			},200);
			return false;
		}
		this.fireAction('beforerouting',[{action: action}],function(){
			action.resume();
			this.fireEvent('afterrouting',{action: action});
		});
	},
	
	trackEvent: function(category,action,label,value,noninteraction){
		// a stub for Google Analytics, or other tracking solutions
		var args = {};
		if (category != undefined) args.category = category;
		if (action != undefined) args.action = action;
		if (label != undefined) args.label = label;
		if (value != undefined) args.value = value;
		if (noninteraction != undefined) args.noninteraction = noninteraction;
		this.fireEvent('trackevent',args);
	},
	
	goToTabAndReset: function(id){
		if (this.getCardJustSwitched() === false){
			var active = this.getMainPanel().getActiveItem().getActiveItem();
			if (typeof active.reset == 'function'){
				this.setCardJustSwitched(true); // Set this so that when firing the 'back' event, I don't end up back here (if the back button does a redirectTo)
				active.reset();
				active.fireEvent('back',active);
			}
		}
		else{
			this.goToTab(id);			
		}
		this.setCardJustSwitched(null);
	},
	
	goToTabThenItem: function(id,index){
		this.goToTab(id);
		this.setCardJustSwitched(true); // Set to true so that tapping on the tabbar tab will return to the proper page
		var wrapper = this.getMainPanel().getInnerItems()[parseInt(id)-1],
			store = Ext.getStore('WrapperPageStore'),
			list = wrapper.down('list'),
			doit = function(){
				var record = list.getStore().getAt(index);
				if (record){
					var config = record.get('item');
					config.useTitleBar = false;
					config.meta = record.get('meta');
					list.up('itemwrapper').push(config);
				}
			}
		;
		
		if (store.isLoaded() && store.getCount()){
			doit();
		}
		else{
			store.on('load',doit);
		}
		
	},
	
	goToTabThenRecord: function(id,record_id){
		this.goToTab(id);
		this.setCardJustSwitched(true); // Set to true so that tapping on the tabbar tab will return to the proper page
		var wrapper = this.getMainPanel().getInnerItems()[parseInt(id)-1];
		var list = wrapper.down('list'), store = list.getStore();
		doit = function(){
			var record = store.getById(record_id);
			if (record){
				store.suspendEvents();
				list.up('itemlist').push({
					xtype: 'itemdetail',
					title: record.get('title'),
					tpl: list.up('itemlist').getMeta().detail_template,
					data: record.getData()
				});
				store.resumeEvents( true );
			}
		}
		// Store is loaded asynchronously, so wait until it's loaded
		if (store.isLoaded()){
			doit();
		}
		else{
			store.on('load',doit);
		}
	},
	
	onMainPanelShow: function(panel){
		// Remove the Ajax Loader icon from the body
		Ext.getBody().setStyle(
			{
				background:'none'
			}
		)
	},

	onMainPanelActiveItemChange: function(panel){
		// this === the Main Controller
		this.setCardJustSwitched(true);
	},
	
	onMainPanelTabBarTabTap: function(tab){
		if (this.getCardJustSwitched() !== true){
			this.setCardJustSwitched(false);
		}
		this.redirectTo('tab/'+this.getTabId(tab));
	},

	onItemWrapperListItemTap: function(list, index, target, record){
		// Show the item
		this.redirectTo('tab/'+this.getActiveTabId()+'/item/'+index);
	},
	
	getActiveTabId: function(){
		var active = this.getMainPanel().getActiveItem();
		var items = this.getMainPanel().getInnerItems();
		var id = null;
		Ext.each(items,function(item,index){
			if (item == active){
				id = index;
			}
		});
		return parseInt(id)+1;
	},
	
	getTabId: function(tab){
		var items = this.getMainPanel().getTabBar().getInnerItems();
		var id = null;
		Ext.each(items,function(item,index){
			if (item == tab){
				id = index;
			}
		});
		return parseInt(id)+1;
	},
	
	onItemWrapperInitialize: function(wrapper){
		var l = wrapper.getComponent('list'); // The List
		l.setItemTpl(wrapper.getMeta().list_template);
		l.setUi(wrapper.getMeta().ui);

		var store = Ext.getStore('WrapperPageStore'),
			doit = function(){
				var record = store.queryBy(function(rec){
						return rec.get('key') == wrapper.getItemId();
					}).getAt(0);

				if (record){
					wrapper.down('list').setItemTpl(wrapper.getMeta().list_template);
					wrapper.down('list').setData(record.getData().pages);
				}
			}
		;
		
		if (store.isLoaded() && store.getCount()){
			doit();
		}
		else{
			store.on('load',doit);
			store.maybeLoad();
		}
		
	},
	
	onItemWrapperShow: function(wrapper){
		// This resets the global WrapperPageStore to only contain the items on this
		// particular wrapper page.  Useful if there are more than one wrapper pages.
		// Ext.getStore('WrapperPageStore').removeAll();
		// Ext.getStore('WrapperPageStore').add(wrapper.config.pages);
		wrapper.getNavigationBar().setTitle(wrapper.getTitle());
	},
	
	onItemWrapperBack: function(wrapper){
		// Unselect all in the list
		wrapper.getComponent('list').deselectAll();
		// Reset the title
		wrapper.getNavigationBar().setTitle(wrapper.getTitle());
		this.redirectTo('tab/'+this.getActiveTabId());
	},
	
	onItemListInitialize: function (panel){
		// Since we can't set Infinite after the fact, I need to set it NOW
		var list_config = Ext.apply( {}, panel.getInitialItem() );
		if ( Ext.isDefined( panel.initialConfig.infinite ) ){
			list_config.infinite = panel.initialConfig.infinite;
		}
		panel.push( list_config );

		var l = panel.getComponent('list'); // The List
		//console.log(['Main',panel,panel.getQueryInstance()]); // @dev
		l.setStore(panel.getMeta().store);
		l.setItemTpl(panel.getMeta().list_template);
		
		panel.getNavigationBar().setTitle(panel.getTitle()); // It was way trickier than it should be to set the title of the list dynamically, turns out I have to do it after the user hits the back button as well (see onItemListBack below)
	},
	
	onItemListActivate: function(panel){
		// We may need to adjust the store based on the query instance
		var doit = function(){
			var l = panel.getComponent('list'); // The List
			if (!l){
				// On lazy panel instantiation, the list might not be active yet.
				panel.on('painted',doit,this,{
					single: true
				});
				return;
			}
			var s = l.getStore(); // The Store
			
			if(!s.getCount()){
				return;
			}
//			console.log(['onItemListActivate',panel,l,s,s.isLoaded()]); // @dev
			var q = s.getQueryInstance(); // The Query Instance
			var months = WP.getMonths(); // WP is a helper object, instantiated in app/helper/WP.js

			if (q != panel.getQueryInstance()){
				// query instance has changed, setup the store.
				l.suspendEvents();

				// Let's see if we need to group the records
				queryFilter = new Ext.util.Filter({
					filterFn: function(item){
						return item.get('query_num').match(new RegExp('_'+panel.getQueryInstance()+'_')) && (panel.getMeta().group_by == 'category' || item.get('spoof_id') == undefined);
					}
				});
				s.clearFilter(true);
				s.filter(queryFilter);
				s.setQueryInstance( panel.getQueryInstance() );
				
				if (panel.getMeta().grouped){
					var field = panel.getMeta().group_by;
					s.setGrouped(true);
					var sortProperty;
					switch(field){
					case 'category':
						sortProperty = 'category';
						break;
					case 'month':
					case 'date':
						sortProperty = 'date_gmt';
						break;
					default:
						sortProperty = (field == 'first_letter' ? 'title' : field);
						break;
					}
					s.sort([
						{property: sortProperty,direction: panel.getMeta().group_order},
						{property: panel.getMeta().orderby,direction: panel.getMeta().order}
					]);
					s.setGrouper({
						groupFn: function(r){
							var value;
							switch(field){
							case 'category': value = r.get(field); break;
							case 'month':
								var date;
								if (r.get('spoof_id') != undefined){
									var r2 = s.getById(r.get('spoof_id'));
									date = new Date(r2.get('date'));
								}
								else{
									date = new Date(r.get('date'));
								}
								value = months[date.getMonth()]+', '+date.getFullYear();
								break;
							case 'first_letter': 
								value = r.get('title');
								if (value == ''){
									value = '-';
								}
								value = value[0];
								break;
							default:
								value = r.get(field);
								break;
							}
							return (value == '' ? '-' : value);
						},
						sortProperty: sortProperty,
						direction: panel.getMeta().group_order
					});
					l.resumeEvents(false);
					//l.setGrouped(true); // triggers a refresh of the list
				}
				else{
					l.resumeEvents(false);
					l.setGrouped(false);
					s.setGrouped(false);
					s.sort([
						{property: panel.getMeta().orderby,direction: panel.getMeta().order}
					]);
				}
			}
		
		
			// If the store is grouped, then let's group the list as well.
			if (s.getGrouped()){
				if (typeof l.findGroupHeaderIndices == 'function'){ // introduced in ST2.1 - I need to call this here to avoid an Uncaught Error at Ext.dataview.List line 742.  
					l.findGroupHeaderIndices();
				}
				else if(typeof l.refreshHeaderIndices == 'function'){ // The version for ST2.2.1
					l.refreshHeaderIndices();
				}
				l.setGrouped(true); // triggers a refresh of the list
				l.resumeEvents();
			}
			
			if (panel.getMeta().indexbar){
				l.setIndexBar(true);
			}			
		}
		
		var store = panel.down('list').getStore(); // The Store

		if (store.isLoaded()){
			doit();
		}
		else{
			store.on('load',doit);
			store.maybeLoad();
		}
	},

	onItemListBack: function(panel){
		// Unselect all in the list
		panel.getComponent('list').deselectAll();
		panel.getNavigationBar().setTitle(panel.getTitle());
		this.redirectTo('tab/'+this.getActiveTabId());
	},
	
	onItemListListItemTap: function(list, index, target, record){
		// Show the item
		if (record.get('spoof_id') != undefined){
			record = list.getStore().getById(record.get('spoof_id'));
		}
		this.redirectTo('tab/'+this.getActiveTabId()+'/record/'+record.get('id'));
	},
	
	onHtmlPageInitialize: function(panel){
		var store = Ext.getStore('HtmlPagesStore'),
			me = this,
			doit = function(){
				if (typeof panel.getMeta != 'undefined' && typeof panel.getMeta().template != 'undefined'){
					panel.setTpl(panel.meta.template);
				}
				
				var this_store = Ext.getStore('HtmlPagesStore'); // created so reference to store (from above) doesn't have to stay in memory
					
				if (!panel.getData()){
					var record = store.queryBy(function(rec){
							return rec.get('key') == panel.getItemId();
						}).getAt(0);
					
					if (record && !panel.isDestroyed){
						panel.setData( record.getData() );
					}
				}
				
				if (panel.getData() && panel.getUseTitleBar()){
					panel.add({
						xtype: 'toolbar',
						docked: 'top',
						title: panel.getTitle()
					});
					
					me.maybeSetupMenuSheet( panel );
				}
			}
		;
		
		if (store.isLoaded() && store.getCount()){
			doit();
		}
		else{
			store.on('load',doit);
			store.maybeLoad();
		}
		
		
	},
	
    onLazyPanelInitialize : function( panel ) { // @dev
//		console.log( ['onLazyPanelInitialize',panel.getOriginalItem(),panel.getOriginalItem().id] );
		
		var original = panel.getOriginalItem(); 

		// Set some items on the Lazy(Tab)Panel that are important
		panel.setItemId( original.id );
		panel.setTitle( original.title );
		panel.setIconCls( original.iconCls );
		panel.setDestroyOnDeactivate( original.destroyOnDeactivate );

        panel.removeAll(true);
        panel.on('activate', this.handleLazyPanelActivate, this, { single : true });
    },

    handleLazyPanelActivate: function( panel ) {
		//	console.log(['handleLazyPanelActivate',panel,panel.getLazyItem(),panel.getDestroyOnDeactivate()]); // @dev
		panel.add( {
			xtype: 'panel',
			masked: true,
			layout: {
				type: 'vbox',
				align: 'center',
				pack: 'center'
			},
			items: [
				{
					styleHtmlContent: true,
					centered: true,
					style: 'text-align:center',
					html: WP.__( 'Preparing Content' ) + '<br/>' + WP.__( 'Please Stand By' )
				}
			],
			listeners: {
				painted: {
					fn: function(){
						Ext.factory( Ext.Object.merge( {}, panel.getOriginalItem(), {
							listeners: {
								initialize: {
									fn: function(){
										// A slight pause to make sure that the stand by message is painted.
										Ext.defer( function(){
											panel.removeAll(true);
											panel.add( this );
										}, 50, this );
									},
									single: true
								}
							}
						}));
					},
					single: true,
					order: 'after'
				}
			}
		});
	    panel.on('deactivate', this.handleLazyPanelDeactivate, this, { single : true });
    },
    handleLazyPanelDeactivate: function( panel ) {
		//	console.log(['handleLazyPanelDeactivate',panel]); // @dev

		if (panel.getDestroyOnDeactivate()){
	        panel.removeAll(true);
	        panel.on('activate', this.handleLazyPanelActivate, this, { single : true });
		}
    },

	getAssociatedTabBarButton: function(panel){
		var matched = -1;
		Ext.each(this.getMainPanel().getInnerItems(),function(item,index){
			if (item == panel){
				matched = index;
				return false;
			}
		});
		if (matched >= 0){
			return this.getMainPanel().getTabBar().getInnerItems()[matched];
		}
		return null;
	},
	
	onNavigationViewActiveItemChange: function( panel, item ){
		if ( panel.getInnerItems().length == 1 ){
			// We're on an index page
			this.maybeSetupMenuSheet( panel );
		}
		else{
			var button = panel.query( '#' + this.makeMenuButtonId( panel ) );
			if ( button.length ){
				button[0].hide();
				var menu = panel.query( '#' + this.makeMenuSheetId( panel ) );
				if ( menu.length && !menu[0].getHidden() ){
					menu[0].hide();
				}
			}
		}
	},
	
	makeMenuButtonId: function( panel ){
		return panel.getItemId() + '-menu-button';
	},
	
	makeMenuSheetId: function( panel ){
		return panel.getItemId() + '-menu-sheet';
	},
	
	maybeSetupMenuSheet: function( panel ){
		if ( !this.getMainPanel().getSheetMenuItems() ){
			return;
		}
		
		var buttonId = this.makeMenuButtonId( panel ),
			menuId = this.makeMenuSheetId( panel ),
			button = panel.query( '#' + buttonId ),
			items = [],
			toolbar;
		
		switch( true ){
		case panel.isXType( 'navigationview' ):
			toolbar = panel.getNavigationBar();
			break;
		default:
			toolbar = panel.down( 'toolbar' );
			break;
		}
		
		if ( button.length ){
			// already exists, nothing to do but show it
			button[0].show();
			return;
		}
		
		var sheetItems = this.getMainPanel().getSheetMenuItems();
		Ext.each( sheetItems, function( item, index ){
			if ( item.xtype == 'list' ){
				Ext.apply( item, {
		            scrollable: { disabled: true },
					listeners: {
						initialize: function (list, eOpts){
							// Okay, crazy.  In Sencha Touch 2.1, lists added to a panel do not have
							// a height, so they render unseen.  It can be seen by giving the parent
							// panel a layout: 'vbox' and the list a flex: 1, but that adds other issues. 
							// It took me a couple of hours to figure out how, but I finally fell upon
							// something that seems to work.  ST2.1 added list.getItemMap(), which is a 
							// getter for a private collection of the actual elements.  In it is a handy
							// function getTotalHeight().  It works in tandem with the `refresh` event
							// on the scroller, which seems to keep firing until the scroller is completely
							// rendered (which you think might have been when the list was `painted`, but
							// oh no, you'd be wrong).
							var me = this;
							if (typeof me.getItemMap == 'function'){
								me.getScrollable().getScroller().on('refresh',function(scroller,eOpts){
									switch(Ext.version.version){
									case '2.1.0':
										me.setHeight(me.getItemMap().getTotalHeight());
										break;
									case '2.2.1':
									default:
										me.setHeight(scroller.getSize().y); // And this is what seems to work for 2.2.1, and 2.3.1
										break;
									}
								});
							}
						},
						itemtap: function(list, index, target, record){
							list.fireAction('sheetmenuitemtap',[record,list.up('sheet')],function(){
								the_app.app.getController('Main').redirectById( record.get( 'id' ) );
								var sheet = list.up( 'sheet' );
								if ( sheet ){
									sheet.destroy(); // it will get recreated
								}
								else{
									// Happens if going from a lazy panel that destroys on deactivate
									list.destroy();
								}
							});
							return false; // Prevents the item from becoming "selected"
						},
						order: 'before'
					}
				});
			}
		});
		
		toolbar.add( {
			xtype: 'button',
			align: 'left',
			iconCls: 'menu-button list',
			ui: 'plain',
			zIndex: 5,
			itemId: buttonId, 
			handler: function(){
				var menu = panel.query( '#' + menuId ); 
				if ( menu.length ){
					if ( menu[0].getHidden() == true ){
						menu[0].show();
					}
					else{
						menu[0].hide();
					}
				}
				else{
					panel.add({
						xtype: 'sheet',
						itemId: menuId,
				        stretchY: true,
				        stretchX: true,
				        enter: 'left',
				        exit: 'left',
						scrollable: 'vertical',
						items: sheetItems,
						zIndex: 1000,
						hidden: true,
						style: 'padding:0'
					}).show();
				}
			}
		});
	},	
	
	checkForUpdates: function(){
		var store = Ext.getStore( 'StoreStatusStore' );
		if ( store ){
			the_app.app.showPopup({
				id: 'updating-popup', 
				html: WP.__( 'Checking for Updates' ),
				spinner: 'black x48',
				hideOnMaskTap: false,
				width:'auto',
				height:'auto'
			});
			store.on( 'load', function(store, records, successful, operation, eOpts){
				if ( !successful ){
					the_app.app.alert({
						html: WP.__( 'Unable to communicate with the server.  Please check your internet connection.' ),
						width:'auto',
						height:'auto'
					});
					return;
				}
				var updates = false;
				// This is from the server load triggered above
				Ext.each(records,function(record){
					var the_store = Ext.getStore(record.get('store'));
					if (!updates && the_store && the_store.getStoreTimestamp() != record.get('timestamp')){
						updates = true; 
					}
				});
				the_app.app.hidePopup( 'updating-popup' );
				if ( updates ){
					store.on( 'syncdecision', function( updating ){
						if ( updating ){
							the_app.app.showPopup({
								id: 'updating-popup', 
								html: WP.__( 'Performing Updates' ),
								spinner: 'black x48',
								hideOnMaskTap: false,
								width:'auto',
								height:'auto'
							});
							this.on( 'all_syncs_complete', function(){
								the_app.app.hidePopup( 'updating-popup' );
							}, this, { single: true });
						}
					}, store, { single: true } );
				}
				else {
					the_app.app.alert({
						html: WP.__( 'There are no updates available' ),
						width:'auto',
						height:'auto'
					});
				}
			}, this, {
				single: true
			});
			store.loadServer();
		}
	}
});