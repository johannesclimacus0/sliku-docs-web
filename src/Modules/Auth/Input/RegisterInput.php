<?php

declare(strict_types=1);

namespace App\Modules\Auth\Input;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterInput
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\Type('string', message: 'Ожидается строка'),
            new Assert\NotBlank(normalizer: 'trim', message: 'Заполните поле'),
            new Assert\Email(normalizer: 'trim', message: 'Укажите корректный email'),
            new Assert\Length(
                max: 256,
                normalizer: 'trim',
                countUnit: Assert\Length::COUNT_BYTES,
                maxMessage: 'Email должен занимать не больше {{ limit }} байт',
            ),
        ])]
        public mixed $email,
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
