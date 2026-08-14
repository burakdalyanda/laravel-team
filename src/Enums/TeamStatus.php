<?php

declare(strict_types=1);

namespace BurakDalyanda\TeamGuard\Enums;

enum TeamStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
