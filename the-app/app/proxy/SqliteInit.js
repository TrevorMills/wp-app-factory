Ext.define('SqliteDemo.util.InitSQLite', {
  singleton : true,

  requires: [
    'Sqlite.Connection',
    'Sqlite.data.proxy.SqliteStorage',
    'Ext.data.reader.Array'
  ],
  
  initDb: function() {
    Ext.ns('DbConnection');
    
    this.DbConnection = Ext.create('Sqlite.Connection', {
      dbName: 'db_' + WP.getAppId(),
      dbDescription: 'Database storage from a WP App Factory built app'
    });
  },
  
  getConnection: function() {
    if(!Ext.isDefined(this.DbConnection)) this.initDb();
    return this.DbConnection;
  }
});
