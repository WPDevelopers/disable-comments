=== Disable Comments ===
Contributors: solarissmoke
Donate link: http://rayofsolaris.net/donate.php
Tags: comments, disable, global
Requires at least: 3.6
Tested up to: 4.1
Stable tag: trunk

Allows administrators to globally disable comments on their site. Comments can be disabled according to post type. Multisite friendly.

== Description ==

This plugin allows administrators to globally disable comments on any post type (posts, pages, attachments, etc.) so that these settings cannot be overridden for individual posts. It also removes all comment-related fields from edit and quick-edit screens. On multisite installations, it can be used to disable comments on the entire network.

Additionally, comment-related items can be removed from the Dashboard, Widgets, the Admin Menu and the Admin Bar.

**Important note**: Use this plugin if you don't want comments at all on your site (or on certain post types). Don't use it if you want to selectively disable comments on individual posts - WordPress lets you do that anyway. If you don't know how to disable comments on individual posts, there are instructions in [the FAQ](http://wordpress.org/extend/plugins/disable-comments/faq/).

If you come across any bugs or have suggestions, please use the plugin support forum. I can't fix it if I don't know it's broken! Please check the [FAQ](http://wordpress.org/extend/plugins/disable-comments/faq/) for common issues.

Want to contribute? Here's the [GitHub development repository](https://github.com/solarissmoke/disable-comments).

A [must-use version](https://github.com/solarissmoke/disable-comments-mu) of the plugin is also available.

== Frequently Asked Questions ==

= What is "persistent mode"? =

By default, the plugin does not make any persistent changes to your posts - it just dynamically closes comments on them. This means that you can use the plugin temporarily and restore comment statuses when you disable it. If the plugin works in this mode, then I recommend that you don't use persistent mode.

Unfortunately some themes do not properly check the comment status of posts, and the plugin in default mode will have no effect with them (comments will still appear to be open). To fix this, switch to persistent mode. Note however that this will make persistent changes: **comments will remain closed even if you later disable the plugin** (you can always reopen them manually, of course).

**I repeat, using persistent mode will make changes to your database. DO NOT USE IT IF YOU WANT TO DISABLE COMMENTS TEMPORARILY.**

**Administrators**: If you want to prevent persistent mode from being used by mistake, hook into the `disable_comments_allow_persistent_mode` filter and return `false`. This will prevent the option from being available on the settings page.

= Nothing happens after I disable comments on all posts - comment forms still appear when I view my posts. =

This is because your theme is not checking the comment status of posts in the correct way. The solution is to switch the plugin to persistent mode (the last option on the plugin settings page).

You may like to point your theme's author to [this explanation](http://rayofsolaris.net/blog/2012/how-to-check-if-comments-are-allowed-in-wordpress) of what they are doing wrong, and how to fix it.

= How can I remove the text that says "comments are closed" at the bottom of articles where comments are disabled? =

The plugin tries its very best to hide this (and any other comment-related) messages.

If you still see the message, then it means your theme is overriding this behaviour, and you will have to edit its files manually to remove it. Two common approaches are to either delete or comment out the relevant lines in `wp-content/your-theme/comments.php`, or to add a declaration to `wp-content/your-theme/style.css` that hides the message from your visitors. In either case, make you you know what you are doing!

= I only want to disable comments on certain posts, not globally. What do I do? =

Don't install this plugin!

Go to the edit page for the post you want to disable comments on. Scroll down to the "Discussion" box, where you will find the comment options for that post. If you don't see a "Discussion" box, then click on "Screen Options" at the top of your screen, and make sure the "Discussion" checkbox is checked.

You can also bulk-edit the comment status of multiple posts from the [posts screen](http://codex.wordpress.org/Posts_Screen).

= Why is persistent mode disabled? =

Someone (probably your site administrator) has chosen to disable this option. See "What is persistent mode?" above.

== Details ==

The plugin provides the option to **completely disable the commenting feature in WordPress**. When this option is selected, the following changes are made:

* All "Comments" links are hidden from the Admin Menu and Admin Bar;
* All comment-related sections ("Recent Comments", "Discussion" etc.) are hidden from the WordPress Dashboard;
* All comment-related widgets are disabled (so your theme cannot use them);
* The "Discussion" settings page is hidden;
* All comment RSS/Atom feeds are disabled (and requests for these will be redirected to the parent post);
* The X-Pingback HTTP header is removed from all pages;
* Outgoing pingbacks are disabled.

**Please delete any existing comments on your site before applying this setting, otherwise (depending on your theme) those comments may still be displayed to visitors.**

== Changelog ==

= 1.2 =
* Allow network administrators to disable comments on custom post types across the whole network.

= 1.1.1 =
* Fix PHP warning when active_sitewide_plugins option doesn't contain expected data type.

= 1.1 =
* Attempt to hide the comments template ("Comments are closed") whenever comments are disabled.

= 1.0.4 =
* Fix CSRF vulnerability in the admin. Thanks to dxw for responsible disclosure.

= 1.0.3 =
* Compatibility fix for WordPress 3.8

= 1.0.2 =
* Disable comment-reply script for themes that don't check comment status properly.
* Add French translation

= 1.0.1 =
* Fix issue with settings persistence in single-site installations.

= 1.0 =
* Prevent theme comments template from being displayed when comments are disabled everywhere.
* Prevent direct access to comment admin pages when comments are disabled everywhere.

= 0.9.2 =
* Make persistent mode option filter available all the time.
* Fix redirection for feed requests
* Fix admin bar filtering in WP 3.6

= 0.9.1 =
* Short life in the wild.

= 0.9 =
* Added gettext support and German translation.
* Added links to GitHub development repo.
* Allow network administrators to prevent the use of persistent mode.

= 0.8 =
* Remove X-Pingback header when comments are completely disabled.
* Disable comment feeds when comment are completely disabled.
* Simplified settings page.

= 0.7 =
* Now supports Network Activation - disable comments on your entire multi-site network.
* Simplified settings page.

= 0.6 = 
* Add "persistent mode" to deal with themes that don't use filterable comment status checking.

= 0.5 =
* Allow temporary disabling of comments site-wide by ensuring that original comment statuses are not overwritten when a post is edited.

= 0.4 =
* Added the option to disable the Recent Comments template widget.
* Bugfix: don't show admin messages to users who don't can't do anything about them.

= 0.3.5 =
* Bugfix: Other admin menu items could inadvertently be hidden when 'Remove the "Comments" link from the Admin Menu' was selected.

= 0.3.4 =
* Bugfix: A typo on the settings page meant that the submit button went missing on some browsers. Thanks to Wojtek for reporting this.

= 0.3.3 =
* Bugfix: Custom post types which don't support comments shouldn't appear on the settings page
* Add warning notice to Discussion settings when comments are disabled

= 0.3.2 =
* Bugfix: Some dashboard items were incorrectly hidden in multisite

= 0.3.1 =
* Compatibility fix for WordPress 3.3

= 0.3 =
* Added the ability to remove links to comment admin pages from the Dashboard, Admin Bar and Admin Menu

= 0.2.1 =
* Usability improvements to help first-time users configure the plugin.

= 0.2 =
* Bugfix: Make sure pingbacks are also prevented when comments are disabled.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin settings can be accessed via the 'Settings' menu in the administration area (either your site administration for single-site installs, or your network administration for network installs).
