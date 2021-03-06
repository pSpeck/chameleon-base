<?php

/*
 * This file is part of the Chameleon System (https://www.chameleonsystem.com).
 *
 * (c) ESONO AG (https://www.esono.de)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ChameleonSystem\AutoclassesBundle\CacheWarmer\AutoclassesCacheWarmer;

/**
 * manages creating, changing, and deleting fields from a table.
/**/
class TCMSTableFieldWriter extends TCMSTableEditor
{
    /**
     * The field represented by this record.
     *
     * @var TCMSField
     */
    protected $_oField = null;

    /**
     * the record of the cms_tbl_conf that points to this field.
     *
     * @var TCMSTableConf
     */
    protected $_oParentRecord = null;

    protected $_sqlFieldName = null;

    /**
     * @return TCMSStdClass
     */
    public function Insert()
    {
        $this->_oParentRecord = new TCMSTableConf();
        $this->_oParentRecord->Load($this->sRestriction);
        // when creating a field we have no name yet (so we generate one), and
        // we have no definition yet
        $foundName = false;
        $fieldName = 'new_field';
        $count = 0;
        while (!$foundName) {
            $tmpName = $fieldName;
            if ($count > 0) {
                $tmpName .= $count;
            }
            $foundName = !(TTools::FieldExists($this->_oParentRecord->sqlData['name'], $tmpName));
            ++$count;
            if ($foundName) {
                $fieldName = $tmpName;
            }
        }
        $this->_sqlFieldName = $fieldName;

        $oRecordData = parent::Insert();
        $this->LoadDataFromDatabase();
        $this->_sqlFieldName = $fieldName;

        $this->_oField = new TCMSField();
        $this->_oField->name = $this->_sqlFieldName;
        $this->_oField->recordId = $this->sId;
        $this->_oField->sTableName = $this->_oParentRecord->sqlData['name'];
        $this->_oField->oDefinition = &$this->oTable;
        $this->_oField->CreateFieldDefinition(false, $this->_oField);

        return $oRecordData;
    }

    /**
     * Creates a config record stub (name, cms_tbl_conf_id only) for an existing SQL field.
     *
     * @param string $sFieldName
     * @param string $sTableID
     *
     * @return TCMSStdClass
     */
    public function InsertFieldDefForExistingSQLField($sFieldName, $sTableID)
    {
        $this->sRestrictionField = 'cms_tbl_conf_id';
        $this->sRestriction = $sTableID;
        $this->_oParentRecord = new TCMSTableConf();
        $this->_oParentRecord->Load($sTableID);
        // when creating a field we have no name yet (so we generate one), and
        // we have no definition yet
        $this->_sqlFieldName = $sFieldName;

        $oRecordData = parent::Insert();
        $this->LoadDataFromDatabase();
        $this->_sqlFieldName = $sFieldName;
        $this->_oField = new TCMSField();
        $this->_oField->name = $this->_sqlFieldName;
        $this->_oField->recordId = $this->sId;
        $this->_oField->sTableName = $this->_oParentRecord->sqlData['name'];
        $this->_oField->oDefinition = &$this->oTable;

        return $oRecordData;
    }

    /**
     * {@inheritdoc}
     */
    public function Delete($sId = null)
    {
        $this->_oField->DeleteFieldDefinition();
        parent::Delete($sId);
        $this->getAutoclassesCacheWarmer()->updateTableById($this->oTable->sqlData['cms_tbl_conf_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function Save(&$postData, $bDataIsInSQLForm = false)
    {
        if (false === $this->DataIsValid($postData, null)) {
            return false;
        }

        $bTargetTableChangeForMLTField = false;
        if (array_key_exists('bTargetTableChangeForMLTField', $postData)) {
            $bTargetTableChangeForMLTField = $postData['bTargetTableChangeForMLTField'];
        }
        // if the name of the field has changed we need to alter the table definition
        // we also need to change the field definition if the type changed
        $bTypeChanged = false;
        if (array_key_exists('cms_field_type_id', $this->oTable->sqlData)) {
            $bTypeChanged = ($this->oTable->sqlData['cms_field_type_id'] != $postData['cms_field_type_id']);
            $currentFieldTypeQuery = "SELECT * FROM `cms_field_type` WHERE `id` = '".MySqlLegacySupport::getInstance()->real_escape_string($this->oTable->sqlData['cms_field_type_id'])."'";
            $currentFieldTypeResult = MySqlLegacySupport::getInstance()->query($currentFieldTypeQuery);
            $currentFieldTypeRow = MySqlLegacySupport::getInstance()->fetch_assoc($currentFieldTypeResult);
        }
        $aOriginalData = $this->oTable->sqlData;

        $newFieldTypeQuery = "SELECT * FROM `cms_field_type` WHERE `id` = '".MySqlLegacySupport::getInstance()->real_escape_string($postData['cms_field_type_id'])."'";
        $newFieldTypeResult = MySqlLegacySupport::getInstance()->query($newFieldTypeQuery);
        $newFieldTypeRow = MySqlLegacySupport::getInstance()->fetch_assoc($newFieldTypeResult);

        $oldName = $postData['name'];
        if (array_key_exists('name', $this->oTable->sqlData)) {
            $oldName = $this->oTable->sqlData['name'];
        }

        if ($bTypeChanged) {
            $this->_oField->ChangeFieldTypePreHook();
        }
        if (!$bTargetTableChangeForMLTField) {
            if ($this->_oField->AllowDeleteRelatedTablesBeforeFieldSave($postData, $currentFieldTypeRow, $newFieldTypeRow)) {
                $this->_oField->DeleteRelatedTables();
            } elseif ($this->_oField->AllowRenameRelatedTablesBeforeFieldSave($postData, $currentFieldTypeRow, $newFieldTypeRow)) {
                $this->_oField->RenameRelatedTables($postData);
            }
        } else {
            $this->_oField->RenameRelatedTables($postData);
        }
        $this->_oField->RemoveFieldIndex();
        // need to change the type of field object
        $this->oTable->sqlData['cms_field_type_id'] = $postData['cms_field_type_id'];
        $this->_oField = &$this->oTable->GetFieldObject();
        $this->_oField->name = $this->oTable->sqlData['name'];
        $this->_oField->sTableName = $this->_oParentRecord->sqlData['name'];
        $this->_oField->recordId = $this->sId;
        $this->_oField->oDefinition = &$this->oTable;
        if ($bTypeChanged) {
            $this->_oField->ChangeFieldTypePostHook();
        }
        $this->_oField->ChangeFieldDefinition($oldName, $postData['name'], $postData);
        $aOldData = $this->oTable->sqlData;
        parent::Save($postData);
        $this->oTable->Load($this->oTable->id);
        if (!$bTargetTableChangeForMLTField && $this->_oField->AllowCreateRelatedTablesAfterFieldSave($aOldData, $currentFieldTypeRow, $newFieldTypeRow)) {
            $this->_oField->CreateRelatedTables();
        }
        $this->_oField->CreateFieldIndex();

        $this->_oField->oDefinition->UpdateFieldTranslationKeys($aOriginalData);
        $this->getAutoclassesCacheWarmer()->updateTableById($this->oTable->sqlData['cms_tbl_conf_id']);

        return $this->GetObjectShortInfo($postData);
    }

    /**
     * {@inheritdoc}
     */
    protected function _OverwriteDefaults(&$oFields)
    {
        if (!is_null($this->_sqlFieldName)) {
            $oFields->GoToStart();
            while ($oField = &$oFields->Next()) {
                /** @var $oField TCMSField */
                if ('name' == $oField->name) {
                    $oField->data = $this->_sqlFieldName;
                }
            }
            $oFields->GoToStart();
        }

        // there must always be a field type defined. if it is not (like after a create field request)
        // then we will set it to varchar
        $oFields->GoToStart();
        while ($oField = &$oFields->Next()) {
            /** @var $oField TCMSField */
            if ('cms_field_type_id' == $oField->name) {
                if (is_null($oField->data) || empty($oField->data)) {
                    $oField->data = 34;
                } // id of the varchar def (in cms_field_type)
            }
        }
        $oFields->GoToStart();
    }

    /**
     * {@inheritdoc}
     */
    protected function LoadDataFromDatabase()
    {
        $this->oTable = new TCMSFieldDefinition();
        $this->_oParentRecord = new TCMSTableConf();
        if (null === $this->sId || true === empty($this->sId)) {
            return;
        }

        $this->oTable->SetLanguage($this->oTableConf->GetLanguage());
        $this->oTable->Load($this->sId);
        $this->_sqlFieldName = $this->oTable->sqlData['name'];
        $this->_oParentRecord->Load($this->oTable->sqlData['cms_tbl_conf_id']);

        $this->_oField = $this->oTable->GetFieldObject();
        // we need to set the fields variables by hand. but only definition related items. we need not care
        // about the contents of the field

        $sTableName = '';
        if (is_array($this->_oParentRecord->sqlData) && isset($this->_oParentRecord->sqlData['name'])) {
            $sTableName = $this->_oParentRecord->sqlData['name'];
        }

        if (null !== $this->_oField) {
            $this->_oField->name = $this->oTable->sqlData['name'];
            $this->_oField->sTableName = $sTableName;
            $this->_oField->recordId = $this->sId;
            $this->_oField->oDefinition = &$this->oTable;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function Copy($postData, $bNoConversion = false)
    {
        $postData['name'] = trim($postData['name']);
        $postData['name'] .= '_copy';
        parent::Copy($postData);
        $this->_sqlFieldName = $postData['name'];
        $this->_oField = new TCMSField();
        $this->_oField->name = $this->_sqlFieldName;
        $this->_oField->recordId = $this->sId;
        $this->_oField->sTableName = $this->_oParentRecord->sqlData['name'];
        $this->_oField->oDefinition = &$this->oTable;
        $this->_oField->CreateFieldDefinition();

        $oClassWriter = new TCMSTableToClass($this->getFileManager(), $this->getAutoclassesDir());
        $oClassWriter->Load($this->oTable->sqlData['cms_tbl_conf_id']);
        $oClassWriter->Update();
    }

    /**
     * {@inheritdoc}
     */
    public function DatabaseCopy($languageCopy = false, $aOverloadedFields = array(), $bCopyAllLanguages = false)
    {
        parent::DatabaseCopy($languageCopy, $aOverloadedFields, $bCopyAllLanguages);
        $this->_sqlFieldName = $this->oTable->sqlData['name'];
        $this->_oField = $this->oTable->GetFieldObject();
        $this->_oField->oDefinition = $this->oTable;
        $this->_oField->name = $this->_sqlFieldName;
        $this->_oField->recordId = $this->sId;
        $this->_oField->sTableName = $this->_oParentRecord->sqlData['name'];
        $this->_oField->CreateFieldDefinition();
        $this->getAutoclassesCacheWarmer()->updateTableById($this->oTable->sqlData['cms_tbl_conf_id']);
    }

    /**
     * makes it possible to modify the contents fetched from database before the copy
     * is committed.
     */
    public function OnBeforeCopy()
    {
        $this->oTable->sqlData['name'] .= '_copy';
    }

    /**
     * here you can add checks to validate the data and prevent saving.
     *
     * @var array     $postData - raw post data (e.g. datetime fields are splitted into 2 post values and in non sql format)
     * @var TIterator $oFields - TIterator of TCMSField objects
     *
     * @return bool
     */
    protected function DataIsValid(&$postData, $oFields = null)
    {
        $bDataValid = parent::DataIsValid($postData, $oFields);

        if ($bDataValid) {
            if (array_key_exists('name', $postData) && (array_key_exists('name', $this->oTable->sqlData) && $this->oTable->sqlData['name'] != $postData['name'])) {
                if (empty($postData['name'])) {
                    $bDataValid = false;
                } else {
                    $postData['name'] = trim($postData['name']);
                    $postData['name'] = strtolower($postData['name']);
                    $postData['name'] = preg_replace('/[^a-z-_0-9]/', '', $postData['name']); // allow only characters allowed by MYSQL

                    $foundName = true;
                    $count = 0;
                    while ($foundName) {
                        $tmpName = $postData['name'];
                        if ($count > 0) {
                            $tmpName .= $count;
                        }
                        $foundName = TTools::FieldExists($this->_oField->sTableName, $tmpName);
                        ++$count;
                        if (!$foundName && $postData['name'] != $tmpName) {
                            $postData['name'] = $tmpName;
                        }
                    }

                    // filter mysql reserved words
                    $aReservedMySQLWords = array('ACCESSIBLE', 'ADD', 'ALL', 'ALTER', 'ANALYZE', 'AND', 'AS', 'ASC', 'ASENSITIVE', 'BEFORE', 'BETWEEN', 'BIGINT', 'BINARY', 'BLOB', 'BOTH', 'BY', 'CALL', 'CASCADE', 'CASE', 'CHANGE', 'CHAR', 'CHARACTER', 'CHECK', 'COLLATE', 'COLUMN', 'CONDITION', 'CONSTRAINT', 'CONTINUE', 'CONVERT', 'CREATE', 'CROSS', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'CURRENT_USER', 'CURSOR', 'DATABASE', 'DATABASES', 'DAY_HOUR', 'DAY_MICROSECOND', 'DAY_MINUTE', 'DAY_SECOND', 'DEC', 'DECIMAL', 'DECLARE', 'DEFAULT', 'DELAYED', 'DELETE', 'DESC', 'DESCRIBE', 'DETERMINISTIC', 'DISTINCT', 'DISTINCTROW', 'DIV', 'DOUBLE', 'DROP', 'DUAL', 'EACH', 'ELSE', 'ELSEIF', 'ENCLOSED', 'ESCAPED', 'EXISTS', 'EXIT', 'EXPLAIN', 'FALSE', 'FETCH', 'FLOAT', 'FLOAT4', 'FLOAT8', 'FOR', 'FORCE', 'FOREIGN', 'FROM', 'FULLTEXT', 'GENERAL', 'GRANT', 'GROUP', 'HAVING', 'HIGH_PRIORITY', 'HOUR_MICROSECOND', 'HOUR_MINUTE', 'HOUR_SECOND', 'IF', 'IGNORE', 'IGNORE_SERVER_IDS', 'IN', 'INDEX', 'INFILE', 'INNER', 'INOUT', 'INSENSITIVE', 'INSERT', 'INT', 'INT1', 'INT2', 'INT3', 'INT4', 'INT8', 'INTEGER', 'INTERVAL', 'INTO', 'IS', 'ITERATE', 'JOIN', 'KEY', 'KEYS', 'KILL', 'LEADING', 'LEAVE', 'LEFT', 'LIKE', 'LIMIT', 'LINEAR', 'LINES', 'LOAD', 'LOCALTIME', 'LOCALTIMESTAMP', 'LOCK', 'LONG', 'LONGBLOB', 'LONGTEXT', 'LOOP', 'LOW_PRIORITY', 'MASTER_HEARTBEAT_PERIOD', 'MASTER_SSL_VERIFY_SERVER_CERT', 'MATCH', 'MAXVALUE', 'MEDIUMBLOB', 'MEDIUMINT', 'MEDIUMTEXT', 'MIDDLEINT', 'MINUTE_MICROSECOND', 'MINUTE_SECOND', 'MOD', 'MODIFIES', 'NATURAL', 'NOT', 'NO_WRITE_TO_BINLOG', 'NULL', 'NUMERIC', 'ON', 'OPTIMIZE', 'OPTION', 'OPTIONALLY', 'OR', 'ORDER', 'OUT', 'OUTER', 'OUTFILE', 'PRECISION', 'PRIMARY', 'PROCEDURE', 'PURGE', 'RANGE', 'READ', 'READS', 'READ_WRITE', 'REAL', 'REFERENCES', 'REGEXP', 'RELEASE', 'RENAME', 'REPEAT', 'REPLACE', 'REQUIRE', 'RESIGNAL', 'RESTRICT', 'RETURN', 'REVOKE', 'RIGHT', 'RLIKE', 'SCHEMA', 'SCHEMAS', 'SECOND_MICROSECOND', 'SELECT', 'SENSITIVE', 'SEPARATOR', 'SET', 'SHOW', 'SIGNAL', 'SLOW[b]', 'SMALLINT', 'SPATIAL', 'SPECIFIC', 'SQL', 'SQLEXCEPTION', 'SQLSTATE', 'SQLWARNING', 'SQL_BIG_RESULT', 'SQL_CALC_FOUND_ROWS', 'SQL_SMALL_RESULT', 'SSL', 'STARTING', 'STRAIGHT_JOIN', 'TABLE', 'TERMINATED', 'THEN', 'TINYBLOB', 'TINYINT', 'TINYTEXT', 'TO', 'TRAILING', 'TRIGGER', 'TRUE', 'UNDO', 'UNION', 'UNIQUE', 'UNLOCK', 'UNSIGNED', 'UPDATE', 'USAGE', 'USE', 'USING', 'UTC_DATE', 'UTC_TIME', 'UTC_TIMESTAMP', 'VALUES', 'VARBINARY', 'VARCHAR', 'VARCHARACTER', 'VARYING', 'WHEN', 'WHERE', 'WHILE', 'WITH', 'WRITE', 'XOR', 'YEAR_MONTH', 'ZEROFILL', 'GENERAL', 'IGNORE_SERVER_IDS', 'MASTER_HEARTBEAT_PERIOD', 'MAXVALUE', 'RESIGNAL', 'SIGNAL', 'SLOW');
                    if (in_array(strtoupper($postData['name']), $aReservedMySQLWords)) {
                        $bDataValid = false;
                    }
                }
            }
        }

        return $bDataValid;
    }

    /**
     * @return AutoclassesCacheWarmer
     */
    private function getAutoclassesCacheWarmer()
    {
        return \ChameleonSystem\CoreBundle\ServiceLocator::get('chameleon_system_autoclasses.cache_warmer');
    }

    /**
     * @return IPkgCmsFileManager
     */
    private function getFileManager()
    {
        return \ChameleonSystem\CoreBundle\ServiceLocator::get('chameleon_system_core.filemanager');
    }

    /**
     * @return string
     */
    private function getAutoclassesDir()
    {
        return \ChameleonSystem\CoreBundle\ServiceLocator::getParameter('chameleon_system_autoclasses.cache_warmer.autoclasses_dir');
    }
}
