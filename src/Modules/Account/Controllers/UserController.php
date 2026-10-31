<?php

declare(strict_types=1);

namespace App\Modules\Account\Controllers;

use App\Entity\User;
use App\Http\JsonResponse;
use App\Modules\Account\Enums\UserRole;
use App\Modules\Account\Input\{DirectoryInput, ProfileInput, RoleInput};
use App\Modules\Account\Security\UserVoter;
use App\Modules\Account\Services\{ProfileService, UserDirectory, UserRoleService};
use App\Modules\Account\UserView;
use App\Modules\Auth\Http\AuthenticatedUser;
use App\Security\Authorization;
use App\Validation\InputValidator;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

class UserController
{
    public function __construct(
        private ProfileService $profiles,
        private UserDirectory $directory,
        private UserRoleService $roles,
        private UserView $view,
        private Authorization $authorization,
        private InputValidator $validator,
        private JsonResponse $json,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authorization->denyAccessUnlessGranted(AuthenticatedUser::fromRequest($request), UserVoter::MANAGE, User::class);
        $query = $request->getQueryParams();
        $input = new DirectoryInput($query['page'] ?? '1', $query['search'] ?? '');
        $this->validator->validate($input);
        return $this->json->success($response, $this->directory->list((int) $input->page, trim($input->search)))->withHeader('Cache-Control', 'private, no-store');
    }

    public function show(User $user, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authorization->denyAccessUnlessGranted(AuthenticatedUser::fromRequest($request), UserVoter::VIEW, $user);
        return $this->json->success($response, $this->view->make($user))->withHeader('Cache-Control', 'private, no-store');
    }

    public function update(User $user, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $input = new ProfileInput($body->name ?? null, $body->bio ?? null);
        $this->validator->validate($input);
        $result = $this->profiles->update($user, AuthenticatedUser::fromRequest($request), $input->name, $input->bio);
        return $this->json->success($response, $this->view->make($result))->withHeader('Cache-Control', 'no-store');
    }

    public function role(User $user, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = new RoleInput($request->getParsedBody()->role ?? null);
        $this->validator->validate($input);
        $this->roles->change($user, UserRole::from($input->role), AuthenticatedUser::fromRequest($request));
        return $this->json->success($response, $this->view->make($user))->withHeader('Cache-Control', 'no-store');
    }
}
