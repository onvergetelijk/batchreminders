<?php

require_once 'batchreminders.civix.php';

use CRM_Batchreminders_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function batchreminders_civicrm_config(&$config): void {
  _batchreminders_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function batchreminders_civicrm_install(): void {
  _batchreminders_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function batchreminders_civicrm_enable(): void {
  _batchreminders_civix_civicrm_enable();
}
