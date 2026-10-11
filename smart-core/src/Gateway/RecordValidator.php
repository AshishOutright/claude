<?php

namespace SmartCore\Gateway;

/**
 * Cleans incoming data against the module's fields: unknown keys, types, required fields,
 * and law L-CODE-001 (a related record must exist before this one can point at it).
 */
final class RecordValidator
{
    public function __construct(private readonly ModuleRegistry $modules, private readonly DataStore $store)
    {
    }

    /** @return array<string, mixed> clean data */
    public function validate(ModuleDefinition $module, array $data, bool $creating): array
    {
        $clean = [];
        $errors = [];

        foreach ($data as $key => $value) {
            $field = $module->field((string) $key);

            if ($field === null) {
                $errors[$key] = 'This field does not exist or cannot be set here.';

                continue;
            }

            if ($value === null || $value === '') {
                $clean[$key] = null;

                continue;
            }

            $problem = $this->checkType($field, $value);

            if ($problem !== null) {
                $errors[$key] = $problem;

                continue;
            }

            $clean[$key] = $this->cast($field, $value);
        }

        foreach ($module->fields as $field) {
            $key = $field->inputKey();
            $missing = $creating ? ! isset($clean[$key]) : array_key_exists($key, $clean) && $clean[$key] === null;

            if ($field->required && $missing && ! isset($errors[$key])) {
                $errors[$key] = "{$field->label} is required.";
            }
        }

        foreach ($module->fields as $field) {
            $key = $field->inputKey();

            if ($field->type === 'relate' && isset($clean[$key]) && ! isset($errors[$key])) {
                $target = $this->modules->get($field->relatesTo);

                if (! $this->store->exists($target, (string) $clean[$key])) {
                    $errors[$key] = "No {$target->label} record with this id (law L-CODE-001).";
                }
            }
        }

        if ($errors !== []) {
            throw new GatewayException('invalid', 'Some fields need fixing.', 422, $errors);
        }

        return $clean;
    }

    private function checkType(FieldDefinition $field, mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return 'Send a single value.';
        }

        $value = (string) $value;

        return match ($field->type) {
            'int' => preg_match('/^-?\d+$/', $value) ? null : 'Must be a whole number.',
            'decimal' => is_numeric($value) ? null : 'Must be a number.',
            'date' => $this->isDate($value, 'Y-m-d') ? null : 'Use the date format YYYY-MM-DD.',
            'datetime' => $this->isDate($value, 'Y-m-d H:i:s') ? null : 'Use the format YYYY-MM-DD HH:MM:SS.',
            'bool' => in_array(strtolower($value), ['0', '1', 'true', 'false'], true) ? null : 'Must be true or false.',
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Must be an email address.',
            'url' => filter_var($value, FILTER_VALIDATE_URL) ? null : 'Must be a link starting with http:// or https://.',
            'enum' => in_array($value, $field->options, true) ? null : 'Pick one of: '.implode(', ', $field->options).'.',
            'text' => strlen($value) <= 65535 ? null : 'Too long.',
            default => strlen($value) <= 255 ? null : 'Keep it under 255 characters.',
        };
    }

    private function cast(FieldDefinition $field, mixed $value): mixed
    {
        return match ($field->type) {
            'int' => (int) $value,
            'bool' => in_array(strtolower((string) $value), ['1', 'true'], true),
            default => trim((string) $value),
        };
    }

    private function isDate(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value;
    }
}
