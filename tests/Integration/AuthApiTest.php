<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Config\Config;
use App\Modules\Account\Enums\UserRole;
use App\Modules\Auth\Handlers\SendEmailVerificationHandler;
use App\Modules\Auth\Handlers\SendPasswordResetHandler;
use App\Modules\Auth\Jobs\SendEmailVerification;
use App\Modules\Auth\Jobs\SendPasswordReset;
use Firebase\JWT\JWT;
use Slim\Psr7\Factory\ServerRequestFactory;

class AuthApiTest extends DatabaseTestCase
{
    public function testRegistrationPersistsHashedPasswordAndQueuesVerification(): void
    {
        $response = $this->request('POST', '/auth/register', ['email' => '  NEW@Example.test ', 'password' => 'ChangeMe12345!']);
        $data = $this->json($response, 201)['data'];
        self::assertSame('Bearer', $data['token_type']);
        self::assertNotEmpty($data['access_token']);
        $this->refreshToken($response);
        $row = $this->em->getConnection()->fetchAssociative('SELECT * FROM users');
        self::assertSame('new@example.test', $row['email']);
        self::assertNotSame('ChangeMe12345!', $row['password_hash']);
        self::assertTrue(password_verify('ChangeMe12345!', $row['password_hash']));
        self::assertNull($row['email_verified_at']);
        self::assertSame(3, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertCount(0, $this->env->mail->messages);
    }

    public function testDuplicateRegistrationReturnsConflictWithoutExtraUser(): void
    {
        $this->user();
        $this->json($this->request('POST', '/auth/register', ['email' => 'USER@example.test', 'password' => 'ChangeMe12345!']), 409);
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM users'));
    }

    public function testInvalidRegistrationReturnsFieldErrorsAndWritesNothing(): void
    {
        $body = $this->json($this->request('POST', '/auth/register', ['email' => ['wrong'], 'password' => 'short']), 422);
        self::assertArrayHasKey('email', $body['error']['details']);
        self::assertArrayHasKey('password', $body['error']['details']);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM users'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testMalformedAndNonObjectJsonAreRejected(): void
    {
        $this->json($this->request('POST', '/auth/login', '{', raw: true), 400);
        $this->json($this->request('POST', '/auth/login', '[]', raw: true), 400);
    }

    public function testLoginTokenAuthenticatesMeWithoutLeakingSecrets(): void
    {
        $user = $this->user();
        $response = $this->request('POST', '/auth/login', ['email' => 'USER@example.test', 'password' => 'ChangeMe12345!']);
        $token = $this->json($response, 200)['data']['access_token'];
        $request = new ServerRequestFactory()->createServerRequest('GET', 'https://api.example.test/auth/me')
            ->withHeader('Authorization', 'Bearer ' . $token);
        $me = $this->app->handle($request);
        $data = $this->json($me, 200)['data'];
        self::assertSame($user->getId(), $data['id']);
        self::assertSame('user@example.test', $data['email']);
        self::assertArrayNotHasKey('passwordHash', $data);
        self::assertArrayNotHasKey('password_hash', $data);
        self::assertStringNotContainsString($user->getPasswordHash(), (string) $me->getBody());
        self::assertSame('private, no-store', $me->getHeaderLine('Cache-Control'));
    }

    public function testWrongPasswordAndMissingTokenAreUnauthorized(): void
    {
        $this->user();
        $this->json($this->request('POST', '/auth/login', ['email' => 'user@example.test', 'password' => 'wrong-password']), 401);
        $this->json($this->request('GET', '/auth/me'), 401);
    }

    public function testExpiredAndTamperedAccessTokensAreUnauthorized(): void
    {
        $user = $this->user();
        $key = $this->container->get(Config::class)->get('app.jwt_key');
        $expired = JWT::encode(['sub' => (string) $user->getId(), 'iat' => time() - 3600, 'exp' => time() - 60], $key, 'HS256');
        $tampered = JWT::encode(['sub' => (string) $user->getId(), 'iat' => time(), 'exp' => time() + 900], 'a-different-key-with-at-least-thirty-two-bytes', 'HS256');
        foreach ([$expired, $tampered] as $token) {
            $request = new ServerRequestFactory()->createServerRequest('GET', 'https://api.example.test/auth/me')
                ->withHeader('Authorization', 'Bearer ' . $token);
            $this->json($this->app->handle($request), 401);
        }
    }

    public function testLoginValidatesRememberBoolean(): void
    {
        $this->user();
        $body = $this->json($this->request('POST', '/auth/login', ['email' => 'user@example.test', 'password' => 'ChangeMe12345!', 'remember' => 'yes']), 422);
        self::assertArrayHasKey('remember', $body['error']['details']);
    }

    public function testRefreshRotatesCookieAndRejectsOldToken(): void
    {
        $this->user();
        $login = $this->request('POST', '/auth/login', ['email' => 'user@example.test', 'password' => 'ChangeMe12345!', 'remember' => true]);
        $this->json($login, 200);
        $oldAccess = $this->json($login, 200)['data']['access_token'];
        $old = $this->refreshToken($login);
        self::assertStringContainsString('Max-Age=31536000', $login->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('HttpOnly', $login->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('Secure', $login->getHeaderLine('Set-Cookie'));
        $refresh = $this->request('POST', '/auth/refresh', cookie: $old);
        $this->json($refresh, 200);
        $new = $this->refreshToken($refresh);
        $this->json($this->app->handle(new ServerRequestFactory()->createServerRequest('GET', '/auth/me')->withHeader('Authorization', 'Bearer ' . $oldAccess)), 200);
        self::assertNotSame($old, $new);
        self::assertStringContainsString('Max-Age=31536000', $refresh->getHeaderLine('Set-Cookie'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM refresh_sessions WHERE token_hash = ?', [hash('sha256', $old)]));
        $this->json($this->request('POST', '/auth/refresh', cookie: $old), 401);
        $this->json($this->request('POST', '/auth/refresh', cookie: $new), 200);
    }

    public function testLogoutRevokesSessionAndClearsCookie(): void
    {
        $this->user();
        $login = $this->request('POST', '/auth/login', ['email' => 'user@example.test', 'password' => 'ChangeMe12345!']);
        $access = $this->json($login, 200)['data']['access_token'];
        $token = $this->refreshToken($login);
        $otherLogin = $this->request('POST', '/auth/login', ['email' => 'user@example.test', 'password' => 'ChangeMe12345!']);
        $otherAccess = $this->json($otherLogin, 200)['data']['access_token'];
        self::assertStringNotContainsString('Max-Age=', $login->getHeaderLine('Set-Cookie'));
        $logout = $this->request('POST', '/auth/logout', cookie: $token);
        $this->json($logout, 200);
        self::assertStringContainsString('Max-Age=0', $logout->getHeaderLine('Set-Cookie'));
        $this->json($this->request('POST', '/auth/refresh', cookie: $token), 401);
        $this->json($this->app->handle(new ServerRequestFactory()->createServerRequest('GET', '/auth/me')->withHeader('Authorization', 'Bearer ' . $access)), 401);
        $this->json($this->app->handle(new ServerRequestFactory()->createServerRequest('GET', '/auth/me')->withHeader('Authorization', 'Bearer ' . $otherAccess)), 200);
    }

    public function testExpiredRefreshSessionIsRejected(): void
    {
        $user = $this->user();
        $token = $this->container->get(\App\Modules\Auth\Repositories\RefreshSessionRepository::class)->issue($user->getId(), false)['token'];
        $this->em->getConnection()->executeStatement('UPDATE refresh_sessions SET expires_at = ?', [time() - 1]);
        $this->json($this->request('POST', '/auth/refresh', cookie: $token), 401);
    }

    public function testVerificationLinkConfirmsEmailAndCannotBeReused(): void
    {
        $this->json($this->request('POST', '/auth/register', ['email' => 'new@example.test', 'password' => 'ChangeMe12345!']), 201);
        $id = (int) $this->em->getConnection()->fetchOne('SELECT id FROM users');
        $version = (int) $this->em->getConnection()->fetchOne('SELECT email_verification_version FROM users');
        $this->container->get(SendEmailVerificationHandler::class)(new SendEmailVerification($id, $version));
        $email = $this->env->mail->messages[0]->getOriginalMessage();
        self::assertSame(1, preg_match('~href="https://frontend\.example\.test/verify-email#([^"]+)"~', html_entity_decode($email->getHtmlBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches));
        parse_str($matches[1], $params);
        $body = ['userId' => $id, 'token' => $params['token']];
        $before = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages');
        $this->json($this->request('POST', '/auth/email/verify', $body), 200);
        self::assertNotNull($this->em->getConnection()->fetchOne('SELECT email_verified_at FROM users WHERE id = ?', [$id]));
        self::assertSame($before + 3, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->json($this->request('POST', '/auth/email/verify', $body), 400);
    }

    public function testExpiredVerificationLinkIsRejected(): void
    {
        $user = $this->user(verified: false);
        $token = str_repeat('a', 64);
        $user->prepareEmailVerification(hash('sha256', $token), new \DateTimeImmutable('-1 minute'));
        $this->em->flush();
        $this->json($this->request('POST', '/auth/email/verify', ['userId' => $user->getId(), 'token' => $token]), 400);
        self::assertNull($this->em->getConnection()->fetchOne('SELECT email_verified_at FROM users'));
    }

    public function testOwnerAndAdminCanReadUserButAnotherMemberCannot(): void
    {
        $owner = $this->user('owner@example.test');
        $other = $this->user('other@example.test');
        $admin = $this->user('admin@example.test', role: UserRole::ADMIN);
        $path = '/users/' . $owner->getId();
        $this->json($this->request('GET', $path, actor: $owner), 200);
        $this->json($this->request('GET', $path, actor: $admin), 200);
        $this->json($this->request('GET', $path, actor: $other), 403);
        $this->json($this->request('PATCH', $path, ['name' => 'Hacked', 'bio' => ''], $other), 403);
        self::assertSame('owner', $this->em->getConnection()->fetchOne('SELECT name FROM users WHERE id = ?', [$owner->getId()]));
        $this->json($this->request('GET', '/users/999999', actor: $owner), 404);
    }

    public function testUnverifiedUserCanViewSelfButCannotChangeProfile(): void
    {
        $user = $this->user(verified: false);
        $this->json($this->request('GET', '/auth/me', actor: $user), 200);
        $this->json($this->request('PATCH', '/users/' . $user->getId(), ['name' => 'Changed', 'bio' => ''], $user), 403);
    }

    public function testOnlyAdminCanListUsersAndOwnerCannotPromoteSelf(): void
    {
        $member = $this->user();
        $admin = $this->user('admin@example.test', role: UserRole::ADMIN);
        $this->json($this->request('GET', '/users', actor: $member), 403);
        $this->json($this->request('GET', '/users', actor: $admin), 200);
        $this->json($this->request('PATCH', '/users/' . $member->getId() . '/role', ['role' => 'admin'], $member), 403);
    }

    public function testPasswordResetRevokesRefreshSessionsAndChangesLoginPassword(): void
    {
        $user = $this->user();
        $login = $this->request('POST', '/auth/login', ['email' => $user->getEmail(), 'password' => 'ChangeMe12345!']);
        $access = $this->json($login, 200)['data']['access_token'];
        $cookie = $this->refreshToken($login);
        $this->json($this->request('POST', '/auth/forgot-password', ['email' => $user->getEmail()]), 202);
        $version = (int) $this->em->getConnection()->fetchOne('SELECT password_reset_version FROM users');
        $this->container->get(SendPasswordResetHandler::class)(new SendPasswordReset($user->getId(), $version));
        $email = $this->env->mail->messages[0]->getOriginalMessage();
        self::assertSame(1, preg_match('~href="https://frontend\.example\.test/reset-password#([^"]+)"~', html_entity_decode($email->getHtmlBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches));
        parse_str($matches[1], $params);
        $body = ['userId' => $user->getId(), 'token' => $params['token'], 'password' => 'NewPassword12345!'];
        $this->json($this->request('POST', '/auth/reset-password', $body), 200);
        $this->json($this->app->handle(new ServerRequestFactory()->createServerRequest('GET', '/auth/me')->withHeader('Authorization', 'Bearer ' . $access)), 401);
        $this->json($this->request('POST', '/auth/refresh', cookie: $cookie), 401);
        $this->json($this->request('POST', '/auth/login', ['email' => $user->getEmail(), 'password' => 'ChangeMe12345!']), 401);
        $this->json($this->request('POST', '/auth/login', ['email' => $user->getEmail(), 'password' => 'NewPassword12345!']), 200);
        $this->json($this->request('POST', '/auth/reset-password', $body), 400);
    }
}
