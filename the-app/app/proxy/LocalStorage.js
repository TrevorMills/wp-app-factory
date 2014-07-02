Ext.define('My.data.proxy.LocalStorage', {
    override: 'Ext.data.proxy.LocalStorage',

	constructor: function(){
		this.callParent( arguments );
		this.addListener( 'exception', this.onException, this, { single: true } );
	},
	
    getIds: function() {
		var ids = this.callParent( arguments );

		// TMills - added this in to make sure that the ids are always numeric (unless they aren't - like if they're UUIDs or something)
		Ext.each( ids, function( id, index ){
			var number = parseInt( id, 10 );
			if ( (number + '') == id ){
				ids[ index ] = number;
			}
		});
        return ids;
    },

	onException: function(proxy, e){
		alert( 'This app requires more localstorage than is available.  Note to developers: in this case, it is recommended that you use another storage mechanism for your persistent data.  Try using \'sqlitestorage\' for the proxy.' );
	}
});
