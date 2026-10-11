<?php

namespace SmartCore\Gateway;

/**
 * One field of a module as the gateway exposes it. Relate fields are sent and stored as "<name>_id".
 */
final class FieldDefinition
{
    public const TYPES = ['varchar', 'text', 'int', 'decimal', 'date', 'datetime', 'bool', 'email', 'phone', 'url', 'enum', 'relate'];

    /** @param list<string> $options */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $label,
        public readonly bool $required = false,
        public readonly array $options = [],
        public readonly ?string $relatesTo = null,
    ) {
    }

    public static function fromArray(array $field): self
    {
        return new self(
            $field['name'],
            $field['type'] ?? 'varchar',
            $field['label'] ?? $field['name'],
            (bool) ($field['required'] ?? false),
            $field['options'] ?? [],
            $field['relates_to'] ?? null,
        );
    }

    /** The key used in requests and records. */
    public function inputKey(): string
    {
        return $this->type === 'relate' ? $this->name.'_id' : $this->name;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'key' => $this->inputKey(),
            'type' => $this->type,
            'label' => $this->label,
            'required' => $this->required,
            'options' => $this->options,
            'relates_to' => $this->relatesTo,
        ];
    }
}
