<?php

namespace SmartCore\Gateway;

final class ModuleRegistry
{
    /** @var array<string, ModuleDefinition> */
    private array $modules = [];

    /** @param list<ModuleDefinition> $modules */
    public function __construct(array $modules)
    {
        foreach ($modules as $module) {
            $this->modules[$module->name] = $module;
        }
    }

    public static function fromConfig(array $modules): self
    {
        return new self(array_map([ModuleDefinition::class, 'fromArray'], $modules));
    }

    public function get(?string $name): ModuleDefinition
    {
        if ($name === null || ! isset($this->modules[$name])) {
            throw new GatewayException('unknown_module', "There is no module called \"{$name}\".", 404);
        }

        return $this->modules[$name];
    }

    /** @return list<ModuleDefinition> */
    public function all(): array
    {
        return array_values($this->modules);
    }

    public function journeyModule(): ?ModuleDefinition
    {
        foreach ($this->modules as $module) {
            if ($module->journey) {
                return $module;
            }
        }

        return null;
    }
}
