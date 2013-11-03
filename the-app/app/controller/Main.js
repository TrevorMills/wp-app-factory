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

	onMainPanelInitialize: function(panel){	
		// If this is a native APP, open any target="_blank" links in the native browser
		if (typeof PACKAGED_APP != 'undefined'){
			Ext.getBody().onBefore(
				'tap',
				function(e){
					e.preventDefault();
					window.open(e.target.href, "_system");
					return false;
				},
				this,
				{
					delegate: 'a[target="_blank"]',
					element: 'element'
				}
			);
		}
		
		if (Ext.os.name == 'iOS' && Ext.os.version.major >= 7){
			Ext.Viewport.addCls('ios7');
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
			store = Ext.getStore('WrapperStore'),
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
		var list = wrapper.down('list');
		doit = function(){
			var record = list.getStore().getById(record_id);
			if (record){
				list.up('itemlist').push({
					xtype: 'itemdetail',
					title: record.get('title'),
					tpl: list.up('itemlist').getMeta().detail_template,
					data: record.getData()
				});
			}
		}
		// Store is loaded asynchronously, so wait until it's loaded
		if (list.getStore().isLoaded()){
			doit();
		}
		else{
			list.getStore().on('load',doit);
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

		var store = Ext.getStore('WrapperStore'),
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
			if (!store.getAutoLoad()){
				store.load();
			}
		}
		
	},
	
	onItemWrapperShow: function(wrapper){
		// This resets the global WrapperStore to only contain the items on this
		// particular wrapper page.  Useful if there are more than one wrapper pages.
		// Ext.getStore('WrapperStore').removeAll();
		// Ext.getStore('WrapperStore').add(wrapper.config.pages);
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
				
				if (panel.getMeta().grouped == 'true'){
					var field = panel.getMeta().group_by;
					s.setGrouped(true);
					var sortProperty;
					switch(field){
					case 'category':
						sortProperty = 'category';
						break;
					case 'month':
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
			
			if (panel.getMeta().indexbar == 'true'){
				l.setIndexBar(true);
			}			
		}
		
		var store = panel.down('list').getStore(); // The Store

		if (store.isLoaded()){
			doit();
		}
		else{
			store.on('load',doit);
			if (!store.getAutoLoad()){
				store.load();
			}
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
				}
			}
		;
		
		if (store.isLoaded() && store.getCount()){
			doit();
		}
		else{
			store.on('load',doit);
			if (!store.getAutoLoad()){
				store.load();
			}
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
//		console.log(['handleLazyPanelActivate',panel,panel.getLazyItem(),panel.getDestroyOnDeactivate()]); // @dev
        panel.add( panel.getOriginalItem() );
	    panel.on('deactivate', this.handleLazyPanelDeactivate, this, { single : true });
    },
    handleLazyPanelDeactivate: function( panel ) {
//		console.log(['handleLazyPanelDeactivate',panel]); // @dev

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
	}	
	
});