<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SecondaryKeyType: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case UUID = 'uuid';
    case ULID = 'ulid';
    case SNOWFLAKE = 'snowflake';

    /**
     * Get the default key type (Snowflake for optimal performance)
     */
    public static function default(): self
    {
        return self::SNOWFLAKE;
    }

    /**
     * Get the display label for the key type
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::UUID => 'UUID v7',
            self::ULID => 'ULID',
            self::SNOWFLAKE => 'Snowflake',
        };
    }

    /**
     * Get the color associated with the key type (for UI/documentation)
     */
    public function getColor(): string
    {
        return match ($this) {
            self::UUID => 'info',
            self::ULID => 'primary',
            self::SNOWFLAKE => 'gray',
        };
    }

    /**
     * Get the description for the key type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::UUID => 'Industry standard with timestamp ordering (Laravel 12 default)',
            self::ULID => 'Compact, case-insensitive with natural sorting',
            self::SNOWFLAKE => 'Distributed system optimized with embedded metadata',
        };
    }

    /**
     * Get the icon for the key type
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::UUID => 'heroicon-o-identification',
            self::ULID => 'heroicon-o-key',
            self::SNOWFLAKE => 'heroicon-o-finger-print',
        };
    }

    /**
     * Get the typical use cases for the key type
     */
    public function useCases(): array
    {
        return match ($this) {
            self::UUID => [
                'Standards compliance',
                'Legacy system integration',
                'Regulatory requirements',
                'Security-critical applications',
            ],
            self::ULID => [
                'Storage efficiency',
                'URL-friendly identifiers',
                'High-volume logging',
                'Time-series data',
            ],
            self::SNOWFLAKE => [
                'Distributed systems',
                'Microservices architecture',
                'Multi-tenant applications',
                'Maximum performance requirements',
            ],
        };
    }

    /**
     * Get the storage characteristics
     */
    public function storageInfo(): array
    {
        return match ($this) {
            self::UUID => [
                'length' => 36,
                'format' => 'string',
                'bytes' => 16,
                'encoding' => 'hexadecimal',
            ],
            self::ULID => [
                'length' => 26,
                'format' => 'string',
                'bytes' => 16,
                'encoding' => 'base32',
            ],
            self::SNOWFLAKE => [
                'length' => 19,
                'format' => 'integer',
                'bytes' => 8,
                'encoding' => 'integer',
            ],
        };
    }
}
