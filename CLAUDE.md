# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`Bpf_Hreflang` — an **OpenMage 20.x (Magento 1)** module that emits `<link rel="alternate" hreflang="…">` tags for multi-store-view shops. Installed via Composer (`bpf/openmage_hreflang`, type `magento-module`) using `magento-hackathon/magento-composer-installer` and the `modman` mapping. PHP `>=8.2`.

This is Magento **1**, not Magento 2: no DI, plugins, `di.xml` or declarative schema. Use M1 idioms — class aliases (`Mage::getModel('bpf_hreflang/…')`), `etc/config.xml` / `system.xml` / `adminhtml.xml`, observers declared in `config.xml`, setup scripts in `sql/bpf_hreflang_setup/`, admin routes via `admin/routers/adminhtml/args/modules`. Ignore guidance from the global `magento-core` skill — it targets Magento 2.4. Use the `openmage-conventions` skill (`../.claude/skills/openmage-conventions`) instead; OpenMage core source for lookup: `/home/div/Projects/Magento/openmage/vendor/openmage/magento-lts`.

## Source of truth

- `docs/SPEC.md` — functional spec for 1.0 (in Polish): config paths, resolver interface, tag-generation rules (self-reference, reciprocity, min. 2 versions, `x-default`, `noindex`, canonical match, exclusions), cache keys/tags, DB schema.
- `docs/PLAN.md` — implementation plan; each numbered item is one small commit, with a proposed commit message. Decisions D1–D10 at the top resolve gaps in the spec and take precedence over it where they differ. Work through the plan in order.

## Layout and packaging

- Module code: `app/code/community/Bpf/Hreflang/` (alias `bpf_hreflang` for models/blocks/helpers, config paths under `bpf_hreflang/…`).
- Module declaration: `app/etc/modules/Bpf_Hreflang.xml`.
- `modman` maps files into a Magento root. **Every new file or directory outside `app/code/community/Bpf/Hreflang` (layouts, templates, locale CSVs, adminhtml layout) must be added to `modman`**, otherwise it is missing after Composer install. Tests and dev config are not mapped.
- Shops install the module from the GitHub dist zipball; `.gitattributes` `export-ignore`s dev files so only `app/`, `composer.json` and `modman` ship. **Any new top-level dev file or directory must be added to `.gitattributes`.** Check with `git archive HEAD | tar -t`.
- Module version lives in `etc/config.xml`; setup scripts (`install-X.php`/`upgrade-X-Y.php`) only run when their version matches it.

## Architecture (target, per SPEC §5)

Request flow: block `Bpf_Hreflang_Block_Head` (child of `head`, handle `default`) → checks enabled / excluded action / `noindex` → `Bpf_Hreflang_Model_Builder` picks the first resolver whose `canResolve()` is true → builder computes candidate stores (same website or global per `group_scope`, active, with `locale_code`, not default-`NOINDEX`) → resolver returns `storeId => URL` for stores where the page exists → builder applies rules and returns `hreflang => URL` → block renders and caches.

- Resolvers implement `Bpf_Hreflang_Model_Resolver_Interface`; built-ins (`product`, `category`, `cms`, `home`) are declared in `config.xml` under `global/bpf_hreflang/resolvers/<code>`; third-party ones are added via event `bpf_hreflang_resolvers_collect` and are checked first.
- `Bpf_Hreflang_Model_Url` is the only place that builds absolute URLs and the only code aware of "root stores" (store code stripped from the path). Product URLs are canonical (no category path).
- CMS pages are paired through translation groups (tables `bpf_hreflang_group`, `bpf_hreflang_group_item`), managed in admin under CMS → Hreflang; pages assigned to all stores pair automatically.
- Reciprocity: the alternate set is computed for the whole version group, so every version emits the same tags.

## Commands

Host PHP lacks `ext-dom`, so PHP and Composer run in Docker via `bin/php` and `bin/composer` (image built from `.docker/php/Dockerfile` on first use; `PHP_VERSION=8.3 bin/php …` picks another version). Never run host `php`/`composer` for project tooling.

```bash
bin/composer install
bin/composer check                                # lint + phpstan + test
bin/composer lint                                 # parallel-lint over app, bin, tests
bin/composer phpstan
bin/composer test                                 # PHPUnit unit suite
bin/php vendor/bin/phpunit --filter <TestName>    # single test
```

`tests/bootstrap.php` loads `vendor/openmage/magento-lts/app/Mage.php` without `Mage::app()` and puts this repo's `app/code/community` first on the include path. Composer's post-install/update hook runs `bin/link-module.php`, which symlinks the module into `vendor/openmage/magento-lts` per `modman` — PHPStan (`macopedia/phpstan-magento1`) reads module config from that root to resolve `bpf_hreflang/…` aliases; rerun `bin/composer run-script post-install-cmd` after changing `modman`.

Integration tests (PLAN 8.x) run against a docker-compose OpenMage + sample data instance via `BASE_URL`.

## Commits

English, imperative mood, one line, no co-author trailers unless explicitly asked. Use the message proposed in `docs/PLAN.md` for the item being implemented. Do not commit unless asked.
