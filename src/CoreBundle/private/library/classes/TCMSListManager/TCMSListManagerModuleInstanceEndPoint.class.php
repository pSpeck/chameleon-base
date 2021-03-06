<?php

/*
 * This file is part of the Chameleon System (https://www.chameleonsystem.com).
 *
 * (c) ESONO AG (https://www.esono.de)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\DBAL\Connection;

class TCMSListManagerModuleInstanceEndPoint extends TCMSListManagerFullGroupTable
{
    /**
     * array of allowed modules for the current spot.
     *
     * @var array
     */
    public $aPermittedModules = null;

    /**
     * {@inheritdoc}
     */
    public function Init(&$oTableConf)
    {
        $tableConf = TdbCmsTblConf::GetNewInstance();
        $tableConf->LoadFromField('name', 'cms_tpl_module_instance');
        parent::Init($tableConf);
    }

    public function _AddFunctionColumn()
    {
    }

    public function AddFields()
    {
        parent::AddFields();
        $jsParas = $this->_GetRecordClickJavaScriptParameters();

        ++$this->columnCount;

        $siteText = TGlobal::Translate('chameleon_system_core.list_module_instance.column_name_pages');
        $this->tableObj->AddHeaderField(array('id' => $siteText.'&nbsp;&nbsp;'), 'left', null, 1, false);
        $this->tableObj->AddColumn('id', 'left', array($this, 'CallBackTemplateEngineInstancePages'), $jsParas, 1);
    }

    /**
     * by returning false the "new entry" button in the list can be supressed.
     *
     * @return bool
     */
    public function ShowNewEntryButton()
    {
        return false;
    }

    public function _GetRecordClickJavaScriptFunctionName()
    {
        return 'LoadCMSInstance';
    }

    /**
     * Add custom joins to the query.
     *
     * @return string
     */
    protected function GetFilterQueryCustomJoins()
    {
        $sQueryJoin = '  LEFT JOIN `cms_tpl_module_cms_usergroup_mlt` ON `cms_tpl_module_instance`.`cms_tpl_module_id` = `cms_tpl_module_cms_usergroup_mlt`.`source_id`
            LEFT JOIN `cms_tpl_module_cms_portal_mlt` ON `cms_tpl_module_instance`.`cms_tpl_module_id`  = `cms_tpl_module_cms_portal_mlt`.`source_id` ';

        return $sQueryJoin;
    }

    /**
     * any custom restrictions can be added to the query by overwriting this function.
     */
    public function GetCustomRestriction()
    {
        $query = parent::GetCustomRestriction();

        $oUser = TCMSUser::GetActiveUser();

        if (!empty($query)) {
            $query .= ' AND ';
        }
        $query .= "`cms_tpl_module`.`show_in_template_engine` = '1'";

        if (!is_null($this->aPermittedModules)) {
            $databaseConnection = $this->getDatabaseConnection();
            $permittedModulesString = implode(',', array_map(array($databaseConnection, 'quote'), array_keys($this->aPermittedModules)));
            $query .= " AND `cms_tpl_module`.`classname` IN ($permittedModulesString)";
        }
        if (array_key_exists('sModuleRestriction', $this->tableObj->_postData) && !empty($this->tableObj->_postData['sModuleRestriction'])) {
            $query .= " AND `cms_tpl_module`.`classname` = '".MySqlLegacySupport::getInstance()->real_escape_string($this->tableObj->_postData['sModuleRestriction'])."'";
        }

        $sUserGroupRestriction = '';
        $sGroupList = $oUser->oAccessManager->user->groups->GroupList();
        if (!empty($sGroupList)) {
            $sUserGroupRestriction = " OR `cms_tpl_module_cms_usergroup_mlt`.`target_id` IN ({$sGroupList})";
        }

        $query .= " AND (`cms_tpl_module`.`is_restricted` = '0'{$sUserGroupRestriction})";

        // add portal restrictions
        $sPortalList = $oUser->oAccessManager->user->portals->PortalList();
        if (!empty($sPortalList)) {
            $sPortalRestriction = ' OR `cms_tpl_module_cms_portal_mlt`.`target_id` IN ('.$sPortalList.')';
        }
        $query .= ' AND (
      (SELECT COUNT(`target_id`) FROM `cms_tpl_module_cms_portal_mlt` WHERE `source_id` = `cms_tpl_module`.`id`)=0
      '.$sPortalRestriction.'
      ) ';

        return $query;
    }

    protected function AddRowPrefixFields()
    {
    }

    /**
     * add table-specific buttons to the editor (add them directly to $this->oMenuItems).
     */
    protected function GetCustomMenuItems()
    {
        parent::GetCustomMenuItems();
        $this->oMenuItems->RemoveItem('sItemKey', 'deleteall');
        $this->oMenuItems->RemoveItem('sItemKey', 'edittableconf');
    }

    /**
     * returns the navigation breadcrumbs of the module instance.
     *
     * @param string $id
     * @param array  $row
     *
     * @return string
     */
    public function CallBackTemplateEngineInstancePages($id, $row)
    {
        $query = "SELECT `cms_tpl_page`.*
                  FROM `cms_tpl_page_cms_master_pagedef_spot`
            INNER JOIN `cms_tpl_page` ON `cms_tpl_page_cms_master_pagedef_spot`.`cms_tpl_page_id` = `cms_tpl_page`.`id`
                 WHERE `cms_tpl_page_cms_master_pagedef_spot`.`cms_tpl_module_instance_id` = '".MySqlLegacySupport::getInstance()->real_escape_string($id)."'
              ORDER BY `cms_tpl_page`.`tree_path_search_string`
               ";
        $pageString = '';

        $oCmsTplPageList = TdbCmsTplPageList::GetList($query);
        while ($oCmsTplPage = $oCmsTplPageList->Next()) {
            $sPath = $oCmsTplPage->fieldTreePathSearchString;
            if (empty($sPath)) {
                $sPath = TGlobal::Translate('chameleon_system_core.list_module_instance.no_usages_found_in_navigation_node');
            }
            $pageString .= '<div style="white-space: nowrap;"><h2 style="margin: 0px 0px 5px 0px;">'.TGlobal::OutHTML($oCmsTplPage->fieldName).' (ID '.TGlobal::OutHTML($oCmsTplPage->id).'):</h2>
        '.$sPath.'</div>';
        }

        return $pageString;
    }

    /**
     * @return Connection
     */
    private function getDatabaseConnection()
    {
        return \ChameleonSystem\CoreBundle\ServiceLocator::get('database_connection');
    }
}
