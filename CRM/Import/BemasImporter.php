<?php

class CRM_Import_BemasImporter {
  public static function onEnd(CRM_Queue_TaskContext $ctx) {
    CRM_Core_Session::setStatus('Klaar.', 'Queue', 'success');
  }

  public static function process_em_participants_task(CRM_Queue_TaskContext $ctx, $id) {

  }

  public static function process_work_address_task(CRM_Queue_TaskContext $ctx, $id) {
    // select all e-mailadresses
    $sql = "
      select
        c.id
        , c.display_name
        , SUBSTRING(c.preferred_language, 1, 2) lang
        , e_main.id main_id
        , e_main.email main
        , e_main.is_primary main_prim
        , e_work.id work_id
        , e_work.email workz
        , e_work.is_primary work_prim
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
        c.id = $id
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      if ($dao->main && $dao->workz && $dao->main == $dao->workz) {
        if ($dao->work_prim == 1) {
          // set main as prim
          CRM_Core_DAO::executeQuery("update civicrm_email set is_primary = 1 where id = " . $dao->main_id);
        }
        CRM_Core_DAO::executeQuery("delete from civicrm_email where id = " . $dao->work_id);
      }
    }

    return TRUE;
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
          2 => [$dao->street_address . '', 'String'],
          3 => [$dao->supplemental_address_1 . '', 'String'],
          4 => [$dao->supplemental_address_2 . '', 'String'],
          5 => [$dao->postal_code . '', 'String'],
          6 => [$dao->city . '', 'String'],
        ];
        CRM_Core_DAO::executeQuery($sqlUpdate, $sqlUpdateParams);
      }
    }

    return TRUE;
  }

}