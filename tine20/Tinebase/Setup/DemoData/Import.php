<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2018 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */

/**
 * Abstract class for DemoData Import
 *
 * @package     Tinebase
 * @subpackage  Setup
 */
class Tinebase_Setup_DemoData_Import
{
    protected $_application = null;
    protected $_options = [];

    public function __construct($modelName, $options = [])
    {
        $extract = Tinebase_Application::extractAppAndModel($modelName);
        $this->_options['$modelName'] = $extract['modelName'];
        $this->_options['dryrun'] = false;
        $this->_application = Tinebase_Application::getInstance()->getApplicationByName($extract['appName']);
        $this->_options = array_merge($this->_options, $options);
    }

    public function importDemodata()
    {
        $importDir = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR
            . $this->_application->name . DIRECTORY_SEPARATOR . 'Setup' . DIRECTORY_SEPARATOR . 'DemoData'
            . DIRECTORY_SEPARATOR . 'import'. DIRECTORY_SEPARATOR . $this->_options['$modelName'];

        if (! file_exists($importDir)) {
            throw new Tinebase_Exception_NotFound('Import dir not found: ' . $importDir);
        }

        // loop all files in import dir
        // TODO allow filters / subdirs
        $fh = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($importDir), RecursiveIteratorIterator::CHILD_FIRST);
        $importedDemoDataFiles = 0;
        foreach ($fh as $splFileInfo) {
            $result = $this->_importDemoDataFile($splFileInfo);
            if ($result) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' Import result: ' . print_r($result, true));
                $importedDemoDataFiles++;
            }
        }

        if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' Imported ' . $importedDemoDataFiles . ' demo data files');
    }

    /**
     * @param SplFileInfo $splFileInfo
     * @return null
     */
    protected function _importDemoDataFile(SplFileInfo $splFileInfo)
    {
        // TODO allow xls
        $importFileExtensions = ['csv'];

        if (in_array($splFileInfo->getExtension(), $importFileExtensions)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                . ' Importing file ' . $splFileInfo->getPathname());
            $filename = $splFileInfo->getFilename();

            // create importer
            if (isset($this->_options['definition'])) {
                $definition = Tinebase_ImportExportDefinition::getInstance()->getByName($this->_options['definition']);
            } else {
                // create generic import definition if not found in options
                $definition = Tinebase_ImportExportDefinition::getInstance()->getGenericImport($this->_options['$modelName']);
            }
            $importClass = $this->_application->name . '_Import_Csv';
            $this->_importer = call_user_func_array([$importClass, 'createFromDefinition'], [$definition, $this->_options]);

            $result = $this->_importer->importFile($splFileInfo->getPath() . DIRECTORY_SEPARATOR . $filename);
            return $result;
        }

        return null;
    }
}
