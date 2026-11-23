<?php

declare(strict_types=1);

namespace Combodo\iTop\Application\WebPage {
    class WebPage
    {
        public array $tabs = [];
        public array $lists = [];
        public function SetCurrentTab($key): void { $this->tabs[] = $key; }
    }
}

namespace {
    use Combodo\iTop\Application\WebPage\WebPage;

    abstract class AbstractApplicationUIExtension {}
    class OSVersion
    {
        public function __construct(private int $id) {}
        public function GetKey(): int { return $this->id; }
        public function IsNew(): bool { return $this->id === 0; }
    }
    const UR_ACTION_READ = 1;
    class UserRights
    {
        public static array $readable = [];
        public static function IsActionAllowed($class, $action): bool
        {
            if (!MetaModel::IsValidClass($class)) {
                throw new RuntimeException("Checking rights for uninstalled class $class");
            }
            return $action === UR_ACTION_READ && in_array($class, self::$readable, true);
        }
    }
    class DisplayBlock
    {
        public function __construct(private string $query, private string $style) {}
        public function Display(WebPage $page, string $id, array $params): void
        {
            $page->lists[] = [$this->query, $this->style, $params['menu']];
        }
    }

    return [
        'OS version registers one device tab and drops the three old lists' => static function (): void {
            if (!in_array('OSVersionDevicesTab.php', SetupWebPage::$datamodel, true)) {
                throw new RuntimeException('The module must load the OS version devices tab.');
            }
            $model = simplexml_load_file(__DIR__ . '/../br-lifecycle/datamodel.br-lifecycle.xml');
            $version = $model->xpath("/itop_design/classes/class[@id='OSVersion']")[0];
            foreach (['server_list', 'virtualmachine_list', 'pc_list'] as $field) {
                if ($version->xpath(".//*[@id='$field']")) {
                    throw new RuntimeException("Unexpected legacy list: $field");
                }
            }
        },
        'OS version lists readable installed device classes for the current object' => static function (): void {
            require_once __DIR__ . '/../br-lifecycle/OSVersionDevicesTab.php';
            $tab = new LifecycleOSVersionDevicesTab();
            $classes = ['Server', 'VirtualMachine', 'PC'];
            $previousAvailability = MetaModel::$available;
            MetaModel::$available = true;
            try {
                foreach ([
                    [new OSVersion(42), [], $classes, $classes],
                    [new OSVersion(12), ['VirtualMachine'], $classes, ['Server', 'PC']],
                    [new OSVersion(19), ['PC', 'VirtualMachine'], $classes, ['Server']],
                    [new OSVersion(73), [], ['PC'], ['PC']],
                    [new OSVersion(42), [], [], []],
                    [new OSVersion(0), [], $classes, []],
                    [new stdClass(), [], $classes, []],
                ] as [$object, $missing, $readable, $expectedClasses]) {
                    MetaModel::$missingClasses = $missing;
                    UserRights::$readable = $readable;
                    $page = new WebPage();
                    $tab->OnDisplayRelations($object, $page);
                    $expectedTabs = $expectedClasses ? ['br-lifecycle:OSVersion:Devices'] : [];
                    $queries = [];
                    foreach ($expectedClasses as $class) {
                        $queries[] = "SELECT $class AS device WHERE device.osversion_id = " . $object->GetKey();
                    }
                    $expectedLists = $queries ? [[implode(' UNION ', $queries), 'list', false]] : [];
                    if ($page->tabs !== $expectedTabs || $page->lists !== $expectedLists) {
                        throw new RuntimeException('Unexpected OS version device tabs or list queries: ' . json_encode($page));
                    }
                }
            } finally {
                MetaModel::$missingClasses = [];
                MetaModel::$available = $previousAvailability;
                UserRights::$readable = [];
            }
        },
    ];
}
