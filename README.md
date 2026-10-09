# Bpf_Hreflang

`<link rel="alternate" hreflang="…">` tags for multi-language [OpenMage](https://www.openmage.org/) shops.

OpenMage has no hreflang support. Existing modules usually cover only products and categories and break on non-standard URLs, such as the default language served at `/` without a store code. CMS pages have different identifiers in each language, so they cannot be paired automatically. This module covers all of that:

- **Products and categories**: paired by entity ID across store views, using the canonical URL of each store view.
- **CMS pages**: translation groups managed in the admin. Pages assigned to all (or several) store views pair automatically.
- **Home page**.
- **`x-default`**: points to a configurable store view.
- **Hreflang code per store view**: validated against ISO 639-1 and ISO 3166-1 (`en`, `en-GB`, `de-AT`).
- **Store views without the store code in the URL**: for example, the default language at `/`.
- **Exclusions**: 404, search results, cart, customer account, `noindex` pages, filtered/sorted listings.
- **Self-reference and reciprocity** as required by [Google's guidelines](https://developers.google.com/search/docs/specialty/international/localized-versions).
- **Extension point** for other content types, such as a blog.

```html
<link rel="alternate" hreflang="pl" href="https://example.com/kubek.html" />
<link rel="alternate" hreflang="en" href="https://example.com/en/mug.html" />
<link rel="alternate" hreflang="de-AT" href="https://example.com/at/becher.html" />
<link rel="alternate" hreflang="x-default" href="https://example.com/kubek.html" />
```

## Requirements

- OpenMage LTS 20.x
- PHP 8.2 or newer
- [`magento-hackathon/magento-composer-installer`](https://github.com/magento-hackathon/magento-composer-installer) (a dependency of the package)

## Installation

Add the repository and require the package in your shop's `composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "git@github.com:bpf-net/openmage_hreflang.git" }
    ]
}
```

```bash
composer require bpf/openmage_hreflang
```

The Composer installer deploys the module into the Magento root according to `modman`.

After installing or updating:

1. **Flush the Magento cache** (System → Cache Management → Flush Magento Cache). The database tables of translation groups are created on the first request after the flush.
2. **Log out of the admin and log in again**. Admin permissions are loaded at login, so until then the new configuration section returns a 404 page and the new menu item is missing.

## Configuration

System → Configuration → **Bpf → Hreflang**:

| Setting | Scope | Description |
| --- | --- | --- |
| Enabled | default, website, store view | Turns the tags on. Off by default. |
| Hreflang Code | store view | Code of the store view: `pl`, `en`, `en-GB`, `de-AT`… An empty value keeps the store view out. Codes must be unique among store views listed as alternates of each other; `en` and `en-GB` can be used together. |
| Alternates Scope | default | Store views of the same website (default), or all store views. |
| x-default Store View | default, website | Store view used as `x-default`. Empty means no `x-default`. With "All store views" the default-scope value is used. |
| Excluded Actions | default | Full action names, one per line; a trailing `*` matches a prefix. The defaults exclude the 404 page, search, checkout, customer account, wishlist, sales, reviews and contacts. |
| Store Views Without Store Code in URL | default | Store views whose URLs are generated without the `/<store code>/` segment, for example the default language at `/`. Applies only with *Add Store Code to Urls* enabled. Your web server must actually serve those store views there. |

A store view takes part only when it is active, has the module enabled, has a hreflang code and is not `NOINDEX` by default (*Design → HTML Head → Default Robots*).

## CMS pages and translation groups

**CMS → Hreflang Translation Groups** pairs CMS pages that are translations of each other but have different identifiers, such as `o-nas` (Polish) and `about-us` (English). For each store view with a hreflang code, choose the page that is its version. The base page is shown in the grid only.

Rules:

- a group needs at least two versions;
- the page chosen for a store view must be shown in that store view;
- a page can be in one group only;
- a CMS page that is not in a group and is assigned to all (or several) store views is its own version in each of them; no group is needed;
- deleting a CMS page removes it from its group.

## How the tags are built

- Every page lists itself and all its versions. All versions get the same set of tags. A version that is unavailable in one store view, such as a disabled product, disappears from every page, not just its own.
- No tags are output when the page exists in fewer than two store views, or when the current page is not one of the versions.
- A version exists in a store view when:

  | Page | Condition |
  | --- | --- |
  | Product | enabled, visible in catalog and/or search, assigned to the store's website, has a URL rewrite |
  | Category | active, under the store's root category, has a URL rewrite |
  | CMS page | active and assigned to the store view |
  | Home page | always |

- URLs equal the canonical URL of each version: products without the category path, always HTTPS when the store view uses it on the frontend.
- Pages with query parameters (layered navigation filters, sorting, paging) get no tags. Tracking parameters (`utm_*`, `gclid`, `fbclid`…) do not count.
- The output is cached per store view, page and protocol. It is refreshed when the product, category or CMS page is saved, a translation group changes, or any configuration section is saved.

## Adding your own content types

Other modules can add resolvers for their own pages, such as blog posts. Implement `Bpf_Hreflang_Model_Resolver_Interface` and register it by observing `bpf_hreflang_resolvers_collect`:

```xml
<global>
    <events>
        <bpf_hreflang_resolvers_collect>
            <observers>
                <my_blog>
                    <class>my_blog/observer</class>
                    <method>addHreflangResolver</method>
                </my_blog>
            </observers>
        </bpf_hreflang_resolvers_collect>
    </events>
</global>
```

```php
public function addHreflangResolver(Varien_Event_Observer $observer): void
{
    $observer->getEvent()->getResolvers()->setData('blog_post', Mage::getModel('my_blog/hreflang_resolver'));
}
```

Resolvers added this way are asked before the built-in ones. A resolver added under the code of a built-in one (`home`, `product`, `category`, `cms`) replaces it. Translation groups can be reused for other entity types through `Bpf_Hreflang_Model_Resource_Group::getEntityGroupItems()`.

## Limitations

- Paginated and filtered listings get no tags.
- No hreflang entries in the XML sitemap.
- Translation groups are available for CMS pages only. Other content types need their own resolver.

## Roadmap

- hreflang in the XML sitemap
- import and export of translation groups (CSV)
- admin report of pages without a pair
- `hreflang:audit` command: crawl the sitemap and report missing reciprocity, 404s, redirects and canonical mismatches
- resolvers for popular blog modules
- automated integration tests against a shop with sample data

## Development

The host PHP is not used: `bin/php` and `bin/composer` run PHP 8.2 (or `PHP_VERSION=8.3`) in Docker.

```bash
bin/composer install     # also links the module into vendor/openmage/magento-lts and enables the git hooks
bin/composer check       # lint, PHPStan, PHPUnit
```

A `pre-push` hook runs the checks on PHP 8.2 and 8.3. GitHub Actions run them for release tags.

## License

[OSL-3.0](LICENSE)
