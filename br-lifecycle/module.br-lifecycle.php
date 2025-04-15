<?php

/**
 * @copyright   Copyright (C) 2023-2024 Björn Rudner
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2024-09-10
 *
 * iTop module definition file
 */

SetupWebPage::AddModule(
    __FILE__, // Path to the current file, all other file names are relative to the directory containing this file
    'br-lifecycle/3.1.4',
    array(
        // Identification
        //
        'label' => 'Datamodel: Lifecycle Management',
        'category' => 'business',

        // Setup
        //
        'dependencies' => array(
            'itop-config-mgmt/3.1.0',
        ),
        'mandatory' => false,
        'visible' => true,
        'installer' => 'LifeCycleManagementInstaller',

        // Components
        //
        'datamodel' => array(),
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
            // Create audit rules introduced in Version 0.2.0
            if (version_compare($sPreviousVersion, '0.2.0', '<')) {
                SetupLog::Info("|- Installing Lifecycle Management from '$sPreviousVersion' to '$sCurrentVersion'. The extension comes with audit rules so corresponding objects will created into the DB...");

                if (MetaModel::IsValidClass('AuditRule')) {
                    // First, create audit category for Physical Device Lifecycle
                    $oSearch = DBObjectSearch::FromOQL('SELECT AuditCategory WHERE name = "Physical Device Lifecycle"');
                    $oSet = new DBObjectSet($oSearch);
                    $oAuditCategory = $oSet->Fetch();

                    if ($oAuditCategory === null) {
                        try {
                            $oAuditCategory = MetaModel::NewObject('AuditCategory', array(
                                'name' => 'Physical Device Lifecycle',
                                'description' => 'Lifecycle of physical device in production',
                                'definition_set' => "SELECT pd,m FROM PhysicalDevice AS pd JOIN Model AS m ON pd.model_id = m.id\n" .
                                    "WHERE pd.status='production' AND pd.model_id != 0",
                            ));
                            $oAuditCategory->DBWrite();
                            SetupLog::Info('|  |- AuditCategory "Physical Device Lifecycle" created.');
                        } catch (Exception $oException) {
                            SetupLog::Info('|  |- Could not create AuditCategory. (Error: ' . $oException->getMessage() . ')');
                        }
                    } else {
                        SetupLog::Info('|  |- AuditCategory "Physical Device Lifecycle" already existing! Weird as it is supposed to be created by this extension, but will use it anyway!');
                    }

                    // Then, create audit rules
                    $aAuditRules = array(
                        array(
                            'name' => '00 - Outdated',
                            'description' => 'EoL / EoSL passed',
                            'query' =>  "SELECT pd,m FROM PhysicalDevice AS pd JOIN Model AS m ON pd.model_id = m.id\n" .
                                "WHERE pd.status='production' AND pd.model_id != 0\n" .
                                "AND (m.eol < NOW() OR m.eosl < NOW())",
                            'valid_flag' => 'false',
                        ),
                        array(
                            'name' => '01 - EoL outdated',
                            'description' => 'End of life passed',
                            'query' =>  "SELECT pd,m FROM PhysicalDevice AS pd JOIN Model AS m ON pd.model_id = m.id\n" .
                                "WHERE pd.status='production' AND pd.model_id != 0\n" .
                                "AND m.eol < NOW()",
                            'valid_flag' => 'false',
                        ),
                        array(
                            'name' => '01 - EoSL outdated',
                            'description' => 'End of service life passed',
                            'query' =>  "SELECT pd,m FROM PhysicalDevice AS pd JOIN Model AS m ON pd.model_id = m.id\n" .
                                "WHERE pd.status='production' AND pd.model_id != 0\n" .
                                "AND m.eosl < NOW()",
                            'valid_flag' => 'false',
                        ),
                        array(
                            'name' => '02 - EoL in 30 days',
                            'description' => 'End of life in less than 30 days',
                            'query' =>  "SELECT pd,m FROM PhysicalDevice AS pd JOIN Model AS m ON pd.model_id = m.id\n" .
                                "WHERE pd.status='production' AND pd.model_id != 0\n" .
                                "AND m.eol > NOW() AND m.eol < DATE_ADD(NOW(), INTERVAL 30 DAY)",
                            'valid_flag' => 'false',
                        ),
                        array(
                            'name' => '02 - EoSL in 30 days',
                            'description' => 'End of service life in less than 30 days',
                            'query' =>  "SELECT pd,m FROM PhysicalDevice AS pd JOIN Model AS m ON pd.model_id = m.id\n" .
                                "WHERE pd.status='production' AND pd.model_id != 0\n" .
                                "AND m.eosl > NOW() AND m.eosl < DATE_ADD(NOW(), INTERVAL 30 DAY)",
                            'valid_flag' => 'false',
                        ),
                        array(
                            'name' => '03 - EoL this year',
                            'description' => 'End of life this year',
                            'query' =>  "SELECT pd,m FROM PhysicalDevice AS pd JOIN Model AS m ON pd.model_id = m.id\n" .
                                "WHERE pd.status='production' AND pd.model_id != 0\n" .
                                "AND m.eol > DATE_FORMAT(NOW(),'%Y-01-01 00:00:00') AND m.eol < DATE_FORMAT(NOW(),'%Y-12-31 23:59:59')",
                            'valid_flag' => 'false',
                        ),
                        array(
                            'name' => '03 - EoSL this year',
                            'description' => 'End of service life this year',
                            'query' =>  "SELECT pd,m FROM PhysicalDevice AS pd JOIN Model AS m ON pd.model_id = m.id\n" .
                                "WHERE pd.status='production' AND pd.model_id != 0\n" .
                                "AND m.eosl > DATE_FORMAT(NOW(),'%Y-01-01 00:00:00') AND m.eosl < DATE_FORMAT(NOW(),'%Y-12-31 23:59:59')",
                            'valid_flag' => 'false',
                        ),
                        array(
                            'name' => '04 - EoL next year',
                            'description' => 'End of life next year',
                            'query' =>  "SELECT pd,m FROM PhysicalDevice AS pd JOIN Model AS m ON pd.model_id = m.id\n" .
                                "WHERE pd.status='production' AND pd.model_id != 0\n" .
                                "AND m.eol > DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-01-01 00:00:00') AND m.eol < DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-12-31 23:59:59')",
                            'valid_flag' => 'false',
                        ),
                        array(
                            'name' => '04 - EoSL next year',
                            'description' => 'End of service life next year',
                            'query' =>  "SELECT pd,m FROM PhysicalDevice AS pd JOIN Model AS m ON pd.model_id = m.id\n" .
                                "WHERE pd.status='production' AND pd.model_id != 0\n" .
                                "AND m.eosl > DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-01-01 00:00:00') AND m.eosl < DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),'%Y-12-31 23:59:59')",
                            'valid_flag' => 'false',
                        ),
                    );
                    foreach ($aAuditRules as $aAuditRule) {
                        try {
                            $oAuditRule = MetaModel::NewObject('AuditRule', $aAuditRule);
                            $oAuditRule->Set('category_id', $oAuditCategory->GetKey());
                            $oAuditRule->DBWrite();
                            SetupLog::Info('|  |- AuditRule "' . $aAuditRule['name'] . '" created.');
                        } catch (Exception $oException) {
                            SetupLog::Info('|  |- Could not create AuditRule "' . $aAuditRule['name'] . '". (Error: ' . $oException->getMessage() . ')');
                        }
                    }
                }
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

                    // Link AuditDomain with AuditCategory
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
                }
            }
        }
    }
};
