<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\SecondaryKeyType;
use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;

trait HasSecondaryUniqueKey
{
    /**
     * The secondary key type (defaults to Snowflake for optimal performance)
     */
    protected SecondaryKeyType $secondaryKeyType = SecondaryKeyType::SNOWFLAKE;

    /**
     * The column name for the secondary key (defaults to public_id)
     */
    protected string $secondaryKeyColumn = 'public_id';

    /**
     * Boot the trait and set up model events.
     */
    protected static function bootHasSecondaryUniqueKey(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->{$model->getSecondaryKeyColumn()})) {
                $model->{$model->getSecondaryKeyColumn()} = $model->generateSecondaryKey();
            }
        });
    }

    /**
     * Find a model by its secondary key
     */
    public static function findBySecondaryKey(string $key): ?static
    {
        return static::where((new static())->getSecondaryKeyColumn(), $key)->first();
    }

    /**
     * Find a model by its secondary key or fail
     */
    public static function findBySecondaryKeyOrFail(string $key): static
    {
        return static::where((new static())->getSecondaryKeyColumn(), $key)->firstOrFail();
    }

    /**
     * Generate a new secondary key based on the configured type
     */
    public function generateSecondaryKey(): string
    {
        return match ($this->getSecondaryKeyType()) {
            SecondaryKeyType::UUID => (string)Str::uuid(), // Generates UUID v7 in Laravel 12
            SecondaryKeyType::ULID => (string)Ulid::generate(),
            SecondaryKeyType::SNOWFLAKE => (string)Snowflake::make(),
        };
    }

    /**
     * Get the secondary key type
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return $this->secondaryKeyType ?? SecondaryKeyType::default();
    }

    /**
     * Set the secondary key type
     */
    public function setSecondaryKeyType(SecondaryKeyType $type): void
    {
        $this->secondaryKeyType = $type;
    }

    /**
     * Get the secondary key column name
     */
    public function getSecondaryKeyColumn(): string
    {
        return $this->secondaryKeyColumn;
    }

    /**
     * Scope query to find by secondary key
     */
    public function scopeBySecondaryKey($query, string $key)
    {
        return $query->where($this->getSecondaryKeyColumn(), $key);
    }

    /**
     * Get the route key name for model binding
     */
    public function getRouteKeyName(): string
    {
        return $this->getSecondaryKeyColumn();
    }

    /**
     * Get key type information with metadata
     */
    public function getKeyTypeInfo(): array
    {
        $type = $this->getSecondaryKeyType();

        return [
            'type' => $type->value,
            'label' => $type->getLabel(),
            'description' => $type->getDescription(),
            'color' => $type->getColor(),
            'icon' => $type->getIcon(),
            'use_cases' => $type->useCases(),
            'storage_info' => $type->storageInfo(),
        ];
    }
}
