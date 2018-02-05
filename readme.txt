=== Revisionize ===
Contributors: jamiechong
Tags: revision, cms, content, staging, stage, draft, variation, scheduling, schedule, change, clone, preview
Requires at least: 4.4
Tested up to: 4.9
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Draft up revisions or variations of live, published content. Publish the drafted content manually or with the built-in scheduling system.


== Description ==

On a busy site you can't afford to make changes to *live*, *published* posts without reviewing and approving them first. **Revisionize** clones your post, page or CPT to a draft that gives you the freedom to tweak, edit and experiment with the content. Preview your drafted changes and/or [share the preview](https://wordpress.org/plugins/public-post-preview/) with a 3rd party to approve the changes. When you're happy, publish the revision, which will copy your content changes to the original post. Alternatively, schedule the revision to publish your content at a specific time. 

= Compatible with =

* Plugin: [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/)
* Plugin: [Public Post Preview](https://wordpress.org/plugins/public-post-preview/)
* Theme: [Divi](https://www.elegantthemes.com/gallery/divi/)
* *Let us know other themes/plugins that you have successfully used Revisionize with*

Please post in the support section for help before leaving a negative review!


== Screenshots ==

1. Revisionize a post
2. Alternate way to Revisionize
3. A Revision. Save as many drafts as you wish. Preview the post to see how it looks. Or delete it if you don't want it. When the revision is published, it will overwrite its original. But don't worry, the original is saved as another revision (for a backup). 
4. Schedule multiple revisions/variations to be published consecutively. The URL of the post stays the same, the content will just update at the scheduled time. No redirects or messing around with slugs. 
5. The original post is kept around as a revision just in case you want to revert back to the way things were. 

== Changelog ==

= 1.3.2 =
* Maintain original post dates when a revision is published
* Add filter (revisionize_keep_original_on_publish) to override behaviour of keeping the original as a revision when publishing. 

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

= 1.1.0 = 
Fixed a permission issue where users could Revisionize and overwrite a post they don't have write access to.
