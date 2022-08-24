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
      'work_address' => 'Verander werk-adres in Main',
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

    if ($this->queue->numberOfItems() > 0) {
//      $this->queue->deleteQueue();
    }

    if ($values['import'] !== '') {
      // put all id's in the queue
      if ($values['import'] == 'work_address') {
        $sql = "
          select
            c.id
            , c.display_name
            , SUBSTRING(c.preferred_language, 1, 2) lang
            , e_main.id main_id
            , e_main.email main
            , e_work.id work_id
            , e_work.email workz
            , e_bill.id bill_id
            , e_bill.email bill
          from
            civicrm_contact c
          left outer join	
            civicrm_email e_main on e_main.contact_id = c.id and e_main.location_type_id = 3
          left outer join	
            civicrm_email e_work on e_work.contact_id = c.id and e_work.location_type_id = 2
          left outer join	
            civicrm_email e_bill on e_bill.contact_id = c.id and e_bill.location_type_id = 5	
          where
            e_main.email = e_work.email
          and
            c.is_deleted = 0
        ";
      }
      else {
        $sql = "select id from " . $values['import'];
      }

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
