<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Auth\LoginAction;
use Internet\Graph\Action\Auth\LogoutAction;
use Internet\Graph\Action\Auth\GetAuthStatusAction;
use Internet\Graph\Action\Auth\GetCsrfTokenAction;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

/**
 * Tests for Auth Actions
 *
 * Note: These tests cannot fully mock SessionManager and Config static methods,
 * so they test the basic request/response structure
 */
class AuthActionsTest extends TestCase
{
    // LoginAction Tests
    public function test_login_missing_credentials(): void
    {
        $action = new LoginAction();
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(400, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertStringContainsString('Missing username or password', $data['error']);
    }

    // LogoutAction Tests
    public function test_logout_returns_success(): void
    {
        $action = new LogoutAction();
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, []);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Logout successful', $data['message']);
    }

    // GetAuthStatusAction Tests
    public function test_get_auth_status_returns_structure(): void
    {
        $action = new GetAuthStatusAction();
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, []);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('authenticated', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('csrf_token', $data);
    }

    // GetCsrfTokenAction Tests - Note: Will fail when not authenticated
    // This is expected behavior, just testing structure
    public function test_get_csrf_token_structure(): void
    {
        $action = new GetCsrfTokenAction();
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, []);

        // Status will be 401 when not authenticated (expected)
        $this->assertContains($result->getStatusCode(), [200, 401]);
        $data = json_decode((string)$result->getBody(), true);
        $this->assertNotNull($data);
    }
}
