<?php

use CRM_Import_ExtensionUtil as E;

class CRM_Import_Form_BemasImport extends CRM_Core_Form {
  private $queue;
  private $queueName = 'bemasmport';

  public function __construct() {
    // create the queue
    $this->queue = CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => $this->queueName,
      'reset' => FALSE, //do not flush queue upon creation
    ]);

    parent::__construct();
  }

  public function buildQuickForm() {
    $importMenuOptions = [
      'em_participants' => 'Importeer Euromaintenance 4.0 deelnemers',
      'tmp_corrected_locblocks' => 'Corrigeer locaties evementen',
    ];
    $this->addRadio('import', 'Import:', $importMenuOptions, NULL, '<br>');

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();

    if ($values['import'] !== '') {
      // put all id's in the queue
      $sql = "select id from " . $values['import'];
      $dao = CRM_Core_DAO::executeQuery($sql);
      while ($dao->fetch()) {
        $method = 'process_' . $values['import'] . '_task';
        $task = new CRM_Queue_Task(['CRM_Import_BemasImporter', $method], [$dao->id]);
        $this->queue->createItem($task);
      }
    }

    // run the queue if we have items
    if ($this->queue->numberOfItems()) {
      // run the queue
      $runner = new CRM_Queue_Runner([
        'title' => 'BEMAS Import',
        'queue' => $this->queue,
        'errorMode' => CRM_Queue_Runner::ERROR_CONTINUE,
        'onEnd' => ['CRM_Import_BemasImporter', 'onEnd'],
        'onEndUrl' => CRM_Utils_System::url('civicrm/bemasimport', 'reset=1'),
      ]);
      $runner->runAllViaWeb();
    }

    parent::postProcess();
  }

  public function getRenderableElementNames() {
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
