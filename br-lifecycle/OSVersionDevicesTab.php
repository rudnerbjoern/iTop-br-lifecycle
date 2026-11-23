<?php

/**
 * @copyright   Copyright (C) 2023-2026 Björn Rudner
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 */

use Combodo\iTop\Application\WebPage\WebPage;

class LifecycleOSVersionDevicesTab extends AbstractApplicationUIExtension
{
    public function OnDisplayRelations($oObject, WebPage $oPage, $bEditMode = false)
    {
        if (!($oObject instanceof OSVersion) || $oObject->IsNew()) {
            return;
        }

        $queries = [];
        $versionId = (int) $oObject->GetKey();
        foreach (['Server', 'VirtualMachine', 'PC'] as $class) {
            if (MetaModel::IsValidClass($class) && UserRights::IsActionAllowed($class, UR_ACTION_READ)) {
                $queries[] = "SELECT $class AS device WHERE device.osversion_id = $versionId";
            }
        }
        if ($queries === []) {
            return;
        }

        $search = DBObjectSearch::FromOQL(implode(' UNION ', $queries));
        $oPage->SetCurrentTab('br-lifecycle:OSVersion:Devices');
        $block = new DisplayBlock($search, 'list');
        $block->Display($oPage, 'lifecycle_osversion_devices_' . $versionId, [
            'menu' => false,
            'table_id' => 'br_lifecycle_osversion_devices',
        ]);
    }
}
