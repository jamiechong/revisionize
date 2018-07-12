=== Revisionize ===
Contributors: jamiechong
Tags: revision, schedule, cron, staging, variation, publishing, content, stage
Requires at least: 4.4
Tested up to: 4.9
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Draft up revisions of live, published content. The live content doesn't change until you publish the revision manually or with the scheduling system.


== Description ==

On a busy site you can't afford to make changes to *live*, *published* posts without reviewing and approving them first. **Revisionize** clones your post, page or CPT to a draft that gives you the freedom to tweak, edit and experiment with the content. Preview your drafted changes and/or [share the preview](https://wordpress.org/plugins/public-post-preview/) with a 3rd party to approve the changes. When you're happy, publish the revision, which will copy your content changes to the original post. Alternatively, schedule the revision to publish your content at a specific time. 

= Official Addons =

Visit [revisionize.pro](https://revisionize.pro) to add functionality that makes Revisionize even more powerful. 

= Compatible with =

* [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/)
* [Public Post Preview](https://wordpress.org/plugins/public-post-preview/)
* *Let us know other plugins that you have successfully used Revisionize with*

Please post in the support section for help before leaving a negative review!


== Screenshots ==

1. Revisionize a post
2. Alternate way to Revisionize
3. A Revision. Save as many drafts as you wish. Preview the post to see how it looks. Or delete it if you don't want it. When the revision is published, it will overwrite its original. But don't worry, the original is saved as another revision (for a backup). 
4. Schedule multiple revisions/variations to be published consecutively. The URL of the post stays the same, the content will just update at the scheduled time. No redirects or messing around with slugs. 
5. The original post is kept around as a revision just in case you want to revert back to the way things were. 

== Changelog ==

= 2.2.7 =
* Fix. Undefined index errors. 

= 2.2.6 =
* Fix. Handle edge case where malformed addon data comes back from revisionize.pro

= 2.2.5 =
* Fix. Use correct path to addons directory for Multisite.

= 2.2.4 =
* New. Action for developers. revisionize_after_copy_post
* Fix. Ensure author ID is set when a backup revision is created during cron.

= 2.2.3 =
* Fix. Move addons directory to a location safe from overwrites. 

= 2.2.2 =
* New. Action for developers to add hook after a revision is created.
* Fix. Fatal error when checking is_wp_revision_different.

= 2.2.1 =
* Fix. Notices when admin bar shown on non-edit pages. Thanks @kshaner.

= 2.2.0 = 
* Fix. Use settings when cron publishes a scheduled post.
* Feature. Revisionize link added to admin bar.

= 2.1.4 =
* Fix. Properly fetch addons list.

= 2.1.3 =
* Fix issue where addons list is empty causing Warnings to be displayed.

= 2.1.2 =
* Fix issue where addons weren't loaded if the revisionize_addons_root was used. Thanks @piscis

= 2.1.1 =
* Make revisionize and its addon framework work better with Wordpress Network/Multisite installations.
* Add filter to change the addon install directory.
* Check for addon updates.

= 2.0.2 = 
* Generalize user_can_revisionize so that it's true if a user can edit pages.

= 2.0.1 =
* Fix Fatal error on 2.0.0 - had forgot to commit settings.php and addon.php. Please update and activate again.

= 2.0.0 =
* Add a basic settings panel.
* Add ability to install addons

= 1.3.6 =
* Fix critical bug for non ACF users. 

= 1.3.5 =
* Add filter to give developers control over updating post dates. [See here](https://github.com/jamiechong/revisionize/issues/13). Thanks @piscis
* Fix bug with gmt date.

= 1.3.4 =
* Fix edge case bug where ACF change was not tracked in WP Revision Tracker.

= 1.3.3 =
* Track ACF changes in the built-in WP Revision Tracker when a Revisionized post is published.
* Add filter (`revisionize_exclude_post_types`) to exclude Revisionize functionality for specific post types. By default ACF custom field types are disabled.

= 1.3.2 =
* Maintain original post dates when a revision is published
* Add filter (`revisionize_keep_original_on_publish`) to override behaviour of keeping the original as a revision when publishing. 
* Hide experimental dashboard widget that was added in 1.3.0. [See here](https://github.com/jamiechong/revisionize/pull/7) if you want to show this widget again.

= 1.3.1 =
* Fix issue where publishing a revision did not overwrite the original post when ACF 5 was installed but no fields were assigned to the post type.

= 1.3.0 =
* Add filters to let developers control which users can revisionize or access revisionized posts. [See here](https://github.com/jamiechong/revisionize/pull/6) for more details on how to use. Thanks @ryanshoover.
* Add a dashboard widget showing pending revisions. [See here](https://github.com/jamiechong/revisionize/pull/7) for more details. Thanks again @ryanshoover.

= 1.2.2 =
* New add filter to allow developers to customize button text. Thanks @robbiepaul.

= 1.2.1 = 
* Fix issue where post titles containing ampersands are escaped when a revision is scheduled with cron. Thanks @piscis.

= 1.2.0 =
* Fix [known issue](https://github.com/jamiechong/revisionize/issues/1) where direct publishing of a Revision that has ACF would not actually update the fields. Thanks for the help @thegaffney

= 1.1.0 =
* Only allow users to Revisionize posts that they can also edit. [Related discussion](https://wordpress.org/support/topic/permissionscapability-issue/)
* The author of the original post is maintained when a Revision is published - it doesn't get set to the author of the user who created the Revision. 

== Upgrade Notice ==

= 2.1.1 =
* Improve compatibility with Multisite/Network installations.

= 2.0.1 =
* Fixed fatal error on 2.0.0. If this affected you, please update and activate again.

= 1.3.6 =
Fixed a critical bug for non ACF users. Sorry!

= 1.1.0 = 
Fixed a permission issue where users could Revisionize and overwrite a post they don't have write access to.
