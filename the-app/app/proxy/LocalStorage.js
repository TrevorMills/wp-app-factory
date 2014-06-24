Ext.define('My.data.proxy.LocalStorage', {
    override: 'Ext.data.proxy.LocalStorage',

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
    }
});
