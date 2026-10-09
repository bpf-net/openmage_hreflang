# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`Bpf_Hreflang` — an **OpenMage 20.x (Magento 1)** module that emits `<link rel="alternate" hreflang="…">` tags for multi-store-view shops. Installed via Composer (`bpf/openmage_hreflang`, type `magento-module`) using `magento-hackathon/magento-composer-installer` and the `modman` mapping. PHP `>=8.1`.

This is Magento **1**, not Magento 2: no DI, plugins, `di.xml` or declarative schema. Use M1 idioms — class aliases (`Mage::getModel('bpf_hreflang/…')`), `etc/config.xml` / `system.xml` / `adminhtml.xml`, observers declared in `config.xml`, setup scripts in `sql/bpf_hreflang_setup/`, admin routes via `admin/routers/adminhtml/args/modules`. Ignore guidance from the global `magento-core` skill — it targets Magento 2.4. Use the `openmage-conventions` skill (`../.claude/skills/openmage-conventions`) instead; OpenMage core source for lookup: `/home/div/Projects/Magento/openmage/vendor/openmage/magento-lts`.

## Source of truth

- `docs/SPEC.md` — functional spec for 1.0 (in Polish): config paths, resolver interface, tag-generation rules (self-reference, reciprocity, min. 2 versions, `x-default`, `noindex`, canonical match, exclusions), cache keys/tags, DB schema.
- `docs/PLAN.md` — implementation plan; each numbered item is one small commit, with a proposed commit message. Decisions D1–D10 at the top resolve gaps in the spec and take precedence over it where they differ. Work through the plan in order.

## Layout and packaging

- Module code: `app/code/community/Bpf/Hreflang/` (alias `bpf_hreflang` for models/blocks/helpers, config paths under `bpf_hreflang/…`).
- Module declaration: `app/etc/modules/Bpf_Hreflang.xml`.
- `modman` maps files into a Magento root. **Every new file or directory outside `app/code/community/Bpf/Hreflang` (layouts, templates, locale CSVs, adminhtml layout) must be added to `modman`**, otherwise it is missing after Composer install. Tests and dev config are not mapped.
- Module version lives in `etc/config.xml`; setup scripts (`install-X.php`/`upgrade-X-Y.php`) only run when their version matches it.

## Architecture (target, per SPEC §5)

Request flow: block `Bpf_Hreflang_Block_Head` (child of `head`, handle `default`) → checks enabled / excluded action / `noindex` → `Bpf_Hreflang_Model_Builder` picks the first resolver whose `canResolve()` is true → builder computes candidate stores (same website or global per `group_scope`, active, with `locale_code`, not default-`NOINDEX`) → resolver returns `storeId => URL` for stores where the page exists → builder applies rules and returns `hreflang => URL` → block renders and caches.

- Resolvers implement `Bpf_Hreflang_Model_Resolver_Interface`; built-ins (`product`, `category`, `cms`, `home`) are declared in `config.xml` under `global/bpf_hreflang/resolvers/<code>`; third-party ones are added via event `bpf_hreflang_resolvers_collect` and are checked first.
- `Bpf_Hreflang_Model_Url` is the only place that builds absolute URLs and the only code aware of "root stores" (store code stripped from the path). Product URLs are canonical (no category path).
- CMS pages are paired through translation groups (tables `bpf_hreflang_group`, `bpf_hreflang_group_item`), managed in admin under CMS → Hreflang; pages assigned to all stores pair automatically.
- Reciprocity: the alternate set is computed for the whole version group, so every version emits the same tags.

## Commands

Dev tooling (PHPUnit, PHPStan, composer scripts, CI) is introduced in PLAN phase 0 and not present yet. Once added:

```bash
composer install
composer lint        # php -l over module files
composer phpstan
composer test        # PHPUnit, unit suite
vendor/bin/phpunit --filter <TestName>   # single test
```

Integration tests (PLAN 8.x) run against a docker-compose OpenMage + sample data instance via `BASE_URL`.

## Commits

English, imperative mood, one line, no co-author trailers unless explicitly asked. Use the message proposed in `docs/PLAN.md` for the item being implemented. Do not commit unless asked.
