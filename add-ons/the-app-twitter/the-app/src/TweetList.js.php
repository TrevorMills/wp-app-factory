<?php if (!defined('APP_IS_VALID')) die('// Move along...'); ?>

the_app.views.TweetList = Ext.extend(Ext.Panel, {
    search: '',
    layout: 'fit',
    initComponent: function(){
	
        var toolbarBase = {
            xtype: 'toolbar',
            title: this.search,
			items: []
        };
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
                }
            }
        }
        
        this.dockedItems = toolbarBase;
        

        var searchModel = Ext.ModelMgr.getModel("TwitterSearch");
        var search = new searchModel({
            query: this.search
        });

        var store = search.tweets();

        var tweetList = {
            cls: 'timeline',
            emptyText   : '<p class="no-searches"><?php _e('No tweets found matching that search','app-twitter'); ?></p>',

            disableSelection: true,

            store: store,

            plugins: [{
                ptype: 'listpaging',
                autoPaging: false
            }, {
                ptype: 'pullrefresh'
            }],

            itemCls: 'tweet',
            itemTpl: new Ext.XTemplate('<?php echo ($the_app->get('twitter_list_template') != '' ? $the_app->get('twitter_list_template') : '<div class="avatar"<tpl if="profile_image_url"> style="background-image: url({profile_image_url})"</tpl>><a href="http://twitter.com/{from_user}" target="_blank">&nbsp;</a></div> <div class="tweet"><strong><a href="http://twitter.com/{from_user}" target="_blank" class="from_user_link">{from_user}</a></strong><span class="created_ago">{created_ago}</span><br />{text:this.linkify}</div><div class="x-list-disclosure tweet-disclosure"><a href="http://twitter.com/{from_user}/status/{id_str}" target="_blank"></a></div>' ); ?>',
                {
                    linkify: function(value) {
						value = value.replace(/(http:\/\/[^\s]*)/g, "<a target=\"_blank\" href=\"$1\">$1</a>");
						value = value.replace(/@([^ ]+)/g,"<a class=\"user-link\" href=\"http://twitter.com/#!/$1\" target=\"_blank\">@$1</a>"); 
						value = value.replace(/(^| )#([^ ]+)/g,"$1<a class=\"hash-tag\" href=\"http://twitter.com/#!/search?q=%23$2\" target=\"_blank\">#$2</a>"); 
                        return value;
                    }
                }
            )
        };

		this.list = new Ext.List(Ext.apply(tweetList,{}));

		store.load();


        this.items = [this.list];
        
        the_app.views.TweetList.superclass.initComponent.call(this);
    }
});

Ext.reg('tweetlist', the_app.views.TweetList);

