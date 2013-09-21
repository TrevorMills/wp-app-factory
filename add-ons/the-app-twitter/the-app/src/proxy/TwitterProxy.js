/**
 * @class Ext.data.TwitterProxy
 * @extends Ext.data.ScriptTagProxy
 * 
 * This simple proxy allows us to use Twitter's JSON-P API to search for tweets. All we're really doing in this
 * class is setting a few defaults (such as the number of tweets per page and a simple JSON Reader), and using
 * any Filters attached to the read Operation to modify the request url (see buildRequest).
 * 
 */
Ext.define('the_app.proxy.TwitterProxy',{
    extend: 'Ext.data.proxy.JsonP',

    config: {
        // This is the url we always query when searching for tweets
        url: '', // set later WP.getUrl()+'data/tweets', //'http://search.twitter.com/search.json',
		nextResults: {},
        
        extraParams: {
            suppress_response_codes: true
        },

        reader: {
            type: 'json',
			rootProperty: 'statuses'
        }
    },

    filterParam: undefined,

    /**
     * We need to add a slight customization to buildRequest - we're just checking for a filter on the 
     * Operation and adding it to the request params/url, and setting the start/limit if paging
     */
    buildRequest: function(operation) {
		this.setUrl(WP.getUrl()+'data/tweets');
        var request = this.callParent(arguments),
			filter, 
            params  = request.getParams();

        Ext.apply(params, {
            count: operation.getLimit(),
            page: operation.getPage(),
			next_results: (operation.getPage() == 1 ? '' : this.getNextResults())
        });

		if (operation.getFilters()){
			filter = operation.getFilters()[0];
		}
        if (filter) {
            delete params.filter;
            Ext.apply(params, {
                q: filter.getValue() // pass in the query string to the search api
            });

            request.setParams(params);
            request.setUrl(this.getUrl());

            // As we're modifiying the request params, we need to regenerate the url now
            request.setUrl(this.buildUrl(request));
        }

        return request;
    },

    createRequestCallback: function(request, operation, callback, scope) {
        var me = this;

        return function(success, response, errorType) {
            delete me.lastRequest;
			var page = request.getParams().page;
			me.setNextResults(typeof response.search_metadata == 'object' ? response.search_metadata.next_results : '');
            me.processResponse(success, operation, request, response, callback, scope);
        };
    },
});