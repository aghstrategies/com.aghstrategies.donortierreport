<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'CRM_Donortierreport_Form_Report_Donortier',
    'entity' => 'ReportTemplate',
    'params' => 
    array (
      'version' => 3,
      'label' => 'Donortier',
      'description' => 'Donortier (com.aghstrategies.donortierreport)',
      'class_name' => 'CRM_Donortierreport_Form_Report_Donortier',
      'report_url' => 'contribute/donortier',
      'component' => 'CiviContribute',
    ),
  ),
);