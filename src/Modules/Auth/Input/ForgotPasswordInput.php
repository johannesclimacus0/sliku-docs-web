<?php

declare(strict_types=1);

namespace App\Modules\Auth\Input;

use Symfony\Component\Validator\Constraints as Assert;

class ForgotPasswordInput
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
    ) {
    }
}
