<?php
/**
 * Tine 2.0 - http://www.tine20.org
 *
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2018 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Paul Mehrer <p.mehrer@metaways.de>
 */

/**
 * Test class for Action Queue
 */
class Tinebase_ActionQueue_Test extends TestCase
{
    /**
     * unit in test
     *
     * @var Tinebase_ActionQueue
     */
    protected $_uit = null;

    protected $_oldConfActive;
    protected $_oldConfBackend;

    /**
     * set up tests
     */
    protected function setUp()
    {
        parent::setUp();

        $config = Tinebase_Config::getInstance();
        $this->_oldConfActive = $config->{Tinebase_Config::ACTIONQUEUE}->{Tinebase_Config::ACTIONQUEUE_ACTIVE};
        $this->_oldConfBackend = $config->{Tinebase_Config::ACTIONQUEUE}->{Tinebase_Config::ACTIONQUEUE_BACKEND};

        $config->{Tinebase_Config::ACTIONQUEUE}->{Tinebase_Config::ACTIONQUEUE_ACTIVE} = true;
        $config->{Tinebase_Config::ACTIONQUEUE}->{Tinebase_Config::ACTIONQUEUE_BACKEND} = 'Test';

        $this->_uit = Tinebase_ActionQueue::getInstance('Test');
    }

    protected function tearDown()
    {
        $config = Tinebase_Config::getInstance();
        $config->{Tinebase_Config::ACTIONQUEUE}->{Tinebase_Config::ACTIONQUEUE_ACTIVE} = $this->_oldConfActive;
        $config->{Tinebase_Config::ACTIONQUEUE}->{Tinebase_Config::ACTIONQUEUE_BACKEND} = $this->_oldConfBackend;

        parent::tearDown();

        Tinebase_ActionQueue::destroyInstance();
    }

    protected function checkMonitoringCheckQueueOutput($expectedOutput, $expectedReturn)
    {
        $tbFe = new Tinebase_Frontend_Cli();
        ob_start();
        $result = $tbFe->monitoringCheckQueue();
        $output = ob_get_clean();

        if (is_string($expectedOutput)) {
            static::assertEquals($expectedOutput, $output);
        } elseif (is_callable($expectedOutput)) {
            static::assertTrue($expectedOutput($output), 'output not as expected: ' . $output);
        }
        static::assertEquals($expectedReturn, $result);
    }

    public function testMonitoringCheckQueue()
    {
        $tbApp = Tinebase_Application::getInstance();

        $config = Tinebase_Config::getInstance();

        $config->{Tinebase_Config::ACTIONQUEUE}->{Tinebase_Config::ACTIONQUEUE_ACTIVE} = false;
        $this->checkMonitoringCheckQueueOutput("QUEUE INACTIVE\n", 0);
        $config->{Tinebase_Config::ACTIONQUEUE}->{Tinebase_Config::ACTIONQUEUE_ACTIVE} = true;

        Tinebase_ActionQueue_Backend_Test::$_hasAsyncBackend = false;
        $this->checkMonitoringCheckQueueOutput("QUEUE INACTIVE\n", 0);
        Tinebase_ActionQueue_Backend_Test::$_hasAsyncBackend = true;

        $tbApp->deleteApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_DURATION);
        $this->checkMonitoringCheckQueueOutput('QUEUE FAIL: ' . Tinebase_Exception::class . ' - state ' .
            Tinebase_Application::STATE_ACTION_QUEUE_LAST_DURATION . " not set\n", 2);

        $tbApp->setApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_DURATION, 3601);
        $tbApp->deleteApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_DURATION_UPDATE);
        $this->checkMonitoringCheckQueueOutput('QUEUE FAIL: ' . Tinebase_Exception::class . ' - state ' .
            Tinebase_Application::STATE_ACTION_QUEUE_LAST_DURATION_UPDATE . " not set\n", 2);

        $tbApp->setApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_DURATION_UPDATE,
            time() - 3650);
        Tinebase_ActionQueue_Backend_Test::$_peekJobId = false;
        $tbApp->setApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_JOB_ID, 'a');
        $this->checkMonitoringCheckQueueOutput('QUEUE FAIL: ' . Tinebase_Exception::class .
            " - last duration > 3600 sec - 3601\n", 2);
        static::assertEquals('', $tbApp->getApplicationState('Tinebase',
            Tinebase_Application::STATE_ACTION_QUEUE_LAST_JOB_ID));

        $tbApp->setApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_DURATION, 3599);
        $this->checkMonitoringCheckQueueOutput(function($val) { return strpos($val, 'QUEUE FAIL: ' .
            Tinebase_Exception::class . ' - last duration update > 3600 sec - 36') === 0;}, 2);

        $tbApp->setApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_DURATION_UPDATE,
            time() - 390);
        $this->checkMonitoringCheckQueueOutput(function($val) { return strpos($val,
            'QUEUE WARN: last duration > 60 sec - 3599 | size=') === 0;}, 1);

        $tbApp->setApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_DURATION, 59);
        $this->checkMonitoringCheckQueueOutput(function($val) { return strpos($val,
            'QUEUE WARN: last duration update > 60 sec - 3') === 0;}, 1);

        $tbApp->setApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_DURATION_UPDATE, time());
        $this->checkMonitoringCheckQueueOutput(function($val) { return strpos($val,
            'QUEUE OK | size=0;lastJobId=0;lastDuration=59;lastDurationUpdate=') === 0;}, 0);


        Tinebase_ActionQueue_Backend_Test::$_peekJobId = 'a';
        $tbApp->deleteApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_JOB_CHANGE);
        $tbApp->deleteApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_JOB_ID);
        $this->checkMonitoringCheckQueueOutput(function($val) { return strpos($val,
                'QUEUE OK | size=0;lastJobId=0;lastDuration=59;lastDurationUpdate=') === 0;}, 0);
        static::assertEquals('a', $tbApp->getApplicationState('Tinebase',
            Tinebase_Application::STATE_ACTION_QUEUE_LAST_JOB_ID));
        static::assertLessThan(2, time() - (int)($tbApp->getApplicationState('Tinebase',
                Tinebase_Application::STATE_ACTION_QUEUE_LAST_JOB_CHANGE)));

        $tbApp->deleteApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_JOB_CHANGE);
        $this->checkMonitoringCheckQueueOutput('QUEUE FAIL: ' . Tinebase_Exception::class . ' - state ' .
            Tinebase_Application::STATE_ACTION_QUEUE_LAST_JOB_CHANGE . " not set\n", 2);

        $tbApp->setApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_JOB_CHANGE, time() - 905);
        $this->checkMonitoringCheckQueueOutput(function($val) { return strpos($val,
            'QUEUE FAIL: ' . Tinebase_Exception::class . ' - last job id change > 900 sec - 90') === 0;}, 2);

        $tbApp->setApplicationState('Tinebase', Tinebase_Application::STATE_ACTION_QUEUE_LAST_JOB_CHANGE, time() - 850);
        $this->checkMonitoringCheckQueueOutput(function($val) { return strpos($val,
                'QUEUE WARN: last job id change > 60 sec - 8') === 0;}, 1);
    }
}