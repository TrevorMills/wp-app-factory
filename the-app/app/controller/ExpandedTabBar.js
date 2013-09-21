Ext.define('the_app.controller.ExpandedTabBar', {
    extend: 'Ext.app.Controller',
    
    config: {
		isPanelUp: false,
		expandedTabBar: null,
		moreButton: null,
        refs: {
			mainPanel: 'mainpanel'
        },
		before: {
		},
        control: {
			'mainpanel': {
				initialize: 'setupExpandedTabBar',
			},
			'mainpanel tabbar tab': {
				tap: 'onMainPanelTabBarTabTap'
			},
        },
		routes: {
		}
    },

	setupExpandedTabBar: function(panel){	
		if (panel.maxTabBarItems == undefined){
			// There is no max tab bar items set, or we've already created an expanded tab bar
			return;
		}
		panel.getTabBar().setScrollable(false);
		var visible = 0;
		var extras = [];
		var row = [];
		var me = this;
		// If the number of items is exactly equal to the maxTabBarItems, we don't
		// want the more button to appear.  However, if there are more than maxTabBarItems,
		// then we want to make sure to hide the "maxTabBarItems"th item.  That's the purpose of swinger
		var swinger = null;  
		Ext.each(panel.getTabBar().getInnerItems(),function(tab){
			if (!tab.isHidden()){ // Maybe the tab is already hidden, only proceed if visible
				visible++;
				if (visible > panel.maxTabBarItems){
					if (swinger){
						swinger.hide();
						row.push(me.manufactureTab(swinger));
						swinger = null;
					}
					tab.hide();
					row.push(me.manufactureTab(tab));
					if (row.length == panel.maxTabBarItems){
						extras.push(me.manufactureTabBar(row));
						row = [];
					}
				}
				else if(visible == panel.maxTabBarItems){
					swinger = tab;
				}
			}
		});
		
		if (row.length){
			while(row.length < panel.maxTabBarItems){
				row.push(me.manufactureTab());
			}
			extras.push(me.manufactureTabBar(row));
		}
		
		if (extras.length){
			this.setExpandedTabBar(
				panel.add({
					xtype: 'panel',
					iconCls: 'more',
					scrollable: 'vertical',
					title: WP.__('More'),
					text: WP.__('More'),
					id: 'expanded-tabbar',
					layout: {
						type: 'vbox',
						align: 'center',
						pack: 'end'
					},
					items: extras,
					style: 'background:none', //rgba(0,0,0,0.5)',
					showAnimation: {
						type: 'slideIn',
						direction: 'up'
						
					},
					hideAnimation: {
						type: 'slideOut',
						direction: 'down'
					}
				})
			);
			this.getExpandedTabBar().hide();
			
			// create a reference to the more button, based on the last item in the tab bar (which will be the More button, after having just been added)
			this.setMoreButton(panel.getTabBar().getInnerItems()[panel.getTabBar().getInnerItems().length-1]);
			this.getMoreButton().on(
				{
					tap: 'onMoreButtonTap',
					scope: this,
					order: 'before'
				}
			);
		}
	},
	
	manufactureTabBar: function(row){
		return {
			xtype: 'tabbar',
			layout: {
				type: 'hbox',
				align: 'center',
				pack: 'center'
			},
			cls: 'x-tabbar-dark x-tabbar x-docked-bottom',
			items: row
		}
	},
	
	manufactureTab: function(tab){
		return {
			xtype: 'tab',
			title: (tab != undefined ? tab.getTitle() : ''),
			iconCls: (tab != undefined ? tab.getIconCls() : ''),
			originalTab: (tab != undefined ? tab : null),
			listeners: 
				{
					tap: {
						fn: 'onExtraTabTap',
						order: 'before',
						scope: this
					}
				}
		}
	},
	
	onMoreButtonTap: function(){
		if (this.getExpandedTabBar().isHidden()){
			this.getExpandedTabBar().show();
		}
		else{
			this.getExpandedTabBar().hide();
		}
		
		// Stop the More Panel from being setActive by the MainPanel
		return false;
	},
	
	onExtraTabTap: function(tab){
		if (tab.originalTab){
			tab.originalTab.fireEvent('tap',tab.originalTab);
		}
		
		// Stop the Extra Tab from being setActive by the MainPanel
		return false;
	},
	
	onMainPanelTabBarTabTap: function (tab){
		if (this.getExpandedTabBar() && !this.getExpandedTabBar().isHidden()){
			this.getExpandedTabBar().hide();
		}
	}

});