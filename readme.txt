=== SolSEO ===
Contributors: hashyau
Tags: seo, sitemap, schema, redirects, meta
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Titles, meta, sitemaps, structured data, redirects and a content score for posts, pages and products.

== Description ==

SolSEO does the SEO work every site needs and gets out of the way. Nothing is held back behind a key, nothing phones home, and there are no notices on screens that have nothing to do with it.

**A score you can act on**

Every post, page and product is scored out of a hundred from around thirty checks: how the page is aimed at its keyword, titles and links, how the writing reads, and what a shopping result needs. Each check says what to change. The score updates as you type, and the posts list gets a column you can edit in place, so forty titles can be fixed without opening a page.

**Titles, meta and sitemaps**

Write a title and description per page, or set a template per post type with placeholders in braces, such as {title} {sep} {sitename}. A pixel gauge shows when a title is about to be cut off. XML sitemaps cover posts, pages, products, categories, tags and author archives, list the images on each page, and are added to robots.txt.

**Structured data**

Organisation or person, the site, breadcrumbs, articles and products. A product with variations gets one offer per variation with its own price, SKU and stock state, which is what sends a buyer to the right size.

**The rest**

* A monthly crawl of up to three hundred of your own pages that turns up internal links going nowhere, each one naming the pages it is written on, and links arriving by way of two or more redirects. It only ever asks your own server for your own addresses
* Redirects with 301, 302, 307 and 410, and a log of dead links that becomes a redirect in one click
* Internal link suggestions that write a real link and store a revision
* An image report: files far bigger than the space they are drawn in, missing width and height, missing alt text, uploads no page uses
* Six accessibility checks on your own content, each named by guideline
* Open Graph and card tags, canonicals, noindex and nofollow, SEO fields on categories and tags, breadcrumbs
* A robots.txt screen with ready made rule sets and a way back
* Import from another SEO plugin, previewed first, originals left in place
* Search Console figures for the page you are editing, on a read only permission

**Works with WooCommerce**

Product schema, product checks in the score, galleries in the sitemap, and price, SKU and stock placeholders. Twelve checks from Google's product data specification run against your products here rather than turning up in Merchant Centre three days later. WooCommerce is optional.

== External services ==

SolSEO does not contact anybody else on its own. Every service below is off until you switch it on yourself, and each one stops the moment you remove the key that turned it on. This is the whole list.

Two things in this plugin ask your own server for your own pages, and neither is one of these. The crawler on the Technical screen reads your published pages when you press the button, and the tag check on the Connections screen reads your home page when you press that button. Both go through the same piece of code, which refuses any address that is not on your own site. Nothing about those pages leaves your server, so there is no third party to name here.

= Google PageSpeed Insights, at www.googleapis.com =

Off until you paste a Google API key on the Connections screen, and used only when you press the button on the Speed screen.

Each check sends one address from your own site, plus your key, and Google sends back how that page performs: a test it runs itself, and, if enough people have visited the page recently, what those visits measured. Nothing else is sent, no check runs on a schedule, and removing the key stops it.

The key is yours, from your own Google Cloud project. Google's terms and privacy policy cover what they do with the address you send.

Terms: https://developers.google.com/terms
Privacy policy: https://policies.google.com/privacy

= IndexNow, at api.indexnow.org =

Off until you switch it on under SolSEO, Technical, Indexing.

When it is on, publishing or updating a page sends that page's address, once, so the search engines that take part know to come and look. One submission reaches all of them: Bing, Yandex, Seznam and Naver read the same endpoint. Nothing is sent for a draft, a private page, or a page you have asked to stay out of search results.

IndexNow also needs a key file at the root of your site, which this plugin creates and serves for you. It holds a line of letters and numbers and nothing else, and it is how the engines check the submission came from whoever runs the site.

Terms and how it works: https://www.indexnow.org/documentation

= Google Search Console, at searchconsole.googleapis.com =

Off until you connect a Google account under SolSEO, Settings, Connections, and you can disconnect on the same screen.

Once connected, the plugin asks Google two things. At the moment you connect, it asks which Search Console properties your Google account can see, so it can show you the list and match one to this site. After that, when you open a post or a page in the editor, it asks what that one page did in Google over the last twenty eight days: clicks, impressions, average position, and the search terms people used to reach it. The answer is kept on your own server for six hours so that opening the same page twice does not ask twice.

The permission asked for is read only. It is https://www.googleapis.com/auth/webmasters.readonly, which is the narrowest one Search Console publishes: it cannot add a property, remove one, or submit a sitemap. Nothing is ever written to your Google account.

What is sent is the address of the page you are editing and the address of the property you matched it to. No page content, no customer data and nothing about your visitors.

None of what comes back is sent anywhere. The figures are shown in your own WordPress admin and stored on your own server, and the plugin has no route that sends Search Console data to SolSEO or to anybody else.

Terms: https://policies.google.com/terms
Privacy policy: https://policies.google.com/privacy

= Connecting that Google account, at accounts.google.com, oauth2.googleapis.com and solseo.com.au =

Off until you press Connect on that same screen. This is the one place in this plugin that contacts solseo.com.au without a SolSEO account, and it is worth reading before you press it.

Connecting to Google needs an application secret, and a secret that shipped inside a plugin anybody can download is not a secret. So the plugin holds none. When you press Connect, your browser goes to solseo.com.au, which sends you on to Google's own sign in and permission screen at accounts.google.com. When you say yes, Google sends your browser back to solseo.com.au, which sends it straight back to your site with a one-time code. Your site then asks solseo.com.au to turn that code into a token at oauth2.googleapis.com, and the same thing happens about once an hour afterwards to keep the token fresh.

What solseo.com.au sees is the one-time code, the token Google hands back, and the address of your site because your browser came from it. It keeps none of them. There is no account, no row and no log line: the code is exchanged and the answer is passed straight to your site, which is where the token is stored, scrambled, in your own database.

If you would rather we were not in the middle at all, you do not have to be. Under "Use my own Google app" on the same screen you can paste a client id and secret from your own Google Cloud project, and then nothing in this handshake touches solseo.com.au: your site talks to accounts.google.com and oauth2.googleapis.com directly.

Disconnecting deletes the stored token, tells Google to forget the permission, and stops all of it in the same click. You can also revoke it from your own Google account at https://myaccount.google.com/permissions.

Google terms: https://policies.google.com/terms
Google privacy policy: https://policies.google.com/privacy
SolSEO terms: https://solseo.com.au/terms
SolSEO privacy policy: https://solseo.com.au/privacy

= SolSEO, at solseo.com.au =

Off until you paste a pairing code on the Connect screen. This is separate from the Google handshake above, which uses solseo.com.au as a post box and needs no account.

Once paired, the plugin sends twice a day: the site address and time zone, the WordPress, PHP, WooCommerce and plugin versions, the number of published posts, pages and products, and the permalink structure. In return it reads back which plan the account is on and a summary of the sites on it, which is what the dashboard widget shows.

No page content, no customer data, no Search Console figures and nothing about your visitors is sent. Disconnecting on the Connect screen removes the stored key and stops it at once.

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

= 2.0.0 =
Connect your Google Search Console account and see what each page actually did
in Google over the last twenty eight days, in the editor, beside the page you
are writing: clicks, impressions, average position, and the searches that
brought them. The permission asked for is read only. If you would rather
solseo.com.au were not in the middle of the connection, paste a client id and
secret from your own Google Cloud project and it will not be.

= 1.5.0 =
The free halves of six paid features: internal link suggestions you approve one
at a time, a warning when two pages go for the same phrase, a Merchant Centre
feed audit, six accessibility checks, six Australian business checks, and a
verification that your analytics tag fires once rather than twice or not at all.
Also a crawl of your own pages, the links on them that go nowhere, and what your
images cost a visitor.

= 1.3.1 =
The Upgrade screen described the add-on as adding two things over a list of
three. It no longer counts them in a sentence the list can outgrow.

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
