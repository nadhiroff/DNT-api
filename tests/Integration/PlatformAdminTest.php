<?php

declare(strict_types=1);

namespace Tests\Integration;

/**
 * Invitations, journal des connexions, utilisateurs et métriques du superadmin.
 */
final class PlatformAdminTest extends WebTestCase
{
    public function testInvitationLifecycle(): void
    {
        $this->loginSuperadmin();
        self::assertSame('/admin/invitations', $this->send('POST', '/admin/invitations', ['email' => 'ami@example.com', 'message' => 'Viens tester !'])->header('location'));
        $mail = $this->lastMail();
        self::assertSame('ami@example.com', $mail['to']);
        self::assertStringContainsString('Viens tester', $mail['html']);
        $token = self::extract('#/invitation/([a-f0-9]{64})#', $mail['html'], 1);
        self::assertSame('pending', self::db()->scalar('SELECT status FROM users WHERE email = ?', ['ami@example.com']));

        // Double invitation refusée.
        $this->send('POST', '/admin/invitations', ['email' => 'ami@example.com']);
        self::assertSame(1, (int) self::db()->scalar('SELECT COUNT(*) FROM invitations WHERE email = ?', ['ami@example.com']));

        // Avant acceptation : impossible de se connecter.
        $this->send('POST', '/logout');
        $this->send('POST', '/login', ['email' => 'ami@example.com', 'password' => 'n-importe-quoi']);
        self::assertStringNotContainsString('Bienvenue', $this->send('GET', '/login')->body());

        // Acceptation.
        self::assertSame(200, $this->send('GET', '/invitation/' . $token)->status());
        $accepted = $this->send('POST', '/invitation/' . $token, ['name' => 'Ami Test', 'password' => 'Lune-Rousse-Marée-77', 'password_confirmation' => 'Lune-Rousse-Marée-77', 'terms' => '1']);
        self::assertSame('/console', $accepted->header('location'));
        self::assertSame(200, $this->send('GET', '/console')->status());
        self::assertSame('active', self::db()->scalar('SELECT status FROM users WHERE email = ?', ['ami@example.com']));
        self::assertSame(410, $this->send('GET', '/invitation/' . $token)->status(), 'lien à usage unique');

        $this->send('POST', '/logout');
        self::assertSame('/console', $this->send('POST', '/login', ['email' => 'ami@example.com', 'password' => 'Lune-Rousse-Marée-77'])->header('location'));
    }

    public function testPendingAccountShowsInvitationMessageAndRevokeDeletesIt(): void
    {
        $this->loginSuperadmin();
        $this->send('POST', '/admin/invitations', ['email' => 'revoque@example.com']);
        $id = (int) self::db()->scalar('SELECT id FROM invitations WHERE email = ?', ['revoque@example.com']);
        $this->send('POST', '/admin/invitations/' . $id . '/revoke');
        self::assertNull(self::db()->scalar('SELECT 1 FROM users WHERE email = ?', ['revoque@example.com']));
        self::assertSame('revoked', self::db()->scalar('SELECT status FROM invitations WHERE id = ?', [$id]));
    }

    public function testLoginEventsAreRecordedWithDevice(): void
    {
        $this->createUser('journal@example.com');
        $this->send('POST', '/login', ['email' => 'journal@example.com', 'password' => 'faux-mot-de-passe'], [], 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Version/17.0 Mobile/15E148 Safari/604.1');
        $this->login('journal@example.com');
        $rows = self::db()->select("SELECT event, browser, os, device FROM login_events WHERE email = 'journal@example.com' ORDER BY id");
        self::assertSame('login_failed', $rows[0]['event']);
        self::assertSame('mobile', $rows[0]['device']);
        self::assertSame('iOS (iPhone)', $rows[0]['os']);
        self::assertSame('login', $rows[1]['event']);
        self::assertSame('desktop', $rows[1]['device']);
        self::assertStringStartsWith('Chrome', (string) $rows[1]['browser']);

        $this->send('POST', '/logout');
        $this->loginSuperadmin();
        $page = $this->send('GET', '/admin/logins')->body();
        self::assertStringContainsString('journal@example.com', $page);
        self::assertStringContainsString('Réseau local', $page, 'IP de documentation traitée comme non routable');
        $feed = json_decode($this->send('GET', '/admin/logins/feed?event=&q=&after=0')->body(), true);
        self::assertIsArray($feed);
        self::assertNotEmpty($feed['events']);
    }

    public function testAdminPagesAndMetrics(): void
    {
        $this->createUser('cible@example.com');
        $this->loginSuperadmin();
        foreach (['/admin', '/admin/users', '/admin/projects', '/admin/invitations', '/admin/logins', '/admin/testimonials', '/admin/community'] as $path) {
            $response = $this->send('GET', $path);
            self::assertSame(200, $response->status(), $path);
            self::assertStringContainsString('aria-current="page"', $response->body(), 'entrée active surlignée : ' . $path);
        }
        $metrics = json_decode($this->send('GET', '/admin/metrics')->body(), true);
        self::assertIsArray($metrics);
        foreach (['cpu_percent', 'ram_percent', 'disk_percent', 'cores'] as $key) {
            self::assertArrayHasKey($key, $metrics);
        }

        $id = (int) self::db()->scalar('SELECT id FROM users WHERE email = ?', ['cible@example.com']);
        $this->send('POST', '/admin/users/' . $id . '/status', ['status' => 'suspended']);
        self::assertSame('suspended', self::db()->scalar('SELECT status FROM users WHERE id = ?', [$id]));
        $planId = (int) self::db()->scalar("SELECT id FROM plans WHERE slug = 'pro'");
        $this->send('POST', '/admin/users/' . $id . '/plan', ['plan_id' => (string) $planId]);
        self::assertSame($planId, (int) self::db()->scalar('SELECT plan_id FROM users WHERE id = ?', [$id]));
    }

    public function testRegularUserHasNoAdminAccess(): void
    {
        $this->createUser('normal@example.com');
        $this->login('normal@example.com');
        foreach (['/admin', '/admin/metrics', '/admin/users', '/admin/logins', '/admin/logins/feed', '/admin/invitations'] as $path) {
            self::assertSame(404, $this->send('GET', $path)->status(), $path);
        }
        self::assertStringNotContainsString('Administration', $this->send('GET', '/console')->body());
    }
}
