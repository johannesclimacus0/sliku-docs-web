<?php

declare(strict_types=1);

namespace App\Console;

class ClassGenerator
{
    public const array TYPES = [
        'controller' => ['Controllers', 'Controller'],
        'service' => ['Services', 'Service'],
        'command' => ['Commands', 'Command'],
        'job' => ['Jobs', 'Job'],
        'handler' => ['Handlers', 'Handler'],
        'event' => ['Events', 'Event'],
        'listener' => ['Listeners', 'Listener'],
        'voter' => ['Security', 'Voter'],
        'dto' => ['Input', 'Input'],
        'repository' => ['Repositories', 'Repository'],
        'middleware' => ['Middleware', 'Middleware'],
    ];
    public function __construct(
        private string $root,
    ) {
    }

    public function generate(string $type, string $module, string $name): string
    {
        if (!isset(self::TYPES[$type]) || preg_match('/\A[A-Z][A-Za-z0-9]*\z/', $module) !== 1) {
            throw new \InvalidArgumentException('Invalid type or module name');
        }
        $parts = explode('/', str_replace('\\', '/', $name));
        foreach ($parts as $part) {
            if (preg_match('/\A[A-Z][A-Za-z0-9]*\z/', $part) !== 1) {
                throw new \InvalidArgumentException('Use PascalCase names; paths cannot contain . or ..');
            }
        }
        [$directory, $suffix] = self::TYPES[$type];
        $class = array_pop($parts);
        if (!str_ends_with($class, $suffix)) {
            $class .= $suffix;
        }
        $namespace = 'App\\Modules\\' . $module . '\\' . $directory . ($parts === [] ? '' : '\\' . implode('\\', $parts));
        $relative = 'src/Modules/' . $module . '/' . $directory . '/' . ($parts === [] ? '' : implode('/', $parts) . '/') . $class . '.php';
        $code = "<?php\n\ndeclare(strict_types=1);\n\nnamespace " . $namespace . ";\n\n" . $this->template($type, $class);
        token_get_all($code, TOKEN_PARSE);
        $path = $this->root . '/' . $relative;
        $parent = dirname($path);
        $ancestor = $parent;
        while (!file_exists($ancestor) && !is_link($ancestor)) {
            $ancestor = dirname($ancestor);
        }
        if (!str_starts_with(realpath($ancestor) . '/', realpath($this->root) . '/')) {
            throw new \InvalidArgumentException('Target directory is outside the project');
        }
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new \RuntimeException('Cannot create target directory');
        }
        if (!str_starts_with(realpath($parent) . '/', realpath($this->root) . '/')) {
            throw new \InvalidArgumentException('Target directory is outside the project');
        }
        $file = @fopen($path, 'x');
        if ($file === false) {
            throw new \RuntimeException('File already exists or is not writable: ' . $relative);
        }
        $written = false;
        try {
            if (fwrite($file, $code) !== strlen($code)) {
                throw new \RuntimeException('Cannot write class');
            }
            $written = true;
        } finally {
            fclose($file);
            if (!$written) {
                @unlink($path);
            }
        }
        return $relative;
    }

    private function template(string $type, string $class): string
    {
        $body = match ($type) {
            'controller' => <<<'CODE'
use App\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;

class %CLASS%
{
    public function __construct(
        private JsonResponse $json,
    ) {
    }
    public function index(ResponseInterface $response): ResponseInterface
    {
        return $this->json->success($response, ['message' => 'Готово']);
    }
}
CODE,
            'command' => <<<'CODE'
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class %CLASS% extends Command
{
    public function __construct()
    {
        parent::__construct('app:%COMMAND%');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Command completed');
        return Command::SUCCESS;
    }
}
CODE,
            'job', 'event' => <<<'CODE'
class %CLASS%
{
    public function __construct(
        public array $payload = [],
    ) {
    }
}
CODE,
            'handler' => <<<'CODE'
class %CLASS%
{
    public function __invoke(object $job): void
    {
        // Implement the job and register the mapping in config/registrations/jobs.php.
    }
}
CODE,
            'listener' => <<<'CODE'
class %CLASS%
{
    public function __invoke(object $event): void
    {
        // Type the event and register the listener in config/registrations/events.php.
    }
}
CODE,
            'voter' => <<<'CODE'
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class %CLASS% extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return false;
    }
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return false;
    }
}
CODE,
            'dto' => <<<'CODE'
use Symfony\Component\Validator\Constraints as Assert;

class %CLASS%
{
    public function __construct(
        #[Assert\NotBlank] public mixed $value,
    ) {
    }
}
CODE,
            'repository' => <<<'CODE'
use Doctrine\ORM\EntityManagerInterface;

class %CLASS%
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }
}
CODE,
            'middleware' => <<<'CODE'
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class %CLASS% implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}
CODE,
            default => "class %CLASS%\n{\n}\n",
        };
        $command = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', preg_replace('/Command$/', '', $class)));
        return str_replace(['%CLASS%', '%COMMAND%'], [$class, $command], $body) . "\n";
    }
}
