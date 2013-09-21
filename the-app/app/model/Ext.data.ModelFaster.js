Ext.define('Ext.data.ModelFaster',{
	extend: 'Ext.data.Model',
	
	config: {
		doAfterCommit: true
	},
	
	commit: function( silent ){
        var me = this,
            modified = this.modified;

        me.phantom = me.dirty = me.editing = false;
        me.modified = {};

        if (this.getDoAfterCommit() !== false && silent !== true) {
            me.afterCommit(modified);
        }
	}
});