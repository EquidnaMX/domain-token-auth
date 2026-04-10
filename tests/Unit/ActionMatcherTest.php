<?php

namespace Equidna\DomainTokenAuth\Tests\Unit;

use Equidna\DomainTokenAuth\Support\ActionMatcher;
use PHPUnit\Framework\TestCase;

class ActionMatcherTest extends TestCase
{
    public function test_allows_exact_action(): void
    {
        self::assertTrue(ActionMatcher::allows(['users.read'], 'users.read'));
    }

    public function test_allows_global_wildcard(): void
    {
        self::assertTrue(ActionMatcher::allows(['*'], 'users.delete'));
    }

    public function test_allows_domain_wildcard(): void
    {
        self::assertTrue(ActionMatcher::allows(['users.*'], 'users.write'));
    }

    public function test_denies_ungranted_action(): void
    {
        self::assertFalse(ActionMatcher::allows(['users.read'], 'apps.read'));
    }

    public function test_parse_csv_normalizes_values(): void
    {
        $parsed = ActionMatcher::parseCsv(' USERS.Read , users.read, users.write ');

        self::assertSame(['users.read', 'users.write'], $parsed);
    }
}
