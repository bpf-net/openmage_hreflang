# Bpf_Hreflang 1.0 — plan implementacji

Plan wynikający z [SPEC.md](SPEC.md). Każdy punkt to jeden mały, logiczny commit, po którym moduł nadal się instaluje i działa (ew. z niepełną funkcjonalnością). W nawiasie kwadratowym proponowany komunikat commita.

Stan wyjściowy: `config.xml` z wersją `0.1.1`, deklaracja modułu, `composer.json`, `modman`.

## Decyzje do podjęcia przed startem

Niespójności i luki w specyfikacji, które wpływają na kolejne commity:

| # | Problem | Propozycja | Dotyczy |
| --- | --- | --- | --- |
| D1 | Nazwa eventu: p. 5.3 podaje `bpf_hreflang_resolvers_collect`, ale też „roboczo `hreflang_resolvers_collect`”. | `bpf_hreflang_resolvers_collect`; usunąć wzmiankę z SPEC. | 3.3 |
| D2 | Skrypt `install-1.0.0.php`, a wersja modułu to `0.1.1` — instalator się nie uruchomi. | Podbić wersję w `config.xml` do `1.0.0` w commicie z tabelą. | 6.1 |
| D3 | Grid w adminie pokazuje „stronę bazową” i „datę modyfikacji”, a tabela `bpf_hreflang_group_item` nie ma tych danych; `group_id` nie ma źródła auto-increment. | Dodać tabelę `bpf_hreflang_group` (`group_id` PK AI, `entity_type`, `base_entity_id`, `base_store_id`, `created_at`, `updated_at`); `group_item.group_id` jako FK z cascade delete. Uzupełnić SPEC p. 5.4. | 6.1, 7.2 |
| D4 | Brak w SPEC źródła opcji dla `group_scope`. | `Model/System/Config/Source/GroupScope.php`; dopisać do p. 7. | 1.3 |
| D5 | Strona CMS przypisana do kilku konkretnych store view (nie `0`) — SPEC nie mówi, czy parować automatycznie. | Traktować jak `store_id = 0`: ta sama strona w każdym przypisanym store jest swoją parą. | 6.4 |
| D6 | „Strony z parametrami nie emitują tagów” — `?utm_source=…`, `?gclid=…` wyłączą tagi na stronach z kampanii. | Ignorować listę parametrów śledzących (`utm_*`, `gclid`, `fbclid`, `___store`, `___from_store`) przy sprawdzaniu. | 3.5 |
| D7 | Bieżący store niedostępny w wyniku resolvera (np. rzadki edge case) — SPEC milczy. | Brak self-reference ⇒ brak tagów. | 3.4 |
| D8 | Testy jednostkowe Buildera/Url bez bazy. | Builder i Url przyjmują zależności (dostawca store'ów, odczyt konfiguracji) przez konstruktor/settery z domyślnymi implementacjami opartymi o `Mage`; testy podstawiają stuby. | 2.x, 3.x |
| D9 | Otwarte pytanie 10.2 (`group_scope`). | Zostawić `website` + `global` jak w SPEC (koszt niewielki). | 1.3, 3.2 |
| D10 | Otwarte pytanie 10.3 (nazwa pakietu). | Rozstrzygnąć przed 9.5. | 9.5 |

## Faza 0 — repozytorium i narzędzia

**0.1** Dodanie specyfikacji i planu do repo.
`[Add 1.0 specification and implementation plan]`

**0.2** Narzędzia PHP w Dockerze (lokalne PHP nie ma `ext-dom`): `.docker/php/Dockerfile` (PHP CLI z rozszerzeniami wymaganymi przez OpenMage + Composer, wersja PHP jako build arg), wrappery `bin/php` i `bin/composer` uruchamiające kontener z bieżącym UID i cache Composera z hosta.
`[Add Docker-based PHP and Composer wrappers]`

**0.3** PHPUnit: `require-dev` (`phpunit/phpunit`), `phpunit.xml.dist` z suite `unit`, `tests/bootstrap.php` ładujący `Mage.php` z `vendor/openmage/magento-lts` (bez `Mage::app()`), `vendor/` w `.gitignore`. Katalog `tests/` poza `modman`.
`[Add PHPUnit setup]`

**0.4** PHPStan: `phpstan/phpstan` + `macopedia/phpstan-magento1`, `phpstan.neon.dist` (poziom 5, ścieżka `app/code/community/Bpf`); `bin/link-module.php` uruchamiany po `composer install/update` linkuje moduł do `vendor/openmage/magento-lts` wg `modman`, bo rozszerzenie czyta konfigurację modułów z roota Magento.
`[Add PHPStan configuration]`

**0.5** Skrypty composera: `lint` (`php -l` po plikach), `phpstan`, `test`.
`[Add composer scripts for lint, analysis and tests]`

## Faza 1 — szkielet modułu i konfiguracja

**1.1** `config.xml`: aliasy `global/models|blocks|helpers` → `bpf_hreflang`; pusty `Helper/Data.php`.
`[Register model, block and helper aliases]`

**1.2** Helper: stałe ścieżek konfiguracji i gettery `isEnabled($store)`, `getLocaleCode($store)`, `getGroupScope()`, `getXDefaultStoreId($store)`, `getExcludedActions()`, `getRootStoreIds()`; wartości domyślne w `config.xml/default` (`enabled=0`, `group_scope=website`, lista `excluded_actions` z p. 6.4).
`[Add config accessors and default values]`

**1.3** `system.xml` (tab Bpf, sekcja `bpf_hreflang`, grupy `general` i `url`, pola z p. 4 z właściwymi zakresami) + source model `GroupScope` (D4) + `adminhtml/system_config_source_store` dla `x_default_store` i `root_stores`; `adminhtml.xml` z ACL `system/config/bpf_hreflang`. `locale_code` na razie bez backend modelu.
`[Add system configuration section and ACL]`

**1.4** Helper: `normalizeLocaleCode()` (trim, `_`→`-`, język lowercase, kraj uppercase) i `isValidLocaleCode()` (regex z p. 4.1) + testy jednostkowe.
`[Add locale code normalization and validation]`

**1.5** `Model/System/Config/Backend/LocaleCode.php`: normalizacja i walidacja formatu w `_beforeSave()`, błąd z czytelnym komunikatem; podpięcie w `system.xml`.
`[Validate locale code on config save]`

**1.6** Backend `LocaleCode`: wykrywanie kolizji kodu w tej samej grupie (wg `group_scope`) z nazwą kolidującego store w komunikacie; logika kolizji w osobnej, testowalnej metodzie + testy (kolizja w website, brak kolizji między website przy `website`, kolizja przy `global`, `en` + `en-GB` dozwolone).
`[Reject duplicate locale codes within store group]`

## Faza 2 — budowa URL-i (`Model/Url.php`)

**2.1** `Bpf_Hreflang_Model_Url::getHomeUrl($storeId)`: bazowy URL store, `_secure` gdy front na HTTPS (`web/secure/use_in_frontend`) + testy.
`[Add URL service with secure home URL]`

**2.2** Tryb bez kodu sklepu: dla `root_stores` przy `web/url/use_store=1` usuwanie segmentu `/<kod>/` (tylko pierwszy segment ścieżki po bazie, bez ruszania domeny i dalszej części) + testy z kodem/bez kodu/HTTPS.
`[Strip store code from URLs of root stores]`

**2.3** `getProductUrl($productId, $storeId)`: request path z `core/url_rewrite` dla `id_path = product/<id>` (bez kategorii, zgodnie z canonical); brak rewrite ⇒ `null`.
`[Build product URLs from store rewrites]`

**2.4** `getCategoryUrl($categoryId, $storeId)`: analogicznie dla `category/<id>`.
`[Build category URLs from store rewrites]`

**2.5** `getCmsPageUrl($identifier, $storeId)`: baza + identyfikator; strona ustawiona jako `web/default/cms_home_page` ⇒ URL strony głównej.
`[Build CMS page URLs]`

## Faza 3 — builder i resolvery (rdzeń)

**3.1** `Model/Resolver/Interface.php` zgodnie z p. 5.2.
`[Add resolver interface]`

**3.2** Builder: zbiór kandydatów — store view z grupy (`group_scope`), aktywne, z `locale_code`, z włączonym modułem, bez `NOINDEX` w `design/head/default_robots` + testy.
`[Add builder with candidate store selection]`

**3.3** Builder: rejestr resolverów z `global/bpf_hreflang/resolvers/<kod>` (klasa + akcje), event `bpf_hreflang_resolvers_collect` z `Varien_Object resolvers` (D1), kolejność: z eventu przed wbudowanymi, pierwszy `canResolve()` wygrywa + testy.
`[Collect resolvers from config and event]`

**3.4** Builder: złożenie wyniku `[hreflang => url]` — mapowanie storeId → kod, brak bieżącego store ⇒ pusto (D7), minimum 2 wersje, `x-default` tylko gdy skonfigurowany store jest w wyniku, stabilna kolejność + testy (w tym zwrotność: ten sam zestaw dla każdej wersji).
`[Assemble hreflang map with minimum and x-default rules]`

**3.5** Wykluczenia: dopasowanie pełnej nazwy akcji do `excluded_actions` (`*` = prefiks), odrzucanie żądań z parametrami zapytania z pominięciem parametrów śledzących (D6) + testy.
`[Skip excluded actions and parameterized requests]`

## Faza 4 — frontend end-to-end (strona główna)

**4.1** `Model/Resolver/Home.php` (`cms_index_index`, URL z `Model/Url::getHomeUrl`, cache key `home`) + rejestracja w `config.xml`.
`[Add home page resolver]`

**4.2** `Block/Head.php` + `template/bpf/hreflang/head.phtml` (`escapeUrl`) + `layout/bpf_hreflang.xml` (dziecko `head` w `default`) + `frontend/layout/updates` w `config.xml` + wpisy w `modman`. Blok sprawdza: włączony moduł, wykluczenia, `noindex` w robots bloku `head`.
`[Render hreflang links in page head]`

**4.3** Cache bloku: klucz (store, akcja, `getCacheKey()`, HTTPS), tagi (`getCacheTags()` + `bpf_hreflang` + `config`), brak zapisu gdy żaden resolver nie obsługuje strony.
`[Cache rendered hreflang block]`

## Faza 5 — katalog

**5.1** `Model/Resolver/Product.php`: dostępność per store (status, widoczność ≠ „nie widoczny osobno”, przypisanie do website), URL z 2.3, cache key `product:<id>`, tag `catalog_product_<id>`; rejestracja w `config.xml`.
`[Add product resolver]`

**5.2** `Model/Resolver/Category.php`: aktywna i w drzewie root category store (`path` zawiera `/<rootId>/`), URL z 2.4, tag `catalog_category_<id>`; rejestracja.
`[Add category resolver]`

## Faza 6 — grupy tłumaczeń i CMS

**6.1** `sql/bpf_hreflang_setup/install-1.0.0.php`: tabele `bpf_hreflang_group` (D3) i `bpf_hreflang_group_item` z indeksami, unikalnymi kluczami i FK z p. 5.4; `resources/bpf_hreflang_setup` i encje resource modelu w `config.xml`; wersja `1.0.0` (D2).
`[Add translation group tables]`

**6.2** `Model/Group.php`, `Model/Group/Item.php`, resource modele i kolekcje; metoda wyszukania wersji encji `entityType + entityId + storeId ⇒ [storeId => entityId]`; czyszczenie tagu cache `bpf_hreflang` po zapisie/usunięciu.
`[Add translation group models]`

**6.3** `Model/Observer.php` na `cms_page_delete_after`: usunięcie wpisów strony z grup.
`[Remove deleted CMS pages from translation groups]`

**6.4** `Model/Resolver/Cms.php`: strona z `store_id = 0` (i wiele store, D5) parowana sama ze sobą; w przeciwnym razie grupa tłumaczeń; dostępność = aktywna i przypisana do store; URL z 2.5; tagi `cms_page_<id>` + `bpf_hreflang`; rejestracja.
`[Add CMS page resolver]`

## Faza 7 — admin grup tłumaczeń

**7.1** Router admina (`admin/routers/adminhtml/args/modules`), `GroupController` z `indexAction` i `_isAllowed()`, menu CMS → Hreflang i ACL `cms/bpf_hreflang` w `adminhtml.xml`, layout admina + wpis w `modman`.
`[Add admin menu and controller for translation groups]`

**7.2** Grid: ID grupy, strona bazowa, liczba store view, data modyfikacji.
`[Add translation groups grid]`

**7.3** Formularz edycji: wybór strony bazowej + select strony CMS dla każdego store view z `locale_code`; akcje `new/edit/save/delete`.
`[Add translation group edit form]`

**7.4** Walidacja przy zapisie: unikalność (encja, store) i (grupa, store) z czytelnymi komunikatami, strona musi być przypisana do wybranego store.
`[Validate translation group constraints on save]`

## Faza 8 — testy integracyjne i CI

**8.1** Środowisko: `docker-compose` z OpenMage 20.x + sample data, skrypt fixture konfigurujący store `pl` (pod `/`), `en`, `de-AT`, `en-GB`, `x-default`; suite `integration` w PHPUnit z `BASE_URL` z env (skip gdy brak) i helperem parsującym `<link rel="alternate">` oraz `canonical`.
`[Add integration test environment and fixtures]`

Każdy przypadek z p. 8.2 SPEC jako osobny commit:

- **8.2** Strona główna: `/` i `/en/`, dokładne URL-e i `x-default`. `[Test hreflang on home page]`
- **8.3** 404 bez tagów. `[Test that 404 page has no hreflang]`
- **8.4** CMS w grupie i z `store_id = 0`. `[Test hreflang on CMS pages]`
- **8.5** Zwrotność: produkt wyłączony w jednym store. `[Test hreflang reciprocity for disabled product]`
- **8.6** href = canonical; strona z filtrami bez tagów. `[Test hreflang matches canonical]`
- **8.7** Strona `noindex` i store z domyślnym `NOINDEX`. `[Test noindex exclusions]`
- **8.8** Cache: różne strony = różne tagi, zapis encji unieważnia cache. `[Test hreflang cache invalidation]`
- **8.9** `en-GB` + `en`. `[Test same language in multiple regions]`

**8.10** GitHub Actions: lint, PHPStan, PHPUnit `unit` na PHP 8.1/8.2/8.3.
`[Add CI workflow for lint, analysis and unit tests]`

**8.11** Job integracyjny w CI (docker-compose + suite `integration`).
`[Run integration tests in CI]`

> 8.10 można przesunąć zaraz po 1.4 (pierwsze testy), żeby CI pilnowało całej dalszej pracy — zalecane.

## Faza 9 — wydanie

**9.1** `app/locale/en_US/Bpf_Hreflang.csv` i `pl_PL/Bpf_Hreflang.csv` + wpisy w `modman`.
`[Add en_US and pl_PL translations]`

**9.2** `LICENSE` (OSL-3.0).
`[Add OSL-3.0 license]`

**9.3** `README.md`: problem, instalacja (Composer + `modman`), konfiguracja, zrzuty z admina, ograniczenia, roadmapa.
`[Write README]`

**9.4** `CHANGELOG.md` z sekcją 1.0.0.
`[Add changelog for 1.0.0]`

**9.5** Finalna nazwa pakietu (D10), aktualizacja SPEC (status, rozstrzygnięte pytania), tag `v1.0.0` (tag bez commita).
`[Finalize package metadata for 1.0.0]`

## Mapowanie na kryteria ukończenia (SPEC p. 9)

| Pozycja p. 2.1 | Commity |
| --- | --- |
| 1. Produkty i kategorie | 2.3, 2.4, 5.1, 5.2 |
| 2. Strony CMS | 2.5, 6.1–6.4, 7.1–7.4 |
| 3. Strona główna | 2.1, 4.1 |
| 4. `x-default` | 1.3, 3.4 |
| 5. Kod języka z walidacją | 1.4–1.6 |
| 6. URL bez kodu sklepu | 2.2 |
| 7. Wykluczenia | 3.5, 4.2, 3.2 (store `NOINDEX`) |
| 8. Self-reference i zwrotność | 3.4 |
| 9. Event rozszerzeń | 3.3 |
