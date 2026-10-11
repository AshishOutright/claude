<?php

namespace SmartCore\Gateway\Store;

use SmartCore\Gateway\DataStore;
use SmartCore\Gateway\ModuleDefinition;

/**
 * Keeps records in memory. For tests and local smoke checks without a CRM.
 */
final class InMemoryStore implements DataStore
{
    /** @var array<string, array<string, array>> module => id => record */
    private array $records = [];

    private int $clock;

    public function __construct(?int $startTime = null, array $records = [])
    {
        $this->clock = $startTime ?? time();
        $this->records = $records;
    }

    /** @return array<string, array<string, array>> */
    public function records(): array
    {
        return $this->records;
    }

    public function find(ModuleDefinition $module, string $id): ?array
    {
        $record = $this->records[$module->name][$id] ?? null;

        return $record ? $this->withRelatedNames($module, $record) : null;
    }

    public function exists(ModuleDefinition $module, string $id): bool
    {
        return isset($this->records[$module->name][$id]);
    }

    public function list(ModuleDefinition $module, array $filters, int $page, int $perPage): array
    {
        $records = array_values(array_filter(
            $this->records[$module->name] ?? [],
            fn (array $record) => array_intersect_assoc(array_map('strval', array_intersect_key($record, $filters)), $filters) === $filters,
        ));

        usort($records, fn ($a, $b) => strcmp($b['date_entered'], $a['date_entered']));

        return [
            'records' => array_map(fn ($r) => $this->withRelatedNames($module, $r), array_slice($records, ($page - 1) * $perPage, $perPage)),
            'total' => count($records),
        ];
    }

    public function insert(ModuleDefinition $module, array $data): array
    {
        $now = gmdate('Y-m-d H:i:s', $this->clock++);
        $id = bin2hex(random_bytes(16));
        $this->records[$module->name][$id] = ['id' => $id, 'date_entered' => $now, 'date_modified' => $now] + $data;

        return $this->find($module, $id);
    }

    public function update(ModuleDefinition $module, string $id, array $data): array
    {
        $this->records[$module->name][$id] = array_merge($this->records[$module->name][$id], $data, ['date_modified' => gmdate('Y-m-d H:i:s', $this->clock++)]);

        return $this->find($module, $id);
    }

    public function delete(ModuleDefinition $module, string $id): void
    {
        unset($this->records[$module->name][$id]);
    }

    private function withRelatedNames(ModuleDefinition $module, array $record): array
    {
        foreach ($module->fields as $field) {
            if ($field->type === 'relate') {
                $relatedId = $record[$field->inputKey()] ?? null;
                $related = $relatedId ? ($this->records[$field->relatesTo][$relatedId] ?? null) : null;
                $record[$field->name.'_name'] = $related['name'] ?? null;
            }
        }

        return $record;
    }
}
