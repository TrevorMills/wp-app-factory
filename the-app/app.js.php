<?php 
	$the_app = & TheAppFactory::getInstance(); 
	if ( $the_app->is('doing_package_command') or $the_app->is('packaging') ){
		$the_app->set('ios_install_popup',false);
	}
	list($icons,$startups) = $the_app->getAppImages(); 
	
	$items = $the_app->the_items();
	
	foreach ($items as $key => $item){
		$items[$key] = array(
			'xtype' => 'lazypanel',
			'originalItem' => $item
		);
		
	}
	
	if ($the_app->get('ios_install_popup')){
		$the_app->enqueue('require','Ext.ux.InstallApp');
	}
	if ($the_app->is('using_manifest')){
		$the_app->enqueue('require','Ext.ux.OfflineSyncStore'); 
		$the_app->enqueue('require','Ext.ux.OfflineSyncStatusStore'); 
		$the_app->enqueue('require','My.data.proxy.LocalStorage');
	}
?>
<?php header('Content-type: text/javascript'); ?>
// While tracking down unacceptable browsers,
// for some reason IE9 is seting the Ext.Loader.config.paths.Ext wrong
// The logic where this gets set is at the bottom of sencha-touch.js.  
// This check is just to cover that use case.  Grrrrr....
if (!Ext.Loader.config.paths.Ext.match(/\/sdk\//)){
    var scripts = document.getElementsByTagName('script');
	Ext.each(document.getElementsByTagName('script'),function(script){
		if (script.src.match(/sencha-touch/)){
	        var src = script.src,
	        	path = src.substring(0, src.lastIndexOf('/') + 1);
			Ext.Loader.config.paths.Ext = path + 'src';
			return false;
		}
	});
}

// Doing this greatly improves the performance of Ext.decode
Ext.USE_NATIVE_JSON = true;

// Disable the _dc=<timestamp> caching of the SDK files. 
Ext.Loader.setConfig({disableCaching:false});

<?php foreach ( $the_app->registered['path'] as $name ) : ?>
Ext.Loader.setPath( '<?php echo $name; ?>', '<?php echo $the_app->registered['paths']['path'][$name]; ?>' );
<?php endforeach; ?>	

<?php if (!$the_app->is('packaging')) : ?>
var ub = <?php $meta = $the_app->get('meta'); echo json_encode($meta['unacceptable_browser']); ?>;
if(!Ext.browser || (ub.not_webkit && !Ext.browser.is.WebKit) || (ub.desktop && Ext.os.is.Desktop)){
	if (isUnsupportedBrowser){ // This gets set in index.html
		unsupportedBrowser();
	}
	else{
		try{
			// This is a minimal application required to show our Unsupported screen in browsers
			Ext.application({
				name: 'the_app', 

				views: ['UnsupportedBrowser'],
				controllers: ['<?php echo implode("','",apply_filters('unsupported_browser_controllers',array('Main'))); ?>'],
				requires: ['<?php echo implode("','",apply_filters('unsupported_browser_requires',array('the_app.helper.WP'))); ?>'],

				launch: function(){
			        // Initialize the main view
					var message;
					if (ub != undefined && ub.content != undefined){
						message = ub.content;
					}
					else{
						message = WP.__('Your browser is not supported.  Please use a Webkit Browser (i.e. Chrome, Safari, iPhone, Android).');
					}
					the_app.app.getController('Main').trackEvent('Browser','unsupported',Ext.browser.name);
					Ext.Viewport.add({
						xtype: 'unsupportedbrowser',
						id: 'unacceptable-browser',
						data: {
							message: message,
							url: window.location.href
						}
					});
					if (Ext.get('app-loading')){
						Ext.get('app-loading').destroy();
					}
				}
			});
		}
		catch(e){
			unsupportedBrowser();
		}
	}
}
else{
	delete ub; 
<?php endif; // $the_app->is('packaging') ?>
	Ext.application({
		name: 'the_app', 

		models: <?php $the_app->render('models'); ?>,
		views: <?php $the_app->render('view'); ?>,
		controllers: <?php $the_app->render('controller'); ?>,
		stores: <?php $the_app->render('stores'); ?>,
		profiles: <?php $the_app->render('profile'); ?>,
		requires: <?php $the_app->render('require'); ?>,

	    icon: <?php echo json_encode($icons); ?>,
	    startupImage: <?php echo json_encode($startups); ?>,
		meta: <?php $the_app->render('meta'); ?>,

		eventPublishers: {
	        touchGesture: {
	            moveThrottle: 10
	        }
	    },

	    isIconPrecomposed: true,

	    viewport: {
			// Changed this based on comments at http://docs.sencha.com/touch/2-0/#!/api/Ext.Viewport-cfg-autoMaximize
	        autoMaximize: ( navigator.userAgent.search("Safari") != -1 && (!Ext.browser.is.Standalone && Ext.os.is.iOS && Ext.browser.version.isGreaterThan(3) ) ? true : false )
			// autoMaximize: false
	    },

		launch: function(){
	        Ext.Viewport.add({
				xtype: 'launcher',
				title: <?php echo json_encode($the_app->get('title')); ?>,
				fullscreen: true,
				mainItems: <?php echo TheAppFactory::anti_escape(json_encode( $items )); ?>,
				<?php if ( 'sheet' == $the_app->get('menu_style') ) : ?>
				sheetMenuItems: <?php echo TheAppFactory::anti_escape( json_encode( $the_app->get( 'sheet_menu_items' ) ) );  ?>,
				<?php endif; ?>				
				<?php if ($the_app->get('ios_install_popup')) : ?>
				installApp: true,
				<?php endif; ?>				
			});
 	/* 
    Here we set the initial data model user in localstorage. This model will be constructed with user information to better communicate with the X-CD web app.
    xcduuid is the unique user identifier of each user
    AppOpen will always be set to false until the user inputs the password to unlock the app. Then it will equal true. */
    Ext.define('user', {
    extend: 'Ext.data.Model',
    config: {
    fields: [ {name: 'xcddeviceuuid', type: 'string'}, 
              {name: 'AppOpen', type: 'boolean'},
              {name: 'LockedOut', type: 'boolean'} ],
    proxy: {
    type: 'localstorage',
    id  : 'users_' + WP.getLocalStorageKey()
    }
    }  
    });
    /* Create a proxy to access the model as a store */
    var storeModel = Ext.create('Ext.data.Store', {
    model: 'user'
    });
    /* Grab the model here we just created */
    var user = Ext.ModelManager.getModel('user');
    /* Load the store we created */
    var userObject = storeModel.load();
    var recordOne = userObject.data.keys[0];
    console.log('Record one: ' + recordOne);
    if ( recordOne ) {
    user.load(recordOne, {
    success: function(user) {
    console.log('SuccessUser: ' + user);
    /* Now check if the user has unlocked the app */
    var unlocked = user.get('AppOpen');
    if ( unlocked ) {
    /* The user may access the app. */
    } else {
    /* Show the password prompt screen. Hide app menu. */
    Ext.Msg.prompt('Conference Key', '******', function(btn, text, cfg) {
    if( text == 55555 && btn == "ok" ) {
    /* Allow the user to access the app */
    /* Update the user's object to show true and grant them access */
    user.set('AppOpen', true);
    /* Now save the change */
    user.save();
    /* Unlock the app for the user */
    } else if ( btn == "cancel" ) {
    window.location.reload();
    } else {
    /* Show the password prompt screen. Hide app menu. */
    Ext.Msg.prompt('Conference Key', '******', function(btn, text, cfg) {
    if( text == 55555 && btn == "ok" ) {
    /* Allow the user to access the app */
    /* Update the user's object to show true and grant them access */
    user.set('AppOpen', true);
    /* Now save the change */
    user.save();
    /* Unlock the app for the user */
    } else if ( btn == "cancel" ) {
    window.location.reload();
    } else {
    /* Show the password prompt screen. Hide app menu. */
    Ext.Msg.prompt('Conference Key', '******', function(btn, text, cfg) {
    if( text == 55555 && btn == "ok" ) {
    /* Allow the user to access the app */
    /* Update the user's object to show true and grant them access */
    user.set('AppOpen', true);
    /* Now save the change */
    user.save();
    /* Unlock the app for the user */
    } else if ( btn == "cancel" ) {
    window.location.reload();
    } else {
        /* Show the password prompt screen. Hide app menu. */
    Ext.Msg.prompt('Conference Key', '******', function(btn, text, cfg) {
    if( text == 55555 && btn == "ok" ) {
    /* Allow the user to access the app */
    /* Update the user's object to show true and grant them access */
    user.set('AppOpen', true);
    /* Now save the change */
    user.save();
    /* Unlock the app for the user */
    } else if ( btn == "cancel" ) {
    window.location.reload();
    } else {
    /* Show the password prompt screen. Hide app menu. */
    Ext.Msg.prompt('Conference Key', '******', function(btn, text, cfg) {
    if( text == 55555 && btn == "ok" ) {
    /* Allow the user to access the app */
    /* Update the user's object to show true and grant them access */
    user.set('AppOpen', true);
    /* Now save the change */
    user.save();
    /* Unlock the app for the user */
    } else if ( btn == "cancel" ) {
    window.location.reload();
    } else {
    alert('You have entered the password incorrectly 5 times.')
    user.set('LockedOut', true);
    /* Reload the page. Make the user try again. Potential for lockout features to be added here. */
    window.location.reload();
    }
    });   
    }
    });   
    }
    });   
    }
    });   
    }
    });   
    }
    },
    failure: function(user) {
    console.log('Load failed. Please alert the administrator.');
    }
    });
    } else {
    /* The user does not have a UUID */
    console.log('does not exist');
    /* Now create a user in localstorage */
    /* First, generate a unique UUID if the user does not have one assigned. */  
    function s4() {
    return Math.floor((1 + Math.random()) * 0x10000)
    .toString(16)
    .substring(1);
    }
    var UUID = s4() + s4() + '-' + s4() + '-' + s4() + '-' +
    s4() + '-' + s4() + s4() + s4();
    /* Now we need to create a new user with a UUID and make them enter a code to unlock the app */
    var newUser = Ext.create('user', {
    xcddeviceuuid: UUID,
    AppOpen: false,
    });
    newUser.save();
    /* Show the password prompt screen. Hide app menu. */
    Ext.Msg.prompt('Conference Key', '******', function(btn, text, cfg) {
    if( text == 55555 && btn == "ok" ) {
    /* Allow the user to access the app */
    /* Update the user's object to show true and grant them access */
    user.set('AppOpen', true);
    /* Now save the change */
    user.save();
    /* Unlock the app for the user */
    } else if ( btn == "cancel" ) {
    window.location.reload();
    } else {
    /* Show the password prompt screen. Hide app menu. */
    Ext.Msg.prompt('Conference Key', '******', function(btn, text, cfg) {
    if( text == 55555 && btn == "ok" ) {
    /* Allow the user to access the app */
    /* Update the user's object to show true and grant them access */
    user.set('AppOpen', true);
    /* Now save the change */
    user.save();
    /* Unlock the app for the user */
    } else if ( btn == "cancel" ) {
    window.location.reload();
    } else {
    /* Show the password prompt screen. Hide app menu. */
    Ext.Msg.prompt('Conference Key', '******', function(btn, text, cfg) {
    if( text == 55555 && btn == "ok" ) {
    /* Allow the user to access the app */
    /* Update the user's object to show true and grant them access */
    user.set('AppOpen', true);
    /* Now save the change */
    user.save();
    /* Unlock the app for the user */
    } else if ( btn == "cancel" ) {
    window.location.reload();
    } else {
        /* Show the password prompt screen. Hide app menu. */
    Ext.Msg.prompt('Conference Key', '******', function(btn, text, cfg) {
    if( text == 55555 && btn == "ok" ) {
    /* Allow the user to access the app */
    /* Update the user's object to show true and grant them access */
    user.set('AppOpen', true);
    /* Now save the change */
    user.save();
    /* Unlock the app for the user */
    } else if ( btn == "cancel" ) {
    window.location.reload();
    } else {
    /* Show the password prompt screen. Hide app menu. */
    Ext.Msg.prompt('Conference Key', '******', function(btn, text, cfg) {
    if( text == 55555 && btn == "ok" ) {
    /* Allow the user to access the app */
    /* Update the user's object to show true and grant them access */
    user.set('AppOpen', true);
    /* Now save the change */
    user.save();
    /* Unlock the app for the user */
    } else if ( btn == "cancel" ) {
    window.location.reload();
    } else {
    alert('You have entered the password incorrectly 5 times.')
    user.set('LockedOut', true);
    /* Reload the page. Make the user try again. Potential for lockout features to be added here. */
    window.location.reload();
    }
    });   
    }
    });   
    }
    });   
    }
    });   
    }
    });   
    } 

		},
		
		confirm: function( options ){
			Ext.apply( options, {
				buttons: [
					{
						xtype: 'button',
						text: WP.__('No'),
						handler: function( button ){
							var handler = options.handlerNo || Ext.emptyFn;
							handler.apply( this );
							this.hidePopup( button.up( 'panel' ).getId() );
						},
						scope: this
					},
					{
						xtype: 'button',
						text: WP.__('Yes'),
						handler: function( button ){
							var handler = options.handler || Ext.emptyFn;
							handler.apply( this );
							this.hidePopup( button.up( 'panel' ).getId() );
						},
						scope: this
					}
				]
			});
			this.showPopup( options );
		},
		
		alert: function( options ){
			Ext.apply( options, {
				hideOnMaskTap: true,
				buttons: [
					{
						xtype: 'button',
						text: options.buttonText ? options.buttonText : WP.__('Ok'),
						handler: function( button ){
							this.hidePopup( button.up( 'panel' ).getId() );
						},
						scope: this
					},
				]
			});
			this.showPopup( options );
		},
		
		showPopup: function( options ){
			var config = {
				xtype: 'panel',
				modal: true,
				hideOnMaskTap: (options.hideOnMaskTap ? true : false),
				hidden: true,
				centered: true,
                width: (options.width  ? options.width : 300),
                height: (options.height  ? options.height : 200),
				cls: 'popup',
				hideAnimation: {
					type: 'popOut',
					duration: 250,
					easing: 'ease-out',
					listeners: {
						animationend: function(evt, obj) {
							this.removePopup( obj.id );
						},
						scope: this
					},
				},
				showAnimation: {
					type: 'popIn',
					duration: 250,
					easing: 'ease-out',
					listeners: {
						animationend: function(evt, obj) {
						}
					},
				},
				items: [],
				zIndex: 10000
			};
			if ( options.id ){
				config.cls += ' ' + options.id;
				config.id = options.id;
				var existing = Ext.getCmp( config.id );
				if ( existing ){
					this.removePopup( existing );
				}
			}
			
			if ( typeof options.showAnimation != 'undefined' ){
				config.showAnimation = options.showAnimation;
			}
			
			if ( this.last_popup ){
				this.last_popup.hide();
				config.showAnimation = false;
			}
			
			if ( options.title ){
				config.items.push( {
                    xtype: 'toolbar',
                    docked: 'top',
                    title: options.title
				});
			}
			if ( !options.html && options.message ){
				options.html = options.message;
			}
			if ( options.html ){
				config.items.push( {
					xtype: 'component',
					html: options.html + (options.spinner ? '<div class="spinner ' + options.spinner + '"></div>' : '' ),
	                styleHtmlContent: true,
	                scrollable: 'vertical'
				});
			}
			if ( options.buttons ){
				config.items.push( {
					xtype: 'toolbar',
					docked: 'bottom',
					layout: {
						type: 'hbox',
						align: 'center',
						pack: 'center'
					},
					items: options.buttons
				});
			}
			
			this.last_popup = Ext.Viewport.add( config );
			this.last_popup.show();
		},
		
		hidePopup: function( id ){
			this.removePopup( id );
		},
		
		removePopup: function( cmp ){
			if ( Ext.isString( cmp ) ){
				cmp = Ext.getCmp( cmp );
			}
			if ( !cmp ){
				return;
			}
			cmp.destroy();
			// For some reason, ST leaves masks lying around.  I'm going to clean them up
			Ext.each( Ext.Viewport.query( 'mask' ), function( mask ){
				mask.destroy();
			});
			this.last_popup = false;
		},
		
	    onUpdated: function() {
			the_app.app.confirm(
				{
					id: 'update', 
					title: WP.__("Application Update"),
					html: WP.__("This application has just successfully been updated to the latest version. Reload now?"),
					hideOnMaskTap: false,
					handler: function(){
						window.location.reload();
					}
				}
			);
	    }



	});
<?php if (!$the_app->is('packaging')) : ?>	
}
<?php endif; ?>