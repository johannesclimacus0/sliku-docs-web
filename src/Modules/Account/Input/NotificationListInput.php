<?php

declare(strict_types=1);

namespace App\Modules\Account\Input;

use Symfony\Component\Validator\Constraints as Assert;

class NotificationListInput
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\Type('string', message: 'Ожидается строка'),
            new Assert\Regex('/\A[1-9][0-9]{0,4}\z/', message: 'Укажите корректный номер страницы')])
        ]
        public mixed $page = '1',
        #[Assert\Choice(choices: ['0', '1'], message: 'Допустимые значения: 0 или 1')]
        public mixed $unreadOnly = '0',
    ) {
    }
}
