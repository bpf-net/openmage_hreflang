<?php

use PHPUnit\Framework\TestCase;

/**
 * Every string the module translates must be in each locale CSV, with matching placeholders.
 */
class Bpf_Hreflang_Etc_TranslationsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';
    private const MODULE = self::ROOT . '/app/code/community/Bpf/Hreflang';
    private const LOCALES = ['en_US', 'pl_PL'];

    /**
     * Strings passed to $helper->__() / $this->__() of this module, plus translatable XML labels.
     * Calls on other modules' helpers (e.g. Mage::helper('adminhtml')) are left out.
     *
     * @return list<string>
     */
    private function moduleStrings(): array
    {
        $strings = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::MODULE));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            preg_match_all("/(?<!helper\\('adminhtml'\\)->)__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $source, $matches);
            foreach ($matches[1] as $string) {
                $strings[] = stripslashes($string);
            }
        }

        foreach (['system.xml', 'adminhtml.xml'] as $xmlFile) {
            $xml = simplexml_load_file(self::MODULE . '/etc/' . $xmlFile);
            foreach ($xml->xpath('//*[@translate]') as $element) {
                foreach (explode(' ', (string) $element['translate']) as $child) {
                    if (isset($element->{$child})) {
                        $strings[] = trim((string) $element->{$child});
                    }
                }
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @return array<string, string>
     */
    private function csv(string $locale): array
    {
        $rows = [];
        $handle = fopen(self::ROOT . "/app/locale/{$locale}/Bpf_Hreflang.csv", 'r');
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $this->assertCount(2, $row, implode(',', $row));
            $rows[$row[0]] = $row[1];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return array<string, array{string}>
     */
    public function localeProvider(): array
    {
        return array_combine(self::LOCALES, array_map(static fn ($l) => [$l], self::LOCALES));
    }

    /**
     * @dataProvider localeProvider
     */
    public function testEveryStringIsTranslated(string $locale): void
    {
        $missing = array_diff($this->moduleStrings(), array_keys($this->csv($locale)));

        $this->assertSame([], array_values($missing), "Missing in {$locale}/Bpf_Hreflang.csv");
    }

    /**
     * @dataProvider localeProvider
     */
    public function testTranslationsKeepPlaceholders(string $locale): void
    {
        foreach ($this->csv($locale) as $source => $translation) {
            $this->assertSame(substr_count($source, '%s'), substr_count($translation, '%s'), $source);
            $this->assertNotSame('', $translation, $source);
        }
    }

    public function testNoUnusedTranslations(): void
    {
        $unused = array_diff(array_keys($this->csv('en_US')), $this->moduleStrings());

        $this->assertSame([], array_values($unused));
    }

    public function testTranslationsAreRegisteredAndDeployed(): void
    {
        $config = simplexml_load_file(self::MODULE . '/etc/config.xml');
        $modman = (string) file_get_contents(self::ROOT . '/modman');

        foreach (['frontend', 'adminhtml'] as $area) {
            $this->assertSame('Bpf_Hreflang.csv', (string) $config->{$area}->translate->modules->Bpf_Hreflang->files->default, $area);
        }
        foreach (self::LOCALES as $locale) {
            $this->assertStringContainsString("app/locale/{$locale}/Bpf_Hreflang.csv", $modman);
        }
    }
}
