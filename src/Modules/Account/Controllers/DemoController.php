<?php

declare(strict_types=1);

namespace App\Modules\Account\Controllers;

use App\Config\Config;
use App\Console\ConsoleFactory;
use App\Entity\User;
use App\Http\JsonResponse;
use App\Modules\Account\Jobs\RecordUserActivity;
use App\Modules\Account\Security\UserVoter;
use App\Modules\Auth\Http\AuthenticatedUser;
use App\Queue\Queue;
use App\Scheduler\Scheduler;
use App\Security\Authorization;
use App\Webhooks\Webhooks;
use DI\Container;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DemoController
{
    public function __construct(
        private Queue $queue,
        private Webhooks $webhooks,
        private Authorization $authorization,
        private JsonResponse $json,
        private HttpClientInterface $http,
        private Config $config,
        private Container $container,
    ) {
    }

    private function admin(ServerRequestInterface $request): User
    {
        $user = AuthenticatedUser::fromRequest($request);
        $this->authorization->denyAccessUnlessGranted($user, UserVoter::MANAGE, User::class);
        return $user;
    }

    public function job(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = AuthenticatedUser::fromRequest($request);
        $failure = $request->getParsedBody()->failure ?? false;
        if (!is_bool($failure)) {
            throw new \App\Exceptions\ValidationException(['failure' => ['Ожидается boolean']]);
        }
        if ($failure) {
            $this->admin($request);
        }
        $id = bin2hex(random_bytes(16));
        $this->queue->dispatch(new RecordUserActivity($user->getId(), $id, 'user.demo_job', $failure));
        return $this->json->success($response, [
            'eventId' => $id,
            'message' => $failure
                ? 'Задание с намеренной ошибкой поставлено в очередь'
                : 'Задание поставлено в очередь. После обработки оно появится в активности'
        ], 202);
    }

    public function webhook(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->admin($request);
        $ids = $this->webhooks->dispatch('user.demo', ['userId' => $user->getId(), 'name' => $user->getName()]);
        return $this->json->success($response, [
            'deliveryIds' => $ids,
            'message' => $ids === []
                ? 'Получатель не настроен: задайте WEBHOOK_USER_URL и WEBHOOK_USER_SECRET'
                : 'Вебхук поставлен в очередь'
        ], 202);
    }

    public function schedule(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->admin($request);
        $app = ConsoleFactory::create($this->container);
        $results = $this->container->get(Scheduler::class)->run(fn (string $command): int => $app->find($command)->run(new ArrayInput([]), new BufferedOutput()));
        return $this->json->success($response, ['tasks' => $results]);
    }

    public function http(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->admin($request);
        $url = $this->config->get('demo.http_url');
        if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false || !in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true)) {
            throw new \LogicException('Invalid DEMO_HTTP_URL');
        }
        $start = microtime(true);
        try {
            $upstream = $this->http->request('GET', $url, ['max_redirects' => 0]);
            try {
                $status = $upstream->getStatusCode();
            } finally {
                $upstream->cancel();
            }
        } catch (TransportExceptionInterface) {
            return $this->json->error($response, 'UPSTREAM_UNAVAILABLE', 'Внешний сервис недоступен', 502);
        }
        return $this->json->success($response, [
            'host' => parse_url($url, PHP_URL_HOST),
            'status' => $status,
            'durationMs' => round((microtime(true) - $start) * 1000, 1)
        ]);
    }
}
