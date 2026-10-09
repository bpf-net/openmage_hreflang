<?php

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Block_HeadTest extends TestCase
{
    private const TEMPLATE = __DIR__ . '/../../../app/design/frontend/base/default/template/bpf/hreflang/head.phtml';

    private const ALTERNATES = [
        'pl' => 'https://example.com/',
        'en' => 'https://example.com/en/',
        'x-default' => 'https://example.com/',
    ];

    /**
     * @return Bpf_Hreflang_Block_Head&MockObject
     */
    private function block(
        bool $enabled = true,
        string $robots = 'INDEX,FOLLOW',
        ?string $action = 'cms_index_index',
        bool $hasResolver = true,
        ?Bpf_Hreflang_Model_Builder $builder = null,
    ): Bpf_Hreflang_Block_Head {
        $helper = $this->createMock(Bpf_Hreflang_Helper_Data::class);
        $helper->method('isEnabled')->willReturn($enabled);

        if ($builder === null) {
            $builder = $this->createMock(Bpf_Hreflang_Model_Builder::class);
            $builder->method('getResolverForRequest')->willReturn(
                $hasResolver ? $this->createMock(Bpf_Hreflang_Model_Resolver_Interface::class) : null,
            );
            $builder->method('getAlternates')->willReturn(self::ALTERNATES);
        }

        $block = $this->getMockBuilder(Bpf_Hreflang_Block_Head::class)
            ->onlyMethods(['_getHelper', '_getBuilder', '_getStore', '_getRobots', '_getFullActionName', '_getRequestObject'])
            ->getMock();
        $block->method('_getHelper')->willReturn($helper);
        $block->method('_getBuilder')->willReturn($builder);
        $block->method('_getStore')->willReturn(new Mage_Core_Model_Store(['store_id' => 1]));
        $block->method('_getRobots')->willReturn($robots);
        $block->method('_getFullActionName')->willReturn($action);
        $block->method('_getRequestObject')->willReturn($this->createMock(Mage_Core_Controller_Request_Http::class));

        return $block;
    }

    public function testAlternatesFromBuilder(): void
    {
        $this->assertSame(self::ALTERNATES, $this->block()->getAlternates());
    }

    /**
     * @dataProvider noTagsProvider
     * @param array<string, mixed> $args
     */
    public function testNoAlternates(array $args): void
    {
        $this->assertSame([], $this->block(...$args)->getAlternates());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public function noTagsProvider(): array
    {
        return [
            'module disabled in store' => [['enabled' => false]],
            'noindex page' => [['robots' => 'NOINDEX,FOLLOW']],
            'noindex lowercase' => [['robots' => 'noindex,nofollow']],
            'no controller action' => [['action' => null]],
            'excluded or unhandled page' => [['hasResolver' => false]],
        ];
    }

    public function testPassesActionAndBuildsOnlyOnce(): void
    {
        $resolver = $this->createMock(Bpf_Hreflang_Model_Resolver_Interface::class);
        $builder = $this->createMock(Bpf_Hreflang_Model_Builder::class);
        $builder->expects($this->once())->method('getResolverForRequest')
            ->with('catalog_product_view')
            ->willReturn($resolver);
        $builder->expects($this->once())->method('getAlternates')
            ->with($resolver)
            ->willReturn(self::ALTERNATES);
        $block = $this->block(action: 'catalog_product_view', builder: $builder);

        $block->getAlternates();
        $block->getAlternates();
    }

    /**
     * @param array<string, string> $alternates
     */
    private function renderTemplate(array $alternates): string
    {
        $view = new class ($alternates) {
            /** @param array<string, string> $alternates */
            public function __construct(private array $alternates)
            {
            }

            /** @return array<string, string> */
            public function getAlternates(): array
            {
                return $this->alternates;
            }

            public function escapeHtml(string $value): string
            {
                return htmlspecialchars($value, ENT_COMPAT, 'UTF-8', false);
            }

            public function escapeUrl(string $value): string
            {
                return htmlspecialchars($value, ENT_COMPAT, 'UTF-8', false);
            }

            public function render(string $template): string
            {
                ob_start();
                include $template;

                return (string) ob_get_clean();
            }
        };

        return $view->render(self::TEMPLATE);
    }

    public function testTemplateRendersOneLinkPerAlternate(): void
    {
        $html = $this->renderTemplate([
            'pl' => 'https://example.com/kubek.html',
            'en' => 'https://example.com/en/mug.html?a=1&b=2',
            'x-default' => 'https://example.com/kubek.html',
        ]);

        $this->assertSame(
            '<link rel="alternate" hreflang="pl" href="https://example.com/kubek.html" />' . "\n"
            . '<link rel="alternate" hreflang="en" href="https://example.com/en/mug.html?a=1&amp;b=2" />' . "\n"
            . '<link rel="alternate" hreflang="x-default" href="https://example.com/kubek.html" />' . "\n",
            $html,
        );
    }

    public function testTemplateEscapesUrls(): void
    {
        $html = $this->renderTemplate(['pl' => 'https://example.com/"><script>']);

        $this->assertStringNotContainsString('"><script>', $html);
    }

    public function testBlockIsAddedToHeadOnEveryPage(): void
    {
        $layout = simplexml_load_file(__DIR__ . '/../../../app/design/frontend/base/default/layout/bpf_hreflang.xml');
        $block = $layout->xpath('default/reference[@name="head"]/block')[0];

        $this->assertSame('bpf_hreflang/head', (string) $block['type']);
        $this->assertSame('bpf/hreflang/head.phtml', (string) $block['template']);
        $this->assertFileExists(self::TEMPLATE);
    }
}
