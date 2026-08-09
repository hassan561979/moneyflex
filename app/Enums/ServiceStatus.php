<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    /**
     * Values accepted by validation rules and documented in the API schema.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether the service is currently billable.
     */
    public function isBillable(): bool
    {
        return $this === self::Active;
    }
}
