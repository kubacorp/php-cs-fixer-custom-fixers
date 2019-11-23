<?php

declare(strict_types = 1);

namespace Tests\Fixer;

/**
 * @internal
 *
 * @covers \PhpCsFixerCustomFixers\Fixer\NoUselessDoctrineRepositoryCommentFixer
 */
final class NoUselessDoctrineRepositoryCommentFixerTest extends AbstractFixerTestCase
{
    public function testIsRisky(): void
    {
        static::assertFalse($this->fixer->isRisky());
    }

    /**
     * @dataProvider provideFixCases
     */
    public function testFix(string $expected, ?string $input = null): void
    {
        $this->doTest($expected, $input);
    }

    public static function provideFixCases(): iterable
    {
        yield [
            '<?php
class FooRepository extends EntityRepository {}
',
            '<?php
/**
 * FooRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class FooRepository extends EntityRepository {}
',
        ];

        yield [
            '<?php
/**
 * FooRepository
 *
 * This class was not generated by the Doctrine ORM.
 */
class FooRepository extends EntityRepository {}
',
        ];

        yield [
            '<?php
class FooRepository extends EntityRepository {
    /**
     * @return array
     */
     public function foo() {}
}
',
            '<?php
/**
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class FooRepository extends EntityRepository {
    /**
     * @return array
     */
     public function foo() {}
}
',
        ];
    }
}
