<?php

/**
 * Tine 2.0
 *
 * @package     Addressbook
 * @subpackage  Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @copyright   Copyright (c) 2014-2018 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */
class Addressbook_Setup_Update_Release11 extends Setup_Update_Abstract
{
    /**
     * @return void
     */
    public function update_0()
    {
        $release10 = new Addressbook_Setup_Update_Release10($this->_backend);
        $release10->update_6();

        $this->setApplicationVersion('Addressbook', '11.1');
    }

    /**
     * update to 11.2
     *
     * Update export templates
     *
     * @return void
     * @throws \Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function update_1()
    {
        Setup_Controller::getInstance()->createImportExportDefinitions(Tinebase_Application::getInstance()->getApplicationByName('Addressbook'), Tinebase_Core::isReplicationSlave());

        $this->setApplicationVersion('Addressbook', '11.2');
    }

    /**
     * update to 11.3
     *
     * Update export templates
     *
     * @return void
     * @throws \Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function update_2()
    {
        Setup_Controller::getInstance()->createImportExportDefinitions(Tinebase_Application::getInstance()->getApplicationByName('Addressbook'), Tinebase_Core::isReplicationSlave());

        $this->setApplicationVersion('Addressbook', '11.3');
    }

    /**
     * update to 11.4
     *
     * Update export templates
     *
     * @return void
     * @throws \Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function update_3()
    {
        Setup_Controller::getInstance()->createImportExportDefinitions(Tinebase_Application::getInstance()->getApplicationByName('Addressbook'), Tinebase_Core::isReplicationSlave());

        $this->setApplicationVersion('Addressbook', '11.4');
    }

    /**
     * update to 11.5
     *
     * Update export templates
     *
     * @return void
     * @throws \Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function update_4()
    {
        Setup_Controller::getInstance()->createImportExportDefinitions(Tinebase_Application::getInstance()->getApplicationByName('Addressbook'), Tinebase_Core::isReplicationSlave());

        $this->setApplicationVersion('Addressbook', '11.5');
    }

    /**
     * update to 11.6
     *
     * Update export templates
     *
     * @return void
     * @throws \Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function update_5()
    {
        Setup_Controller::getInstance()->createImportExportDefinitions(Tinebase_Application::getInstance()->getApplicationByName('Addressbook'), Tinebase_Core::isReplicationSlave());

        $this->setApplicationVersion('Addressbook', '11.6');
    }

    /**
     * update to 11.7
     *
     * Update export templates
     *
     * @return void
     * @throws \Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function update_6()
    {
        Setup_Controller::getInstance()->createImportExportDefinitions(Tinebase_Application::getInstance()->getApplicationByName('Addressbook'), Tinebase_Core::isReplicationSlave());

        $this->setApplicationVersion('Addressbook', '11.7');
    }

    /**
     * update to 11.8
     *
     * Update export templates
     *
     * @return void
     * @throws \Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function update_7()
    {
        Setup_Controller::getInstance()->createImportExportDefinitions(Tinebase_Application::getInstance()->getApplicationByName('Addressbook'), Tinebase_Core::isReplicationSlave());

        $this->setApplicationVersion('Addressbook', '11.8');
    }

    /**
     * update to 11.9
     *
     * Update export templates
     *
     * @return void
     * @throws \Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function update_8()
    {
        Setup_Controller::getInstance()->createImportExportDefinitions(Tinebase_Application::getInstance()->getApplicationByName('Addressbook'), Tinebase_Core::isReplicationSlave());

        $this->setApplicationVersion('Addressbook', '11.9');
    }

    /**
     * update to 11.10
     *
     * Update export templates
     *
     * @return void
     * @throws \Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function update_9()
    {
        Setup_Controller::getInstance()->createImportExportDefinitions(Tinebase_Application::getInstance()->getApplicationByName('Addressbook'), Tinebase_Core::isReplicationSlave());

        $this->setApplicationVersion('Addressbook', '11.10');
    }

    /**
     * update to 11.11
     *
     * @return void
     * @throws \Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function update_10()
    {
        $this->updateKeyFieldIcon(Addressbook_Config::getInstance(), Addressbook_Config::CONTACT_SALUTATION);

        $this->setApplicationVersion('Addressbook', '11.11');
    }

    /**
     * update to 11.12
     *
     * @return void
     * @throws \Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function update_11()
    {
        $stateRepo = new Tinebase_Backend_Sql(array(
            'modelName' => 'Tinebase_Model_State',
            'tableName' => 'state',
        ));

        $states = $stateRepo->search(new Tinebase_Model_StateFilter(array(
            array('field' => 'state_id', 'operator' => 'equals', 'value' => 'Addressbook-Contact-GridPanel-Grid'),
        )));

        foreach($states as $state) {
            $decodedState = Tinebase_State::decode($state->data);
            $spliceAt = 0;
            if ($decodedState['columns'][1]['id'] == 'type') {
                $spliceAt = 1;
                $decodedState['columns'][1]['width'] = 25;
            }

            array_splice($decodedState, $spliceAt, 0, ['id' => 'jpegphoto', 'width' => 25]);
            array_splice($decodedState, $spliceAt, 0, ['id' => 'attachments', 'width' => 25]);

            $state->data = Tinebase_State::encode($decodedState);
            $stateRepo->update($state);
        }

        $this->setApplicationVersion('Addressbook', '11.12');
    }

    /**
     * update to 12.0
     *
     * @return void
     */
    public function update_12()
    {
        $this->setApplicationVersion('Addressbook', '12.0');
    }
}
