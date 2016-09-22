=== Content Audit ===
Contributors: sillybean
Tags: content, audit, review, inventory
Donate Link: http://stephanieleary.com/code/wordpress/content-audit/
Requires at least: 4.4
Tested up to: 4.6.1
Stable tag: 1.9.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Lets you create a content inventory right in the WordPress Edit screens. You can mark content as redundant, outdated, trivial, or in need of a review.

== Description ==

Lets you create a content inventory right in the WordPress Edit screens, similar to the process you might use to assess your site's content in a spreadsheet. You can mark content as redundant, outdated, trivial, or in need of a review for SEO or style. These content status labels work just like categories, so you can remove the built-in ones and add your own if you like. You can also assign a content owner (distinct from the original author) and keep internal notes. The IDs are revealed on the Edit screens so you can keep track of your content even if you change titles and permalinks. The plugin supports custom post types as well as posts, pages, and media files.

There's an Overview report under the Dashboard menu that shows you which posts/pages/attachments/etc. need attention, sorted by user. This screen also lets you export a CSV file of the audit report.

The plugin creates three new filters on the Edit screens: author, content owner, and content status. This should make it easy to narrow your focus to just a few pages at a time.

You can display the audit details to logged-in editors on the front end if you want, either above or below the content. You can style the audit message with custom CSS.

<strong>New:</strong> you can now clear data from past audits and start over!

= Translations =

If you would like to send me a translation, please write to me through <a href="http://sillybean.net/about/contact/">my contact page</a>. Let me know which plugin you've translated and how you would like to be credited. I will write you back so you can attach the files in your reply.

== Notes ==

= Filter reference =

'content_audit_notes' filters the public display of the notes field

'content_audit_dashboard_get_posts_args' filters the get_posts() arguments for the Dashboard widget

'content_audit_dashboard_output' filters the table output of the Dashboard widget

'content_audit_dashboard_congrats' filters the congratulations message of the Dashboard widget

'content_audit_csv_filename' filters the file name of the CSV download

'content_audit_csv_header_data' filters the header label array in the CSV download

'content_audit_csv_row_data' filters the contents of each row (as an array) in the CSV download

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/` 
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Visit the Settings screen to set your status labels (redundant, outdated, trivial) and choose which content types (posts, pages, and/or custom) should be audited.

== Screenshots ==

1. The options screen
1. Edit pages, with the content audit columns and filter dropdowns
1. Edit a page, with the content audit notes, owner, and status boxes
1. The overview screen under the Dashboard
1. Quick Edit with the Content Audit fields
1. Categorizing a page from the front end using the admin bar

== Changelog ==

= 1.9.1 =
* Users dropdown is now limited to roles allowed to audit as per Settings screen.
= 1.9 =
* Filter ALL THE THINGS! See the Notes tab for filter reference.
* Remove unnecessary globals and deprecated get_currentuserinfo() function.
* Localize and add text domain to forgotten strings.
* General cleanup.
* Remove recommendation and column support for defunct Google Analytics Dashboard plugin.
= 1.8.2 =
* Fix bug where CSV export term column was cumulative instead of per-post.
= 1.8.1 =
* Avoid creating multiple copies of the required "outdated" term.
= 1.8 =
* Better CSV export.
= 1.7 =
* New option to delete information from previous content audits and start over. The audit attribute terms themselves are preserved and can be reused, but they will no longer be assigned to posts/pages.
* Term descriptions added to the default audit attributes. Thanks to @garyj for the suggestion.
* Removed old media attachment fields to edit, which were duplicated now that attachments use post.php.
* Updated custom CSS option to meet current security guidelines. (Bonus: line breaks are now preserved! Yay!)
* Cleaned up JS and CSS enqueueing.
* Cleaned up notices.
* Updated POT.
= 1.6.2 =
* Corrected a problem in the outdated query introduced in 1.6.1.
* Fixed notices on settings screen.
* Updated POT.
= 1.6.1 =
* Fixed notices on post edit screen.
* Introduced sanitization function for options
* Escaped SQL get_results() query to prevent injection, as reported by <a href="https://security.dxw.com/">dwxsecurity</a>
= 1.6 =
* Audit attribute (term) counts are now totaled per post type. Under 'Pages,' the Content Audit terms' page counts show only pages, not the cumulative number of posts across all post types (which is the WordPress default for counting term totals, and is what was shown in older versions). For cumulative totals, see the top of the Overview screen.
* Content Audit Attributes are now available to edit under all the post types being audited, not just Pages.
= 1.5.3 =
* Added an extra hook to make sure audit fields are saved when you have not edited any built-in post fields
* Fixed silent error when adding menu items while using Content Audit.
= 1.5.2 =
* Fixed error when adding menu items while using Content Audit.
= 1.5.1 =
* Fixed white screen error on front end.
* Fixed disappearing role option bug.
= 1.5 =
* Fixed various permissions-related issues, including the Overview screen.
* Added Audit links to the admin bar, allowing auditors to quickly categorize or trash content from the front end.
* Added Content Audit fields to Quick Edit and Bulk Edit.
* Added a CSV export of the audit report, available from the Overview screen.
* Fixed a bug where the content owner field was saved for every post, even when no owner was set.
= 1.4.2 =
* Fixed various notices and warnings.
= 1.4.1 =
* Fixed disappearing columns after Quick Edit.
= 1.4 =
* New Overview screen (the "boss view") under Dashboard. Shows counts for each content audit attribute (outdated, trivial, etc.) and lists how many of each content type belong to the various content owners.
* New option to set an expiration date for individual posts/pages/etc., at which time the content will be marked as outdated.
* Supports custom roles.
* Added "audited" status to the default list, to be used when the audit for that item is complete. This can be removed.
= 1.3.1 =
* Bugfix: The auto-outdate feature was using 'months' no matter what unit of time you chose. This is fixed.
* Authors or contributors who can't audit content can now see the audit notes, owner, and attributes on their own posts.
* Improvements to the Dashboard widget. 
= 1.3 =
* Authors are now prevented from auditing their own posts when the auditor role option is set to Administrator or Editor.
* You can now choose whether to send email notifications immediately.
* Bugfix: All the default attributes are now created when the plugin is first activated. (Only Outdated appeared before.)
* Bugfix: Auditing media files no longer prevents you from editing titles and descriptions.
* Bugfix: Audit fields are shown for media files ONLY when you have chosen to audit media.
* Various warnings and notices cleaned up ( thanks to <a href="http://www.linkedin.com/in/davidmdoolin">David Doolin</a> ).
* Compatibility fixes for WP 3.2.
= 1.2.1 =
* Bugfix: The option to show the status and notes to logged-in users will now respect the checkbox
* Bugfix: You should now be able to delete all the built-in status categories except Outdated ( which is used by the auto-outdate feature ).
= 1.2 =
* New feature: Automatically mark content as outdated after a certain period of time
* New feature: Email content owners (or original authors) a summary of outdated content
* Bugfix: You should no longer see notices for pages that do not have a content status (or anywhere else, for that matter).
= 1.1.2 =
* Bugfix: the default attributes (redundant, outdated, etc.) were not created properly when the plugin was installed.
= 1.1.1 =
* Fixed a bug that prevented the audit columns from appearing on the Edit Pages screens
= 1.1 =
* Allows you to audit media files.
= 1.01 = 
* Fixed a typo that prevented you from leaving the content owner field blank when editing something
* Moved the Google Analytics Dashboard plugin's sparklines column to the last row of the Edit screen tables, if that plugin is installed
= 1.0 =
* Out of beta!
= 0.9b =
* Changed the way the content status taxonomy is created so that you can actually edit and delete the built-in categories.
= 0.8b =
* First beta.