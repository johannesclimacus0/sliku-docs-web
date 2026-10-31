<?php

declare(strict_types=1);

namespace App\Modules\Account\Input;

use Symfony\Component\Validator\Constraints as Assert;

class RoleInput
{
    public function __construct(
        #[Assert\Choice(choices: ['member', 'admin'], message: 'Выберите роль member или admin')]
        #[Assert\NotNull(message: 'Укажите значение')]
        public mixed $role,
    ) {
    }
}
