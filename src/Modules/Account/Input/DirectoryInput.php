<?php

declare(strict_types=1);

namespace App\Modules\Account\Input;

use Symfony\Component\Validator\Constraints as Assert;

class DirectoryInput
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\Type('string', message: 'Ожидается строка'),
            new Assert\Regex('/\A[1-9][0-9]{0,5}\z/', message: 'Укажите корректный номер страницы')])
        ]
        public mixed $page,
        #[Assert\Sequentially([
            new Assert\Type('string', message: 'Ожидается строка'),
            new Assert\Length(max: 80, maxMessage: 'Не больше {{ limit }} символов')])
        ]
        public mixed $search,
    ) {
    }
}
