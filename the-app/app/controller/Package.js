Ext.define('the_app.controller.Package', {
    extend: 'Ext.app.Controller',

	launch: function(){
		var me = this;
		Ext.onReady(function(){
			me.onReady();
		});
	},
	
	onReady: function(){
	    var documentLocation = document.location,
	        currentLocation = documentLocation.origin + documentLocation.pathname + documentLocation.search,
	        dependencies = [],
	        path;

	    function getRelativePath(from, to) {
	        var fromParts = from.split('/'),
	            toParts = to.split('/'),
	            index = null,
	            i, ln;

	        for (i = 0, ln = toParts.length; i < ln; i++) {
	            if (toParts[i] !== fromParts[i]) {
	                index = i;
	                break;
	            }
	        }

	        if (index === null || index === 0) {
	            return from;
	        }

	        fromParts = fromParts.slice(index);

	        for (i = 0; i < ln - index - 1; i++) {
	            fromParts.unshift('..');
	        }

	        for (i = 0, ln = fromParts.length; i < ln; i++) {
	            if (fromParts[i] !== '..' && fromParts[i+1] === '..') {
	                fromParts.splice(i, 2);
	                i -= 2;
	                ln -= 2;
	            }
	        }

	        fromParts = fromParts.map(function(part){
	            return decodeURIComponent(part);
	        });

	        return fromParts.join('/');
	    }

	    Ext.Loader.history.forEach(function(item) {
	        path = Ext.Loader.getPath(item);
	        path = getRelativePath(path, currentLocation);

	        dependencies.push({
	            path: path,
	            className: item
	        });
	    });
	
		AjaxChain = function(commands,which){
			if (which == undefined){
				which = 0;
			}
			if (which >= commands.length){
				// all done
				the_app.app.hidePopup('build');
				this.wrapup();
				return;
			}
			the_app.app.showPopup(
				{
					id: 'build', 
					title: 'Packaging....',
					html: commands[which].message,
					spinner: 'black x48',
					hideOnMaskTap: false,
					showAnimation: false,
				}
			);

			var the_callback = commands[which].callback;
			delete commands[which].message; // don't sent to the server
			delete commands[which].callback;
			
			Ext.apply( commands[which], {
				action: 'package_app',
				id: WP.getID(),
				building: 'true',
				target: WP.getPackageTarget(),
				minify: WP.getIsMinifying()
			});
						
			Ext.Ajax.request({
				url: WP.getAjaxUrl(),
				timeout: 300000,
				params: commands[which],
				success: function(data){
					try{
						var response = JSON.parse(data.responseText);
						if (response.success){
							if (typeof the_callback == 'function'){
								the_callback(data);
							}
							else{
								console.log(response.message);
							}
							setTimeout(function(){
								AjaxChain(commands,which+1);
							},2000);
						}
						else{
							the_app.app.showPopup(
								{
									id: 'build', 
									title: 'Package Failed....',
									html: 'The package processed failed.  Please review messages in the console and retry',
									hideOnMaskTap: false
								}
							);
							console.log(response.message);
						}
					}
					catch(e){
						the_app.app.showPopup(
							{
								id: 'build', 
								title: 'Build Failed....',
								html: 'The build processed failed.  Please review messages in the console and retry',
								hideOnMaskTap: false
							}
						);
						
						console.log('[ERROR] - I expected valid JSON to be returned from the server, but I got this: ');
						console.log(data.responseText);
					}
				},
				failure: function(response){
					console.log(response);
				}
			});
		}.bind(this);

		AjaxChain([
			/*
			*/
			{
				// Step 0 - Rough in Cordova
				// Sets up directory structure for package
				command: 'cordova',
				message: 'Setting up Cordova (Phonegap)...'
			},
			/*
			*/
			{
				// Step 1 - Deploy.  This creates the production directory structure
				// and copies over sencha-touch.js plus other resources
				command: 'deploy',
				message: 'Deploying App...<br/>- copying JS and CSS files'
			},
			/*
			*/
			{
				// Step 1a - Deploy Resources.  This copies over any data as well as
				// icons and splash images
				command: 'resources',
				message: 'Deploying Resources...<br/>- data, icons and splash images'
			},
			/*
			*/
			{
				// Step 2 - Dependencies.  This concatenates all required JS files
				// into a single app.js file
				command: 'dependencies',
				dependencies: Ext.encode(dependencies),
				message: 'Finding Dependencies (be patient)...<br/>- concats all required JS files into one<br/>',
			},
			/*
			*/
			{
				// Step 3 - Deploy index.html.  This generates the index.html
				command: 'index',
				message: 'Building index.html'
			},
			/*
			*/
			{
				// Step 4 - Wrapup - vital - this adds the version numbers to app.json
				command: 'wrapup',
				message: 'Done...'
			},
			/*
			*/
			{
				// Step 5 - Download the zip
				command: 'zip',
				message: 'Zipping up the archive',
				callback: function( data ){
					var response = JSON.parse(data.responseText);
					the_app.app.hidePopup('build');
					
					the_app.app.confirm({
						id: 'download-zip', 
						title: WP.__("Ready"),
						html: WP.__("The package is ready to be downloaded as a ZIP file.  Download now?"),
						hideOnMaskTap: false,
						handler: function(){
							window.open( response.message, '_blank');
						}
					})
				}
			},
			/*
			//*/
		]);
	},
	
	wrapup: function(){
	}
    
});
