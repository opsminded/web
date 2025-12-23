<?php declare(strict_types=1);

namespace Internet\Graph\Tests;

use Internet\Graph\AuditContext;
use PHPUnit\Framework\TestCase;

class AuditContextTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        AuditContext::clear();
    }

    protected function tearDown(): void {
        parent::tearDown();
        AuditContext::clear();
    }

    public function testSetAndGetBoth(): void {
        AuditContext::set('user123', '192.168.1.1');

        $this->assertEquals('user123', AuditContext::get_user());
        $this->assertEquals('192.168.1.1', AuditContext::get_ip());
    }

    public function testSetUserOnly(): void {
        AuditContext::set_user('user456');

        $this->assertEquals('user456', AuditContext::get_user());
        $this->assertNull(AuditContext::get_ip());
    }

    public function testSetIpOnly(): void {
        AuditContext::set_ip('10.0.0.1');

        $this->assertNull(AuditContext::get_user());
        $this->assertEquals('10.0.0.1', AuditContext::get_ip());
    }

    public function testGetReturnsArray(): void {
        AuditContext::set('user789', '172.16.0.1');

        $context = AuditContext::get();

        $this->assertIsArray($context);
        $this->assertArrayHasKey('user_id', $context);
        $this->assertArrayHasKey('ip_address', $context);
        $this->assertEquals('user789', $context['user_id']);
        $this->assertEquals('172.16.0.1', $context['ip_address']);
    }

    public function testClear(): void {
        AuditContext::set('user999', '8.8.8.8');
        AuditContext::clear();

        $this->assertNull(AuditContext::get_user());
        $this->assertNull(AuditContext::get_ip());
    }

    public function testSetNullValues(): void {
        AuditContext::set('user', '1.1.1.1');
        AuditContext::set(null, null);

        $this->assertNull(AuditContext::get_user());
        $this->assertNull(AuditContext::get_ip());
    }

    public function testInitFromRequestWithRemoteAddr(): void {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        AuditContext::init_from_request('user001');

        $this->assertEquals('user001', AuditContext::get_user());
        $this->assertEquals('203.0.113.1', AuditContext::get_ip());

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testInitFromRequestWithXForwardedFor(): void {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 203.0.113.1';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        AuditContext::init_from_request('user002');

        $this->assertEquals('user002', AuditContext::get_user());
        $this->assertEquals('198.51.100.1', AuditContext::get_ip());

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
    }

    public function testInitFromRequestWithXRealIp(): void {
        $_SERVER['HTTP_X_REAL_IP'] = '198.51.100.2';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        AuditContext::init_from_request('user003');

        $this->assertEquals('user003', AuditContext::get_user());
        $this->assertEquals('198.51.100.2', AuditContext::get_ip());

        unset($_SERVER['HTTP_X_REAL_IP'], $_SERVER['REMOTE_ADDR']);
    }

    public function testInitFromRequestWithoutIp(): void {
        AuditContext::init_from_request('user004');

        $this->assertEquals('user004', AuditContext::get_user());
        $this->assertNull(AuditContext::get_ip());
    }

    public function testInitFromRequestNullUser(): void {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';

        AuditContext::init_from_request(null);

        $this->assertNull(AuditContext::get_user());
        $this->assertEquals('203.0.113.5', AuditContext::get_ip());

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testMultipleUpdates(): void {
        AuditContext::set('user1', '1.1.1.1');
        $this->assertEquals('user1', AuditContext::get_user());

        AuditContext::set_user('user2');
        $this->assertEquals('user2', AuditContext::get_user());
        $this->assertEquals('1.1.1.1', AuditContext::get_ip());

        AuditContext::set_ip('2.2.2.2');
        $this->assertEquals('user2', AuditContext::get_user());
        $this->assertEquals('2.2.2.2', AuditContext::get_ip());
    }
}
