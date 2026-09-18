=== SolSEO ===
Contributors: solkarra
Tags: seo, sitemap, schema, redirects, meta
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Titles, meta, sitemaps, structured data, redirects and a content score for posts, pages and products.

== Description ==

SolSEO covers the work every site needs doing and gets out of the way. Nothing is held back behind a key, nothing phones home, and there are no notices on screens that have nothing to do with it.

**A score you can act on**

Every post, page and product gets a score out of a hundred, worked out from around thirty checks across four groups: how the page is aimed at its keyword, the basics of titles and links, how the writing reads, and, for a shop, whether a product has what a shopping result needs. Each check says what to change rather than just failing.

The score updates as you type, and it shows up as a sortable column on the posts and products lists, so the pages that need work are one click away.

**Titles and meta**

Write a title and description per page, or set a template per post type and let it fill itself in. Placeholders in braces, so `{title} {sep} {sitename}` reads as what it produces. A pixel width gauge shows when a title is about to be cut off, which is what actually happens in a search result rather than a character count.

**XML sitemaps**

Posts, pages, products, categories, tags and optionally author archives, split into pages, with the images on each page listed. Product galleries are listed too. The sitemap is readable in a browser and is added to robots.txt.

**Structured data**

Organisation or person, the site itself, breadcrumbs, articles, and products. A product with variations gets one offer per variation with its own price, SKU and stock state, inside an aggregate offer. That is what a shopping result needs to send a buyer to the right size.

**Redirects and a log of dead links**

A redirect manager with 301, 302, 307 and 410, exact addresses or patterns. Every address that found nothing is logged with the page that linked to it, and one click turns a logged miss into a redirect. The log keeps the address and the referring page. It keeps nothing about the visitor.

**The rest**

* Open Graph and card tags, with a fallback image
* Canonical addresses, noindex and nofollow per page, per post type and per taxonomy
* SEO fields on categories and tags
* Breadcrumbs, as a shortcode or a template tag
* Bulk alt text for images that have none
* Additions to robots.txt
* Attachment pages sent to the post they belong to
* Import of titles, descriptions, keywords and canonical addresses from another SEO plugin, previewed before anything is written and leaving the originals in place

**Works with WooCommerce**

Product schema, product checks in the score, product galleries in the sitemap, and price, SKU and stock placeholders in templates. WooCommerce is optional, and nothing nags you to install it.

== External services ==

SolSEO works entirely on your own site and makes no outbound request unless you connect it to a SolSEO account.

If you choose to connect, the plugin talks to the SolSEO service at solseo.com.au. You paste a pairing code from your account, and from then on the plugin sends, twice a day: the site address and time zone, the WordPress, PHP, WooCommerce and plugin versions, the number of published posts, pages and products, and the permalink structure. In return it reads back which plan the account is on and a summary of the sites on it, which is what the dashboard widget shows.

No page content, no customer data and nothing about your visitors is sent. Disconnecting on the Connect screen removes the stored key and stops it at once.

Service terms: https://solseo.com.au/terms
Privacy policy: https://solseo.com.au/privacy

== Installation ==

1. Install and activate the plugin.
2. Open SolSEO in the admin menu. It works from the moment it is switched on, with no setup.
3. Under Titles and Meta, set the organisation name and logo so the structured data is complete.
4. If you are moving from another SEO plugin, go to SolSEO, Tools, Import, and check the preview before you copy anything.

== Frequently Asked Questions ==

= Does anything need an account? =

No. Everything described above works with no account, no key and no network connection.

= Is there a paid version? =

Yes, and nothing in this plugin is held back for it. SolSEO Pro is a separate add-on that installs beside this one. It scores every phrase you are aiming at rather than only the focus keyword, and it shows your tracked positions in the editor. There is a page about it under SolSEO, Upgrade, and at https://solseo.com.au/pro

= Will it clash with another SEO plugin? =

Two plugins writing meta tags will produce two of everything. Import your fields first, then switch the other one off.

= I imported by mistake. Is the old data gone? =

No. The import copies. It never deletes or changes what the other plugin stored, so switching that plugin back on puts things as they were.

= Where is the sitemap? =

At /sitemap.xml. It replaces the one WordPress generates.

= Can I change what a check expects? =

The word count target can be filtered with `solseo_minimum_words`, and the whole set of results can be filtered with `solseo_checks` before it is scored.

= Does deleting the plugin remove my SEO fields? =

Not unless you ask it to, under SolSEO, Tools, Data.

== Screenshots ==

1. The score and its checks in the editor, updating as you write.
2. The SolSEO dashboard, with the average score and the pages worth looking at first.
3. Titles and meta templates, with the placeholders listed underneath.
4. The redirect manager and the log of addresses that found nothing.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
