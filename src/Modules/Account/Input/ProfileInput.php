<?php

declare(strict_types=1);

namespace App\Modules\Account\Input;

use Symfony\Component\Validator\Constraints as Assert;

class ProfileInput
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\Type('string', message: 'Ожидается строка'),
            new Assert\NotBlank(normalizer: 'trim', message: 'Заполните поле'),
            new Assert\Length(min: 2, max: 80, normalizer: 'trim', minMessage: 'Не меньше {{ limit }} символов', maxMessage: 'Не больше {{ limit }} символов')])
        ]
        public mixed $name,
        #[Assert\Sequentially([
            new Assert\NotNull(message: 'Укажите значение'),
            new Assert\Type('string', message: 'Ожидается строка'),
            new Assert\Length(max: 500, maxMessage: 'Не больше {{ limit }} символов')])
        ]
        public mixed $bio,
    ) {
    }
}
