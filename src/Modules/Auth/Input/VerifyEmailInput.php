<?php

declare(strict_types=1);

namespace App\Modules\Auth\Input;

use Symfony\Component\Validator\Constraints as Assert;

class VerifyEmailInput
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\NotNull(message: 'Укажите значение'),
            new Assert\Type('int', message: 'Ожидается целое число'),
            new Assert\Positive(message: 'Значение должно быть больше нуля'),
        ])]
        public mixed $userId,
        #[Assert\Sequentially([
            new Assert\Type('string', message: 'Ожидается строка'),
            new Assert\NotBlank(message: 'Заполните поле'),
            new Assert\Regex(pattern: '/\A[a-f0-9]{64}\z/', message: 'Недействительный токен подтверждения'),
        ])]
        public mixed $token,
    ) {
    }
}
