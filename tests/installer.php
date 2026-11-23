<?php

declare(strict_types=1);

// Minimal in-memory substitutes; OQL is captured, not evaluated.
class Config {}
class ModuleInstallerAPI {}
class SetupWebPage
{
    public static string $moduleId;
    public static array $dependencies;
    public static array $datamodel;

    public static function AddModule($file, string $moduleId, array $definition): void
    {
        self::$moduleId = $moduleId;
        self::$dependencies = $definition['dependencies'];
        self::$datamodel = $definition['datamodel'];
    }
}
class SetupLog
{
    public static array $messages = [];
    public static function Info($message): void { self::$messages[] = $message; }
}
class AuditObject
{
    public function __construct(public string $class, public array $values, private int $id) {}
    public function Get($key) { return $this->values[$key]; }
    public function GetKey(): int { return $this->id; }
    public function Set($key, $value): void { $this->values[$key] = $value; }
    public function DBWrite(): void { MetaModel::$objects[$this->id] = $this; }
}
class MetaModel
{
    public static array $objects = [];
    public static bool $available = true;
    public static array $missingClasses = [];
    public static function DBGetTable(string $class): string
    {
        return match ($class) {
            'Model' => 'fixture_model',
            'OSVersion' => 'fixture_osversion',
            default => throw new RuntimeException("Unexpected table for $class"),
        };
    }
    public static function IsValidClass($class): bool
    {
        return self::$available && !in_array($class, self::$missingClasses, true);
    }
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
    private int $position = 0;
    public function __construct(private string $query, array $order = [], private array $args = []) {}
    public function Fetch(): ?AuditObject
    {
        if ($this->query === 'SELECT AuditRule WHERE category_id = :category') {
            $matches = array_values(array_filter(MetaModel::$objects, fn ($object) =>
                $object->class === 'AuditRule' && $object->Get('category_id') === $this->args['category']));
            return $matches[$this->position++] ?? null;
        }
        if ($this->query === 'SELECT AuditCategory WHERE name = :name') {
            foreach (MetaModel::$objects as $object) {
                if ($object->class === 'AuditCategory' && $object->Get('name') === $this->args['name']) {
                    return $object;
                }
            }
            return null;
        }
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
// Execute the migration statements against real rows in a disposable SQLite database.
class CMDBSource
{
    private static PDO $db;
    private static int $affected;

    public static function reset(): void
    {
        self::$db = new PDO('sqlite::memory:');
        self::$db->exec('CREATE TABLE fixture_osversion (id INTEGER PRIMARY KEY, eol DATE, end_of_support DATE, eomss DATE, eoesu DATE)');
        self::$db->exec('CREATE TABLE fixture_model (id INTEGER PRIMARY KEY, eosl DATE, end_of_support DATE, eol DATE)');
    }

    public static function QueryToArray(string $sql): array
    {
        return self::$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function Query(string $sql): void { self::$affected = self::$db->exec($sql); }
    public static function AffectedRows(): int { return self::$affected; }
}

require __DIR__ . '/../br-lifecycle/module.br-lifecycle.php';

$manifest = simplexml_load_file(__DIR__ . '/../extension.xml');
$currentVersion = trim((string) $manifest->version);

return [
    'module registration and manifest agree on version and iTop minimum' => static function () use ($manifest, $currentVersion): void {
        $minimum = trim((string) $manifest->itop_version_min);
        if ($minimum !== '3.3.0' || version_compare($currentVersion, '3.3.0', '<')
            || SetupWebPage::$moduleId !== 'br-lifecycle/' . $currentVersion
            || !in_array('itop-config-mgmt/' . $minimum, SetupWebPage::$dependencies, true)) {
            throw new RuntimeException('Unexpected lifecycle module version or minimum iTop dependency.');
        }
    },
    'fresh installation creates complete audit queries and domain links' => static function () use ($currentVersion): void {
        MetaModel::$objects = [];
        MetaModel::$available = true;
        LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '0.0.0', $currentVersion);
        $groups = [
            'Physical Device Lifecycle' => ['PhysicalDevice', 'pd', 'Model', 'm', 'model_id', ['EoL' => 'eol', 'EoSL' => 'end_of_support']],
            'Server OS Version Lifecycle' => ['Server', 's', 'OSVersion', 'osv', 'osversion_id', ['EoMSS' => 'end_of_support', 'EoL' => 'eol', 'EoESU' => 'eoesu']],
            'Virtual Machine OS Version Lifecycle' => ['VirtualMachine', 'vm', 'OSVersion', 'osv', 'osversion_id', ['EoMSS' => 'end_of_support', 'EoL' => 'eol', 'EoESU' => 'eoesu']],
            'PC OS Version Lifecycle' => ['PC', 'pc', 'OSVersion', 'osv', 'osversion_id', ['EoMSS' => 'end_of_support', 'EoL' => 'eol', 'EoESU' => 'eoesu']],
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
    'fresh installs create OS audits only for installed device classes' => static function () use ($currentVersion): void {
        foreach ([['PC'], ['VirtualMachine'], ['PC', 'VirtualMachine']] as $missing) {
            MetaModel::$objects = [];
            MetaModel::$missingClasses = $missing;
            try {
                LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '', $currentVersion);
                $categories = [];
                $ruleCount = $linkCount = 0;
                foreach (MetaModel::$objects as $object) {
                    if ($object->class === 'AuditCategory') {
                        $categories[] = $object->Get('name');
                    } elseif ($object->class === 'AuditRule') {
                        $ruleCount++;
                    } elseif ($object->class === 'lnkAuditCategoryToAuditDomain') {
                        $linkCount++;
                    }
                }
                $expected = ['Physical Device Lifecycle', 'Server OS Version Lifecycle'];
                foreach (['VirtualMachine' => 'Virtual Machine', 'PC' => 'PC'] as $class => $label) {
                    if (!in_array($class, $missing, true)) {
                        $expected[] = "$label OS Version Lifecycle";
                    }
                }
                sort($categories);
                sort($expected);
                if ($categories !== $expected || $ruleCount !== 48 - 13 * count($missing) || $linkCount !== count($expected)) {
                    throw new RuntimeException('Unexpected audits without ' . implode(', ', $missing));
                }
            } finally {
                MetaModel::$missingClasses = [];
                MetaModel::$objects = [];
            }
        }
    },
    'model upgrade copies only populated legacy dates into empty targets' => static function () use ($currentVersion): void {
        CMDBSource::reset();
        CMDBSource::Query("INSERT INTO fixture_model VALUES
            (1,'2030-02-01',NULL,'2028-01-01'),
            (2,'2030-02-01','2031-03-02',NULL),
            (3,'2030-02-01','2030-02-01',NULL),
            (4,NULL,'2032-04-03',NULL),
            (5,NULL,NULL,NULL),
            (6,'2033-01-01','0000-00-00',NULL),
            (7,'0000-00-00',NULL,NULL)");
        $select = 'SELECT * FROM fixture_model ORDER BY id';
        $expected = CMDBSource::QueryToArray($select);
        SetupLog::$messages = [];
        LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), $currentVersion, $currentVersion);
        if (CMDBSource::QueryToArray($select) !== $expected || SetupLog::$messages !== []) {
            throw new RuntimeException('Setup at the current version must skip the legacy migration.');
        }
        $expected[0]['end_of_support'] = $expected[0]['eosl'];
        $expected[5]['end_of_support'] = $expected[5]['eosl'];
        LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '3.2.3', $currentVersion);
        if (CMDBSource::QueryToArray($select) !== $expected || count(SetupLog::$messages) !== 2) {
            throw new RuntimeException('Incorrect migrated dates or number of skipped models.');
        }
        foreach ([2 => '2031-03-02', 3 => '2030-02-01'] as $id => $date) {
            $log = implode("\n", SetupLog::$messages);
            if (!str_contains($log, "Model #$id skipped EOSL migration. Keeping end_of_support=$date; legacy eosl=2030-02-01")) {
                throw new RuntimeException("Missing targeted log entry for Model #$id.");
            }
        }
        LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '3.2.3', $currentVersion);
        if (CMDBSource::QueryToArray($select) !== $expected) {
            throw new RuntimeException('Repeating the migration changed model data.');
        }
    },
    'OS upgrade migrates only legacy mainstream support and logs occupied native dates' => static function () use ($currentVersion): void {
        CMDBSource::reset();
        MetaModel::$objects = [];
        CMDBSource::Query("INSERT INTO fixture_osversion (id, eomss, end_of_support, eol, eoesu) VALUES
            (1,'2030-02-01',NULL,'2032-01-01','2034-01-01'),
            (2,'2030-02-01','2031-03-02',NULL,'2034-01-01'),
            (3,'2030-02-01','2030-02-01',NULL,NULL),
            (4,NULL,NULL,'2032-01-01','2034-01-01'),
            (5,'2030-02-01','0000-00-00',NULL,NULL),
            (6,'0000-00-00',NULL,NULL,NULL),
            (7,'2029-01-01','2032-01-01','2032-01-01','2034-01-01')");
        $select = 'SELECT * FROM fixture_osversion ORDER BY id';
        $expected = CMDBSource::QueryToArray($select);
        $expected[0]['end_of_support'] = $expected[0]['eomss'];
        $expected[4]['end_of_support'] = $expected[4]['eomss'];
        SetupLog::$messages = [];
        LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '3.2.3', $currentVersion);
        $messages = SetupLog::$messages;
        sort($messages);
        if (CMDBSource::QueryToArray($select) !== $expected || $messages !== [
            'br-lifecycle: OSVersion #2 skipped EOMSS migration. Keeping end_of_support=2031-03-02; legacy eomss=2030-02-01.',
            'br-lifecycle: OSVersion #3 skipped EOMSS migration. Keeping end_of_support=2030-02-01; legacy eomss=2030-02-01.',
            'br-lifecycle: OSVersion #7 skipped EOMSS migration. Keeping end_of_support=2032-01-01; legacy eomss=2029-01-01.',
        ]) {
            throw new RuntimeException('Unexpected OS dates or skip messages.');
        }
        LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '3.2.3', $currentVersion);
        if (CMDBSource::QueryToArray($select) !== $expected) {
            throw new RuntimeException('Repeating the OS migration changed dates.');
        }
    },
    'upgrade updates the five model and fifteen OS queries by category and name' => static function () use ($currentVersion): void {
        CMDBSource::reset();
        MetaModel::$objects = [];
        MetaModel::$available = true;
        LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '0.0.0', $currentVersion);
        $categories = new DBObjectSet('SELECT AuditCategory WHERE name = "Physical Device Lifecycle"');
        $categoryId = $categories->Fetch()->GetKey();
        $canonical = [];
        foreach (MetaModel::$objects as $rule) {
            if ($rule->class !== 'AuditRule') {
                continue;
            }
            $osRule = str_contains($rule->Get('query'), 'osv.end_of_support');
            if ($osRule || ($rule->Get('category_id') === $categoryId && str_contains($rule->Get('query'), 'm.end_of_support'))) {
                $canonical[$rule->GetKey()] = $rule->Get('query');
                // One customized query demonstrates that matching is based on the rule name.
                $rule->Set('query', in_array($rule->Get('name'), ['01 - EoSL outdated', '01 - EoMSS outdated'], true)
                    ? 'SELECT PhysicalDevice WHERE id = 42'
                    : str_replace(['m.end_of_support', 'osv.end_of_support'], ['m.eosl', 'osv.eomss'], $rule->Get('query')));
                $rule->Set('description', 'Operator description');
                $rule->Set('contact_id', 123);
            } else {
                $rule->Set('query', 'SELECT PhysicalDevice WHERE id = 99');
            }
        }
        $renamed = MetaModel::NewObject('AuditRule', [
            'name' => '02 - EoSL in 30 days (custom)', 'category_id' => $categoryId, 'query' => 'SELECT Model',
        ]);
        $renamed->DBWrite();
        $pcCategory = (new DBObjectSet('SELECT AuditCategory WHERE name = "PC OS Version Lifecycle"'))->Fetch();
        MetaModel::NewObject('AuditRule', [
            'name' => '01 - EoMSS outdated (custom)', 'category_id' => $pcCategory->GetKey(), 'query' => 'SELECT PC',
        ])->DBWrite();
        $otherCategory = MetaModel::NewObject('AuditCategory', ['name' => 'Other OS audits']);
        $otherCategory->DBWrite();
        MetaModel::NewObject('AuditRule', [
            'name' => '01 - EoMSS outdated', 'category_id' => $otherCategory->GetKey(), 'query' => 'SELECT Server',
        ])->DBWrite();
        $expected = array_map(static fn ($object) => $object->values, MetaModel::$objects);
        foreach ($canonical as $id => $query) {
            $expected[$id]['query'] = $query;
        }
        SetupLog::$messages = [];
        LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '3.2.3', $currentVersion);
        if (count($canonical) !== 20 || count(SetupLog::$messages) !== 20
            || $expected !== array_map(static fn ($object) => $object->values, MetaModel::$objects)) {
            throw new RuntimeException('Upgrade must update only the 20 named queries on their existing rule IDs.');
        }
        // A retry must neither rewrite current queries nor recreate a removed rule.
        $removedId = array_key_first($canonical);
        unset(MetaModel::$objects[$removedId], $expected[$removedId]);
        SetupLog::$messages = [];
        LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '3.2.3', $currentVersion);
        if (SetupLog::$messages !== [] || $expected !== array_map(static fn ($object) => $object->values, MetaModel::$objects)) {
            throw new RuntimeException('Retry changed existing rules or recreated a removed rule.');
        }
    },
    'upgrade from 3.2.0 leaves unrelated audit objects unchanged' => static function () use ($currentVersion): void {
        MetaModel::$available = true;
        MetaModel::$objects = [1 => new AuditObject('AuditCategory', ['name' => 'User-maintained category'], 1)];
        CMDBSource::reset();
        $before = serialize(MetaModel::$objects);
        LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '3.2.0', $currentVersion);
        if (serialize(MetaModel::$objects) !== $before) {
            throw new RuntimeException('Upgrade changed existing audit objects.');
        }
    },
    'installation without audit classes skips audit creation' => static function () use ($currentVersion): void {
        MetaModel::$objects = [];
        MetaModel::$available = false;
        try {
            LifeCycleManagementInstaller::AfterDatabaseCreation(new Config(), '0.0.0', $currentVersion);
            if (MetaModel::$objects !== []) {
                throw new RuntimeException('Audit objects created without audit classes.');
            }
        } finally {
            MetaModel::$available = true;
        }
    },
];
