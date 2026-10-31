<?php

declare(strict_types=1);

use App\Modules\Account\Security\UserVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;

return [UserVoter::class, RoleVoter::class];
