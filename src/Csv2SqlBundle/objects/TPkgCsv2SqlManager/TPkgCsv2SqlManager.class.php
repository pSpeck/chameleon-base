<?php

/*
 * This file is part of the Chameleon System (https://www.chameleonsystem.com).
 *
 * (c) ESONO AG (https://www.esono.de)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class TPkgCsv2SqlManager
{
    /**
     * Mail profile.
     */
    const IMPORT_ERROR_LOG_MAIL = 'shop-import-data';
    /**
     * error-log filename.
     */
    const IMPORT_ERROR_LOG_FILE = 'TPkgCsv2SqlManager.log';

    /**
     * call this method if you want to import all files (manages validation, and error handling)
     * note: code was moved from TPkgCsv2Sql_CmsListManagerPkgCsv2sql::ProcessImport so we can call it via cron-job as well.
     *
     * @static
     */
    public static function ProcessAll()
    {
        $aImportErrors = array();
        $aAllErr = array();

        $aData = array();
        $aData['oImportName'] = 'CSV-2-SQL Datenimport:';
        $aData['successMessage'] = '';

        TTools::WriteLogEntry('TPkgCsv2SqlManager: ProcessAll Start', 4, __FILE__, __LINE__, self::IMPORT_ERROR_LOG_FILE);
        $aValidationErrors = self::ValidateAll();
        TTools::WriteLogEntry('TPkgCsv2SqlManager: ValidateAll end', 4, __FILE__, __LINE__, self::IMPORT_ERROR_LOG_FILE);
        if (0 == count($aValidationErrors)) {
            // all good, import
            $aImportErrors = self::ImportAll();
            $aData['successMessage'] = TGlobal::OutHTML(TGlobal::Translate('chameleon_system_csv2sql.msg.import_completed'));
        }
        TTools::WriteLogEntry('TPkgCsv2SqlManager: ImportAll end', 4, __FILE__, __LINE__, self::IMPORT_ERROR_LOG_FILE);

        $aAllErr = TPkgCsv2Sql::ArrayConcat($aAllErr, $aValidationErrors);
        $aAllErr = TPkgCsv2Sql::ArrayConcat($aAllErr, $aImportErrors);
        if (count($aAllErr) > 0) {
            //send all errors by email
            self::SendErrorNotification($aAllErr);
        }
        TTools::WriteLogEntry('TPkgCsv2SqlManager: SendErrorNotification end', 4, __FILE__, __LINE__, self::IMPORT_ERROR_LOG_FILE);

        //View vars
        $aData['aValidationErrors'] = (array) $aValidationErrors;
        $aData['aImportErrors'] = (array) $aImportErrors;

        return $aData;
    }

    /**
     * Import all csv files to database tables.
     *
     * Manager::ImportAll()
     * get list of import handler
     * set Log-File-name
     * for each handler, call Import()
     * if Log-File is not empty at end of Import, send E-Mail PKG-CSV-2-SQL-ERRORS-LOGGED and the Log-File as attachment
     */
    public static function ImportAll()
    {
        $aErrors = array();
        // get list of import handler
        /** @var $oCsv2SqlList TdbPkgCsv2sqlList */
        $oCsv2SqlList = TdbPkgCsv2sqlList::GetList();
        $oCsv2SqlList->GoToStart();
        while ($oListItem = $oCsv2SqlList->Next()) {
            TTools::WriteLogEntry('TPkgCsv2SqlManager: Import '.$oListItem->fieldName, 4, __FILE__, __LINE__, self::IMPORT_ERROR_LOG_FILE);

            $aItemErrors = $oListItem->Import();
            $aErrors = TPkgCsv2Sql::ArrayConcat($aErrors, $aItemErrors);
        }

        return $aErrors;
    }

    /**
     * Validate all csv files.
     *
     * Manager::ValidateAll()
     *  get list of import handler
     *  all files found?
     *  YES: for every hanlder, call validate.
     *     merge validate results an return
     *  NO: only bestand present?
     *    YES: validate bestand and return result
     *    NO: generate error "PKG-CSV-2-SQL-MISSING-FILES" with list of files missing and return
     *
     *
     * @return array
     */
    public static function ValidateAll()
    {
        $aErrors = array();

        //get list of import handler
        /** @var $oCsv2SqlList TdbPkgCsv2sqlList */
        $oCsv2SqlList = TdbPkgCsv2sqlList::GetList();
        $oCsv2SqlList->GoToStart();
        while ($oListItem = $oCsv2SqlList->Next()) {
            TTools::WriteLogEntry('TPkgCsv2SqlManager: Validating '.$oListItem->fieldName, 4, __FILE__, __LINE__, self::IMPORT_ERROR_LOG_FILE);
            $aItemErrors = $oListItem->Validate();
            $aErrors = TPkgCsv2Sql::ArrayConcat($aErrors, $aItemErrors);
        }

        return $aErrors;
    }

    /**
     * Merge all logs to big one.
     */
    protected static function MergeLogs()
    {
        $sLogSourcePath = ERROR_LOG_FOLDER;
        $sMainLogFile = $sLogSourcePath.self::IMPORT_ERROR_LOG_FILE;

        /** @var $oCsv2SqlList TdbPkgCsv2sqlList */
        $oCsv2SqlList = TdbPkgCsv2sqlList::GetList();
        while ($oListItem = $oCsv2SqlList->Next()) {
            $sLog = $sLogSourcePath.$oListItem->fieldDestinationTableName.'.log';
            if (file_exists($sLog)) {
                $sfp = fopen($sLog, 'rb');
                $fp = fopen($sMainLogFile, 'wb');
                $sStrTmp = "\r\n\r\n*******************************".$oListItem->fieldDestinationTableName."*******************************\r\n\r\n";
                fwrite($fp, $sStrTmp, strlen($sStrTmp));
                while ($string = fread($sfp, 4096)) {
                    fwrite($fp, $string, strlen($string));
                }
                fclose($sfp);
                fclose($fp);
            }
        }
    }

    /**
     * Send import log (on error!).
     *
     * @param $aErrors
     */
    public static function SendErrorNotification($aErrors)
    {
        self::MergeLogs();

        $sLogSourcePath = ERROR_LOG_FOLDER;
        $sLogAchivePath = $sLogSourcePath.'/import-archiv/';

        //check archive-path
        $bArchivePathOK = false;
        if (file_exists($sLogAchivePath) && is_dir($sLogAchivePath) && is_writeable($sLogAchivePath)) {
            $bArchivePathOK = true;
        }

        $sMailBody = __CLASS__.'-Report'."\r\n\r\n";
        //$sErrorLines = implode("\r\n",$aErrors);
        $sErrorLines = '';
        if (is_array($aErrors) && count($aErrors)) {
            foreach ($aErrors as $iK => $mVal) {
                if (is_array($mVal)) {
                    $mVal = '<pre>'.print_r($mVal, true).'</pre>';
                }
                $sNum = str_pad((string) $iK, 5, ' ', STR_PAD_RIGHT);
                $sErrorLines .= $sNum.': '.$mVal."\r\n";
            }
        }
        $sMailBody .= $sErrorLines;

        //send report by mail
        $oMailProfile = TdbDataMailProfile::GetProfile(self::IMPORT_ERROR_LOG_MAIL);
        if (count($aErrors)) {
            $oMailProfile->SetSubject('FEHLER: '.$oMailProfile->sqlData['subject']);
        }

        $aAttachFiles = null;
        $sLogFile = $sLogSourcePath.self::IMPORT_ERROR_LOG_FILE;
        if (count($aErrors)) {
            //attach error-log as mail-attachment
            if (file_exists($sLogFile)) {
                $aAttachFiles[] = $sLogFile;
            } else {
                //we have errors but no error file? report!
                $sMailBody .= "\r\nACHTUNG: \r\nEs sind Fehler aufgetreten, aber es wurde kein Logfile gefunden in:".$sLogFile." !\r\n";
            }

            //check if Archive-Path exists (if not report!)
            if (!$bArchivePathOK) {
                $sMailBody .= "\r\nACHTUNG: \r\nArchiv-Pfad für Logs existiert nicht oder ist nicht beschreibbar!\r\nLogs können nicht verschoben werden nach: ".$sLogAchivePath."\r\n";
            }
        }

        $aMailData = array('sReport' => '<pre>'.$sMailBody.'</pre>');
        $oMailProfile->AddDataArray($aMailData);
        $oMailProfile->SendUsingObjectView('emails', 'Customer', $aAttachFiles);

        if (is_array($aAttachFiles) && $bArchivePathOK) {
            //move files to import-archive
            foreach ($aAttachFiles as $iFKey => $sFilePath) {
                $sNewPath = $sLogAchivePath.date('y-m-d-H-i-s').basename($sFilePath);
                if (!@rename($sFilePath, $sNewPath)) {
                    if (copy($sFilePath, $sNewPath)) {
                        if (!@unlink($sFilePath)) {
                            echo 'Das Log wurde in das Archiv-Verzeichnis kopiert. Es konnte jedoch nicht entfernt werden!';
                            //try to clear file
                            $f = fopen($sFilePath, 'w+');
                            fclose($f);
                        }
                    }
                }
            }
        }
    }
}
