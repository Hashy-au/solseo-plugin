=== SolSEO ===
Contributors: hashyau
Tags: seo, sitemap, schema, redirects, meta
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Titles, meta, sitemaps, structured data, redirects and a content score for posts, pages and products.

== Description ==

SolSEO covers the work every site needs doing and gets out of the way. Nothing is held back behind a key, nothing phones home, and there are no notices on screens that have nothing to do with it.

**It sets itself up**

A five step screen covers the things a new site cannot guess: who is behind it, what a search result should say, what should turn up in a search at all, and whether to serve a sitemap. Nothing redirects you there and nothing nags you about it, because the plugin works with none of it answered. It is under SolSEO, Setup, and you can run it again whenever you like.

**A score you can act on**

Every post, page and product gets a score out of a hundred, worked out from around thirty checks across four groups: how the page is aimed at its keyword, the basics of titles and links, how the writing reads, and, for a shop, whether a product has what a shopping result needs. Each check says what to change rather than just failing.

The score updates as you type, in a panel in the editor sidebar reached from a coloured number in the top right. The classic editor gets the same fields in a box below the content.

On the posts and products lists it is a column carrying the score, the focus keyword or the fact that there is not one, the kind of structured data the page carries, and how the page sits in your own links: out to this site, out to others, and in from this site. A pencil in the column header turns every row into an editable SEO title and description, so forty pages can be fixed without opening any of them.

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
* Bulk alt text for images that have none, with the text we would write shown beside each image so you can untick the ones you would rather write yourself
* A robots.txt screen that shows what your site is actually serving, with five ready made sets of rules and a way back to how it was
* Attachment pages sent to the post they belong to
* Import of titles, descriptions, keywords and canonical addresses from another SEO plugin, previewed before anything is written and leaving the originals in place. One click copies the lot, it checks itself afterwards, and only then does it offer to switch the other plugin off for you

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
2. Open SolSEO in the admin menu. It works from the moment it is switched on, with no setup. If you would rather answer a few questions, SolSEO, Setup walks through five of them.
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

= Will it stop AI crawlers using my writing? =

There is a set of rules under SolSEO, Tools, robots.txt that names eight of them: GPTBot, ClaudeBot, Google-Extended, CCBot, PerplexityBot, Bytespider, Applebot-Extended and meta-externalagent. One button fills the box and nothing is saved until you press Save.

Three things are worth knowing, and the screen says all three. Two of those eight do not fetch anything at all; they decide what may be done with pages an ordinary search crawler already read, so saying no to them costs you nothing in search results. One of them is a search crawler whose owner says it does not train on what it reads, so blocking it takes your site out of that product's answers and saves no training use. And one of them has been seen fetching addresses it was told to stay off. A robots.txt is a request. The well behaved crawlers follow it.

= Does it change the robots.txt file on my server? =

No. WordPress generates robots.txt when there is no file, and that is what this adds to. If there is a real file on your server, nothing you type here is served, and the screen says so and shows you what the file contains. It offers once to take over: it copies the file's lines into the box first so nothing is lost, then renames the file to robots.txt.solseo-backup. It never deletes it, it never does it on its own, and one button puts it back.

= Where did my robots.txt go? =

If you took that offer, it is robots.txt.solseo-backup, in the same folder. "Put it back as it was" on SolSEO, Tools, robots.txt returns it.

= Where is the sitemap? =

At /sitemap.xml. It replaces the one WordPress generates.

= Can I change what a check expects? =

The word count target can be filtered with `solseo_minimum_words`, and the whole set of results can be filtered with `solseo_checks` before it is scored.

= Does deleting the plugin remove my SEO fields? =

Not unless you ask it to, under SolSEO, Tools, Data.

== Screenshots ==

1. The score and its checks in the editor sidebar, updating as you write.
2. The posts list, with the score, the keyword, the structured data and the link counts, and a pencil that edits titles and descriptions from the list.
3. The SolSEO dashboard, with the average score, the pages worth looking at first and the site checks.
4. The robots.txt screen, showing what the site is serving and the sets of rules to choose from.
5. Titles and meta templates, with the placeholders listed underneath.
6. The redirect manager and the log of addresses that found nothing.

== Changelog ==

= 1.3.0 =
Seven SolSEO tests in WordPress's own Site Health screen, built from what the
plugin already knows and working with no account: whether search engines are
allowed in, readable addresses, the sitemap, structured data, pages with no
description of their own, images with no alt text, and how many pages are kept
out of search results on purpose. A SolSEO section on the Info tab lists the
version, the post types scored, whether a sitemap is served and how the scoring
stands, and none of it leaves the site.

Two places an add-on can hook into, and the image description tool can now be
pointed at the images on one page.

= 1.2.0 =
First release on wordpress.org.

A setup screen covering the five things a new site cannot guess. A panel in the block editor sidebar, opened from the score in the top right, in place of the box below the content; the classic editor keeps its box. The posts list column now carries the keyword, the structured data type and three link counts, and a pencil in its header lets you edit titles and descriptions straight from the list.

The robots.txt screen shows what your site is actually serving, offers five ready made sets of rules, records what the site served before this plugin arrived, and can put it back. Anything with a per cent encoded character in it was being silently stripped on save before now, so it is worth reopening that box.

Every bulk tool queues the whole job instead of asking you to press a button repeatedly. The importer reads four more plugins, checks itself when it finishes, and then offers to switch the other one off for you. A notice appears when another SEO plugin is writing the same tags, on three screens only, dismissible for good.

Breadcrumbs have a live preview and the prefix setting finally has a field.

= 1.0.0 =
Built, and never published.

== Upgrade Notice ==

= 1.2.0 =
Rules typed into robots.txt with a per cent encoded character in them have been saved without it since the first build. Open SolSEO, Tools, robots.txt and check yours.
