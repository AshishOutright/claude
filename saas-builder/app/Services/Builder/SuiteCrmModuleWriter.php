<?php

namespace App\Services\Builder;

use App\Models\AppModule;
use App\Models\AppModuleField;
use App\Models\SaasApp;

/**
 * Writes the CRM part of an app: one real SuiteCRM module per wizard module (vardefs, bean class,
 * list / edit / detail views, language), plus the extension files that register them, the
 * journey dropdowns and the smart_gateway entry point.
 *
 * Fields are real columns on the module's own table, never studio custom fields (law L-DATA-001).
 */
class SuiteCrmModuleWriter
{
    private const VARDEF_TYPES = [
        'varchar' => ['type' => 'varchar', 'len' => 255],
        'text' => ['type' => 'text'],
        'int' => ['type' => 'int', 'len' => 11],
        'decimal' => ['type' => 'decimal', 'len' => '18', 'precision' => '2'],
        'date' => ['type' => 'date'],
        'datetime' => ['type' => 'datetime'],
        'bool' => ['type' => 'bool', 'default' => '0'],
        'email' => ['type' => 'varchar', 'len' => 255],
        'phone' => ['type' => 'phone', 'dbType' => 'varchar', 'len' => 100],
        'url' => ['type' => 'url', 'dbType' => 'varchar', 'len' => 255],
        'enum' => ['type' => 'enum', 'len' => 100],
    ];

    /** @return array<string, string> path inside crm/ => contents */
    public function files(SaasApp $app): array
    {
        $prefix = $app->crmPrefix();
        $files = [];

        foreach ($app->modules as $module) {
            $key = $module->crmKey($prefix);
            $dir = "modules/{$key}";

            $files["{$dir}/{$key}.php"] = $this->beanClass($module, $key);
            $files["{$dir}/vardefs.php"] = $this->vardefs($app, $module, $key);
            $files["{$dir}/Menu.php"] = $this->menu($key);
            $files["{$dir}/language/en_us.lang.php"] = $this->moduleLanguage($module);
            $files["{$dir}/metadata/listviewdefs.php"] = $this->listViewDefs($app, $module, $key);
            $files["{$dir}/metadata/editviewdefs.php"] = $this->viewDefs($module, $key, 'EditView');
            $files["{$dir}/metadata/detailviewdefs.php"] = $this->viewDefs($module, $key, 'DetailView');
            $files["{$dir}/metadata/searchdefs.php"] = $this->searchDefs($key);
        }

        $files["custom/Extension/application/Ext/Include/{$app->slug}.php"] = $this->includeExtension($app);
        $files["custom/Extension/application/Ext/Language/en_us.{$app->slug}.php"] = $this->appLanguage($app);
        $files["custom/Extension/application/Ext/EntryPointRegistry/{$app->slug}_smart_gateway.php"] = $this->entryPoint();

        return $files;
    }

    private function beanClass(AppModule $module, string $key): string
    {
        $table = strtolower($key);
        $properties = $module->fields->reject(fn ($f) => in_array($f->name, ['name', 'description'], true))
            ->map(fn ($f) => '    public $'.($f->type === 'relate' ? $f->name.'_id' : $f->name).';')
            ->implode("\n");

        if ($module->is_journey) {
            $properties .= "\n    public \$journey_phase;\n    public \$journey_stage;\n    public \$journey_status;";
        }

        return <<<PHP
<?php

if (! defined('sugarEntry') || ! sugarEntry) {
    die('Not A Valid Entry Point');
}

class {$key} extends Basic
{
    public \$new_schema = true;
    public \$module_dir = '{$key}';
    public \$object_name = '{$key}';
    public \$table_name = '{$table}';
    public \$importable = true;
    public \$disable_row_level_security = false;

{$properties}

    public function bean_implements(\$interface)
    {
        return \$interface === 'ACL';
    }
}

PHP;
    }

    private function vardefs(SaasApp $app, AppModule $module, string $key): string
    {
        $fields = [];
        $relationships = [];
        $indices = [];

        foreach ($module->fields as $field) {
            if ($field->name === 'name') {
                continue; // The basic template already has a required name.
            }

            if ($field->type === 'relate') {
                $target = $app->modules->firstWhere('name', $field->type_arg);
                $targetKey = $target->crmKey($app->crmPrefix());
                $relationship = strtolower("{$key}_{$field->name}");

                $fields[$field->name.'_id'] = ['name' => $field->name.'_id', 'vname' => $this->lbl($field->name.'_id'), 'type' => 'id', 'required' => $field->is_required, 'reportable' => true];
                $fields[$field->name] = [
                    'name' => $field->name, 'vname' => $this->lbl($field->name), 'type' => 'relate', 'source' => 'non-db',
                    'id_name' => $field->name.'_id', 'module' => $targetKey, 'rname' => 'name', 'link' => $field->name.'_link',
                    'required' => $field->is_required, 'massupdate' => false,
                ];
                $fields[$field->name.'_link'] = ['name' => $field->name.'_link', 'type' => 'link', 'relationship' => $relationship, 'source' => 'non-db', 'module' => $targetKey, 'vname' => $this->lbl($field->name)];
                $relationships[$relationship] = [
                    'lhs_module' => $targetKey, 'lhs_table' => strtolower($targetKey), 'lhs_key' => 'id',
                    'rhs_module' => $key, 'rhs_table' => strtolower($key), 'rhs_key' => $field->name.'_id',
                    'relationship_type' => 'one-to-many',
                ];
                $indices[] = ['name' => 'idx_'.substr(strtolower($key), 0, 30).'_'.substr($field->name, 0, 20), 'type' => 'index', 'fields' => [$field->name.'_id', 'deleted']];

                continue;
            }

            $def = ['name' => $field->name, 'vname' => $this->lbl($field->name), 'required' => $field->is_required, 'audited' => true, 'reportable' => true]
                + (self::VARDEF_TYPES[$field->type] ?? self::VARDEF_TYPES['varchar']);

            if ($field->type === 'enum') {
                $def['options'] = $this->enumDom($key, $field);
            }

            $fields[$field->name] = $def;
        }

        if ($module->is_journey) {
            foreach (['phase', 'stage', 'status'] as $part) {
                $fields["journey_{$part}"] = [
                    'name' => "journey_{$part}", 'vname' => $this->lbl("journey_{$part}"), 'type' => 'enum', 'len' => 60,
                    'options' => "{$app->crmPrefix()}_journey_{$part}_dom", 'audited' => true, 'massupdate' => false, 'reportable' => true,
                ];
            }

            $indices[] = ['name' => 'idx_'.substr(strtolower($key), 0, 30).'_journey', 'type' => 'index', 'fields' => ['journey_phase', 'journey_stage', 'journey_status', 'deleted']];
        }

        $dictionary = [
            'table' => strtolower($key),
            'audited' => true,
            'unified_search' => true,
            'duplicate_merge' => true,
            'optimistic_locking' => true,
            'fields' => $fields,
            'relationships' => $relationships,
            'indices' => $indices,
        ];

        return "<?php\n\n// Generated by the SaaS Builder. Real columns only: no studio custom fields (law L-DATA-001).\n"
            ."\$dictionary['{$key}'] = ".$this->export($dictionary).";\n\n"
            ."if (! class_exists('VardefManager')) {\n    require_once 'include/SugarObjects/VardefManager.php';\n}\n"
            ."VardefManager::createVardef('{$key}', '{$key}', ['basic', 'assignable', 'security_groups']);\n";
    }

    private function menu(string $key): string
    {
        return <<<PHP
<?php

if (! defined('sugarEntry') || ! sugarEntry) {
    die('Not A Valid Entry Point');
}

global \$mod_strings, \$app_strings;

if (ACLController::checkAccess('{$key}', 'edit', true)) {
    \$module_menu[] = ['index.php?module={$key}&action=EditView&return_module={$key}&return_action=DetailView', \$mod_strings['LNK_NEW_RECORD'], 'Add', '{$key}'];
}

if (ACLController::checkAccess('{$key}', 'list', true)) {
    \$module_menu[] = ['index.php?module={$key}&action=index&return_module={$key}&return_action=DetailView', \$mod_strings['LNK_LIST'], 'View', '{$key}'];
}

PHP;
    }

    private function moduleLanguage(AppModule $module): string
    {
        $singular = rtrim($module->name, 's');
        $strings = [
            'LBL_MODULE_NAME' => $module->name,
            'LBL_MODULE_TITLE' => $module->name,
            'LBL_MODULE_ID' => $module->name,
            'LBL_LIST_FORM_TITLE' => $module->name,
            'LBL_SEARCH_FORM_TITLE' => "Search {$module->name}",
            'LBL_HOMEPAGE_TITLE' => "My {$module->name}",
            'LNK_NEW_RECORD' => "Create {$singular}",
            'LNK_LIST' => "View {$module->name}",
            'LBL_NAME' => 'Name',
            'LBL_DATE_ENTERED' => 'Date Created',
        ];

        foreach ($module->fields as $field) {
            $strings[$this->lbl($field->name)] = $field->label;

            if ($field->type === 'relate') {
                $strings[$this->lbl($field->name.'_id')] = $field->label.' ID';
            }
        }

        if ($module->is_journey) {
            $strings['LBL_JOURNEY_PHASE'] = 'Phase';
            $strings['LBL_JOURNEY_STAGE'] = 'Stage';
            $strings['LBL_JOURNEY_STATUS'] = 'Status';
        }

        return "<?php\n\n\$mod_strings = ".$this->export($strings).";\n";
    }

    /** L-UI-001: Date Created is always the first column. */
    private function listViewDefs(SaasApp $app, AppModule $module, string $key): string
    {
        $columns = ['DATE_ENTERED' => ['width' => '10', 'label' => 'LBL_DATE_ENTERED', 'default' => true]];
        $columns['NAME'] = ['width' => '25', 'label' => 'LBL_NAME', 'link' => true, 'default' => true];

        foreach ($module->fields->reject(fn ($f) => in_array($f->name, ['name', 'description'], true) || $f->type === 'text')->take(4) as $field) {
            $columns[strtoupper($field->name)] = array_filter([
                'width' => '15', 'label' => $this->lbl($field->name), 'default' => true,
                'id' => $field->type === 'relate' ? strtoupper($field->name.'_id') : null,
                'module' => $field->type === 'relate' ? $app->modules->firstWhere('name', $field->type_arg)?->crmKey($app->crmPrefix()) : null,
            ], fn ($v) => $v !== null);
        }

        if ($module->is_journey) {
            $columns['JOURNEY_STAGE'] = ['width' => '12', 'label' => 'LBL_JOURNEY_STAGE', 'default' => true];
            $columns['JOURNEY_STATUS'] = ['width' => '12', 'label' => 'LBL_JOURNEY_STATUS', 'default' => true];
        }

        $columns['ASSIGNED_USER_NAME'] = ['width' => '10', 'label' => 'LBL_ASSIGNED_TO_NAME', 'module' => 'Employees', 'id' => 'ASSIGNED_USER_ID', 'default' => true];

        return "<?php\n\n// Law L-UI-001: Date Created is always the first column.\n\$listViewDefs['{$key}'] = ".$this->export($columns).";\n";
    }

    private function viewDefs(AppModule $module, string $key, string $view): string
    {
        $names = $module->fields->map(fn (AppModuleField $f) => $f->name)->all();

        if ($module->is_journey && $view === 'DetailView') {
            array_push($names, 'journey_phase', 'journey_stage', 'journey_status');
        }

        $names[] = 'assigned_user_name';
        $rows = array_map(fn ($pair) => array_pad($pair, 2, ''), array_chunk($names, 2));

        $buttons = $view === 'DetailView' ? ['EDIT', 'DUPLICATE', 'DELETE'] : ['SAVE', 'CANCEL'];
        $defs = [
            'templateMeta' => ['form' => ['buttons' => $buttons], 'maxColumns' => '2', 'widths' => [['label' => '10', 'field' => '30'], ['label' => '10', 'field' => '30']]],
            'panels' => ['default' => $rows],
        ];

        return "<?php\n\n\$viewdefs['{$key}']['{$view}'] = ".$this->export($defs).";\n";
    }

    private function searchDefs(string $key): string
    {
        $defs = [
            'templateMeta' => ['maxColumns' => '3', 'widths' => ['label' => '10', 'field' => '30']],
            'layout' => [
                'basic_search' => ['name', ['name' => 'current_user_only', 'label' => 'LBL_CURRENT_USER_FILTER', 'type' => 'bool']],
                'advanced_search' => ['name', ['name' => 'assigned_user_id', 'label' => 'LBL_ASSIGNED_TO', 'type' => 'enum', 'function' => ['name' => 'get_user_array', 'params' => [false]]]],
            ],
        ];

        return "<?php\n\n\$searchdefs['{$key}'] = ".$this->export($defs).";\n";
    }

    private function includeExtension(SaasApp $app): string
    {
        $lines = ["<?php\n", "// Registers the {$app->name} modules with the CRM."];

        foreach ($app->modules as $module) {
            $key = $module->crmKey($app->crmPrefix());
            $lines[] = "\$beanList['{$key}'] = '{$key}';";
            $lines[] = "\$beanFiles['{$key}'] = 'modules/{$key}/{$key}.php';";
            $lines[] = "\$moduleList[] = '{$key}';";
        }

        return implode("\n", $lines)."\n";
    }

    private function appLanguage(SaasApp $app): string
    {
        $prefix = $app->crmPrefix();
        $out = "<?php\n\n// Module names, enum options and the {$app->name} journey as CRM dropdowns.\n";

        foreach ($app->modules as $module) {
            $out .= "\$app_list_strings['moduleList']['{$module->crmKey($prefix)}'] = ".var_export($module->name, true).";\n";

            foreach ($module->fields->where('type', 'enum') as $field) {
                $options = array_combine($field->enumOptions(), $field->enumOptions());
                $out .= "\$app_list_strings['{$this->enumDom($module->crmKey($prefix), $field)}'] = ".$this->export(['' => ''] + $options).";\n";
            }
        }

        $phases = $stages = $statuses = ['' => ''];

        foreach ($app->phases as $phase) {
            $phases[Naming::key($phase->name)] = $phase->name;

            foreach ($phase->stages as $stage) {
                $stages[Naming::key($stage->name)] = $stage->name;

                foreach ($stage->statuses as $status) {
                    $statuses[Naming::key($status->name)] = $status->name;
                }
            }
        }

        $out .= "\$app_list_strings['{$prefix}_journey_phase_dom'] = ".$this->export($phases).";\n";
        $out .= "\$app_list_strings['{$prefix}_journey_stage_dom'] = ".$this->export($stages).";\n";
        $out .= "\$app_list_strings['{$prefix}_journey_status_dom'] = ".$this->export($statuses).";\n";

        return $out;
    }

    private function entryPoint(): string
    {
        return <<<'PHP'
<?php

// The Smart Gateway checks its own tokens, so the CRM session check is off for this entry point only.
$entry_point_registry['smart_gateway'] = [
    'file' => 'custom/smart_gateway/entry.php',
    'auth' => false,
];

PHP;
    }

    private function enumDom(string $key, AppModuleField $field): string
    {
        return strtolower($key).'_'.$field->name.'_dom';
    }

    private function lbl(string $name): string
    {
        return 'LBL_'.strtoupper($name);
    }

    /** var_export with short arrays and 4-space indents. */
    private function export(array $value, int $depth = 0): string
    {
        if ($value === []) {
            return '[]';
        }

        $pad = str_repeat('    ', $depth + 1);
        $isList = array_is_list($value);
        $lines = [];

        foreach ($value as $k => $v) {
            $item = is_array($v) ? $this->export($v, $depth + 1) : var_export($v, true);
            $lines[] = $pad.($isList ? '' : var_export($k, true).' => ').$item.',';
        }

        return "[\n".implode("\n", $lines)."\n".str_repeat('    ', $depth).']';
    }
}
