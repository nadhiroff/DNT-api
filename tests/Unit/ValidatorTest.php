<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validation\ValidationException;
use App\Core\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testReturnsOnlyDeclaredFields(): void
    {
        $data = Validator::validate(
            ['email' => '  Ada@Example.COM ', 'role' => 'superadmin', 'age' => '42'],
            ['email' => 'required|email', 'age' => 'integer|min:18'],
        );
        self::assertSame(['email' => 'ada@example.com', 'age' => 42], $data);
    }

    public function testRequired(): void
    {
        $v = new Validator(['name' => '   '], ['name' => 'required|string']);
        self::assertFalse($v->passes());
        self::assertArrayHasKey('name', $v->errors());
    }

    public function testEmailRejectsHeaderInjection(): void
    {
        self::assertFalse(Validator::isEmail("a@b.co\r\nBcc: x@y.z"));
        self::assertFalse(Validator::isEmail('pas-un-email'));
        self::assertTrue(Validator::isEmail('a.b+c@example.org'));
    }

    public function testStringLengthUsesMultibyte(): void
    {
        self::assertTrue((new Validator(['n' => 'éééé'], ['n' => 'string|max:4']))->passes());
        self::assertFalse((new Validator(['n' => 'ééééé'], ['n' => 'string|max:4']))->passes());
    }

    public function testNumericBounds(): void
    {
        self::assertFalse((new Validator(['n' => '5'], ['n' => 'integer|min:10']))->passes());
        self::assertTrue((new Validator(['n' => '15'], ['n' => 'integer|between:10,20']))->passes());
    }

    public function testInRegexSameAndBoolean(): void
    {
        $data = Validator::validate(
            ['type' => 'public', 'slug' => 'abc_1', 'p' => 'secret-pass', 'p2' => 'secret-pass', 'flag' => 'on'],
            ['type' => 'in:public,private', 'slug' => ['regex:/^[a-z][a-z0-9_]*$/'], 'p' => 'required', 'p2' => 'same:p', 'flag' => 'boolean'],
        );
        self::assertTrue($data['flag']);
        self::assertFalse((new Validator(['type' => 'secret'], ['type' => 'in:public,private']))->passes());
    }

    public function testMissingCheckboxIsFalse(): void
    {
        self::assertSame(['remember' => false], Validator::validate([], ['remember' => 'boolean']));
    }

    public function testNullable(): void
    {
        self::assertSame(['bio' => null], Validator::validate(['bio' => ''], ['bio' => 'nullable|string|max:10']));
    }

    public function testExceptionKeepsOldInputWithoutPasswords(): void
    {
        try {
            Validator::validate(['email' => 'bad', 'password' => 'hunter2hunter2'], ['email' => 'required|email', 'password' => 'required']);
            self::fail('ValidationException attendue');
        } catch (ValidationException $e) {
            self::assertSame(['email' => 'bad'], $e->old());
            self::assertStringContainsString('e-mail', $e->errors()['email'][0]);
        }
    }

    public function testDateAndUuid(): void
    {
        self::assertTrue((new Validator(['d' => '2026-02-28'], ['d' => 'date']))->passes());
        self::assertFalse((new Validator(['d' => '2026-02-30'], ['d' => 'date']))->passes());
        self::assertTrue((new Validator(['u' => '0192f2a4-7c1e-7b3a-9f00-1a2b3c4d5e6f'], ['u' => 'uuid']))->passes());
    }

    public function testUnknownRuleIsProgrammingError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Validator(['a' => 'x'], ['a' => 'frobnicate']))->passes();
    }
}
