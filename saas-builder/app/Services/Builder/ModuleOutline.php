<?php

namespace App\Services\Builder;

use App\Models\SaasApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Reads and writes the modules outline the wizard edits, one CRM module per line:
 *
 *     Candidates*: email:email!, applied_on:date, department:relate(Departments)!
 *     Departments: code
 *
 * "*" marks the journey module, "!" marks a required field. Every module gets a required "name".
 * Types: varchar (default), text, int, decimal, date, datetime, bool, email, phone, url,
 * enum(A|B|C) and relate(Module).
 */
class ModuleOutline
{
    /** Columns SuiteCRM and the journey already own. "name" and "description" may be listed to change them. */
    private const RESERVED = ['id', 'date_entered', 'date_modified', 'deleted', 'created_by', 'modified_user_id', 'assigned_user_id', 'journey_phase', 'journey_stage', 'journey_status'];

    /**
     * @return list<array{name: string, journey: bool, fields: list<array{name: string, label: string, type: string, type_arg: ?string, required: bool}>}>
     */
    public function parse(string $outline): array
    {
        $modules = [];
        $errors = [];

        foreach (preg_split('/\R/', $outline) as $number => $raw) {
            $line = trim($raw);
            $lineNo = $number + 1;

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$head, $body] = array_pad(explode(':', $line, 2), 2, '');
            $journey = str_ends_with(trim($head), '*');
            $name = trim(rtrim(trim($head), '*'));

            if (Naming::className($name) === '') {
                $errors[] = "Line {$lineNo}: start with the module name, e.g. \"Candidates: email:email\".";

                continue;
            }

            $fields = [];

            foreach ($this->splitFields($body) as $spec) {
                $field = $this->parseField($spec, $lineNo, $errors);

                if ($field === null) {
                    continue;
                }

                if (isset($fields[$field['name']])) {
                    $errors[] = "Line {$lineNo}: {$name} lists \"{$field['name']}\" twice.";
                }

                $fields[$field['name']] = $field;
            }

            $fields = ['name' => $fields['name'] ?? ['name' => 'name', 'label' => 'Name', 'type' => 'varchar', 'type_arg' => null, 'required' => true]] + $fields;
            $modules[] = ['name' => Str::headline(Naming::className($name)), 'journey' => $journey, 'fields' => array_values($fields)];
        }

        $this->checkModules($modules, $errors);

        if ($errors !== []) {
            throw new InvalidArgumentException(implode("\n", array_unique($errors)));
        }

        return $modules;
    }

    public function format(SaasApp $app): string
    {
        return $app->modules()->with('fields')->get()->map(function ($module) {
            $fields = $module->fields
                ->reject(fn ($f) => $f->name === 'name' && $f->type === 'varchar' && $f->is_required)
                ->map(fn ($f) => $f->name.($f->type === 'varchar' ? '' : ':'.$f->type.($f->type_arg ? "({$f->type_arg})" : '')).($f->is_required ? '!' : ''))
                ->implode(', ');

            return $module->name.($module->is_journey ? '*' : '').': '.$fields;
        })->implode("\n");
    }

    public function save(SaasApp $app, string $outline): void
    {
        $modules = $this->parse($outline);

        DB::transaction(function () use ($app, $modules) {
            $app->modules()->delete();

            foreach ($modules as $m => $data) {
                $module = $app->modules()->create(['name' => $data['name'], 'is_journey' => $data['journey'], 'position' => $m]);

                foreach ($data['fields'] as $f => $field) {
                    $module->fields()->create([
                        'name' => $field['name'], 'label' => $field['label'], 'type' => $field['type'],
                        'type_arg' => $field['type_arg'], 'is_required' => $field['required'], 'position' => $f,
                    ]);
                }
            }
        });
    }

    /** Splits on commas that are not inside enum(...) or relate(...). */
    private function splitFields(string $body): array
    {
        $parts = preg_split('/,(?![^(]*\))/', $body);

        return array_values(array_filter(array_map('trim', $parts), 'strlen'));
    }

    private function parseField(string $spec, int $lineNo, array &$errors): ?array
    {
        $required = str_ends_with($spec, '!');
        $spec = rtrim($spec, '!');

        if (! preg_match('/^([^:(]+)(?::\s*([a-z]+)\s*(?:\(([^)]*)\))?)?$/i', $spec, $m)) {
            $errors[] = "Line {$lineNo}: could not read the field \"{$spec}\".";

            return null;
        }

        $name = Naming::key(trim($m[1]));
        $type = strtolower($m[2] ?? '') ?: 'varchar';
        $arg = isset($m[3]) ? trim($m[3]) : null;

        if ($name === '' || in_array($name, self::RESERVED, true)) {
            $errors[] = "Line {$lineNo}: \"{$m[1]}\" is a name the CRM already uses; pick another.";

            return null;
        }

        if (! in_array($type, ['varchar', 'text', 'int', 'decimal', 'date', 'datetime', 'bool', 'email', 'phone', 'url', 'enum', 'relate'], true)) {
            $errors[] = "Line {$lineNo}: \"{$type}\" is not a field type. Use varchar, text, int, decimal, date, datetime, bool, email, phone, url, enum(A|B) or relate(Module).";

            return null;
        }

        if ($type === 'enum' && count(array_filter(explode('|', (string) $arg), fn ($o) => trim($o) !== '')) < 2) {
            $errors[] = "Line {$lineNo}: {$name} needs at least two options, e.g. enum(Low|High).";
        }

        if ($type === 'relate') {
            $arg = Str::headline(Naming::className((string) $arg));

            if ($arg === '') {
                $errors[] = "Line {$lineNo}: {$name} needs the module it points to, e.g. relate(Departments).";
            }
        }

        if ($name === 'description') {
            $type = 'text';
        }

        return ['name' => $name, 'label' => Str::headline($name), 'type' => $type, 'type_arg' => $arg ?: null, 'required' => $required || $name === 'name'];
    }

    private function checkModules(array $modules, array &$errors): void
    {
        if ($modules === []) {
            $errors[] = 'Add at least one module.';

            return;
        }

        $names = array_column($modules, 'name');

        foreach (array_count_values($names) as $name => $count) {
            if ($count > 1) {
                $errors[] = "There are two modules called \"{$name}\".";
            }
        }

        $journeyCount = count(array_filter(array_column($modules, 'journey')));

        if ($journeyCount !== 1) {
            $errors[] = 'Mark exactly one module with * as the journey module (the one that moves through phases, stages and statuses).';
        }

        foreach ($modules as $module) {
            foreach ($module['fields'] as $field) {
                if ($field['type'] === 'relate' && $field['type_arg'] && ! in_array($field['type_arg'], $names, true)) {
                    $errors[] = "{$module['name']}.{$field['name']} points to \"{$field['type_arg']}\", which is not one of the modules.";
                }

                if ($field['type'] === 'relate' && in_array($field['name'].'_id', array_column($module['fields'], 'name'), true)) {
                    $errors[] = "{$module['name']} has both {$field['name']} and {$field['name']}_id; rename one.";
                }
            }
        }
    }
}
