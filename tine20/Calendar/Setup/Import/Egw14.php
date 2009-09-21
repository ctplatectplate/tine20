<?php
/**
 * Tine 2.0
 * 
 * @package     Calendar
 * @subpackage  Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id$ 
 */

/**
 * class to import calendars/events from egw14
 * 
 * @todo find out organizer tz
 * 
 * @package     Calendar
 * @subpackage  Setup
 */
class Calendar_Setup_Import_Egw14 {
    
    /**
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_egwDb = NULL;
    
    /**
     * @var Zend_Config
     */
    protected $_config = NULL;
    
    /**
     * @var Zend_Log
     */
    protected $_log = NULL;
    
    /**
     * maps egw attender status to tine attender status
     * 
     * @var array
     */
    protected $_attenderStatusMap = array(
        'U' => Calendar_Model_Attender::STATUS_NEEDSACTION,
        'A' => Calendar_Model_Attender::STATUS_ACCEPTED,
        'R' => Calendar_Model_Attender::STATUS_DECLINED,
        'T' => Calendar_Model_Attender::STATUS_TENTATIVE
    );
    
    /**
     * maps egw/mcal recur freqs to tine/ical requr freqs
     * 
     * @var array
     */
    protected $_rruleFreqMap = array(
        1 => Calendar_Model_Rrule::FREQ_DAILY,
        2 => Calendar_Model_Rrule::FREQ_WEEKLY,
        3 => Calendar_Model_Rrule::FREQ_MONTHLY,
        4 => Calendar_Model_Rrule::FREQ_MONTHLY,
        5 => Calendar_Model_Rrule::FREQ_YEARLY,
    );
    
    /**
     * maps egw/mcal recur wdays to tine/ical recur wdays
     * 
     * @var array (bitmap)
     */
    protected $_rruleWdayMap = array(
         1 => Calendar_Model_Rrule::WDAY_SUNDAY,
         2 => Calendar_Model_Rrule::WDAY_MONDAY,
         4 => Calendar_Model_Rrule::WDAY_TUESDAY,
         8 => Calendar_Model_Rrule::WDAY_WEDNESDAY,
        16 => Calendar_Model_Rrule::WDAY_THURSDAY,
        32 => Calendar_Model_Rrule::WDAY_FRIDAY,
        64 => Calendar_Model_Rrule::WDAY_SATURDAY,
    );
    
    /**
     * constructs a calendar import for egw14 data
     * 
     * @param Zend_Db_Adapter_Abstract  $_egwDb
     * @param Zend_Config               $_config
     * @param Zend_Log                  $_log
     */
    public function __construct($_egwDb, $_config, $_log)
    {
        $this->_egwDb  = $_egwDb;
        $this->_config = $_config;
        $this->_log    = $_log;
        
        $this->_migrationStartTime = Zend_Date::now();
        $this->_calEventBackend = new Calendar_Backend_Sql();
        
        $eventPage = $this->_getRawEgwEventPage(1, 1);
        
        foreach ($eventPage as $egwEventData) {
            $event = $this->_getTineEventRecord($egwEventData);
            /*
            $event = $this->_calEventBackend->create($event);
            
            // save attendee
            $attendee = $this->_getEventAttendee($egwEventData);
            $attendee->cal_event_id = $event->getId();
            foreach ($attendee as $attender) {
                $this->_calEventBackend->createAttendee($attender);
            }
            */
            //exdate in egw are fallouts and edits
            
        }
    }
    
    protected function _getTineEventRecord($_egwEventData)
    {
        // basic datas
        $tineEventData = array(
            'id'           => $_egwEventData['cal_id'],
            'uid'           => substr($_egwEventData['cal_uid'], 0, 40),
            'creation_time' => $_egwEventData['cal_modified'],
            'created_by'    => $_egwEventData['cal_modifier'],
            // 'tags'
            'dtstart'       => $_egwEventData['cal_start'],
            'dtend'         => $_egwEventData['cal_end'],
            'summary'       => $_egwEventData['cal_title'],
            'description'   => $_egwEventData['cal_description'],
            'location'      => $_egwEventData['cal_location'],
            'organizer'     => $_egwEventData['cal_owner'],
            'transp'        => $_egwEventData['cal_non_blocking'] ? Calendar_Model_Event::TRANSP_TRANSP : Calendar_Model_Event::TRANSP_OPAQUE,
            'priority'      => $this->getPriority($_egwEventData['cal_priority']),
            // 'class_id'
        );
        
        // ;-)
        $tineEventData['originator_tz'] = 'Europe/Berlin';
        
        // find calendar
        $tineEventData['container_id'] = $_egwEventData['cal_public'] ? 
            $this->_getPersonalCalendar($_egwEventData['cal_owner'])->getId() :
            $this->_getPrivateCalendar($_egwEventData['cal_owner'])->getId();

        // handle recuring
        if ($_egwEventData['rrule']) {
            $tineEventData['rrule'] = $this->_convertRrule($_egwEventData);
        }
        // handle alarms
        
        
        // finally create event record
        date_default_timezone_set($this->_config->egwServerTimezone);
        $tineEvent = new Calendar_Model_Event($tineEventData, FALSE, Zend_Date::TIMESTAMP);
        
        $tineEvent->dateConversionFormat = Calendar_Model_Event::ISO8601LONG;
        date_default_timezone_set('UTC');
        
        return $tineEvent;
    }
    
    /**
     * converts egw rrule into tine/iCal rrule
     * 
     * @param  array $egwRrule
     * @return Calendar_Model_Rrule
     */
    protected function _convertRrule($_egwEventData)
    {
        $egwRrule = $_egwEventData['rrule'];
        
        $rrule = new Calendar_Model_Rrule(array());
        
        if (! array_key_exists($egwRrule['recur_type'], $this->_rruleFreqMap)) {
            throw new Exception('unsupported rrule freq');
        }
        
        $rrule->freq        = $this->_rruleFreqMap[$egwRrule['recur_type']];
        $rrule->interval    = $egwRrule['recur_interval'];
        $rrule->until       = $this->convertDate($egwRrule['recur_enddate']);
        
        // weekly/monthly by wday
        if ($egwRrule['recur_type'] == 2 || $egwRrule['recur_type'] == 4) {
            $wdays = array();
            foreach($this->_rruleWdayMap as $egwBit => $iCalString) {
                if ($egwRrule['recur_data'] & $egwBit) {
                    $wdays[] = $iCalString;
                }
            }
            
            $rrule->byday = implode(',', $wdays);
        }
        
        // monthly byday/yearly bymonthday
        if ($egwRrule['recur_type'] == 3 || $egwRrule['recur_type'] == 5) {
            $dtstart = $this->convertDate($_egwEventData['cal_start']);
            $dateArray = Calendar_Model_Rrule::date2array($dtstart);
            
            $rrule->bymonthday = $dateArray['day'];
            
            if ($egwRrule['recur_type'] == 5) {
                $rrule->bymonth    = $dateArray['month'];
            }
        }
        
        return $rrule;
    }
    
    protected function _getEventAttendee($_egwEventData)
    {
        $tineAttendee = new Tinebase_Record_RecordSet('Calendar_Model_Attender');
        foreach ($_egwEventData['attendee'] as $idx => $egwAttender) {
            try {
                $tineAttenderArray = array(
                    'quantity'          => $egwAttender['cal_quantity'],
                    'role'              => Calendar_Model_Attender::ROLE_REQUIRED,
                    'status'            => array_key_exists($egwAttender['cal_status'], $this->_attenderStatusMap) ? 
                                               $this->_attenderStatusMap[$egwAttender['cal_status']] : 
                                               Calendar_Model_Attender::STATUS_NEEDSACTION,
                    'status_authkey'    => Calendar_Model_Attender::generateUID(),
                );
                
                switch($egwAttender['cal_user_type']) {
                    case 'u':
                        // user and group
                        if ($egwAttender['cal_user_id'] > 0) {
                            $tineAttenderArray['user_type'] = Calendar_Model_Attender::USERTYPE_USER;
                            $tineAttenderArray['user_id']   = Tinebase_User::getInstance()->getUserById($egwAttender['cal_user_id'])->contact_id;
                            
                            $tineAttenderArray['displaycontainer_id'] = $_egwEventData['cal_public'] ? 
                                $this->_getPersonalCalendar($egwAttender['cal_user_id'])->getId() :
                                $this->_getPrivateCalendar($egwAttender['cal_user_id'])->getId();
                        
                        } else {
                            $tineAttenderArray['user_type'] = Calendar_Model_Attender::USERTYPE_GROUP;
                            $tineAttenderArray['user_id']   = abs($egwAttender['cal_user_id']);
                        }
                        break;
                    case 'c':
                        // try to find contact in tine (NOTE: id is useless, as contacts get new ids during migration)
                        $contact_id = $this->_getContactIdByEmail($egwAttender['email'], $_egwEventData['cal_owner']);
                        if (! $contact_id) {
                            continue 2;
                        }
                        
                        $tineAttenderArray['user_type'] = Calendar_Model_Attender::USERTYPE_USER;
                        $tineAttenderArray['user_id']   = $contact_id;
                        break;
                        
                    case 'r':
                        $resource_id = $this->_getResourceId($egwAttender['cal_user_id']);
                        if (! $resource_id) {
                            continue 2;
                        }
                        
                        $tineAttenderArray['user_type'] = Calendar_Model_Attender::USERTYPE_RESOURCE;
                        $tineAttenderArray['user_id']   = $resource_id;
                        break;
                        
                    default: 
                        throw new Exception("unsupported attender type: {$egwAttender['cal_user_type']}");
                        break;
                }
                
                $tineAttendee->addRecord(new Calendar_Model_Attender($tineAttenderArray));
            } catch (Exception $e) {
                $this->_log->warn(' catched exception -> skipping attender');
                // skip attender
            }
        }

        // resolve groupmembers
        Calendar_Model_Attender::resolveGroupMembers($tineAttendee);
        $groupMembers = $tineAttendee->filter('user_type', Calendar_Model_Attender::USERTYPE_GROUPMEMBER);
        foreach ($groupMembers as $groupMember) {
            $contact = Addressbook_Controller_Contact::getInstance()->get($groupMember->user_id);
            $groupMember->displaycontainer_id = $_egwEventData['cal_public'] ? 
                $this->_getPersonalCalendar($contact->account_id)->getId() :
                $this->_getPrivateCalendar($contact->account_id)->getId();
        }
        
        return $tineAttendee;
    }
    
    /**
     * gets contact id of given email address
     * 
     * NOTE: if we find more than one contact, we could spend hours of smart guessing which one is the right one...
     *       but we don't do so yet
     *       
     * @param  string $_email
     * @param  string $_organizer
     * @return string
     */
    protected function _getContactIdByEmail($_email, $_organizer)
    {
        if (! $_email) {
            // contact not resolveable
            $this->_log->warn('no mail for contact given, contact not resolveable');
            return NULL;
        }
        
        $tineDb = Tinebase_Core::getDb();
        $select = $tineDb->select()
            ->from(array('contacts' => $tineDb->table_prefix . 'addressbook'))
            ->join(array('container' => $tineDb->table_prefix . 'container'), 
                $tineDb->quoteIdentifier('contacts.container_id') . ' = ' . $tineDb->quoteIdentifier('container.id'))
            /*->join(array('container_acl' => $tineDb->table_prefix . 'container_acl'), 
                $tineDb->quoteIdentifier('addressbook.container_id') . ' = ' . $tineDb->quoteIdentifier('container.id'))
            */
            ->where($tineDb->quoteInto($tineDb->quoteIdentifier('contacts.email') . ' LIKE ?', $_email));
            //->where($tineDb->quoteInto($tineDb->quoteIdentifier('container.type') . ' = ?', Tinebase_Model_Container::TYPE_SHARED));
        
        $contacts = $tineDb->fetchAll($select, NULL, Zend_Db::FETCH_ASSOC);

        return count($contacts) === 1 ? $contacts[0]['id'] : NULL;
    }
    
    /**
     * gets tine cal recource by egw resource id
     * 
     * @param  int $_egwResourceId
     * @return string
     */
    protected function _getResourceId($_egwResourceId)
    {
        $select = $this->_egwDb->select()
            ->from(array('resouces' => 'egw_resources'))
            ->where($this->_egwDb->quoteInto($this->_egwDb->quoteIdentifier('res_id') . ' = ?', $_egwResourceId));
        
        $egwResouces = $tineDb->fetchAll($select, NULL, Zend_Db::FETCH_ASSOC);
        
        if (count($egwResouces) !== 1) {
            $this->_log->warn('egw resource not found');
            return NULL;
        }
        $egwResouce = $egwResouces[0];
        
        // find tine resouce
        $tineResouces = Calendar_Controller_Resource::getInstance()->search(new Calendar_Model_ResourceFilter(array(
            array('field' => 'name', 'operator' => 'equals', 'value' => $egwResouce['name'])
        )));
        
        if (count($tineResouces) === 0) {
            // migrate on the fly
            $this->_log->info("migrating resource {$egwResouce['name']}");
            
            $resource = new Calendar_Model_Resource(array(
                'name'        => $egwResouce['name'],
                'description' => $egwResouce['short_description'],
                'email'       => preg_replace('/[^A-Za-z0-9.\-]/', '', $egwResouce['name'])
            ));
            
            $tineResouce = Calendar_Controller_Resource::getInstance()->create($resource);
        } else {
            $tineResouce->getFirstRecord();
        }
        
        return $tineResouce->getId();
    }
    
    /**
     * gets the personal calendar of given user
     * 
     * @param  string $_userId
     * @return Tinebase_Model_Container
     */
    protected function _getPersonalCalendar($_userId)
    {
        // get calendar by preference to ensure its the default personal
        $defaultCalendarId = Tinebase_Core::getPreference('Calendar')->getValueForUser(Calendar_Preference::DEFAULTCALENDAR, $_userId, Tinebase_Acl_Rights::ACCOUNT_TYPE_USER);
        $calendar = Tinebase_Container::getInstance()->getContainerById($defaultCalendarId);
        
        // detect if container just got created
        $isNewContainer = false;
        if ($calendar->creation_time instanceof Zend_Date) {
            $isNewContainer = $this->_migrationStartTime->isEarlier($calendar->creation_time);
        }
        
        if (($isNewContainer && $this->_config->setPersonalCalendarGrants) || $this->_config->forcePersonalCalendarGrants) {
            // resolve grants based on user/groupmemberships
            $grants = $this->getGrantsByOwner('Calendar', $_userId);
            Tinebase_Container::getInstance()->setGrants($calendar->getId(), $grants, TRUE);
        }
        
        return $calendar;
    }
    
    /**
     * gets a personal container for private events
     * 
     * NOTE: During migration phase, this container is identified by its name
     * 
     * @param  string $_userId
     * @return Tinebase_Model_Container
     */
    protected function _getPrivateCalendar($_userId)
    {
        $privateString = 'private events';
        
        $personalCalendars = Tinebase_Container::getInstance()->getPersonalContainer($_userId, 'Calendar', $_userId, Tinebase_Model_Container::GRANT_ADMIN, TRUE);
        $privateCalendar = $personalCalendars->filter('name', $privateString);
        
        if (count($privateCalendar) < 1) {
            $container = new Tinebase_Model_Container(array(
                'name'           => $privateString,
                'type'           => Tinebase_Model_Container::TYPE_PERSONAL,
                'application_id' => Tinebase_Application::getInstance()->getApplicationByName('Calendar')->getId(),
                'backend'        => 'sql',
            ));
            
            // NOTE: if no grants are given, container class gives all grants to accountId
            $privateCalendar = Tinebase_Container::getInstance()->addContainer($container, NULL, TRUE, $_userId);
        } else {
            $privateCalendar = $personalCalendars->getFirstRecord();
        }
        
        return $privateCalendar;
    }
    
    /**
     * gets a page of raw egw event data
     * 
     * @param  int $pageNumber
     * @param  int $pageSize
     * @return array
     */
    protected function _getRawEgwEventPage($pageNumber, $pageSize)
    {
        // get base event data
        $select = $this->_egwDb->select()
            ->from(array('events' => 'egw_cal'))
            ->join(array('dates'  => 'egw_cal_dates'), 'events.cal_id = dates.cal_id', array('MIN(cal_start) AS cal_start', 'MIN(cal_end) AS cal_end'))
            ->where($this->_egwDb->quoteInto($this->_egwDb->quoteIdentifier('cal_reference') . ' = ?', 0))
            //->where('cal_owner = ' . 3144)
            ->where('events.cal_id = ' . 414)
            //->where('events.cal_id = ' . 9090)
            //->where('events.cal_id = ' . 1241)
            ->group('cal_id')
            ->limitPage($pageNumber, $pageSize);
            
        $eventPage = $this->_egwDb->fetchAll($select, NULL, Zend_Db::FETCH_ASSOC);
        $eventPageIdMap = array();
        foreach ($eventPage as $idx => $egwEventData) {
            $eventPageIdMap[$egwEventData['cal_id']] = $idx;
            // preset attendee and rrule
            $egwEventData['attendee'] = array();
            $egwEventData['rrule'] = NULL;
        }
        
        // collect attendee
        $select = $this->_egwDb->select()
            ->from(array('attendee' => 'egw_cal_user')/*, array('*', 'COUNT(cal_recur_date) AS status_count')*/)
            //->group(array('cal_id', 'cal_user_type', 'cal_user_id', 'cal_status'))
            ->joinLeft(array('contacts' => 'egw_addressbook'), 
                $this->_egwDb->quoteInto($this->_egwDb->quoteIdentifier('attendee.cal_user_type') . ' = ?', 'c') . ' AND ' .
                $this->_egwDb->quoteIdentifier('attendee.cal_user_id') . ' = ' . $this->_egwDb->quoteIdentifier('contacts.contact_id'), 
                array('contacts.contact_email AS email'))
            ->where($this->_egwDb->quoteInto($this->_egwDb->quoteIdentifier('cal_recur_date') . ' = ?', 0))
            ->where($this->_egwDb->quoteInto($this->_egwDb->quoteIdentifier('cal_id') . ' IN (?)', array_keys($eventPageIdMap)));
        
        $eventPageAttendee = $this->_egwDb->fetchAll($select, NULL, Zend_Db::FETCH_ASSOC);
        
        foreach ($eventPageAttendee as $eventAttendee) {
            $idx = $eventPageIdMap[$eventAttendee['cal_id']];
            $eventPage[$idx]['attendee'][] = $eventAttendee;
        }
        unset($eventPageAttendee);
        
        // collect rrules
        $select = $this->_egwDb->select()
            ->from(array('rrule' => 'egw_cal_repeats'))
            ->where($this->_egwDb->quoteInto('cal_id IN (?)', array_keys($eventPageIdMap)));
        
        $eventPageRrules = $this->_egwDb->fetchAll($select, NULL, Zend_Db::FETCH_ASSOC);
        
        foreach ($eventPageRrules as $eventRrule) {
            $idx = $eventPageIdMap[$eventRrule['cal_id']];
            $eventPage[$idx]['rrule'] = $eventRrule;
        }
        unset($eventPageRrules);
        
        return $eventPage;
    }
    
    
    
    
    
    
    
    /**************************** generic stuff *******************************/
    
    /**
     * map egwPriority => tine prioroty
     * 
     * @see etemplate/inc/class.select_widget.inc.php
     * @var array
     */
    protected $_priorityMap = array(
        0 => NULL,  // not set
        1 => 0,     // low
        2 => 1,     // normaml
        3 => 2,     // high
    );
    
    /**
     * map egwGrant => tine grant
     * 
     * @todo   move to a generic egw import helper
     * @see phpgwapi/inc/class.egw.inc.php
     * @var array
     */
    protected $_grantMap = array(
        1 => Tinebase_Model_Container::READGRANT,
        2 => Tinebase_Model_Container::ADDGRANT,
        4 => Tinebase_Model_Container::EDITGRANT,
        8 => Tinebase_Model_Container::DELETEGRANT,
    );
    
    /**
     * converts egw date to Zend_Date
     * 
     * @param  int $_egwTS
     * @param  string $_tz timezone
     * @return Zend_Date
     */
    public function convertDate($_egwTS, $_tz)
    {
        if (! $_egwTS) {
            return NULL;
        }
        
        date_default_timezone_set($_tz);
        $date = new Zend_Date($_egwTS, Zend_Date::TIMESTAMP);
        date_default_timezone_set('UTC');
        
        return $date;
    }
    
    /**
     * converts egw -> tine priority
     * 
     * @param  int $_egwPrio
     * @return mixed
     */
    public function getPriority($_egwPrio)
    {
        return $this->_priorityMap[(int) $_egwPrio];
    }
    
    /**
     * returns grants by owner
     * 
     * eGW has owner based grants whereas Tine 2.0 has container based grants.
     * this class reads the egw owner grants and converts them into Tine 2.0 grants
     * attacheable to a tine 2.0 container
     * 
     * @todo   move to a generic egw import helper
     * 
     * @param  string $_application
     * @param  string $_accountId
     * @return Tinebase_Record_RecordSet of Tinebase_Model_Grant
     * @throws Tinebase_Exception_NotFound
     */
    public function getGrantsByOwner($_application, $_accountId)
    {
        $acl_account = array($_accountId);
        
        if ($_accountId > 0) {
            $user     = Tinebase_User::getInstance()->getUserById($_accountId);
            $groupIds = $user->getGroupMemberships();
            
            
            foreach($groupIds as $groupId) {
                $acl_account[] = '-' . $groupId;
            }
        }
        
        $select = $this->_egwDb->select()
            ->from(array('grants' => 'egw_acl'))
            ->where($this->_egwDb->quoteInto($this->_egwDb->quoteIdentifier('acl_appname') . ' = ?', $_application))
            ->where($this->_egwDb->quoteInto($this->_egwDb->quoteIdentifier('acl_account') . ' IN (?)', $acl_account));
            
        $egwGrantDatas = $this->_egwDb->fetchAll($select, NULL, Zend_Db::FETCH_ASSOC);
        //print_r($egwGrantDatas);
        
        // in a first run we merge grants from different sources
        $effectiveGrants = array();
        if ($_accountId > 0) {
            // owner has implicitly all grants in egw
            $effectiveGrants[$_accountId] = 15;
        }
        foreach ($egwGrantDatas as $egwGrantData) {
            // grants are int != 0
            if ( (int) $egwGrantData['acl_location'] == 0) {
                continue;
            }
            
            // NOTE: The grant source is not resolveable in Tine 2.0!
            //       In Tine 2.0 grants are directly given to a container
            $grantsSource      = $egwGrantData['acl_account'];
            $grantsDestination = $egwGrantData['acl_location'];
            $grantsGiven       = $egwGrantData['acl_rights'];
            
            if (! array_key_exists($grantsDestination, $effectiveGrants)) {
                $effectiveGrants[$grantsDestination] = 0;
            }
            $effectiveGrants[$grantsDestination] |= $grantsGiven;
        }
        //print_r($effectiveGrants);
        
        // convert to tine grants
        $tineGrants = new Tinebase_Record_RecordSet('Tinebase_Model_Grants');
        foreach ($effectiveGrants as $grantAccount => $egwGrants) {
            $tineGrant = new Tinebase_Model_Grants(array(
                'account_id' => abs($grantAccount),
                'account_type' => (int) $grantAccount > 0 ? 
                    Tinebase_Acl_Rights::ACCOUNT_TYPE_USER : 
                    Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP
            ));
            
            foreach ($this->_grantMap as $egwGrant => $tineGrantString) {
                $tineGrant->{$tineGrantString} = (bool) ($egwGrants & $egwGrant);
            }
            
            // the owner also gets admin grants
            if ($_accountId > 0 && $grantAccount == $_accountId) {
                $tineGrant->{Tinebase_Model_Container::ADMINGRANT} = TRUE;
            }
            
            $tineGrants->addRecord($tineGrant);
        }
        //print_r($tineGrants->toArray());
        
        // for group owners (e.g. group addressbooks) we need an container admin
        if ($_accountId < 0) {
            $adminGroup = Tinebase_Group::getInstance()->getDefaultAdminGroup();
            $tineGrant = new Tinebase_Model_Grants(array(
                'account_id' => abs($_accountId),
                'account_type' => Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP
            ));
            $tineGrant->{Tinebase_Model_Container::ADMINGRANT} = TRUE;
            
            $tineGrants->addRecord($tineGrant);
        }
        
        return $tineGrants;
    }
}