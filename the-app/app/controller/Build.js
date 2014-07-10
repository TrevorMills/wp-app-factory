Ext.define('the_app.controller.Build', {
    extend: 'Ext.app.Controller',

	launch: function(){
		Ext.onReady(this.onReady);
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
				return;
			}
			the_app.app.showPopup(
				{
					id: 'build', 
					title: 'Building....',
					html: commands[which].message,
					spinner: 'black x48',
					hideOnMaskTap: false,
					showAnimation: false
				}
			);

			delete commands[which].message; // don't sent to the server
			commands[which].action = 'build_app';
			commands[which].id = WP.getID();
			commands[which].building = 'true';
			commands[which].minify = WP.getIsMinifying();
			
			Ext.Ajax.request({
				url: WP.getAjaxUrl(),
				timeout: 120000,
				params: commands[which],
				success: function(data){
					try{
						var response = JSON.parse(data.responseText);
						if (response.success){
							if (typeof commands[which].callback == 'function'){
								commands[which].callback(data);
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
									title: 'Build Failed....',
									html: 'The build processed failed.  Please review messages in the console and retry',
									hideOnMaskTap: false,
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
				// Step 1 - Deploy.  This creates the production directory structure
				// and copies over sencha-touch.js plus other resources
				command: 'deploy',
				message: 'Deploying App...<br/>- copying JS and CSS files'
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
			// Packer Code - see note at top of the_app_builder.php
			// I'm going to leave this code in just in case I'm able to get back to it.
			// I'd rather use class.JavaScriptPacker.php to pack the Javascript than JSMin.
			// This code was added because the packing was causing problems and I wanted to 
			// created beautified versions of the packed javascript files to be able to 
			// track down JS errors.  As I was investigating, I found that the packing class
			// itself must have a bug because it wasn't able to properly pack the Ext.JSON 
			// object created around line 3481 of sencha-touch.js.  
			{
				command: 'get_production_url',
				message: 'Creating unpacked versions of JS files (for testing)',
				callback: function(data){
					try{
						var response = JSON.parse(data.responseText);
						var beautify = [];
						var unpack_count = 0;
						Ext.each(response.data.js,function(js){
							if (js.remote){
								return;
							}
							unpack_count++;
							Ext.Ajax.request({
								url: js.path, //response.data.root+js.path,
								success: function(data){
									//data.responseText = data.responseText.replace(/^\/[^\/]+\/eval\(/,'var unpacked = ');
									//data.responseText = data.responseText.replace(/[\)][^\)]*$/,';');
									try{
										//eval(data.responseText);
										beautify.push({
											command: 'beautify',
											path: js.path,
											unpacked: data.responseText,
											message: 'Creating test files<br/>- unpacking '+js.path
										});
									}
									catch(e){
										console.log('[ERROR] - could not evaluate '+js.path);
									}
								}
							});
						});
						var interval = setInterval(function(){
							if (beautify.length == unpack_count){
								clearInterval(interval);
								AjaxChain(beautify);
							}
						},200);
					}
					catch (e){
						console.log(data.responseText);
					}
				}
			}
			//*/
		]);
	}
    
});
