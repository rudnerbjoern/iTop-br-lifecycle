<?php

declare(strict_types=1);

// Minimal in-memory substitutes; OQL is captured, not evaluated.
class Config {}
class ModuleInstallerAPI {}
class SetupWebPage
{
    public static function AddModule(...$arguments): void {}
}
class SetupLog
{
    public static function Info($message): void {}
}
class AuditObject
{
    public function __construct(public string $class, public array $values, private int $id) {}
    public function GetKey(): int { return $this->id; }
    public function Set($key, $value): void { $this->values[$key] = $value; }
    public function DBWrite(): void { MetaModel::$objects[$this->id] = $this; }
}
class MetaModel
{
    public static array $objects = [];
    public static bool $available = true;
    public static function IsValidClass($class): bool { return self::$available; }
    public static function NewObject($class, $values): AuditObject
    {
        return new AuditObject($class, $values, count(self::$objects) + 1);
    }
}
class DBObjectSearch
{
    public static function FromOQL($query): string { return $query; }
}
class DBObjectSet
{
    public function __construct(private string $query) {}
    public function Fetch(): ?AuditObject
    {
        if (!preg_match('/^SELECT (AuditCategory|AuditDomain) WHERE name = "([^"]+)"$/', $this->query, $match)) {
            throw new RuntimeException('Unexpected lookup: ' . $this->query);
        }
        foreach (MetaModel::$objects as $object) {
            if ($object->class === $match[1] && $object->values['name'] === $match[2]) {
                return $object;
            }
        }
        return null;
    }
}
require __DIR__ . '/../br-lifecycle/module.br-lifecycle.php';

return [
    'fresh installation creates complete audit queries and domain links' => static function (): void {
        MetaModel::$objects = [];
        MetaModel::$available = true;
        LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '0.0.0', '3.2.2');
        $groups = [
            'Physical Device Lifecycle' => ['PhysicalDevice', 'pd', 'Model', 'm', 'model_id', ['EoL' => 'eol', 'EoSL' => 'eosl']],
            'Server OS Version Lifecycle' => ['Server', 's', 'OSVersion', 'osv', 'osversion_id', ['EoMSS' => 'eomss', 'EoL' => 'eol', 'EoESU' => 'eoesu']],
            'Virtual Machine OS Version Lifecycle' => ['VirtualMachine', 'vm', 'OSVersion', 'osv', 'osversion_id', ['EoMSS' => 'eomss', 'EoL' => 'eol', 'EoESU' => 'eoesu']],
            'PC OS Version Lifecycle' => ['PC', 'pc', 'OSVersion', 'osv', 'osversion_id', ['EoMSS' => 'eomss', 'EoL' => 'eol', 'EoESU' => 'eoesu']],
        ];
        $objects = array_values(MetaModel::$objects);
        $domains = array_values(array_filter($objects, static fn ($o) => $o->class === 'AuditDomain'));
        if (count($objects) !== 57 || count($domains) !== 1 || $domains[0]->values['name'] !== 'Lifecycle Management') {
            throw new RuntimeException('Expected 4 categories, 48 rules, 1 domain and 4 links.');
        }
        foreach ($groups as $name => [$class, $alias, $target, $ref, $key, $fields]) {
            $category = array_values(array_filter($objects, static fn ($o) => $o->class === 'AuditCategory' && $o->values['name'] === $name));
            $base = "SELECT $alias,$ref FROM $class AS $alias JOIN $target AS $ref ON $alias.$key = $ref.id\nWHERE $alias.status='production' AND $alias.$key != 0";
            if (count($category) !== 1 || $category[0]->values['definition_set'] !== $base) {
                throw new RuntimeException("Invalid category: $name");
            }
            $expected = ['00 - Outdated' => '(' . implode(' OR ', array_map(static fn ($f) => "$ref.$f < NOW()", array_values($fields))) . ')'];
            foreach ($fields as $label => $field) {
                $expected["01 - $label outdated"] = "$ref.$field < NOW()";
                $expected["02 - $label in 30 days"] = "$ref.$field > NOW() AND $ref.$field < DATE_ADD(NOW(), INTERVAL 30 DAY)";
                foreach (['this' => 'NOW()', 'next' => 'DATE_ADD(NOW(), INTERVAL 1 YEAR)'] as $period => $date) {
                    $expected["0" . ($period === 'this' ? '3' : '4') . " - $label $period year"] = "$ref.$field > DATE_FORMAT($date,'%Y-01-01 00:00:00') AND $ref.$field < DATE_FORMAT($date,'%Y-12-31 23:59:59')";
                }
            }
            foreach ($objects as $object) {
                if ($object->class === 'AuditRule' && $object->values['category_id'] === $category[0]->GetKey()) {
                    $rule = $object->values;
                    if (!isset($expected[$rule['name']]) || $rule['query'] !== $base . "\nAND " . $expected[$rule['name']] || $rule['valid_flag'] !== 'false') {
                        throw new RuntimeException("Invalid audit rule in $name: " . $rule['name']);
                    }
                    unset($expected[$rule['name']]);
                }
            }
            $links = array_filter($objects, static fn ($o) => $o->class === 'lnkAuditCategoryToAuditDomain'
                && $o->values['category_id'] === $category[0]->GetKey() && $o->values['domain_id'] === $domains[0]->GetKey());
            if ($expected !== [] || count($links) !== 1) {
                throw new RuntimeException("Missing audit rules or domain link: $name");
            }
        }
    },
    'upgrade from 3.2.0 leaves existing audit objects unchanged' => static function (): void {
        MetaModel::$available = true;
        MetaModel::$objects = [1 => new AuditObject('AuditCategory', ['name' => 'User-maintained category'], 1)];
        $before = serialize(MetaModel::$objects);
        LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '3.2.0', '3.2.2');
        if (serialize(MetaModel::$objects) !== $before) {
            throw new RuntimeException('Upgrade changed existing audit objects.');
        }
    },
    'installation without audit classes skips audit creation' => static function (): void {
        MetaModel::$objects = [];
        MetaModel::$available = false;
        try {
            LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '0.0.0', '3.2.2');
            if (MetaModel::$objects !== []) {
                throw new RuntimeException('Audit objects created without audit classes.');
            }
        } finally {
            MetaModel::$available = true;
        }
    },
];
