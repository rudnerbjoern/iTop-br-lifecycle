<?php

declare(strict_types=1);

return [
    'lifecycle dates are optional and visible in details and summary' => static function (): void {
        $model = simplexml_load_file(__DIR__ . '/../br-lifecycle/datamodel.br-lifecycle.xml');
        foreach (['Model' => ['eol', 'eosl'], 'OSVersion' => ['eomss', 'eol', 'eoesu']] as $class => $fields) {
            foreach ($fields as $field) {
                $nodes = $model->xpath("/itop_design/classes/class[@id='$class']/fields/field[@id='$field']");
                if (count($nodes) !== 1 || (string) $nodes[0]->attributes('xsi', true)->type !== 'AttributeDate'
                    || (string) $nodes[0]->sql !== $field || (string) $nodes[0]->is_null_allowed !== 'true') {
                    throw new RuntimeException("Invalid lifecycle date: $class.$field");
                }
                foreach (['details', 'summary'] as $view) {
                    if (count($model->xpath("/itop_design/classes/class[@id='$class']/presentation/$view//item[@id='$field']")) !== 1) {
                        throw new RuntimeException("Missing $view presentation for $class.$field");
                    }
                }
            }
        }
    },
    'OS version device lists use the correct read-only reverse relationship' => static function (): void {
        $model = simplexml_load_file(__DIR__ . '/../br-lifecycle/datamodel.br-lifecycle.xml');
        foreach (['server_list' => 'Server', 'virtualmachine_list' => 'VirtualMachine', 'pc_list' => 'PC'] as $field => $class) {
            $nodes = $model->xpath("/itop_design/classes/class[@id='OSVersion']/fields/field[@id='$field']");
            if (count($nodes) !== 1 || (string) $nodes[0]->attributes('xsi', true)->type !== 'AttributeLinkedSet'
                || (string) $nodes[0]->linked_class !== $class || (string) $nodes[0]->ext_key_to_me !== 'osversion_id'
                || (string) $nodes[0]->edit_mode !== 'none'
                || count($model->xpath("/itop_design/classes/class[@id='OSVersion']/presentation/details//item[@id='$field']")) !== 1) {
                throw new RuntimeException("Invalid OS version device list: $field");
            }
        }
    },
    'all lifecycle fields have labels in every bundled language' => static function (): void {
        foreach (['en' => 'EN US', 'de' => 'DE DE', 'ru' => 'RU RU'] as $file => $language) {
            require __DIR__ . "/../br-lifecycle/dictionaries/$file.dict.br-lifecycle.php";
            foreach (['Model' => ['eol', 'eosl'], 'OSVersion' => ['eomss', 'eol', 'eoesu', 'server_list', 'virtualmachine_list', 'pc_list']] as $class => $fields) {
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
