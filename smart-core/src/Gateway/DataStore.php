<?php

namespace SmartCore\Gateway;

/**
 * Where records live. SuiteCrmStore in a CRM copy, InMemoryStore in tests.
 * Records are plain arrays: id, date_entered, date_modified, the module's fields,
 * "<relate>_id" and "<relate>_name" for relate fields, and journey_phase/stage/status on the journey module.
 */
interface DataStore
{
    public function find(ModuleDefinition $module, string $id): ?array;

    public function exists(ModuleDefinition $module, string $id): bool;

    /**
     * Newest first (date_entered descending).
     *
     * @param array<string, string> $filters exact-match filters on field keys
     * @return array{records: list<array>, total: int}
     */
    public function list(ModuleDefinition $module, array $filters, int $page, int $perPage): array;

    public function insert(ModuleDefinition $module, array $data): array;

    public function update(ModuleDefinition $module, string $id, array $data): array;

    public function delete(ModuleDefinition $module, string $id): void;
}
