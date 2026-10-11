<?php

namespace SmartCore\Laws;

use DomainException;

/**
 * S-001 in code: a model lists the records it depends on, and saving without them fails.
 *
 *     protected array $requires = ['payment_link_id' => PaymentLink::class];
 */
trait RequiresDependencies
{
    public static function bootRequiresDependencies(): void
    {
        static::saving(function ($model): void {
            foreach ($model->requires ?? [] as $column => $class) {
                $id = $model->getAttribute($column);

                if ($id === null || ! $class::query()->whereKey($id)->exists()) {
                    throw new DomainException(class_basename($model)." needs a valid {$column} before it can be saved (law L-CODE-001).");
                }
            }
        });
    }
}
