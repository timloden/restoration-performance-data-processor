=== Restoration Performance Data Processor ===
Contributors: (this should be a list of wordpress.org userid's)
Donate link: https://example.com/
Tags: comments, spam
Requires at least: 4.5
Tested up to: 5.4.2
Requires PHP: 5.6
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WP-CLI commands for downloading and processing vendor data

== Description ==

This is the long description.  No limit, and you can use Markdown (as well as in the following sections).

For backwards compatibility, if this section is missing, the full length of the short description will be used, and
Markdown parsed.

A few notes about the sections above:

*   "Contributors" is a comma separated list of wp.org/wp-plugins.org usernames
*   "Tags" is a comma separated list of tags that apply to the plugin
*   "Requires at least" is the lowest version that the plugin will work on
*   "Tested up to" is the highest version that you've *successfully used to test the plugin*. Note that it might work on
higher versions... this is just the highest one you've verified.
*   Stable tag should indicate the Subversion "tag" of the latest stable version, or "trunk," if you use `/trunk/` for
stable.

    Note that the `readme.txt` of the stable tag is the one that is considered the defining one for the plugin, so
if the `/trunk/readme.txt` file says that the stable tag is `4.3`, then it is `/tags/4.3/readme.txt` that'll be used
for displaying information about the plugin.  In this situation, the only thing considered from the trunk `readme.txt`
is the stable tag pointer.  Thus, if you develop in trunk, you can update the trunk `readme.txt` to reflect changes in
your in-development version, without having that information incorrectly disclosed about the current stable version
that lacks those changes -- as long as the trunk's `readme.txt` points to the correct stable tag.

    If no stable tag is provided, it is assumed that trunk is stable, but you should specify "trunk" if that's where
you put the stable version, in order to eliminate any doubt.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

= What about foo bar? =

Answer to foo bar dilemma.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==
= 1.13.4=
* Version bump 

= 1.13.3 =
* Added oversize protection for ground items over 30 lbs

= 1.13.2 =
* Updated oer heavy over sized to be 75 inches

= 1.20.0 =
* Updated goodmark to use dynamic pricing in the feed processing

= 1.11.3 =
* Fixed DII freight items marks as in stock when they shouldnt have been

= 1.11.2 =
* Fixed DII ground items marks as in stock when they shouldnt have been

= 1.11.1 =
* Updated DII oversized part to be in stock only if in bot warehouses and update weight to 30 if under 30 in feed

= 1.11.0 =
* Added DII feed processing 

= 1.10.2 =
* Fixed windshield condition when length was too long

= 1.10.1 =
* Added OER windshield shipping class condition

= 1.10.0 =
* Added pricing fields for OER and DII
* Added pricing calculation for OER

= 1.9.7 =
* Added shipping class output to OER
* Added heavy freight class for freight items over 70 inches in length

= 1.9.3 =
* Added condition for penny shipping wheels to set them to 50lbs

= 1.9.2 =
* Updated OER OS3 to 50lbs

= 1.9.0 =
* Updated OER and Goodmark to use actual stock status instead of quantity number
* Added RP and CBP multiple import scripts

= 1.8.0 =
* Added OER weight calculation for updating OS

= 1.2.0 =
* Added a field for OER product export path
* Added command to download OER export
* Added SKU compare to OER processing if export URL is defined
* Added Sherman download command
* Added Sherman processing command

== Arbitrary section ==

You may provide arbitrary sections, in the same format as the ones above.  This may be of use for extremely complicated
plugins where more information needs to be conveyed that doesn't fit into the categories of "description" or
"installation."  Arbitrary sections will be shown below the built-in sections outlined above.

== A brief Markdown Example ==

Ordered list:

1. Some feature
1. Another feature
1. Something else about the plugin

Unordered list:

* something
* something else
* third thing

Here's a link to [WordPress](https://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax].
Titles are optional, naturally.

[markdown syntax]: https://daringfireball.net/projects/markdown/syntax
            "Markdown is what the parser uses to process much of the readme file"

Markdown uses email style notation for blockquotes and I've been told:
> Asterisks for *emphasis*. Double it up  for **strong**.

`<?php code(); // goes in backticks ?>`
