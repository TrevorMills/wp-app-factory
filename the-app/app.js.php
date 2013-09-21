<?php 
	$the_app = & TheAppFactory::getInstance(); 
	if ( $the_app->is('doing_package_command') or $the_app->is('packaging') ){
		$the_app->set('ios_install_popup',false);
	}
	list($icons,$startups) = $the_app->getAppImages(); 
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

// Disable the _dc=<timestamp> caching of the SDK files. 
Ext.Loader.setConfig({disableCaching:false});

Ext.define('Ext.data.MyModel', {
	override: 'Ext.data.Model',
    commit: function(silent) {
        var me = this,
            modified = this.modified;

        me.phantom = me.dirty = me.editing = false;
        me.modified = {};

        if (false && silent !== true) {
            me.afterCommit(modified);
        }
    }
});

<?php if ($the_app->get('ios_install_popup') == 'true') : $the_app->enqueue('require','Ext.ux.InstallApp'); ?>
// Tell the loader where to find the Ext.ux.InstallApp.js
Ext.Loader.setPath('Ext.ux.InstallApp','app/helper/Ext.ux.InstallApp.js');
<?php endif; ?>

<?php if ($the_app->is('using_manifest')) : $the_app->enqueue('require','Ext.ux.OfflineSyncStore'); $the_app->enqueue('require','Ext.data.proxy.LocalStorage'); ?>
// Tell the loader where to find the Ext.ux.OfflineSyncStore.js
Ext.Loader.setPath('Ext.ux.OfflineSyncStore','app/store/Ext.ux.OfflineSyncStore.js');
<?php endif; ?>

// Tell the loader where to find the Ext.data.ModelFaster.js
Ext.Loader.setPath('Ext.data.ModelFaster','app/model/Ext.data.ModelFaster.js');

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
	    },

		launch: function(){
	        // Initialize the main view
			Ext.Viewport.mask({
				xtype: 'loadmask',
				message: '',
				cls: 'splash'
			});
			<?php if ($the_app->get('has_splash') == 'true' and $the_app->get('splash_pause')) : ?>
			setTimeout(function(){
			<?php endif; ?>
			Ext.Viewport.unmask();
	        Ext.Viewport.add({
				xtype: 'mainpanel',
				title: <?php echo json_encode($the_app->get('title')); ?>,
				items: <?php echo TheAppFactory::anti_escape(json_encode($the_app->the_items())); ?>,
				hidden: true,
				showAnimation: {type: 'fade'},
				maxTabBarItems: <?php echo ($the_app->get('maxtabbaritems') ? $the_app->get('maxtabbaritems') : 'null');  ?>
			});
			if (Ext.get('app-loading')){
				Ext.get('app-loading').destroy();
			}
			<?php if ($the_app->get('ios_install_popup') == 'true') : ?>
			Ext.ux.InstallApp.init();
			<?php endif; ?>
		 
			<?php if ($the_app->get('has_splash') == 'true' and $the_app->get('splash_pause')) : ?>
			},<?php echo $the_app->get('splash_pause')*1000; ?>);
			<?php endif; ?>
			
		},
		
		confirm: function( options ){
			if (this.popupStack == undefined){
				this.popupStack = {};
			}

			if (this.popupStack[options.id] == undefined){
				this.popupStack[options.id] = Ext.create('Ext.Panel',
					{
		                modal: true,
						hideOnMaskTap: (options.hideOnMaskTap ? true : false),
						hidden: true,
		                centered: true,
		                width: (options.width  ? options.width : 300),
		                height: (options.height  ? options.height : 200),
						cls: 'popup '+options.id,
						items: [
							{
			                    xtype: 'toolbar',
			                    docked: 'top',
			                    title: options.title
							},
							{
								xtype: 'component',
								html: options.html,
				                styleHtmlContent: true,
				                scrollable: 'vertical'
							},
							{
								xtype: 'toolbar',
								docked: 'bottom',
								layout: {
									type: 'hbox',
									align: 'center',
									pack: 'center'
								},
								items: [
									{
										xtype: 'button',
										text: WP.__('No'),
										handler: function(){
											this.hidePopup( options.id );
										},
										scope: this
									},
									{
										xtype: 'button',
										text: WP.__('Yes'),
										handler: function(){
											var handler = options.handler || function(){};
											handler();
											this.hidePopup( options.id );
										},
										scope: this
									}
								]
							}
						]
	            	}
				);
				Ext.Viewport.add(this.popupStack[options.id]);
			}
			else{
				this.popupStack[options.id].destroy();
				delete this.popupStack[options.id];
				this.confirm( options );
				return;
			}

			this.popupStack[options.id].show('pop');
		},

		showPopup: function(options){
			if (this.popupStack == undefined){
				this.popupStack = {};
			}

			if (this.popupStack[options.id] == undefined){
				this.popupStack[options.id] = Ext.create('Ext.Panel',
					{
		                modal: true,
						hideOnMaskTap: (options.hideOnMaskTap ? true : false),
						hidden: true,
		                centered: true,
		                width: (options.width  ? options.width : 300),
		                height: (options.height  ? options.height : 200),
		                styleHtmlContent: true,
		                scrollable: 'vertical',
		                html: options.html+(options.spinner ? '<div class="spinner '+options.spinner+'"></div>' : ''),
						cls: 'popup '+options.id,
						items: [
							{
			                    xtype: 'toolbar',
			                    docked: 'top',
			                    title: options.title
							}
						]
	            	}
				);
				Ext.Viewport.add(this.popupStack[options.id]);
			}
			else{
				this.popupStack[options.id].setHtml(options.html+(options.spinner ? '<div class="spinner '+options.spinner+'"></div>' : ''));
			}

			this.popupStack[options.id].show('pop');
		},

		hidePopup: function(id){
			if (this.popupStack != undefined && this.popupStack[id] != undefined){
				this.popupStack[id].hide('fadeOut');
			}

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