<?php

namespace SmartCore\Gateway;

/**
 * A CRM module as the gateway exposes it: its API name, its CRM module key and its fields.
 */
final class ModuleDefinition
{
    /** @param list<FieldDefinition> $fields */
    public function __construct(
        public readonly string $name,
        public readonly string $crmModule,
        public readonly string $label,
        public readonly array $fields,
        public readonly bool $journey = false,
    ) {
    }

    public static function fromArray(array $module): self
    {
        return new self(
            $module['name'],
            $module['crm_module'],
            $module['label'] ?? $module['name'],
            array_map([FieldDefinition::class, 'fromArray'], $module['fields'] ?? []),
            (bool) ($module['journey'] ?? false),
        );
    }

    public function field(string $inputKey): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->inputKey() === $inputKey) {
                return $field;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'journey' => $this->journey,
            'fields' => array_map(fn (FieldDefinition $f) => $f->toArray(), $this->fields),
        ];
    }
}
