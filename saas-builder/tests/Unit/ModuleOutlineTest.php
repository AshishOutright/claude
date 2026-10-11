<?php

namespace Tests\Unit;

use App\Services\Builder\ModuleOutline;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ModuleOutlineTest extends TestCase
{
    public function test_it_reads_modules_fields_types_and_the_journey_module(): void
    {
        $modules = (new ModuleOutline())->parse("Employees*: email:email!, source:enum(Referral|Job Board), department:relate(Departments)!\nDepartments: code");

        $this->assertSame(['Employees', 'Departments'], array_column($modules, 'name'));
        $this->assertSame([true, false], array_column($modules, 'journey'));
        $this->assertSame(['name', 'email', 'source', 'department'], array_column($modules[0]['fields'], 'name'));
        $this->assertSame(['type' => 'enum', 'type_arg' => 'Referral|Job Board'], array_intersect_key($modules[0]['fields'][2], ['type' => 1, 'type_arg' => 1]));
        $this->assertSame(['relate', 'Departments', true], [$modules[0]['fields'][3]['type'], $modules[0]['fields'][3]['type_arg'], $modules[0]['fields'][3]['required']]);
    }

    public function test_exactly_one_journey_module_is_needed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly one module with *');

        (new ModuleOutline())->parse("Tasks: due:date\nClients: email:email");
    }

    public function test_relate_must_point_at_a_listed_module(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not one of the modules');

        (new ModuleOutline())->parse('Tasks*: owner:relate(People)');
    }

    public function test_crm_column_names_are_reserved(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('the CRM already uses');

        (new ModuleOutline())->parse('Tasks*: date_entered:date');
    }

    public function test_unknown_types_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a field type');

        (new ModuleOutline())->parse('Tasks*: cost:money');
    }
}
