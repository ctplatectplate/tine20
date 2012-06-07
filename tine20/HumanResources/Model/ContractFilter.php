<?php
/**
 * Tine 2.0
 * @package     HumanResources
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Alexander Stintzing <a.stintzing@metaways.de>
 * @copyright   Copyright (c) 2007-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * employee filter Class
 * @package     HumanResources
 */
class HumanResources_Model_ContractFilter extends Tinebase_Model_Filter_FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'HumanResources';
    
    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = 'HumanResources_Model_Contract';
    
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'id'          => array('filter' => 'Tinebase_Model_Filter_Id', 'options' => array('modelName' => 'HumanResources_Model_Contract')),
        'employee_id' => array('filter' => 'Tinebase_Model_Filter_Id','options' => array('modelName' => 'HumanResources_Model_Employee')),
        'start_date' => array('filter' => 'Tinebase_Model_Filter_Date'),
        'end_date' => array('filter' => 'Tinebase_Model_Filter_Date'),
//         'text' => array('filter' => 'Tinebase_Model_Filter_Text'),
//         'query'                => array('filter' => 'Tinebase_Model_Filter_Query', 'options' => array('fields' => array('n_given', 'n_family', 'title'))),
//         'created_by'           => array('filter' => 'Tinebase_Model_Filter_User')
    );
}
