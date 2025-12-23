<?php declare(strict_types=1);

namespace Internet\Graph\Tests;

use Internet\Graph\Authenticator;
use PHPUnit\Framework\TestCase;

class AuthenticatorTest extends TestCase {
    private Authenticator $authenticator;
    private array $validBearerTokens;
    private array $validUsers;

    protected function setUp(): void {
        parent::setUp();

        $this->validBearerTokens = [
            'token123' => 'automation_user_1',
            'secrettoken456' => 'automation_user_2',
        ];

        $this->validUsers = [
            'alice' => password_hash('password123', PASSWORD_DEFAULT),
            'bob' => password_hash('securepass', PASSWORD_DEFAULT),
        ];

        $this->authenticator = new Authenticator($this->validBearerTokens, $this->validUsers);
    }

    protected function tearDown(): void {
        parent::tearDown();

        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    public function testConstructorSetsBearerTokens(): void {
        $tokens = ['test_token' => 'test_user'];
        $users = ['user' => 'hash'];

        $auth = new Authenticator($tokens, $users);

        $this->assertInstanceOf(Authenticator::class, $auth);
    }

    public function testAuthenticateWithValidBearerToken(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';

        $result = $this->authenticator->authenticate();

        $this->assertEquals('automation_user_1', $result);
    }

    public function testAuthenticateWithValidBearerTokenCaseInsensitive(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'bearer token123';

        $result = $this->authenticator->authenticate();

        $this->assertEquals('automation_user_1', $result);
    }

    public function testAuthenticateWithValidBearerTokenMixedCase(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'BeArEr token123';

        $result = $this->authenticator->authenticate();

        $this->assertEquals('automation_user_1', $result);
    }

    public function testAuthenticateWithInvalidBearerToken(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid_token';

        $result = $this->authenticator->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithEmptyBearerToken(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ';

        $result = $this->authenticator->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithDifferentValidBearerToken(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secrettoken456';

        $result = $this->authenticator->authenticate();

        $this->assertEquals('automation_user_2', $result);
    }

    public function testAuthenticateWithValidBasicAuth(): void {
        $credentials = base64_encode('alice:password123');
        $_SERVER['HTTP_AUTHORIZATION'] = "Basic $credentials";

        $result = $this->authenticator->authenticate();

        $this->assertEquals('alice', $result);
    }

    public function testAuthenticateWithValidBasicAuthCaseInsensitive(): void {
        $credentials = base64_encode('alice:password123');
        $_SERVER['HTTP_AUTHORIZATION'] = "basic $credentials";

        $result = $this->authenticator->authenticate();

        $this->assertEquals('alice', $result);
    }

    public function testAuthenticateWithValidBasicAuthMixedCase(): void {
        $credentials = base64_encode('alice:password123');
        $_SERVER['HTTP_AUTHORIZATION'] = "BaSiC $credentials";

        $result = $this->authenticator->authenticate();

        $this->assertEquals('alice', $result);
    }

    public function testAuthenticateWithDifferentValidBasicAuth(): void {
        $credentials = base64_encode('bob:securepass');
        $_SERVER['HTTP_AUTHORIZATION'] = "Basic $credentials";

        $result = $this->authenticator->authenticate();

        $this->assertEquals('bob', $result);
    }

    public function testAuthenticateWithInvalidBasicAuthPassword(): void {
        $credentials = base64_encode('alice:wrongpassword');
        $_SERVER['HTTP_AUTHORIZATION'] = "Basic $credentials";

        $result = $this->authenticator->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithInvalidBasicAuthUsername(): void {
        $credentials = base64_encode('nonexistent:password123');
        $_SERVER['HTTP_AUTHORIZATION'] = "Basic $credentials";

        $result = $this->authenticator->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithMalformedBasicAuthCredentials(): void {
        $credentials = base64_encode('alice:pass');
        $_SERVER['HTTP_AUTHORIZATION'] = "Basic $credentials";

        $result = $this->authenticator->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithBasicAuthPasswordContainingColon(): void {
        $passwordWithColon = 'pass:word:123';
        $users = [
            'testuser' => password_hash($passwordWithColon, PASSWORD_DEFAULT),
        ];
        $auth = new Authenticator([], $users);

        $credentials = base64_encode("testuser:$passwordWithColon");
        $_SERVER['HTTP_AUTHORIZATION'] = "Basic $credentials";

        $result = $auth->authenticate();

        $this->assertEquals('testuser', $result);
    }

    public function testAuthenticateWithNoAuthorizationHeader(): void {
        $result = $this->authenticator->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithRedirectAuthorizationHeader(): void {
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer token123';

        $result = $this->authenticator->authenticate();

        $this->assertEquals('automation_user_1', $result);
    }

    public function testAuthenticatePrefersHttpAuthorizationOverRedirect(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer secrettoken456';

        $result = $this->authenticator->authenticate();

        $this->assertEquals('automation_user_1', $result);
    }

    public function testAuthenticateWithInvalidAuthorizationFormat(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'InvalidFormat token123';

        $result = $this->authenticator->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithEmptyAuthorizationHeader(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = '';

        $result = $this->authenticator->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithOnlyAuthType(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer';

        $result = $this->authenticator->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithBearerTokenContainingSpaces(): void {
        $tokens = ['token with spaces' => 'user1'];
        $auth = new Authenticator($tokens, []);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token with spaces';

        $result = $auth->authenticate();

        $this->assertEquals('user1', $result);
    }

    public function testAuthenticateWithEmptyBearerTokensArray(): void {
        $auth = new Authenticator([], $this->validUsers);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';

        $result = $auth->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithEmptyUsersArray(): void {
        $auth = new Authenticator($this->validBearerTokens, []);
        $credentials = base64_encode('alice:password123');
        $_SERVER['HTTP_AUTHORIZATION'] = "Basic $credentials";

        $result = $auth->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithBothArraysEmpty(): void {
        $auth = new Authenticator([], []);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';

        $result = $auth->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateReturnsNullForEmptyCredentials(): void {
        $credentials = base64_encode(':');
        $_SERVER['HTTP_AUTHORIZATION'] = "Basic $credentials";

        $result = $this->authenticator->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithBasicAuthEmptyUsername(): void {
        $credentials = base64_encode(':password123');
        $_SERVER['HTTP_AUTHORIZATION'] = "Basic $credentials";

        $result = $this->authenticator->authenticate();

        $this->assertNull($result);
    }

    public function testAuthenticateWithBasicAuthEmptyPassword(): void {
        $users = [
            'alice' => password_hash('', PASSWORD_DEFAULT),
        ];
        $auth = new Authenticator([], $users);

        $credentials = base64_encode('alice:');
        $_SERVER['HTTP_AUTHORIZATION'] = "Basic $credentials";

        $result = $auth->authenticate();

        $this->assertEquals('alice', $result);
    }

    public function testAuthenticateWithWhitespaceOnlyAuthHeader(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = '   ';

        $result = $this->authenticator->authenticate();

        $this->assertNull($result);
    }

    public function testMultipleAuthenticationAttempts(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';
        $result1 = $this->authenticator->authenticate();
        $this->assertEquals('automation_user_1', $result1);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secrettoken456';
        $result2 = $this->authenticator->authenticate();
        $this->assertEquals('automation_user_2', $result2);

        $credentials = base64_encode('alice:password123');
        $_SERVER['HTTP_AUTHORIZATION'] = "Basic $credentials";
        $result3 = $this->authenticator->authenticate();
        $this->assertEquals('alice', $result3);
    }

    public function testAuthenticateWithSpecialCharactersInBearerToken(): void {
        $tokens = ['token!@#$%^&*()_+-=[]{}|;:,.<>?' => 'special_user'];
        $auth = new Authenticator($tokens, []);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token!@#$%^&*()_+-=[]{}|;:,.<>?';

        $result = $auth->authenticate();

        $this->assertEquals('special_user', $result);
    }

    public function testAuthenticateWithUnicodeCharactersInBasicAuth(): void {
        $users = [
            'josé' => password_hash('contraseña123', PASSWORD_DEFAULT),
        ];
        $auth = new Authenticator([], $users);

        $credentials = base64_encode('josé:contraseña123');
        $_SERVER['HTTP_AUTHORIZATION'] = "Basic $credentials";

        $result = $auth->authenticate();

        $this->assertEquals('josé', $result);
    }
}
