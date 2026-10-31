<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Config\Config;
use App\Queue\ServiceLocator;
use DI\Container;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/** An isolated container: deliberately never loads production definitions or .env. */
class TestEnvironment
{
    public string $directory;
    public Container $container;
    public InMemoryTransport $transport;
    public InMemoryTransport $failed;
    public RecordingMailTransport $mail;
    public MockHttpClient $http;
    private bool $closed = false;

    /** @param array<class-string, list<callable>> $handlers */
    public function __construct(array $settings = [], array $handlers = [], ?MockHttpClient $http = null)
    {
        $this->directory = sys_get_temp_dir() . '/slim-test-' . bin2hex(random_bytes(12));
        if (!mkdir($this->directory, 0700)) {
            throw new RuntimeException('Cannot create temporary test directory');
        }

        $this->transport = new InMemoryTransport();
        $this->failed = new InMemoryTransport();
        $this->mail = new RecordingMailTransport();

        $this->http = $http ?? new MockHttpClient(static function (): never {
            throw new RuntimeException('Configure an HTTP response in this test first');
        });
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'), [
            'cache' => false,
            'strict_variables' => true,
            'autoescape' => 'name',
        ]);
        $items = array_replace_recursive([
            'app' => ['env' => 'testing', 'app_name' => 'Test Workspace', 'debug' => true],
            'storage' => [
                'default' => 'public',
                'disks' => [
                    'public' => ['driver' => 'local', 'root' => $this->directory . '/public', 'url' => '/media'],
                    'private' => ['driver' => 'local', 'root' => $this->directory . '/private'],
                ],
            ],
            'mail' => ['from_address' => 'sender@example.test', 'from_name' => 'Test Workspace'],
        ], $settings);
        foreach ($items['storage']['disks'] as $name => &$disk) {
            $disk['root'] = $this->directory . '/' . rawurlencode((string) $name);
        }
        unset($disk);
        $config = new Config($items);
        $bus = new MessageBus([
            new SendMessageMiddleware(new SendersLocator(['*' => ['async']], new ServiceLocator([
                'async' => fn (): InMemoryTransport => $this->transport,
            ]))),
            new HandleMessageMiddleware(new HandlersLocator($handlers)),
        ]);
        $this->container = new Container([
            Config::class => $config,
            CacheItemPoolInterface::class => new ArrayAdapter(),
            FilesystemOperator::class => new Filesystem(new LocalFilesystemAdapter($config->get('storage.disks.' . $config->get('storage.default') . '.root'))),
            LoggerInterface::class => new NullLogger(),
            Environment::class => $twig,
            BodyRendererInterface::class => new BodyRenderer($twig, ['appName' => $config->get('app.app_name')]),
            TransportInterface::class => $this->mail,
            MailerInterface::class => new Mailer($this->mail),
            HttpClientInterface::class => $this->http,
            MessageBusInterface::class => $bus,
            InMemoryTransport::class => $this->transport,
        ]);
    }

    /** @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        return $this->container->get($id);
    }

    /** Runs the real Messenger worker until the in-memory queue is idle, without retry delays. */
    public function work(int $maxRetries = 3): void
    {
        $events = new EventDispatcher();
        $events->addSubscriber(new AddErrorDetailsStampListener());
        $events->addSubscriber(new SendFailedMessageForRetryListener(
            new ServiceLocator(['async' => fn () => $this->transport]),
            new ServiceLocator(['async' => static fn () => new MultiplierRetryStrategy($maxRetries, 0)]),
            eventDispatcher: $events,
        ));
        $events->addSubscriber(new SendFailedMessageToFailureTransportListener(
            new ServiceLocator(['async' => fn () => $this->failed]),
            failureTransportsByName: ['async' => 'failed'],
        ));
        $events->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        });
        new Worker(['async' => $this->transport], $this->get(MessageBusInterface::class), $events)
            ->run(['sleep' => 0, 'time_limit' => 5]);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->get(CacheItemPoolInterface::class)->clear();
        $this->transport->reset();
        $this->failed->reset();
        $disk = new Filesystem(new LocalFilesystemAdapter($this->directory));
        $disk->deleteDirectory('');
        if (is_dir($this->directory) && !rmdir($this->directory)) {
            throw new RuntimeException('Cannot remove temporary test directory');
        }
        $this->closed = true;
    }
}
