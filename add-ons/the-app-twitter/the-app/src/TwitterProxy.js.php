/**
 * @class Ext.data.TwitterProxy
 * @extends Ext.data.ScriptTagProxy
 * 
 * This simple proxy allows us to use Twitter's JSON-P API to search for tweets. All we're really doing in this
 * class is setting a few defaults (such as the number of tweets per page and a simple JSON Reader), and using
 * any Filters attached to the read Operation to modify the request url (see buildRequest).
 * 
 */
Ext.data.TwitterProxy = Ext.extend(Ext.data.ScriptTagProxy, {
    //this is the url we always query when searching for tweets
    url: 'http://search.twitter.com/search.json',
    filterParam: undefined,
    
    constructor: function(config) {
        config = config || {};

        Ext.applyIf(config, {
            extraParams: {
                suppress_response_codes: true
            },
            
            reader: {
                type: 'json',
                root: function(d){
					if (!d.results){
						return [];
					}
					var now = new Date(); 
					var now_utc = Date.parse(now.toUTCString()); 
					for(var i = 0; i < d.results.length; i++){ 
						d.results[i].created_ago = getCreatedAgo(d.results[i].created_at,now_utc); 
					} 
					return d.results;
				}
            }
        });
        
        Ext.data.TwitterProxy.superclass.constructor.call(this, config);
    },
    
    /**
     * We need to add a slight customization to buildRequest - we're just checking for a filter on the 
     * Operation and adding it to the request params/url, and setting the start/limit if paging
     */
    buildRequest: function(operation) {
        var request = Ext.data.TwitterProxy.superclass.buildRequest.apply(this, arguments),
            filter  = operation.filters[0],
            params  = request.params;
        
        Ext.apply(params, {
            rpp: operation.limit,
            page: operation.page || 1
        });
        
        if (filter) {
            Ext.apply(params, {
                q: filter.value
            });
            
            //as we're modified the request params, we need to regenerate the url now
            request.url = this.buildUrl(request);
        }
        return request;
    }
});

Ext.data.ProxyMgr.registerType('twitter', Ext.data.TwitterProxy);

getCreatedAgo = function(created_at,now_utc){
	var created_at_utc = Date.parse(created_at);
	var difference = Math.round((now_utc - created_at_utc)/1000);
	var difference_str, unit;
	if (difference < 60){
		difference_str = difference + ' ' + (difference == 1 ? '<?php _e('second','app-twitter'); ?>' : '<?php _e('seconds','app-twitter'); ?>');
	}
	else if (difference < 60*60){
		difference = Math.round(difference/(60));
		difference_str = difference + ' ' + (difference == 1 ? '<?php _e('minute','app-twitter'); ?>' : '<?php _e('minutes','app-twitter'); ?>');
	}
	else if (difference < 60*60*24){
		difference = Math.round(difference/(60*60));
		difference_str = difference + ' ' + (difference == 1 ? '<?php _e('hour','app-twitter'); ?>' : '<?php _e('hours','app-twitter'); ?>');
	}
	else if (difference < 60*60*24*7){
		difference = Math.round(difference/(60*60*24));
		difference_str = difference + ' ' + (difference == 1 ? '<?php _e('day','app-twitter'); ?>' : '<?php _e('days','app-twitter'); ?>');
	}
	else{
		difference = Math.round(difference/(60*60*24*7));
		difference_str = difference + ' ' + (difference == 1 ? '<?php _e('week','app-twitter'); ?>' : '<?php _e('weeks','app-twitter'); ?>');
	}
	return difference_str + ' ' + '<?php _e('ago','app-twitter'); ?>';;
}