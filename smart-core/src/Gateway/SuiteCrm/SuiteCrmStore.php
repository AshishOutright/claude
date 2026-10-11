<?php

namespace SmartCore\Gateway\SuiteCrm;

use BeanFactory;
use SmartCore\Gateway\DataStore;
use SmartCore\Gateway\ModuleDefinition;
use SugarBean;

/**
 * Reads and writes records through SuiteCRM beans, so logic hooks, auditing and
 * security groups keep working. Only runs inside a SuiteCRM entry point.
 */
final class SuiteCrmStore implements DataStore
{
    /** @param array<string, string> $crmModules API module name => CRM module key, for relate lookups */
    public function __construct(private readonly array $crmModules = [])
    {
    }

    public function find(ModuleDefinition $module, string $id): ?array
    {
        $bean = $this->retrieve($module, $id);

        return $bean ? $this->toArray($module, $bean) : null;
    }

    public function exists(ModuleDefinition $module, string $id): bool
    {
        return $this->retrieve($module, $id) !== null;
    }

    public function list(ModuleDefinition $module, array $filters, int $page, int $perPage): array
    {
        global $db;

        $bean = BeanFactory::newBean($module->crmModule);
        $table = $bean->table_name;
        $where = ["{$table}.deleted = 0"];

        foreach ($filters as $key => $value) {
            $column = preg_replace('/[^a-z0-9_]/', '', strtolower($key));
            $where[] = "{$table}.{$column} = ".$db->quoted($value);
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) $db->getOne("SELECT COUNT(*) FROM {$table} WHERE {$whereSql}");
        $offset = ($page - 1) * $perPage;
        $result = $db->limitQuery("SELECT id FROM {$table} WHERE {$whereSql} ORDER BY {$table}.date_entered DESC", $offset, $perPage);
        $records = [];

        while ($row = $db->fetchByAssoc($result)) {
            $found = $this->find($module, $row['id']);

            if ($found !== null) {
                $records[] = $found;
            }
        }

        return ['records' => $records, 'total' => $total];
    }

    public function insert(ModuleDefinition $module, array $data): array
    {
        $bean = BeanFactory::newBean($module->crmModule);
        $this->fill($bean, $data);
        $bean->save();

        return $this->find($module, $bean->id);
    }

    public function update(ModuleDefinition $module, string $id, array $data): array
    {
        $bean = $this->retrieve($module, $id);
        $this->fill($bean, $data);
        $bean->save();

        return $this->find($module, $id);
    }

    public function delete(ModuleDefinition $module, string $id): void
    {
        BeanFactory::newBean($module->crmModule)->mark_deleted($id);
    }

    private function retrieve(ModuleDefinition $module, string $id): ?SugarBean
    {
        $bean = BeanFactory::newBean($module->crmModule);

        return $bean->retrieve($id) && empty($bean->deleted) ? $bean : null;
    }

    private function fill(SugarBean $bean, array $data): void
    {
        foreach ($data as $key => $value) {
            $bean->{$key} = is_bool($value) ? (int) $value : $value;
        }
    }

    private function toArray(ModuleDefinition $module, SugarBean $bean): array
    {
        $row = $bean->fetched_row ?: [];
        $record = [
            'id' => $bean->id,
            'date_entered' => $row['date_entered'] ?? $bean->date_entered,
            'date_modified' => $row['date_modified'] ?? $bean->date_modified,
        ];

        foreach ($module->fields as $field) {
            $key = $field->inputKey();
            $record[$key] = $row[$key] ?? $bean->{$key} ?? null;

            if ($field->type === 'relate') {
                $related = $record[$key] ? BeanFactory::getBean($this->crmModuleFor($field->relatesTo, $module), $record[$key]) : null;
                $record[$field->name.'_name'] = $related->name ?? null;
            }
        }

        if ($module->journey) {
            foreach (['journey_phase', 'journey_stage', 'journey_status'] as $key) {
                $record[$key] = $row[$key] ?? $bean->{$key} ?? null;
            }
        }

        return $record;
    }

    /** Relate targets are named by API name; their CRM module key comes from the registry config. */
    private function crmModuleFor(?string $apiName, ModuleDefinition $fallback): string
    {
        return $this->crmModules[$apiName] ?? $fallback->crmModule;
    }
}
