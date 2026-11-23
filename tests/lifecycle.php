<?php

declare(strict_types=1);

return [
    'lifecycle dates are optional and legacy dates are hidden' => static function (): void {
        $model = simplexml_load_file(__DIR__ . '/../br-lifecycle/datamodel.br-lifecycle.xml');
        foreach (['Model' => ['eol', 'eosl'], 'OSVersion' => ['eomss', 'eol', 'eoesu']] as $class => $fields) {
            foreach ($fields as $field) {
                $nodes = $model->xpath("/itop_design/classes/class[@id='$class']/fields/field[@id='$field']");
                if (count($nodes) !== 1 || (string) $nodes[0]->attributes('xsi', true)->type !== 'AttributeDate'
                    || (string) $nodes[0]->sql !== $field || (string) $nodes[0]->is_null_allowed !== 'true') {
                    throw new RuntimeException("Invalid lifecycle date: $class.$field");
                }
                foreach (['details', 'summary'] as $view) {
                    $legacyField = ($class === 'Model') ? 'eosl' : 'eomss';
                    $expected = ($field === $legacyField) ? 0 : 1;
                    if (count($model->xpath("/itop_design/classes/class[@id='$class']/presentation/$view//item[@id='$field']")) !== $expected) {
                        throw new RuntimeException("Missing $view presentation for $class.$field");
                    }
                }
            }
        }
    },
    'device dates follow the iTop 3.3 model support date' => static function (): void {
        $xml = simplexml_load_file(__DIR__ . '/../br-lifecycle/datamodel.br-lifecycle.xml');
        $classes = '/itop_design/classes/class';
        $field = $xml->xpath($classes . "[@id='PhysicalDevice']/fields/field[@id='model_eol']")[0];
        if ((string) $field->attributes('xsi', true)->type !== 'AttributeExternalField'
            || (string) $field->extkey_attcode !== 'model_id' || (string) $field->target_attcode !== 'eol') {
            throw new RuntimeException('Model end of life must read through the device model.');
        }
        $model = $xml->xpath($classes . "[@id='Model']/presentation")[0];
        if ($model->xpath(".//item[@id='eosl']") || (string) $model->xpath("details/items/item[@id='eol']/rank")[0] !== '26') {
            throw new RuntimeException('Incorrect model date placement.');
        }
        $groups = [
            'fieldset:Server:Date' => ['Server', 'NetworkDevice', 'NAS'],
            'fieldset:ConfigMgmt:dates' => ['Rack', 'Enclosure', 'PowerSource', 'PDU', 'Phone', 'MobilePhone', 'IPPhone',
                'Tablet', 'PC', 'Printer', 'Peripheral', 'StorageSystem', 'SANSwitch', 'TapeLibrary'],
        ];
        foreach ($groups as $fieldset => $devices) {
            foreach ($devices as $device) {
                $items = $xml->xpath($classes . "[@id='$device']/presentation/details/items/item[@id='col:col2']"
                    . "/items/item[@id='$fieldset']/items/item[@id='model_eol']");
                if (count($items) !== 1 || (string) $items[0]->rank !== '41') {
                    throw new RuntimeException("Incorrect model end-of-life placement for $device.");
                }
            }
        }
    },
    'custom lifecycle dates are search criteria and optional list columns' => static function (): void {
        $xml = simplexml_load_file(__DIR__ . '/../br-lifecycle/datamodel.br-lifecycle.xml');
        foreach (['Model' => ['eol'], 'OSVersion' => ['eol', 'eoesu']] as $class => $dates) {
            $view = $xml->xpath("/itop_design/classes/class[@id='$class']/presentation")[0];
            foreach ($dates as $date) {
                foreach (['search', 'default_search'] as $search) {
                    if (count($view->xpath("$search/items/item[@id='$date']")) !== 1) {
                        throw new RuntimeException("Missing $class.$date in $search");
                    }
                }
                if ($view->xpath("list/items/item[@id='$date']")) {
                    throw new RuntimeException("$class.$date must be an optional list column.");
                }
            }
        }
    },
    'OS version presents native mainstream support before extended support and ESU' => static function (): void {
        $xml = simplexml_load_file(__DIR__ . '/../br-lifecycle/datamodel.br-lifecycle.xml');
        $view = $xml->xpath("/itop_design/classes/class[@id='OSVersion']/presentation")[0];
        foreach (['details', 'default_search'] as $nativeView) {
            // iTop provides the mainstream support end at rank 30 in each of these views.
            $items = $view->{$nativeView}->items;
            if ($items->xpath("item[@id='end_of_support']")
                || (string) $items->xpath("item[@id='eol']/rank")[0] !== '31'
                || (string) $items->xpath("item[@id='eoesu']/rank")[0] !== '32') {
                throw new RuntimeException("Unexpected OS lifecycle placement in $nativeView.");
            }
        }
        if ($view->xpath(".//item[@id='eomss']")
            || (string) $view->xpath("search/items/item[@id='end_of_support']/rank")[0] !== '30'
            || (string) $view->xpath("summary/items/item[@id='end_of_support']/rank")[0] !== '20'
            || (string) $view->xpath("summary/items/item[@id='eol']/rank")[0] !== '30') {
            throw new RuntimeException('Unexpected OS lifecycle presentation.');
        }
    },
    'linked devices display extended support and ESU after native OS support' => static function (): void {
        $xml = simplexml_load_file(__DIR__ . '/../br-lifecycle/datamodel.br-lifecycle.xml');
        foreach (['Server' => ['fieldset:Server:Date', 50], 'PC' => ['fieldset:ConfigMgmt:dates', 50],
            'VirtualMachine' => ['fieldset:ConfigMgmt:dates', 20]] as $class => [$fieldset, $supportRank]) {
            $device = $xml->xpath("/itop_design/classes/class[@id='$class']")[0];
            $group = "presentation/details/items/item[@id='col:col2']/items/item[@id='$fieldset']/items";
            foreach (['eol' => 1, 'eoesu' => 2] as $id => $offset) {
                $fields = $device->xpath("fields/field[@id='os_$id']");
                $ranks = $device->xpath("$group/item[@id='os_$id']/rank");
                if (count($fields) !== 1 || (string) $fields[0]->attributes('xsi', true)->type !== 'AttributeExternalField'
                    || (string) $fields[0]->extkey_attcode !== 'osversion_id' || (string) $fields[0]->target_attcode !== $id
                    || count($ranks) !== 1 || (int) $ranks[0] !== $supportRank + $offset) {
                    throw new RuntimeException("Unexpected OS date source or placement for $class.os_$id");
                }
            }
        }
    },
    'all lifecycle fields have labels in every bundled language' => static function (): void {
        foreach (['en' => 'EN US', 'de' => 'DE DE', 'ru' => 'RU RU'] as $file => $language) {
            require __DIR__ . "/../br-lifecycle/dictionaries/$file.dict.br-lifecycle.php";
            if (trim(Dict::$labels[$language]['br-lifecycle:OSVersion:Devices'] ?? '') === '') {
                throw new RuntimeException("Missing $language label for the OS version devices tab.");
            }
            foreach ([
                'PhysicalDevice' => ['model_eol'],
                'Server' => ['os_eol', 'os_eoesu'],
                'PC' => ['os_eol', 'os_eoesu'],
                'VirtualMachine' => ['os_eol', 'os_eoesu'],
                'Model' => ['eol', 'eosl'],
                'OSVersion' => ['eomss', 'eol', 'eoesu'],
            ] as $class => $fields) {
                foreach ($fields as $field) {
                    if (trim(Dict::$labels[$language]["Class:$class/Attribute:$field"] ?? '') === '') {
                        throw new RuntimeException("Missing $language label: $class.$field");
                    }
                }
            }
        }
    },
];

// Capture dictionary registration without an iTop installation.
class Dict
{
    public static array $labels = [];
    public static function Add($language, $name, $nativeName, $labels): void
    {
        self::$labels[$language] = $labels;
    }
}
