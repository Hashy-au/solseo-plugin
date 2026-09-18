# SolSEO

A free SEO plugin for WordPress and WooCommerce. Titles, meta, XML sitemaps,
structured data, redirects and a content score for posts, pages and products.

Nothing is held back behind a key, nothing phones home unless you connect it to
an account, and it puts no notices on screens that have nothing to do with it.

## What it does

- **A score out of a hundred** on every post, page and product, from about
  thirty checks across four groups: how the page is aimed at its keyword, the
  basics of titles and links, how the writing reads, and, for a shop, whether a
  product has what a shopping result needs. Each check says what to change. The
  score updates as you type and sorts as a column on the list tables.
- **Titles and meta** per page, or a template per post type with placeholders in
  braces (`{title} {sep} {sitename}`). A pixel width gauge shows when a title is
  about to be cut off, which is what a search result actually does to it.
- **XML sitemaps** at `/sitemap.xml` for posts, pages, products, categories,
  tags and optionally author archives, split into pages, with images and product
  galleries listed, and a line in robots.txt.
- **Structured data** for the organisation, the site, breadcrumbs, articles and
  products. A variable product gets one offer per purchasable variation with its
  own price, SKU and stock state inside an aggregate offer.
- **Redirects** with 301, 302, 307 and 410, exact addresses or patterns, and a
  log of every address that found nothing, with the page that linked to it. One
  click turns a logged miss into a redirect. The log stores nothing about the
  visitor.

Also: Open Graph and card tags, canonical addresses, noindex and nofollow per
page, post type and taxonomy, SEO fields on categories and tags, breadcrumbs as
a shortcode or template tag, bulk alt text, robots.txt additions, attachment
pages sent to their parent, and an importer that previews a diff before it
copies anything and never touches what the other plugin stored.

WooCommerce is optional. Nothing asks you to install it.

## Requirements

WordPress 6.4 or later, PHP 7.4 or later.

## Download

**[solseo-1.0.0.zip](https://github.com/Hashy-au/solseo-plugin/releases/download/v1.0.0/solseo-1.0.0.zip)**

In WordPress, go to Plugins, Add New, Upload Plugin, choose the file and
activate it. It works from the moment it is switched on, with no setup.

Every version is on the [releases
page](https://github.com/Hashy-au/solseo-plugin/releases).

## Tests

```powershell
php tests\run.php
```

147 assertions, no WordPress and no database required. The suite covers the
analyser, the templating, the importer, the wiring, the directory rules the
wordpress.org listing has to keep, and a guard that fails on an em dash or an en
dash anywhere in the plugin.

Coding standards are WordPress-Extra, configured in `phpcs.xml`.

## The optional account

The plugin makes no outbound request unless you connect it to a SolSEO account
at [solseo.com.au](https://solseo.com.au). If you paste a pairing code, it then
sends twice a day: the site address and time zone, the WordPress, PHP,
WooCommerce and plugin versions, the count of published posts, pages and
products, and the permalink structure. It reads back which plan the account is
on and a summary of its sites, which is what the dashboard widget shows.

No page content, no customer data and nothing about visitors is sent.
Disconnecting removes the stored key and stops it at once.

[Terms](https://solseo.com.au/terms) and
[privacy policy](https://solseo.com.au/privacy).

## The paid add-on

[SolSEO Pro](https://solseo.com.au/pro) is a separate plugin that installs
beside this one. It scores every phrase a page is aimed at rather than only the
focus keyword, and shows tracked positions in the editor. Nothing in this plugin
is disabled or withheld for it.

## Licence

GPL-2.0-or-later. See [LICENSE](LICENSE).

Built by [Solkarra Group](https://solkarra.com.au).
