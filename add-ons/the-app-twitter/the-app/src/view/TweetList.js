Ext.define('the_app.view.TweetList',{
	extend: 'Ext.navigation.View',
	xtype: 'tweetlist',
	
	requires: [
		'Ext.dataview.List',
		'the_app.proxy.TwitterProxy',
		'Ext.plugin.ListPaging',
		'Ext.plugin.PullRefresh',
		'Ext.data.Store' /* This is vital */
	],
	
	config: {
		title: 'Twitter Page',
		search: '',
		useAppSearch: true,
		items: [
			{
				xtype: 'list',
				title: '',
	            itemCls: 'tweet',
				styleHtmlContent: true,
				loadingText: null,
				itemTpl: new Ext.XTemplate('<div class="avatar"<tpl if="profile_image_url"> style="background-image: url({profile_image_url})"</tpl>><a href="http://twitter.com/{from_user}" target="_blank">&nbsp;</a></div> <div class="tweet"><strong><a href="http://twitter.com/{from_user}" target="_blank" class="from_user_link">{from_user}</a></strong><br />{text:this.linkify}</div><div class="tweet-disclosure '+(Ext.version.version >= '2.2.1' ? '' : 'no-pictos')+'"><div class="created_ago">{created_at:this.created_ago}</div><a href="http://twitter.com/{from_user}/status/{id_str}" target="_blank"></a></div>',
	                {
	                    linkify: function(value) {
							value = value.replace(/(http:\/\/[^\s]*)/g, "<a target=\"_blank\" href=\"$1\">$1</a>");
							value = value.replace(/@([^ ]+)/g,"<a class=\"user-link\" href=\"http://twitter.com/#!/$1\" target=\"_blank\">@$1</a>"); 
							value = value.replace(/(^| )#([^ ]+)/g,"$1<a class=\"hash-tag\" href=\"http://twitter.com/#!/search?q=%23$2\" target=\"_blank\">#$2</a>"); 
	                        return value;
	                    },
						created_ago: function(created_at){
							var now = new Date(); 
							var now_utc = Date.parse(now.toUTCString()); 
							var created_at_utc = Date.parse(created_at);
							var difference = Math.round((now_utc - created_at_utc)/1000);
							var difference_str, unit;
							if (difference < 60){
								difference_str = difference + ' ' + (difference == 1 ? WP.__('second') : WP.__('seconds'));
							}
							else if (difference < 60*60){
								difference = Math.round(difference/(60));
								difference_str = difference + ' ' + (difference == 1 ? WP.__('minute') : WP.__('minutes'));
							}
							else if (difference < 60*60*24){
								difference = Math.round(difference/(60*60));
								difference_str = difference + ' ' + (difference == 1 ? WP.__('hour') : WP.__('hours'));
							}
							else if (difference < 60*60*24*7){
								difference = Math.round(difference/(60*60*24));
								difference_str = difference + ' ' + (difference == 1 ? WP.__('day') : WP.__('days'));
							}
							else{
								difference = Math.round(difference/(60*60*24*7));
								difference_str = difference + ' ' + (difference == 1 ? WP.__('week') : WP.__('weeks'));
							}
							return difference_str + ' ' + WP.__('ago');
						}
	                }
	            ),
				itemId: 'list', 
	            cls: 'timeline',
	            emptyText   : '<p class="no-searches">'+WP.__('No tweets found matching that search')+'</p>',

	            disableSelection: true,
				scrollToTopOnRefresh: false,

	            plugins: [{
	                type: 'listpaging',
	                autoPaging: false
	            }, {
	                type: 'pullrefresh'
	            }],
			}
		],
		listeners: {
			activate: function(){
				var store = Ext.create('Ext.data.Store',
					{
						model: 'the_app.model.Tweet',
		                pageSize       : 25,
		                remoteFilter   : true,
		                clearOnPageLoad: false,
						filters: [
							new Ext.util.Filter({property: 'q',value   : this.getSearch()}),
							new Ext.util.Filter({property: 'use_app_search',value   : this.getUseAppSearch()})
						],
						autoLoad: false
					}
				);
				this.setMasked( {
					xtype: 'loadmask',
					message: WP.__('Loading')
				} );
				store.on( 'load', function(){
					this.setMasked( false );
				}, this, {
					single: true,
				});
				store.load();
				this.down('list').setStore(store);
				this.down('list').add({
						xtype: 'panel',
						itemId: 'matching',
						docked: 'top',
						scrollable: 'horizontal',
						height:40,
						cls: 'matching-message',
						html: ''
				});
				this.getNavigationBar().setTitle(this.getTitle());
				this.down('#matching').setHtml(WP.__('Tweets matching: ') + this.getSearch());
			}
		}		
	}
});