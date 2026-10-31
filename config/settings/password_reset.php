<?php

declare(strict_types=1);

return ['ttl' => max(60, (int) ($_ENV['PASSWORD_RESET_TTL'] ?? 1800))];
