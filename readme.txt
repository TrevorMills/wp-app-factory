=== WP App Factory ===
Contributors: topquarky
Tags: sencha, app, custom post types, shortcodes, mobile, web app
Requires at least: 3.0
Tested up to: 3.6
Donate link: http://topquark.com
Stable tag: 2.0.4

WP App Factory adds an App custom post type that allows you to build a cross-device mobile web app out of your content.

== Description ==

Distinct from simply a mobile version of your web site, the goal of WP App Factory is to be able to make something that looks and feels like a native app.  It's not meant to display your entire content, but rather to display specific posts, pages or custom post types in an easy to use, cross-device mobile format.  Further, it is the goal of WP App Factory to be able to deliver the content such that it can be saved to the user's device for later offline use.  

WP App Factory uses [Sencha Touch](http://www.sencha.com/products/touch/) as the Javascript framework for building the app.  WP App Factory adds an App custom post type allowing you to build as many apps as you want.  The apps themselves are rendered using shortcodes.  For a Sample App, please see the Frequently Asked Questions section.  

== Installation ==

1. WP App Factory is temporarily available from the Top Quark repository at [topquark.com/wp-app-factory/extend/plugins/wp-app-factory](http://topquark.com/wp-app-factory/extend/plugins/wp-app-factory).  It will eventually live in the WordPress repository.
1. Download the plugin from the above URL, or use the WordPress plugin page to add it to your site directly.   
1. Install the ZIP file to your server and activate the plugin

== Screenshots ==

1. A page of posts where category_name=news, grouped by month using this shortcode: `[app_posts orderby=date order=desc title=News group_by=month category_name=news indexbar=false]`
2. A page of posts where category_name=news, grouped by post title using this shortcode: `[app_posts category_name=news title=Index]`
3. A custom page.  The shortcode that generated this is `[app_item title="Our Artists" icon=team1 callback=borealis_catalogue_app group_by=category orderby=category indexbar=false]`.  Note the callback attribute.  Elsewhere, I defined a function called `borealis_catalogue_app` that set up this page.  See the frequently asked questions.

== Frequently Asked Questions ==

= How does it work? = 
An app build with WP App Factory is built as a custom post type using shortcodes.  When the custom app renders, it completely bypasses the WordPress templating and builds its own HTML page, from the <!DOCTYPE> to the </html>.  As it parses the custom app, it builds up settings within an object that is globally available to any other plugin via a PHP statement like `$the_app = & TheAppFactory::getInstance();`.  The available shortcodes for building an app are listed below.  See the sample app later in this section for more details: 

* [the_app] - required as the first shortcode within all apps.  It instantiates the app
* [app_item] - either raw HTML or a post ID to create a page within the app 
* [app_item_wrapper] - allows you to wrap multiple items into a list on a single page
* [app_posts] - creates an index page and the appropriate detail page for a list of posts

= I get a 404 when I try to view my app = 
First off, you must set your permalink structure to something other than the default.  If your pages are at http://mydomain.com/?p=123, then this plugin will not work.

If you get a 404 on your app's permalink, try going to the Permalink settings page and resaving your permalink structure.  This flushes the rewrite rules and will allow the rewrite rules for the new apps post_type to get setup properly.

= How do I change the startup splash image = 
There are two startup images that you'll need to create - one for tablets (768 x 1004) and one for phones (320 x 460).  The image can be jpg, png or gif.  The title of the attachment to be used as the tablet startup must start with `startup_tablet`.  For the phone, the attachment title must start with `startup_phone`.  The easiest way to create these titles is just to name the files appropriately before uploading (i.e. startup_phone.jpg). To use a custom splash screen, simply upload the properly named file to your App post - the same way you would attach an image to a regular post.  WP App Factory will search associated media for the proper names and use those images.  

= How do I change the app icon = 
You'll likely want to create a custom icon for your app.  The icon should be 72 x 72 to cover both tablets and phones.  The image can be jpg, png or gif.  The icon image attachment title must begin with `icon`.  To use a custom icon, simply upload the icon to the App as you would upload an image to a regular post.  WP App Factory will search associated media and use the first appropriately titled file for the icon.

= How do I use a custom CSS stylesheet = 
As with using custom images, you may want to use a custom stylesheet.  Your stylesheet must be a css file and the filename must start with `stylesheet`.  Upload it to the app as you would an image to a regular WordPress post.  WP App Factory will search associated media and use the first appropriately named stylesheet.  

= Is there any way that I can define my own list from things other than posts? =
Yes, within the `[app_item]` shortcode, if you specify a `callback` attribute, then WP App Factory will call that function instead of trying to build the page out of posts.  You are then responsible for building the page yourself, and if you're taking your app offline, you are also responsible for making sure any files that need caching are added to the manifest.  

In the screenshots, I've put up a shot of a page of albums sorted by artist.  This was done using a callback.  The code is below for reference:

`
function borealis_catalogue_app($atts){
	$the_app = & TheAppFactory::getInstance();
	
	$item_defaults = array(
		'_is_default' => 'false',
		'xtype' => 'catalogue',
		'title' => 'Catalogue',
		'icon' => 'info',
		'store' => 'catalogue',
		'list_template' => '<div class="avatar"<tpl if="thumbnail"> style="background-image: url({thumbnail})"</tpl>></div><span class="name">{title}<br/><span class="tertiary">{category}</span></span>',
		'detail_template' => '<tpl if="thumbnail"><a href="{purchase_link}" target="_blank"><img class="thumbnail" src="{thumbnail}"></a></tpl></div><h3>{title}</h3><h4><a href="{purchase_link}" target="_blank">Buy this album</a></h4> {content}',
		'grouped' => 'true',
		'group_by' => 'first_letter',
		'indexbar' => 'true',
	);
	$query_defaults = array(
		'xtype' => 'catalogue',
		'model_callback' => 'borealis_catalogue_models',
		'data_callback' => 'borealis_catalogue_data',
		'orderby' => 'title'
	);
	
	$item_atts = shortcode_atts($item_defaults,$atts);
	$item_atts['query_vars'] = shortcode_atts($query_defaults,$atts);
	
	$the_app->addPostListItem($item_atts);

	add_action('the_app_factory_print_manifest','borealis_app_print_manifest');
}

function borealis_catalogue_data(){
	// Let's get the catalogue
	
	// Create an array of stdClass items, modelled the same way as the model setup in the next function.  For example
	$products = array();

	// Put your own logic here, likely a loop of some kind
	$product = new stdClass;
	$product->id = '123';
	$product->title = 'My Fancy Title';
	$product->content = 'Lorem Ipsum....';
	$product->thumbnail = 'http://mydomain.com/wp-content/uploads/my_image.jpg';
	$product->purchase_link = 'http://mydomain.com/buy-me';
	$product->category = 'My Category';
	
	$products[] = $product;
	return $products;
}

function borealis_catalogue_models($type){
	$model = array(
		'fields' => array(
			'id',
			'title',
			'category',
			'content',
			'thumbnail',
			'purchase_link'
		)
	);
	return $model;
}

function borealis_app_print_manifest(){
	$the_app = & TheAppFactory::getInstance();
	
	echo '# The Album Thumbnails'."\n";
	echo 'http://mydomain.com/wp-content/uploads/my_image.jpg'."\n";
}
`

= What hooks are available? = 
You may well want to do things with your app(s) that aren't possible with WP App Factory natively.  Fortunately, there are many hooks available to allow you to do custom things.  

Commonly used filters include:

* TheAppFactory_models - change the models for the app
* TheAppFactory_stores - change the stores for the app
* TheAppFactory_scripts - add additional Javascript to the app

Commonly used actions include
* the_app_factory_print_stylesheets - print additional stylesheets
* the_app_factory_print_scripts - print additional Javascript scripts
* the_app_factory_print_manifest - add files to the Cache Manifest

= Sample App = 

Here is a sample app that will give you an idea on how an app gets built.  Note that though they're formatted as comments, really any text within the app post is ignored (unless it's within a `[app_item]`).

`
/**
In the beginning, there was an app...
And the coder declared, let there be options:

* _is_debug_on = false (default) or true --+ sends Javascript messages to console.log() via a maybelog() call. Loads debug version of Sencha library.
* _is_using_manifest = false (default) or true --+ saves data to the user's device. An app gets 5MB for free without having to ask the user for permission.
* transition = slide (default), fade, pop, flip, cube, wipe (buggy) --+ the CSS3 transition to use between pages
* twitter = (string) --+ twitter query for a Twitter page. e.g. @my_twitter or "#my_hash OR #my_other_hash" (pro)

****************************************************/
[the_app _is_debug_on=true transition=slide]


/***************************************************
Then the coder said,
                    let there be [ app_item ]
may it render HTML5 markup in a variety of ways

* _is_default = false (default) or true --+ makes this item the first one that appears.
* title = (string) --+ the title of page. Also the title on the bottom toolbar icon.
* icon = action, add, arrow_down, arrow_left, arrow_right, arrow_up, compose, delete, organize, refresh, reply, search, settings, star (default), trash, maps, locate, home
* post = (int) --+ $post-&gt;ID of any WordPress post (optional)
* callback = (function_name) --+ a function to call to setup the page.  The bypasses the logic that sets up the page with posts and allows you to create a page out of whatever you wish.
* ]]Any HTML5 markup [[ or_wordpress_shortcode ]] can be contenticized [[/ --+ create a page from scratch. (note: markup must pass WordPress editor valid elements)
***************************************************/
[app_item title="Testing" icon=arrow_up]This is some content with <strong>markup</strong> and <a href="http://topquark.com">a link</a>[/app_item]
[app_item post=1] [/app_item] +-- Note the space


/*********************************************************
At this point, the coder paused for a smoke, whence he
predicted someone might want to group items together to
appear as a list, and so
                        let there be [ app_item_wrapper ]

* title = (string) --+ the title of page. Also the title on the bottom toolbar icon.
* icon = action, add, arrow_down, arrow_left, arrow_right, arrow_up, compose, delete, organize, refresh, reply, search, settings, star (default), trash, maps, locate, home
* _is_default = false (default) or true --+ makes this item the first one that appears.
***************************************************/
[app_item_wrapper title="Excellent" icon=star]
    [app_item title="Testing"]First item in the list[/app_item]
    [app_item post=1]
[/app_item_wrapper]


/*******************************************************
And as the peace of resistance, the coder requested for
comment, the ability to shortcodely-iddly a list of any
WordPress custom post type,
                           let there be [ app_posts ]

* title = (string) --+ the title of page. Also the title on the bottom toolbar icon.
* icon = action, add, arrow_down, arrow_left, arrow_right, arrow_up, compose, delete, organize, refresh, reply, search, settings, star (default), trash, maps, locate, home
* _is_default = false (default) or true --+ makes this item the first one that appears.
* post_type = (string) --+ default is 'posts', but can be any custom post type
* grouped = true (default) or false --+ whether to create group headers
* group_by = first_letter (default), category, month
* indexbar = true (default) or false --+ whether to create index bar
* list_template = the Sencha tpl for the list item
* detail_template = the Sencha tpl for the detail page

If you specify post_type, then you can actually
specify just about any of the parameters
available to get_posts(). [WP_Query documentation](http://codex.wordpress.org/Class_Reference/WP_Query#Parameters)
***************************************************/
[app_posts icon=info orderby=category grouped=true group_by=category]

[app_posts title="Events" category_name=events orderby=date order=desc grouped=true group_by=month indexbar=false list_template='<span class="title">{title}</span><span class="date secondary">{date:date("Y-m-d")}</span>']

[app_posts post_type=records title="Records" _is_default=true list_template='<div class="avatar"<tpl if="thumbnail"> style="background-image: url({thumbnail})"</tpl>></div><span class="name">{title}<br><span class="tertiary">{artist}</span></span>']

/*******************************************************
And finally a version 1.1 came out, with the addition of
a couple of new friendly shortcodes, via add-ons.  The
Programmer did them as plugins to lay a framework for
other people who might want to create add-ons.  At
any rate, with no further,
                          let there be [ app_map ]

* title = (string) --+ the title of the page, also the title of the bottom toolbar icon
* use_current_location = (string) false (default), or true.  Whether the map page should add a point for the current location
* _is_default = false (default) or true --+ makes this item the first one that appears.
* icon = action, add, arrow_down, arrow_left, arrow_right, arrow_up, compose, delete, organize, refresh, reply, search, settings, star, trash, maps, locate (default), home

                          also, let there be [ app_map_point ]

* title = (string) --+ the title of the point (required)
* lat = (string) --+ the latitude of the point (required)
* long = (string) --+ the longitude of the point (required)
* {content} = this shortcode accepts [app_map_point]content[/app_map_point], which will show up in the info bubble

Note: here's a great address to lat/long converter: http://itouchmap.com/latlong.html
***************************************************/
[app_map title=Map use_current_location=true]
	[app_map_point title="WordCamp Toronto" lat="43.652515" long="-79.370339"]
	    George Brown College
	    Building C
	    290 Adelaide Street East
	    Toronto, ON
	[/app_map_point]
	[app_map_point title="Holiday Inn Express" lat="43.652123" long="-79.373292"]
	    111 Lombard Street.
	[/app_map_point]
[/app_map]

/*******************************************************
Tweet tweet, twiddle, twiddle,
                              let there be [ app_twitter ]

* title = (string) --+ the title of the page, also the title of the bottom toolbar icon (default: 'Twitter')
* _is_default = false (default) or true --+ makes this item the first one that appears.
* icon = action, add, arrow_down, arrow_left, arrow_right, arrow_up, compose, delete, organize, refresh, reply, search, settings, star, trash, maps, locate (default), home
* search = a Twitter search to run the search.  e.g. "from:topquarky" to get tweets from @topquarky.  See https://support.twitter.com/articles/71577-how-to-use-advanced-twitter-search
***************************************************/
[app_twitter search="#WordPress"]
`

== Changelog ==

= 2.0.3beta6 = 
* Opening _blank links in device browser works again on native apps.

= 2.0.3beta5 = 
* Package for Android works again.

= 2.0.3beta4 = 
* Performance Improvement: Lazy panel instantiation, lazy store loading, faster model committing and infinite list scrolling
* New: Now supports Sencha Touch version 2.2.1
* Change: Removed dependency on constants when packaging/building.  $the_app->is('packaging') now tells you if you are packaging the app

= 2.0.3beta3 = 
* Fix: iOS Install App popup will not appear on packaged apps
* Change: Removed dependency on constants when packaging/building.  $the_app->is('packaging') now tells you if you are packaging the app
* Add: Build (no minify) option to build production without minifying the code.

= 2.0.3beta2 = 
* New: App can now be packaged for Native Native.  Uses Phonegap and delivers a ZIP file that can be built using Android SDK Tools

= 2.0.3beta = 
* New: App can now be packaged for iOS Native.  Uses Phonegap and delivers a ZIP file containing a MyApp.xcodeproj file that can be opened and run in XCode
* Change: Data for HTML Pages and Wrapper pages are now retrieved from server as opposed to being hardcoded in generated app.

= 2.0.2 = 
* Fix: how the rewrite rules get written for Multisite installations

= 2.0 = 
* Upgraded to work with Sencha Touch Version 2.  Lots of changes

= 1.1.0 = 
* New: added [app_twitter] shortcode to include a Twitter feed and [app_map] + [app_map_point] shortcode to include a Google map
* Fix: Made the splash screen better, including an ajax loading spinner (ooooh aaaah)

= 1.0.3 = 
* Made an SVN fail checking in 1.0.2.  Same fixes, proper check-in.  Duhhhhhhh.

= 1.0.2 = 
* Fix: orderby & order are allowed attributes within app_posts again
* Fix: if callback attribute is specified, then original shortcode atts are passed unfiltered to callback
* Fix: orderby can be `date`, doesn't have to be `date_gmt`
* Oops....SVN fail.  Please use version 1.0.3. 

= 1.0.1 = 
* Added filters for the data output

= 1.0.0rfc = 
* Initial Checkin

== Upgrade Notice ==

