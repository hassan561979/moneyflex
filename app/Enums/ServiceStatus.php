<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    /**
     * Whether the service is currently billable.
     */
    public function isBillable(): bool
    {
        return $this === self::Active;
    }
}
