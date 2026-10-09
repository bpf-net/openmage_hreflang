# Bpf_Hreflang — specyfikacja 1.0

Moduł OpenMage 20.x generujący tagi `<link rel="alternate" hreflang="…">` dla sklepów wielojęzycznych (wiele store view), instalowany przez Composer.

Status: szkic, wersja dokumentu 2026-10-09.

## 1. Problem

- OpenMage nie ma natywnej obsługi hreflang.
- Gotowe moduły zwykle obsługują tylko produkty i kategorie i psują się przy niestandardowych URL-ach (np. domyślny język pod `/` bez kodu sklepu).
- Strony CMS i podobne treści mają różne identyfikatory w każdym języku, więc nie da się ich sparować automatycznie — potrzebne jest ręczne parowanie w adminie.

## 2. Zakres

### 2.1 W zakresie 1.0

1. Produkty i kategorie: parowanie po ID encji między store view.
2. Strony CMS: ręczne grupy tłumaczeń w adminie; strony przypisane do wszystkich store view (`store_id = 0`) parowane automatycznie.
3. Strona główna.
4. `x-default` wskazujący konfigurowalny store view.
5. Kod języka per store view z walidacją formatu.
6. Tryb URL bez kodu sklepu w ścieżce dla wybranych store view.
7. Wykluczenia stron (404, wyszukiwarka, koszyk, konto, strony `noindex`).
8. Self-reference i zwrotność zgodnie z wytycznymi Google.
9. Punkt rozszerzeń dla własnych typów treści (event).

### 2.2 Poza zakresem 1.0 (roadmapa)

- hreflang w sitemap XML.
- Import/eksport grup tłumaczeń z CSV.
- Raport stron bez pary w adminie.
- Komenda CLI `hreflang:audit`: crawl sitemap, pobranie alternate każdej strony, raport braku zwrotności, 404, przekierowań i niezgodności z canonical.
- Resolvery dla konkretnych modułów bloga (przez event z p. 5.3).

## 3. Środowisko i identyfikacja

| Element | Wartość |
| --- | --- |
| Pakiet Composer | `bpf/openmage_hreflang`, typ `magento-module` |
| Instalacja | `magento-hackathon/magento-composer-installer`, mapowanie w `modman` |
| Moduł | `Bpf_Hreflang`, code pool `community` |
| Alias klas (model/blok/helper) | `bpf_hreflang` |
| Setup resource | `bpf_hreflang_setup` |
| Ścieżki konfiguracji | `bpf_hreflang/…` |
| OpenMage | 20.x |
| PHP | `>=8.1` (CI: 8.1/8.2/8.3) |
| Licencja | OSL-3.0 |

## 4. Konfiguracja (System → Konfiguracja → Bpf → Hreflang)

| Ścieżka | Zakres | Typ | Domyślnie | Opis |
| --- | --- | --- | --- | --- |
| `bpf_hreflang/general/enabled` | default, website, store | yes/no | 0 | Włącza generowanie tagów. |
| `bpf_hreflang/general/locale_code` | store | tekst | pusty | Kod hreflang store view, np. `pl`, `en`, `de-AT`. Pusty = store nie bierze udziału. |
| `bpf_hreflang/general/group_scope` | default | select `website` / `global` | `website` | Które store view są wzajemnymi alternatywami: tylko w obrębie tej samej strony (website) czy wszystkie. |
| `bpf_hreflang/general/x_default_store` | default, website | select store view | pusty | Store view używany jako `x-default`. Pusty = brak `x-default`. |
| `bpf_hreflang/general/excluded_actions` | default | textarea | lista z p. 6.4 | Pełne nazwy akcji, jedna na linię; `*` na końcu = prefiks. |
| `bpf_hreflang/url/root_stores` | default | multiselect store view | pusty | Store view serwowane bez kodu sklepu w ścieżce (np. domyślny język pod `/`). |

### 4.1 Walidacja kodu języka

- Format: `^[a-z]{2}(-[A-Z]{2})?$` — ISO 639-1, opcjonalnie `-` + ISO 3166-1 alpha-2 (np. `en`, `en-GB`, `de-AT`).
- Wpisana wartość jest normalizowana (język małymi, kraj wielkimi literami; `_` zamieniane na `-`).
- Dwa store view z tej samej grupy (p. 4, `group_scope`) nie mogą mieć identycznego kodu — zapis konfiguracji kończy się błędem z nazwą kolidującego store.
- Ten sam język w kilku krajach jest dozwolony przez różne kody, np. `en-GB` dla UK i `en` dla reszty świata.

## 5. Architektura

### 5.1 Przepływ

1. Blok `bpf_hreflang/head` (dziecko bloku `head`, layout handle `default`) przy renderowaniu sprawdza, czy moduł jest włączony i czy akcja nie jest wykluczona.
2. `Bpf_Hreflang_Model_Builder` wybiera resolver po pełnej nazwie akcji (`catalog_product_view` itd.).
3. Builder ustala zbiór kandydatów: store view z tej samej grupy (`group_scope`), aktywne, z ustawionym `locale_code`, z włączonym modułem.
4. Resolver zwraca URL-e strony w tych store view, w których strona istnieje i jest dostępna.
5. Builder stosuje reguły z sekcji 6 (noindex, minimum 2 wersje, `x-default`) i zwraca listę `[hreflang => url]`.
6. Blok renderuje tagi; wynik jest cache'owany (p. 5.5).

### 5.2 Interfejs resolvera

```php
interface Bpf_Hreflang_Model_Resolver_Interface
{
    /**
     * Czy resolver obsługuje bieżące żądanie.
     */
    public function canResolve(Mage_Core_Controller_Request_Http $request);

    /**
     * URL-e bieżącej strony w podanych store view.
     * Zwraca tylko store view, w których strona istnieje i jest dostępna.
     *
     * @param int[] $storeIds
     * @return array<int, string> storeId => absolutny URL
     */
    public function resolve(Mage_Core_Controller_Request_Http $request, array $storeIds);

    /**
     * Identyfikator strony do klucza cache (np. "product:42").
     */
    public function getCacheKey(Mage_Core_Controller_Request_Http $request);

    /**
     * Tagi cache encji, np. catalog_product_42.
     *
     * @return string[]
     */
    public function getCacheTags(Mage_Core_Controller_Request_Http $request);
}
```

Resolvery wbudowane:

| Kod | Akcje | Parowanie | Dostępność w store |
| --- | --- | --- | --- |
| `product` | `catalog_product_view` | to samo ID produktu | status włączony, widoczność katalog i/lub wyszukiwarka, produkt przypisany do website store |
| `category` | `catalog_category_view` | to samo ID kategorii | kategoria aktywna i w drzewie root category danego store |
| `cms` | `cms_page_view` | grupa tłumaczeń (p. 5.4) lub ta sama strona przypisana do wszystkich store view | strona aktywna i przypisana do store |
| `home` | `cms_index_index` | zawsze | store aktywny |

### 5.3 Rejestracja resolverów i event

- Wbudowane resolvery są zadeklarowane w `config.xml` w węźle `global/bpf_hreflang/resolvers/<kod>` (klasa + lista akcji).
- Builder po zebraniu resolverów z konfiguracji wywołuje event `bpf_hreflang_resolvers_collect` z obiektem `resolvers` (`Varien_Object`), do którego inne moduły mogą dodać własne instancje.
- Nazwa eventu ma prefiks modułu, żeby uniknąć kolizji z innymi rozszerzeniami (w planie roboczo `hreflang_resolvers_collect`).
- Pierwszy resolver, którego `canResolve()` zwraca `true`, wygrywa; resolvery z eventu są sprawdzane przed wbudowanymi.

### 5.4 Grupy tłumaczeń

Ogólny mechanizm ręcznego parowania, używany przez resolver CMS i dostępny dla resolverów z innych modułów (np. blog).

Tabela `bpf_hreflang_group_item`:

| Kolumna | Typ | Opis |
| --- | --- | --- |
| `item_id` | int, PK, auto increment | |
| `group_id` | int, indeks | Identyfikator grupy tłumaczeń. |
| `entity_type` | varchar(32) | Typ encji, np. `cms_page`. |
| `entity_id` | int | ID encji. |
| `store_id` | smallint unsigned, FK `core_store` (cascade delete) | Store view tej wersji. |

Ograniczenia:

- unikalne (`entity_type`, `entity_id`, `store_id`) — encja w danym store należy do najwyżej jednej grupy;
- unikalne (`group_id`, `store_id`) — w grupie najwyżej jedna wersja na store view.

Zasady:

- Model „wersja bazowa + przypisane tłumaczenia”: w adminie wybierasz stronę bazową i dla każdego store view przypisujesz jej odpowiednik.
- Strona CMS przypisana do wszystkich store view (`store_id = 0`) jest automatycznie swoją własną parą w każdym store (ten sam identyfikator, URL danego store) i nie wymaga grupy.
- Usunięcie strony CMS usuwa jej wpisy z grup (observer na `cms_page_delete_after`).
- Heurystyki parowania (ta sama data, zdjęcie, tytuł) są celowo pominięte: w praktyce dają zbyt dużo błędnych par.

### 5.5 Blok i cache

- Klasa `Bpf_Hreflang_Block_Head`, dodana w `layout/bpf_hreflang.xml` jako dziecko `head` w handle `default`; renderuje się przez `getChildHtml()` w szablonie `head`.
- Szablon `bpf/hreflang/head.phtml` wypisuje tagi, wartości URL escapowane (`escapeUrl`).
- Klucz cache: store ID, pełna nazwa akcji, `getCacheKey()` resolvera, flaga HTTPS.
- Tagi cache: tagi z `getCacheTags()` + `bpf_hreflang` + `config`. Zapis grupy tłumaczeń czyści tag `bpf_hreflang`.
- Gdy żaden resolver nie obsługuje strony, blok zwraca pusty string bez zapisu do cache.

### 5.6 Budowa URL-i

- Jedna usługa `Bpf_Hreflang_Model_Url` buduje wszystkie absolutne URL-e i jako jedyna zna tryb „bez kodu sklepu”.
- Dla store view z `bpf_hreflang/url/root_stores` usuwa segment `/<kod_store>/` z URL-a zbudowanego standardowo, gdy włączone jest `web/url/use_store`.
- URL produktu = URL kanoniczny produktu w danym store (bez ścieżki kategorii, zgodnie z `catalog/seo/product_canonical_tag`), z rewrite'a tego store.
- URL kategorii = URL kategorii z rewrite'a danego store.
- URL strony głównej = bazowy URL store (z uwzględnieniem trybu bez kodu sklepu).
- Zawsze bezpieczny (`_secure`) URL, jeśli store ma włączone HTTPS na froncie.

### 5.7 Admin

- Menu: CMS → Hreflang: grupy tłumaczeń.
- Grid: grupa, strona bazowa, liczba przypisanych store view, data modyfikacji.
- Formularz: wybór strony bazowej i po jednej stronie CMS dla każdego store view z ustawionym `locale_code`; walidacja ograniczeń z p. 5.4.
- ACL: `cms/bpf_hreflang` (zarządzanie grupami), `system/config/bpf_hreflang` (konfiguracja).

## 6. Reguły generowania tagów

### 6.1 Self-reference
Każda strona zawiera tag wskazujący na samą siebie z własnym kodem języka.

### 6.2 Zwrotność
Zbiór alternatyw jest liczony dla całej grupy wersji, więc każda wersja emituje ten sam zestaw tagów. Gdy wersja jest niedostępna w jednym store (np. produkt wyłączony), ten język znika ze wszystkich wersji, nie tylko z jednej strony.

### 6.3 Minimum
Gdy po filtrach zostaje mniej niż 2 wersje językowe, blok nie emituje żadnych tagów (także `x-default`).

### 6.4 Wykluczenia
Domyślna lista `excluded_actions`:

```
cms_index_noRoute
cms_index_defaultNoRoute
catalogsearch_*
checkout_*
customer_*
wishlist_*
sales_*
review_*
contacts_*
```

`cms_index_noRoute` jest wykluczona jawnie: strona 404 jest stroną CMS `no-route` i naiwny resolver CMS sparowałby ją z 404 w innych językach.

### 6.5 noindex
- Bieżąca strona z `noindex` w meta robots (wartość bloku `head`) nie emituje tagów.
- Store view, którego `design/head/default_robots` zawiera `NOINDEX`, jest wyłączony z kandydatów.

### 6.6 Zgodność z canonical i parametry
- URL w hreflang musi być równy canonical danej wersji (p. 5.6).
- Strony z parametrami zapytania (filtry warstwowe, sortowanie, paginacja) nie emitują tagów w 1.0.

### 6.7 x-default
Dodawany, gdy skonfigurowany store view `x_default_store` jest wśród dostępnych wersji strony; wskazuje URL tej wersji. Jeśli ta wersja jest niedostępna, `x-default` jest pomijany. Dla strony głównej przy trybie bez kodu sklepu `x-default` to zwykle `/`.

### 6.8 Przykład wyniku

Produkt dostępny w store `pl` (pod `/`), `en` i `de-AT`, `x-default` = `pl`:

```html
<link rel="alternate" hreflang="pl" href="https://example.com/kubek.html" />
<link rel="alternate" hreflang="en" href="https://example.com/en/mug.html" />
<link rel="alternate" hreflang="de-AT" href="https://example.com/at/becher.html" />
<link rel="alternate" hreflang="x-default" href="https://example.com/kubek.html" />
```

## 7. Struktura plików

```
app/etc/modules/Bpf_Hreflang.xml
app/code/community/Bpf/Hreflang/
    Block/Head.php
    Block/Adminhtml/Group/…            (grid + formularz)
    controllers/Adminhtml/Bpf/Hreflang/GroupController.php
    etc/config.xml
    etc/system.xml
    etc/adminhtml.xml
    Helper/Data.php
    Model/Builder.php
    Model/Url.php
    Model/Group.php, Model/Resource/Group…
    Model/Resolver/Interface.php
    Model/Resolver/Product.php, Category.php, Cms.php, Home.php
    Model/Observer.php
    Model/System/Config/Backend/LocaleCode.php
    sql/bpf_hreflang_setup/install-1.0.0.php
app/design/frontend/base/default/layout/bpf_hreflang.xml
app/design/frontend/base/default/template/bpf/hreflang/head.phtml
app/design/adminhtml/default/default/layout/bpf_hreflang.xml
app/locale/en_US/Bpf_Hreflang.csv
app/locale/pl_PL/Bpf_Hreflang.csv
```

Każdy nowy katalog lub plik poza `app/code/community/Bpf/Hreflang` musi zostać dopisany do `modman`.

## 8. Testy

### 8.1 Jednostkowe (PHPUnit)
- Walidacja i normalizacja kodów języka (p. 4.1), w tym kolizja kodów w grupie.
- `Bpf_Hreflang_Model_Url`: tryb z kodem i bez kodu sklepu, HTTPS.
- Builder: minimum 2 wersje, `x-default` obecny/nieobecny, zwrotność, wykluczenia z prefiksem `*`.

### 8.2 Integracyjne (OpenMage 20.x + sample data)
Każdy przypadek brzegowy jako osobny test (curl strony + parsowanie tagów):

1. Strona główna z domyślnym językiem pod `/` i pozostałymi pod `/en/` itd.: alternate i `x-default` wskazują dokładne adresy.
2. Strona 404: brak tagów.
3. Strona CMS w grupie tłumaczeń i strona przypisana do wszystkich store view.
4. Zwrotność: produkt wyłączony w jednym store znika ze wszystkich wersji.
5. Zgodność z canonical: href = `<link rel="canonical">` każdej wersji; strona z filtrami bez tagów.
6. Strona `noindex` i store z domyślnym `NOINDEX`.
7. Cache: dwie różne strony w tym samym store mają różne tagi; zapis produktu/kategorii/CMS unieważnia cache.
8. Ten sam język w kilku krajach (`en-GB` + `en`).

### 8.3 CI
GitHub Actions: lint PHP, PHPStan, PHPUnit na macierzy PHP 8.1/8.2/8.3.

## 9. Kryteria ukończenia 1.0

- [ ] Wszystkie pozycje z p. 2.1 zaimplementowane.
- [ ] Testy z sekcji 8 przechodzą w CI.
- [ ] README: problem, instalacja (Composer + `modman`), konfiguracja, zrzuty z admina, ograniczenia, roadmapa.
- [ ] `CHANGELOG.md`, plik `LICENSE`, tag `v1.0.0`.

## 10. Otwarte pytania

1. Paginacja kategorii: w 1.0 strony z parametrami nie mają tagów. Czy `?p=2` powinno emitować alternate do `?p=2` w innych językach?
2. `group_scope`: czy przypadek „osobne website per kraj, wspólne alternatywy” jest potrzebny w 1.0, czy wystarczy `website`?
3. Nazwa pakietu: `bpf/openmage_hreflang` czy `bpf/openmage-hreflang` (zmienić przed publikacją na Packagist)?
