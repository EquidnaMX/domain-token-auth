<?php

namespace Equidna\DomainTokenAuth\Tests\Feature;

use Composer\InstalledVersions;
use Equidna\DomainTokenAuth\Tests\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

class FrameworkCompatibilityTest extends TestCase
{
    public function test_supported_framework_stack_boots_and_returns_a_symfony_response(): void
    {
        $laravelMajor = (int) InstalledVersions::getVersion('laravel/framework');
        $symfonyMajor = (int) InstalledVersions::getVersion('symfony/http-foundation');

        self::assertContains($laravelMajor, [12, 13]);
        self::assertContains($symfonyMajor, [7, 8]);

        $response = $this->getJson('/secured');

        self::assertInstanceOf(JsonResponse::class, $response->baseResponse);
        $response->assertUnauthorized();
    }
}
