<?php

class CRM_Import_BemasImporter {
  public static function onEnd(CRM_Queue_TaskContext $ctx) {
    CRM_Core_Session::setStatus('Klaar.', 'Queue', 'success');
  }

  public static function process_em_participants_task(CRM_Queue_TaskContext $ctx, $id) {

  }

  public static function process_tmp_corrected_locblocks_task(CRM_Queue_TaskContext $ctx, $id) {
    $sql = "
      select
        nlb.*
        , lb.address_id
      from
        tmp_corrected_locblocks nlb
      inner join
        civicrm_loc_block lb on lb.id = nlb.id
      where
        nlb.id = $id";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      if ($dao->replace_with) {
        $sqlUpdate = "update civicrm_event set loc_block_id = " . $dao->replace_with . " where loc_block_id = " . $dao->id;
        CRM_Core_DAO::executeQuery($sqlUpdate);
      }
      else {
        $sqlUpdate = "
          update
            civicrm_address
          set
            street_address = %2
            , supplemental_address_1 = %3
            , supplemental_address_2 = %4
            , postal_code = %5
            , city = %6
          where
            id = %1
        ";
        $sqlUpdateParams = [
          1 => [$dao->address_id, 'Integer'],
          2 => [$dao->street_address, 'String'],
          3 => [$dao->supplemental_address_1, 'String'],
          4 => [$dao->supplemental_address_2, 'String'],
          5 => [$dao->postal_code, 'String'],
          6 => [$dao->city, 'String'],
        ];
        CRM_Core_DAO::executeQuery($sqlUpdate, $sqlUpdateParams);
      }
    }

    return TRUE;
  }

}