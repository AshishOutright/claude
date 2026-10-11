<?php

namespace SmartCore\Gateway\Store;

use SmartCore\Gateway\DataStore;
use SmartCore\Gateway\ModuleDefinition;

/**
 * Keeps records in one JSON file. For running the React app locally without a CRM.
 */
final class JsonFileStore implements DataStore
{
    private InMemoryStore $memory;

    public function __construct(private readonly string $path)
    {
        $records = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $this->memory = new InMemoryStore(null, is_array($records) ? $records : []);
    }

    public function find(ModuleDefinition $module, string $id): ?array
    {
        return $this->memory->find($module, $id);
    }

    public function exists(ModuleDefinition $module, string $id): bool
    {
        return $this->memory->exists($module, $id);
    }

    public function list(ModuleDefinition $module, array $filters, int $page, int $perPage): array
    {
        return $this->memory->list($module, $filters, $page, $perPage);
    }

    public function insert(ModuleDefinition $module, array $data): array
    {
        return $this->saved($this->memory->insert($module, $data));
    }

    public function update(ModuleDefinition $module, string $id, array $data): array
    {
        return $this->saved($this->memory->update($module, $id, $data));
    }

    public function delete(ModuleDefinition $module, string $id): void
    {
        $this->memory->delete($module, $id);
        $this->saved([]);
    }

    private function saved(array $record): array
    {
        file_put_contents($this->path, json_encode($this->memory->records(), JSON_PRETTY_PRINT), LOCK_EX);

        return $record;
    }
}
