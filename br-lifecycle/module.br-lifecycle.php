<?php

/**
 * @copyright   Copyright (C) 2023-2026 Björn Rudner
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * iTop module definition file
 */

/** @disregard P1009 Undefined type SetupWebPage */
SetupWebPage::AddModule(
    __FILE__, // Path to the current file, all other file names are relative to the directory containing this file
    'br-lifecycle/3.3.0',
    array(
        // Identification
        //
        'label' => 'Datamodel: Lifecycle Management',
        'category' => 'business',

        // Setup
        //
        'dependencies' => array(
            'itop-config-mgmt/3.3.0',
            'itop-datacenter-mgmt/3.3.0 || !(itop-datacenter-mgmt/0.0.0)',
            'itop-endusers-devices/3.3.0 || !(itop-endusers-devices/0.0.0)',
            'itop-storage-mgmt/3.3.0 || !(itop-storage-mgmt/0.0.0)',
            'itop-virtualization-mgmt/3.3.0 || !(itop-virtualization-mgmt/0.0.0)',
        ),
        'mandatory' => false,
        'visible' => true,
        'installer' => 'LifeCycleManagementInstaller',

        // Components
        //
        'datamodel' => array('OSVersionDevicesTab.php'),
        'webservice' => array(),
        'data.struct' => array(
            // add your 'structure' definition XML files here,
        ),
        'data.sample' => array(
            // add your sample data XML files here,
        ),

        // Documentation
        //
        'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
        'doc.more_information' => '', // hyperlink to more information, if any

        // Default settings
        //
        'settings' => array(
            // Module specific settings go here, if any
        ),
    )
);

if (!class_exists('LifeCycleManagementInstaller')) {
    /**
     * Module installation handler
     */
    class LifeCycleManagementInstaller extends ModuleInstallerAPI
    {
        public static function AfterDatabaseCreation(Config $oConfiguration, $sPreviousVersion, $sCurrentVersion)
        {
            if ($sPreviousVersion !== '' && $sPreviousVersion !== '0.0.0'
                && version_compare($sPreviousVersion, '3.3.0', '<')) {
                self::MigrateEndOfSupport('Model', 'eosl');
                self::MigrateEndOfSupport('OSVersion', 'eomss');
            }

            if (version_compare($sPreviousVersion, '3.3.0', '<') && MetaModel::IsValidClass('AuditRule')) {
                // The original device rules were introduced in 0.2.0.
                self::InstallPhysicalDeviceAuditRules(version_compare($sPreviousVersion, '0.2.0', '<'));
                self::InstallOSVersionAuditRules(version_compare($sPreviousVersion, '3.2.0', '<'));
            }

            // Introduce AuditDomain in Version 3.2.0
            if (version_compare($sPreviousVersion, '3.2.0', '<')) {
                SetupLog::Info("|- Installing Lifecycle Management from '$sPreviousVersion' to '$sCurrentVersion'. Updating AuditDomain and lnkAuditCategoryToAuditDomain ...");

                $iAuditDomainId = 0;
                $iAuditCategoryId = 0;

                if (MetaModel::IsValidClass('AuditDomain')) {
                    // First, create audit category for Server mismatch
                    $oSearch = DBObjectSearch::FromOQL('SELECT AuditDomain WHERE name = "Lifecycle Management"');
                    $oSet = new DBObjectSet($oSearch);
                    $oAuditDomain = $oSet->Fetch();

                    if ($oAuditDomain === null) {
                        try {
                            $oAuditDomain = MetaModel::NewObject('AuditDomain', array(
                                'name' => 'Lifecycle Management',
                                'description' => 'Audit Lifecycle Management defined in the CMDB',
                            ));
                            $oAuditDomain->DBWrite();
                            SetupLog::Info('|  |- AuditDomain "Lifecycle Management" created.');
                        } catch (Exception $oException) {
                            SetupLog::Info('|  |- Could not create AuditDomain. (Error: ' . $oException->getMessage() . ')');
                        }
                    } else {
                        SetupLog::Info('|  |- AuditDomain "Lifecycle Management" already existing! We will use it!');
                    }

                    // Link AuditDomain with AuditCategory: Physical Device Lifecycle
                    $oSearch = DBObjectSearch::FromOQL('SELECT AuditCategory WHERE name = "Physical Device Lifecycle"');
                    $oSet = new DBObjectSet($oSearch);
                    $oAuditCategory = $oSet->Fetch();

                    $iAuditDomainId = ($oAuditDomain !== null) ? $oAuditDomain->GetKey() : 0;
                    $iAuditCategoryId = ($oAuditCategory !== null) ? $oAuditCategory->GetKey() : 0;

                    if ($iAuditDomainId > 0 && $iAuditCategoryId > 0) {
                        try {
                            $oAuditLink = MetaModel::NewObject('lnkAuditCategoryToAuditDomain', array(
                                'domain_id' => $iAuditDomainId,
                                'category_id' => $iAuditCategoryId,
                            ));
                            $oAuditLink->DBWrite();
                            SetupLog::Info('|  |- AuditLink created.');
                        } catch (Exception $oException) {
                            SetupLog::Info('|  |- Could not create AuditLink. (Error: ' . $oException->getMessage() . ')');
                        }
                    }

                    // Link AuditDomain with AuditCategory: Server OS Version Lifecycle
                    $oSearch = DBObjectSearch::FromOQL('SELECT AuditCategory WHERE name = "Server OS Version Lifecycle"');
                    $oSet = new DBObjectSet($oSearch);
                    $oAuditCategory = $oSet->Fetch();

                    $iAuditDomainId = ($oAuditDomain !== null) ? $oAuditDomain->GetKey() : 0;
                    $iAuditCategoryId = ($oAuditCategory !== null) ? $oAuditCategory->GetKey() : 0;

                    if ($iAuditDomainId > 0 && $iAuditCategoryId > 0) {
                        try {
                            $oAuditLink = MetaModel::NewObject('lnkAuditCategoryToAuditDomain', array(
                                'domain_id' => $iAuditDomainId,
                                'category_id' => $iAuditCategoryId,
                            ));
                            $oAuditLink->DBWrite();
                            SetupLog::Info('|  |- AuditLink created.');
                        } catch (Exception $oException) {
                            SetupLog::Info('|  |- Could not create AuditLink. (Error: ' . $oException->getMessage() . ')');
                        }
                    }

                    // Link AuditDomain with AuditCategory: Virtual Machine OS Version Lifecycle
                    $oSearch = DBObjectSearch::FromOQL('SELECT AuditCategory WHERE name = "Virtual Machine OS Version Lifecycle"');
                    $oSet = new DBObjectSet($oSearch);
                    $oAuditCategory = $oSet->Fetch();

                    $iAuditDomainId = ($oAuditDomain !== null) ? $oAuditDomain->GetKey() : 0;
                    $iAuditCategoryId = ($oAuditCategory !== null) ? $oAuditCategory->GetKey() : 0;

                    if ($iAuditDomainId > 0 && $iAuditCategoryId > 0) {
                        try {
                            $oAuditLink = MetaModel::NewObject('lnkAuditCategoryToAuditDomain', array(
                                'domain_id' => $iAuditDomainId,
                                'category_id' => $iAuditCategoryId,
                            ));
                            $oAuditLink->DBWrite();
                            SetupLog::Info('|  |- AuditLink created.');
                        } catch (Exception $oException) {
                            SetupLog::Info('|  |- Could not create AuditLink. (Error: ' . $oException->getMessage() . ')');
                        }
                    }

                    // Link AuditDomain with AuditCategory: PC OS Version Lifecycle
                    $oSearch = DBObjectSearch::FromOQL('SELECT AuditCategory WHERE name = "PC OS Version Lifecycle"');
                    $oSet = new DBObjectSet($oSearch);
                    $oAuditCategory = $oSet->Fetch();

                    $iAuditDomainId = ($oAuditDomain !== null) ? $oAuditDomain->GetKey() : 0;
                    $iAuditCategoryId = ($oAuditCategory !== null) ? $oAuditCategory->GetKey() : 0;

                    if ($iAuditDomainId > 0 && $iAuditCategoryId > 0) {
                        try {
                            $oAuditLink = MetaModel::NewObject('lnkAuditCategoryToAuditDomain', array(
                                'domain_id' => $iAuditDomainId,
                                'category_id' => $iAuditCategoryId,
                            ));
                            $oAuditLink->DBWrite();
                            SetupLog::Info('|  |- AuditLink created.');
                        } catch (Exception $oException) {
                            SetupLog::Info('|  |- Could not create AuditLink. (Error: ' . $oException->getMessage() . ')');
                        }
                    }
                }
            }
        }

        private static function InstallPhysicalDeviceAuditRules(bool $createMissing): void
        {
            $baseQuery = "SELECT pd,m FROM PhysicalDevice AS pd JOIN Model AS m ON pd.model_id = m.id\n"
                . "WHERE pd.status='production' AND pd.model_id != 0";
            $supportRules = [
                '00 - Outdated' => [
                    'End of life or end of support passed',
                    "(m.eol < NOW() OR m.end_of_support < NOW())",
                ],
                '01 - EoSL outdated' => [
                    'End of support passed',
                    "m.end_of_support < NOW()",
                ],
                '02 - EoSL in 30 days' => [
                    'End of support in less than 30 days',
                    "m.end_of_support > NOW() AND m.end_of_support < DATE_ADD(NOW(), INTERVAL 30 DAY)",
                ],
                '03 - EoSL this year' => [
                    'End of support this year',
                    "m.end_of_support > DATE_FORMAT(NOW(),'%Y-01-01 00:00:00') AND m.end_of_support < DATE_FORMAT(NOW(),'%Y-12-31 23:59:59')",
                ],
                '04 - EoSL next year' => [
                    'End of support next year',
                    "m.end_of_support > DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-01-01 00:00:00') AND m.end_of_support < DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-12-31 23:59:59')",
                ],
            ];
            $otherRules = [
                '01 - EoL outdated' => [
                    'End of life passed',
                    "m.eol < NOW()",
                ],
                '02 - EoL in 30 days' => [
                    'End of life in less than 30 days',
                    "m.eol > NOW() AND m.eol < DATE_ADD(NOW(), INTERVAL 30 DAY)",
                ],
                '03 - EoL this year' => [
                    'End of life this year',
                    "m.eol > DATE_FORMAT(NOW(),'%Y-01-01 00:00:00') AND m.eol < DATE_FORMAT(NOW(),'%Y-12-31 23:59:59')",
                ],
                '04 - EoL next year' => [
                    'End of life next year',
                    "m.eol > DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-01-01 00:00:00') AND m.eol < DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-12-31 23:59:59')",
                ],
            ];
            self::InstallAuditCategoryRules('Physical Device Lifecycle', 'Lifecycle of physical device in production',
                $baseQuery, $supportRules, $otherRules, $createMissing);
        }

        private static function InstallOSVersionAuditRules(bool $createMissing): void
        {
            $supportRules = [
                '00 - Outdated' => [
                    'EoMSS / EoL / EoESU passed',
                    "(osv.end_of_support < NOW() OR osv.eol < NOW() OR osv.eoesu < NOW())",
                ],
                '01 - EoMSS outdated' => [
                    'End of Mainstream Support passed',
                    "osv.end_of_support < NOW()",
                ],
                '02 - EoMSS in 30 days' => [
                    'End of Mainstream Support in less than 30 days',
                    "osv.end_of_support > NOW() AND osv.end_of_support < DATE_ADD(NOW(), INTERVAL 30 DAY)",
                ],
                '03 - EoMSS this year' => [
                    'End of Mainstream Support this year',
                    "osv.end_of_support > DATE_FORMAT(NOW(),'%Y-01-01 00:00:00') AND osv.end_of_support < DATE_FORMAT(NOW(),'%Y-12-31 23:59:59')",
                ],
                '04 - EoMSS next year' => [
                    'End of Mainstream Support next year',
                    "osv.end_of_support > DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-01-01 00:00:00') AND osv.end_of_support < DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-12-31 23:59:59')",
                ],
            ];
            $otherRules = [
                '01 - EoL outdated' => [
                    'End of extended support passed',
                    "osv.eol < NOW()",
                ],
                '02 - EoL in 30 days' => [
                    'End of extended support in less than 30 days',
                    "osv.eol > NOW() AND osv.eol < DATE_ADD(NOW(), INTERVAL 30 DAY)",
                ],
                '03 - EoL this year' => [
                    'End of extended support this year',
                    "osv.eol > DATE_FORMAT(NOW(),'%Y-01-01 00:00:00') AND osv.eol < DATE_FORMAT(NOW(),'%Y-12-31 23:59:59')",
                ],
                '04 - EoL next year' => [
                    'End of extended support next year',
                    "osv.eol > DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-01-01 00:00:00') AND osv.eol < DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-12-31 23:59:59')",
                ],
                '01 - EoESU outdated' => [
                    'End of Extended Security Update passed',
                    "osv.eoesu < NOW()",
                ],
                '02 - EoESU in 30 days' => [
                    'End of Extended Security Update in less than 30 days',
                    "osv.eoesu > NOW() AND osv.eoesu < DATE_ADD(NOW(), INTERVAL 30 DAY)",
                ],
                '03 - EoESU this year' => [
                    'End of Extended Security Update this year',
                    "osv.eoesu > DATE_FORMAT(NOW(),'%Y-01-01 00:00:00') AND osv.eoesu < DATE_FORMAT(NOW(),'%Y-12-31 23:59:59')",
                ],
                '04 - EoESU next year' => [
                    'End of Extended Security Update next year',
                    "osv.eoesu > DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-01-01 00:00:00') AND osv.eoesu < DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-12-31 23:59:59')",
                ],
            ];
            $categories = [
                'Server OS Version Lifecycle' => ['Server', 's', 'servers'],
                'Virtual Machine OS Version Lifecycle' => ['VirtualMachine', 'vm', 'virtual machines'],
                'PC OS Version Lifecycle' => ['PC', 'pc', 'PCs'],
            ];
            foreach ($categories as $name => [$class, $alias, $devices]) {
                if (!MetaModel::IsValidClass($class)) {
                    continue;
                }
                $baseQuery = "SELECT $alias,osv FROM $class AS $alias JOIN OSVersion AS osv ON $alias.osversion_id = osv.id\n"
                    . "WHERE $alias.status='production' AND $alias.osversion_id != 0";
                self::InstallAuditCategoryRules($name, "Lifecycle of OS version on $devices in production",
                    $baseQuery, $supportRules, $otherRules, $createMissing);
            }
        }

        private static function InstallAuditCategoryRules(
            string $categoryName,
            string $categoryDescription,
            string $baseQuery,
            array $supportRules,
            array $otherRules,
            bool $createMissing
        ): void {
            $categories = new DBObjectSet(DBObjectSearch::FromOQL('SELECT AuditCategory WHERE name = :name'), [], ['name' => $categoryName]);
            $category = $categories->Fetch();
            if ($category === null) {
                if (!$createMissing) {
                    return;
                }
                try {
                    $category = MetaModel::NewObject('AuditCategory', [
                        'name' => $categoryName,
                        'description' => $categoryDescription,
                        'definition_set' => $baseQuery,
                    ]);
                    $category->DBWrite();
                } catch (Exception $error) {
                    SetupLog::Info("br-lifecycle: Could not create $categoryName audit category: " . $error->getMessage());
                    return;
                }
            }

            $missing = $supportRules + $otherRules;
            $rules = new DBObjectSet(
                DBObjectSearch::FromOQL('SELECT AuditRule WHERE category_id = :category'),
                [],
                ['category' => $category->GetKey()]
            );
            while ($rule = $rules->Fetch()) {
                $name = $rule->Get('name');
                unset($missing[$name]);
                if (isset($supportRules[$name])) {
                    $query = $baseQuery . "\nAND " . $supportRules[$name][1];
                    if ($rule->Get('query') !== $query) {
                        $rule->Set('query', $query);
                        $rule->DBWrite();
                        SetupLog::Info("br-lifecycle: Updated $categoryName audit rule #{$rule->GetKey()} ($name).");
                    }
                }
            }
            if ($createMissing) {
                foreach ($missing as $name => [$description, $condition]) {
                    try {
                        $rule = MetaModel::NewObject('AuditRule', [
                            'category_id' => $category->GetKey(),
                            'name' => $name,
                            'description' => $description,
                            'query' => $baseQuery . "\nAND " . $condition,
                            'valid_flag' => 'false',
                        ]);
                        $rule->DBWrite();
                        SetupLog::Info("br-lifecycle: Created $categoryName audit rule #{$rule->GetKey()} ($name).");
                    } catch (Exception $error) {
                        SetupLog::Info("br-lifecycle: Could not create $categoryName audit rule ($name): " . $error->getMessage());
                    }
                }
            }
        }

        private static function MigrateEndOfSupport(string $class, string $legacyField): void
        {
            $table = MetaModel::DBGetTable($class);
            // Older MySQL databases can store an empty date as 0000-00-00.
            $hasLegacyDate = "CAST(`$legacyField` AS CHAR) NOT IN ('', '0000-00-00')";
            $emptyTarget = "(end_of_support IS NULL OR CAST(end_of_support AS CHAR) IN ('', '0000-00-00'))";
            $records = CMDBSource::QueryToArray("SELECT id FROM `$table` WHERE $hasLegacyDate");
            foreach ($records as $record) {
                $id = (int) $record['id'];
                CMDBSource::Query("UPDATE `$table` SET end_of_support = `$legacyField`
                    WHERE id = $id AND $hasLegacyDate AND $emptyTarget");
                if (CMDBSource::AffectedRows() > 0) {
                    continue;
                }
                $rows = CMDBSource::QueryToArray("SELECT `$legacyField` AS legacy_date, end_of_support FROM `$table`
                    WHERE id = $id AND $hasLegacyDate AND NOT $emptyTarget");
                if (!$rows) {
                    continue;
                }
                $record = $rows[0];
                SetupLog::Info("br-lifecycle: $class #$id skipped " . strtoupper($legacyField) . " migration. "
                    . "Keeping end_of_support={$record['end_of_support']}; legacy $legacyField={$record['legacy_date']}.");
            }
        }
    }
};
