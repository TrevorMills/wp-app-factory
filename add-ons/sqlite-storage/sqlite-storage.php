<?php
/*
Plugin Name: SqliteStorage
*/

class SqliteStorage{
	public function __construct(){
		add_action( 'TheAppFactory_parsePost', array( &$this, 'parsePost' ) );
	}
	
	public function parsePost( &$the_app ){
		if ( $the_app->is( 'using_manifest' ) && $the_app->get( 'storage_engine' ) == 'sqlitestorage' ){
			add_action( 'the_app_package_cordova', array( &$this, 'package_cordova' ) );
			add_action( 'the_app_config_xml', array( &$this, 'config_xml' ), 10, 2 );
			//add_filter( 'the_app_factory_package_app_json', array( &$this, 'app_json' ) );
		}
	}
	
	public function package_cordova( &$the_app ){
		$target = $the_app->get('package_target');
		
		switch( $target ){
		case 'android':
			$this->adjustAndroidManifest();
			$this->copyAndroidSourceFiles();
			$this->adjustCordovaPlugins();
			break;
		case 'ios':
			$this->copyIOSSourceFiles();
			$this->adjustCordovaPlugins();
			break;
		case 'pb':
			// Nothing to do for Phonegap Build
			break;
		}
	}
	
	public function adjustAndroidManifest(){
		$the_app = & TheAppFactory::getInstance();
		$native_root = $the_app->get('package_native_root');
		$ns = 'http://schemas.android.com/apk/res/android';
	
		$manifest_file = $native_root . 'AndroidManifest.xml';
		$manifest = simplexml_load_file( $manifest_file );
		
		// 
		$node = $manifest->xpath( 'application[@android:label="@string/app_name"]' );
		if ( $node ){
			$node = $node[0];
			/* adding in:
		        <activity android:label="@string/app_name" android:name="org.pgsqlite.SQLitePlugin">
		            <intent-filter />
		        </activity>
			*/
			$child = $node->addChild( 'activity' );
			$child->addAttribute( 'android:label', '@string/app_name', $ns );
			$child->addAttribute( 'android:name', 'org.pgsqlite.SQLitePlugin', $ns );
			$child->addChild( 'intent-filter' );
		}
		
		file_put_contents( $manifest_file, $the_app->prettify_xml( $manifest->asXML() ) );
	}
	
	public function copyAndroidSourceFiles(){
		$the_app = & TheAppFactory::getInstance();
		$the_app->build_cp_deep( dirname( __FILE__ ) . '/the-app/platforms/android/src', $the_app->get( 'package_native_root' ) . 'src/', null, true );
		$the_app->build_cp_deep( dirname( __FILE__ ) . '/the-app/platforms/android/assets', $the_app->get( 'package_native_root' ) . 'assets/', null, true );
	}
	
	public function copyIOSSourceFiles(){
		$the_app = & TheAppFactory::getInstance();
		$the_app->build_cp_deep( dirname( __FILE__ ) . '/the-app/platforms/ios/MyApp/Plugins', $the_app->get( 'package_native_root' ) . $the_app->get( 'package_name' ) . '/Plugins/', null, true );
		$the_app->build_cp_deep( dirname( __FILE__ ) . '/the-app/platforms/ios/www', $the_app->get( 'package_native_root' ) . 'www/', null, true );
	}
	
	public function config_xml( & $xml, $path ){
		/* adding in:
		    <feature name="SQLitePlugin">
		        <param name="android-package" value="org.pgsqlite.SQLitePlugin" />
		    </feature>
		*/	
		$the_app = & TheAppFactory::getInstance();
		if ( $the_app->get( 'package_target' ) == 'pb' ){
			$plugin = $xml->addChild( 'gap:plugin', null, 'http://phonegap.com/ns/1.0' );
			$plugin->addAttribute( 'name', 'com.millerjames01.sqlite-plugin' ); // Note: the Phonegap Build one is a fork of the plugin I downloaded - https://github.com/brodysoft/Cordova-SQLitePlugin
		}
		else{
			$feature = $xml->addChild( 'feature' );
			$feature->addAttribute( 'name', 'SQLitePlugin' );
			$param = $feature->addChild( 'param' );
			$param->addAttribute( 'name', $the_app->get( 'package_target' ) . '-package' );
			switch( $the_app->get( 'package_target' ) ){
			case 'ios':
				$param->addAttribute( 'value', 'SQLitePlugin' );
				break;
			case 'android':
				$param->addAttribute( 'value', 'org.pgsqlite.SQLitePlugin' );
				break;
			}
		}
	}
	
	public function adjustCordovaPlugins(){
		$the_app = & TheAppFactory::getInstance();
		$source = $the_app->get('package_native_www') . 'cordova_plugins.js';
		
		$in = file_get_contents( $source );
		$in = preg_replace( '/(module.exports.*)];/s', '$1
	,{
        "file": "plugins/com.phonegap.plugins.sqlite/www/SQLitePlugin.js",
        "id": "com.phonegap.plugins.sqlite.SQLitePlugin",
        "clobbers": [
            "SQLitePlugin"
        ]
    }
];', $in);
		$in = preg_replace( '/(module.exports.metadata[^}]*)/s', '$1
	,"com.phonegap.plugins.sqlite": "1.0.0"
', $in);
		file_put_contents( $source, $in );
	}
	
}	

new SqliteStorage();
