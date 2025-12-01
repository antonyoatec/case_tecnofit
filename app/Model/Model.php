<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model as BaseModel;
use Ramsey\Uuid\Uuid;

/**
 * Base model class with UUID support and common functionality
 */
abstract class Model extends BaseModel
{

    /**
     * Indicates if the model uses UUID as primary key
     */
    protected string $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing
     */
    public bool $incrementing = false;

    /**
     * The attributes that should be cast to native types
     */
    protected array $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Generate UUID for new instances
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        if (empty($this->{$this->getKeyName()})) {
            $this->{$this->getKeyName()} = Uuid::uuid4()->toString();
        }
    }

    /**
     * Get the route key for the model
     */
    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }
}