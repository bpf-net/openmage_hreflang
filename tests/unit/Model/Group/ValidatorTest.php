<?php

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_Group_ValidatorTest extends TestCase
{
    private const STORES = [1 => 'English', 2 => 'French', 3 => 'German'];

    /**
     * @param array<int, array{title: string, store_ids: list<int>}> $pages
     * @param array<int, int> $pageGroups page ID => group ID it already belongs to
     * @return Bpf_Hreflang_Model_Group_Validator&MockObject
     */
    private function validator(array $pages, array $pageGroups = []): Bpf_Hreflang_Model_Group_Validator
    {
        $helper = $this->getMockBuilder(Bpf_Hreflang_Helper_Data::class)->onlyMethods(['__'])->getMock();
        $helper->method('__')->willReturnCallback(static fn (string $text, ...$args): string => vsprintf($text, $args));

        $validator = $this->getMockBuilder(Bpf_Hreflang_Model_Group_Validator::class)
            ->onlyMethods(['_getPageInfo', '_getEntityGroupId', '_getStoreName', '_getHelper'])
            ->getMock();
        $validator->method('_getPageInfo')->willReturnCallback(static fn (int $id) => $pages[$id] ?? null);
        $validator->method('_getEntityGroupId')->willReturnCallback(static fn (int $id) => $pageGroups[$id] ?? null);
        $validator->method('_getStoreName')->willReturnCallback(static fn (int $id) => self::STORES[$id] ?? null);
        $validator->method('_getHelper')->willReturn($helper);

        return $validator;
    }

    /**
     * @param array<int, int> $items
     */
    private function group(array $items, ?int $groupId = null, ?int $basePageId = null): Bpf_Hreflang_Model_Group
    {
        $group = new Bpf_Hreflang_Model_Group(['group_id' => $groupId, 'base_entity_id' => $basePageId]);

        return $group->setItems($items);
    }

    private function pages(): array
    {
        return [
            3 => ['title' => 'About', 'store_ids' => [1]],
            4 => ['title' => 'A propos', 'store_ids' => [2]],
            5 => ['title' => 'Cookies', 'store_ids' => [0]],
            6 => ['title' => 'Ueber uns', 'store_ids' => [3]],
        ];
    }

    public function testValidGroup(): void
    {
        $errors = $this->validator($this->pages())->validate($this->group([1 => 3, 2 => 4, 3 => 5], basePageId: 3));

        $this->assertSame([], $errors);
    }

    public function testNeedsAtLeastTwoVersions(): void
    {
        $errors = $this->validator($this->pages())->validate($this->group([1 => 3]));

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('at least two store views', $errors[0]);
    }

    public function testPageMustBeShownInItsStoreView(): void
    {
        $errors = $this->validator($this->pages())->validate($this->group([1 => 3, 2 => 6]));

        $this->assertSame(['Page "Ueber uns" is not shown in store view "French".'], $errors);
    }

    public function testPageCanBeInOneGroupOnly(): void
    {
        $errors = $this->validator($this->pages(), [4 => 9])->validate($this->group([1 => 3, 2 => 4], groupId: 2));

        $this->assertSame(['Page "A propos" already belongs to translation group #9; a page can be in one group only.'], $errors);
    }

    public function testPagesOfTheSameGroupDoNotConflict(): void
    {
        $errors = $this->validator($this->pages(), [3 => 2, 4 => 2])->validate($this->group([1 => 3, 2 => 4], groupId: 2));

        $this->assertSame([], $errors);
    }

    public function testMissingPagesAndStoresAreReported(): void
    {
        $errors = $this->validator($this->pages())->validate($this->group([1 => 3, 2 => 99, 7 => 4], basePageId: 98));

        $this->assertSame([
            'The base page (ID 98) no longer exists.',
            'The page chosen for store view "French" (ID 99) no longer exists.',
            'Store view ID 7 does not exist.',
        ], $errors);
    }

    public function testSharedPageMayBeTheVersionOfSeveralStoreViews(): void
    {
        $errors = $this->validator($this->pages())->validate($this->group([1 => 5, 2 => 5]));

        $this->assertSame([], $errors);
    }
}
