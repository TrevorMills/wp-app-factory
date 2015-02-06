Ext.define('the_app.helper.GoogleAnalytics', {

    mixins: ['Ext.mixin.Observable'],

    singleton: true,

	alternateClassName: ['GoogleAnalytics'],

    /**
     * @constructor
     * Load the Google Analytics SDK asynchronously
     */
    initialize: function() {

		(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		})(window,document,'script','//www.google-analytics.com/analytics' + ( GoogleAnalyticsConfig.getDebug() ? '_debug' : '' ) + '.js','ga');
		
		var config = 'auto';
		if (document.domain == 'localhost'){
			config = {
				'cookieDomain': 'none'
			}
		}

		ga('create', GoogleAnalyticsConfig.getAccount(), config);
		ga('send','pageview');
    },

	push: function(commandArray){
		if (GoogleAnalyticsConfig.getDebug() == 'true'){
			console.log(commandArray);
		}
		//_gaq.push(commandArray);
	}

});