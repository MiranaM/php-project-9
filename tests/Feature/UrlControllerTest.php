<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;

final class UrlControllerTest extends TestCase
{
    public function testGetUrlsPage(): void
    {
        $app = require dirname(__DIR__, 2) . '/app/config/bootstrap.php';

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/urls');
        $response = $app->handle($request);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertEquals(200, $response->getStatusCode());
        self::assertStringContainsString('Сайты', (string) $response->getBody());
    }
}
