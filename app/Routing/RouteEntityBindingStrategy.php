<?php

declare(strict_types=1);

namespace App\Routing;

use App\Exceptions\EntityNotFoundException;
use Closure;
use DI\Container;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\Mapping\MappingException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionNamedType;
use Slim\Interfaces\InvocationStrategyInterface;

class RouteEntityBindingStrategy implements InvocationStrategyInterface
{
    public function __construct(
        private Container $container,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function __invoke(
        callable $callable,
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $routeArguments,
    ): ResponseInterface {
        foreach ($routeArguments as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        $handler = Closure::fromCallable($callable);
        $parameters = [
            'request' => $request,
            'response' => $response,
            'args' => $routeArguments,
        ] + $routeArguments + $request->getAttributes();

        foreach (new ReflectionFunction($handler)->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $name = $parameter->getName();
            $class = $type->getName();

            if (is_a($request, $class)) {
                $parameters[$name] = $request;
            } elseif (is_a($response, $class)) {
                $parameters[$name] = $response;
            } elseif (isset($routeArguments[$name])) {
                $em = $this->container->get(EntityManagerInterface::class);
                if (!$em->getMetadataFactory()->isTransient($class)) {
                    $parameters[$name] = $em->find($class, $routeArguments[$name])
                        ?? throw new EntityNotFoundException();
                }
            }
        }

        return $this->container->call($handler, $parameters);
    }
}
