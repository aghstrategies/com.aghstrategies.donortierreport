<?php

require_once 'donortierreport.civix.php';

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function donortierreport_civicrm_config(&$config) {
  _donortierreport_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function donortierreport_civicrm_install() {
  _donortierreport_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function donortierreport_civicrm_enable() {
  _donortierreport_civix_civicrm_enable();
}

// /**
//  * Implements hook_civicrm_postInstall().
//  *
//  * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
//  */
// function donortierreport_civicrm_postInstall() {
//   _donortierreport_civix_civicrm_postInstall();
// }
