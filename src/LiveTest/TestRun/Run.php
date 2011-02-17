<?php

namespace LiveTest\TestRun;

use LiveTest\Listener\ProgressBar;

use Annovent\Event\Event;

use Annovent\Event\Dispatcher;

use Base\Http\HttpClient;
use Base\Timer\Timer;

use Base\Http\ConnectionStatus;

use Base\Www\Uri;

use LiveTest\Extensions\Extension;
use LiveTest\TestCase\Exception;
use LiveTest\TestRun\Result\ResultSet;
use LiveTest\TestRun\Result\Result;

use Base\Http\Response;
use Base\Http\Client;

class Run
{
  /**
   * All properties for the test run
   * 
   * @var LiveTest\TestRun\Properties
   */
  private $properties;
  private $httpClient = null;
  private $eventDispatcher;
  
  public function __construct(Properties $properties, HttpClient $httpClient, Dispatcher $dispatcher)
  {    
    $this->eventDispatcher = $dispatcher;    
    $this->httpClient = $httpClient;
    // @todo is the properties object needed? TestSet would work as well
    $this->properties = $properties;
  }
   
  private function runTests(TestSet $testSet, Response $response)
  {
    foreach ($testSet->getTests() as $test)
    {
      $testCaseName = $test->getClassName();
      try
      {
        $testCaseObject = new $testCaseName($test->getParameter());
        $testCaseObject->test($response, new Uri($testSet->getUrl()));
        $result = new Result($test, Result::STATUS_SUCCESS, '', $testSet->getUrl());
      }
      catch ( \LiveTest\TestCase\Exception $e )
      {
        $result = new Result($test, Result::STATUS_FAILED, $e->getMessage(), $testSet->getUrl());
      }
      catch (\Exception $e )
      {
        $result = new Result($test, Result::STATUS_ERROR, $e->getMessage(), $testSet->getUrl());
      }
      $event = new Event('LiveTest.Run.HandleResult', array( 'result' => $result, 'response' => $response ));
      $this->eventDispatcher->notify($event);
    }
  }
   
  public function run()
  {    
    $event = new Event('LiveTest.Run.PreRun', array('properties' => $this->properties));
    $continueRun = $this->eventDispatcher->notify($event);
    
    if ($continueRun)
    {
      $timer = new Timer();
      $testSets = $this->properties->getTestSets();
      $client = $this->httpClient;
      
      foreach ($testSets as $testSet)
      {
        try
        {
          $client->setUri($testSet->getUrl());
          $response = $client->request();
          $connectionStatus = new ConnectionStatus(ConnectionStatus::SUCCESS, new Uri($testSet->getUrl()));
          $event = new Event('LiveTest.Run.HandleConnectionStatus', array( 'connectionStatus' => $connectionStatus ));
          $this->eventDispatcher->notify($event);
        }
        catch ( \Zend_Http_Client_Adapter_Exception $e )
        {
          $connectionStatus = new ConnectionStatus(ConnectionStatus::ERROR, new Uri($testSet->getUrl()), $e->getMessage());
          $event = new Event('LiveTest.Run.HandleConnectionStatus', array( 'connectionStatus' => $connectionStatus ));
          $this->eventDispatcher->notify($event);
          continue;
        }
        catch ( \Zend_Http_Client_Exception $e )
        {
          $connectionStatus = new ConnectionStatus(ConnectionStatus::ERROR, new Uri($testSet->getUrl()), $e->getMessage());
          $event = new Event('LiveTest.Run.HandleConnectionStatus', array( 'connectionStatus' => $connectionStatus ));
          $this->eventDispatcher->notify($event);
          continue;
        }
        $this->runTests($testSet, $response);
      }
      $timer->stop();
      $information = new Information($timer->getElapsedTime(), $this->properties->getDefaultDomain());

      $event = new Event('LiveTest.Run.PostRun', array( 'information' => $information ));
      $this->eventDispatcher->notify($event);
    }
  }
}
