<?php

declare(strict_types=1);

namespace App\Modules\Auth\Input;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordInput
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
            new Assert\Regex(pattern: '/\A[a-f0-9]{64}\z/', message: 'Недействительный токен восстановления'),
        ])]
        public mixed $token,
        #[Assert\Sequentially([
            new Assert\Type('string', message: 'Ожидается строка'),
            new Assert\NotBlank(message: 'Заполните поле'),
            new Assert\Length(
                min: 12,
                max: 72,
                countUnit: Assert\Length::COUNT_BYTES,
                minMessage: 'Пароль должен занимать не меньше {{ limit }} байт',
                maxMessage: 'Пароль должен занимать не больше {{ limit }} байт',
            ),
            new Assert\Regex(pattern: '/\x00/', match: false, message: 'Пароль не должен содержать нулевые байты'),
        ])]
        public mixed $password,
    ) {
    }
}
