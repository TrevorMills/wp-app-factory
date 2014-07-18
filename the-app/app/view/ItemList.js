Ext.define('the_app.view.ItemList',{
	extend: 'Ext.navigation.View',
	xtype: 'itemlist',
	
	requires: [
		'Ext.dataview.List',
		'Ext.data.proxy.JsonP',
		'Ext.data.Store' /* This is vital */
	],

	config: {
		title: 'Item List',
		iconCls: 'star',
		items: [],
		defaultBackButtonText: WP.__( 'Back' ),
		initialItem: {
			xtype: 'list',
			title: '',
			itemTpl: '{title}',
			itemId: 'list', 
			infinite: true,
			variableHeights: true,
			/* This Disables Overscroll */
			scrollable : {
				direction: 'vertical',
				directionLock: true,
				momentumEasing:  {
					momentum: {
						acceleration: 30,
						friction: 0.5
					},
					bounce: {
						acceleration: 0.0001,
						springTension: 0.9999,
					},
					minVelocity: 5
				},
				outOfBoundRestrictFactor: 0	
			},		
			/** */ 
		},
		
		queryInstance: null,
		store: null,
		meta: null,
	}
});