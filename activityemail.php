<?php

require_once 'activityemail.civix.php';
use CRM_Activityemail_ExtensionUtil as E;

/**
 * Implements hook_civicrm_validateForm().
 */
function activityemail_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName == 'CRM_Activityemail_Form_Activityemailsettings') {
    $row = [
      'activity_type_',
      'groups_',
      'from_',
      'message_template_',
    ];
    $actTypes = [];
    foreach ($fields as $fieldName => $value) {
      if (substr($fieldName, 0, 14) == 'activity_type_') {
        // Check that there is only one row per Activity Type
        $suffix = substr($fieldName, 14);
        if (empty($actTypes[$value])) {
          $actTypes[$value] = 'set';
        }
        elseif (!empty($actTypes[$value])) {
          $errors[$fieldName] = ts('There is already a row for this Activity Type, at this time there can only be one row per activity type');
        }
        // Check that all fields in a row are filled OR Empty
        $fieldFilled = 0;
        foreach ($row as $key => $fieldPrefix) {
          if (!empty($fields[$fieldPrefix . $suffix])) {
            $fieldFilled = $fieldFilled + 1;
          }
        }
        if ($fieldFilled == 1 || $fieldFilled == 2 || $fieldFilled == 3) {
          $errors[$fieldName] = ts('All or none of the fields in this row must be filled');
        }
      }
      // Check that the from email contact selected has a primary email
      if (substr($fieldName, 0, 5) == 'from_') {
        try {
          $from = civicrm_api3('Contact', 'getsingle', array(
            'id' => $value,
            'return' => 'email',
          ));
        }
        catch (CiviCRM_API3_Exception $e) {
          $error = $e->getMessage();
          CRM_Core_Error::debug_log_message(
            ts('API Error: %1', array(1 => $error, 'domain' => 'com.aghstrategies.activityemail'))
          );
        }
        if (empty($from['email'])) {
          $errors[$fieldName] = ts('The From Email Contact must have a primary email address set.');
        }
      }
    }
  }
}

/**
 * Get Activity Email Settings
 * @return array Activity Email Settings
 */
function activityemail_getsetting() {
  $setting = NULL;
  try {
    $existingSetting = civicrm_api3('Setting', 'getsingle', array(
      'sequential' => 1,
      'return' => 'activityemail_setting',
    ));
  }
  catch (CiviCRM_API3_Exception $e) {
    $error = $e->getMessage();
    CRM_Core_Error::debug_log_message(
      ts('API Error: %1', array(1 => $error, 'domain' => 'com.aghstrategies.activityemail'))
    );
  }
  if (!empty($existingSetting['activityemail_setting'])) {
    $setting = $existingSetting['activityemail_setting'];
  }
  return $setting;
}

/**
 * Implements hook_civicrm_post().
 */
function activityemail_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  // On Creation of an Activity
  if ($op == 'create' && $objectName == 'Activity') {
    $settings = activityemail_getsetting();
    if (!empty($settings[$objectRef->activity_type_id]['group'])
      && !empty($settings[$objectRef->activity_type_id]['from'])
      && !empty($settings[$objectRef->activity_type_id]['message_template'])
    ) {
      // Save Settings
      $groups = explode(',', $settings[$objectRef->activity_type_id]['group']);
      $messageTemplateID = $settings[$objectRef->activity_type_id]['message_template'];

      try {
        $from = civicrm_api3('Contact', 'getsingle', [
          'return' => ["email", 'id', 'display_name'],
          'id' => $settings[$objectRef->activity_type_id]['from'],
        ]);
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
        CRM_Core_Error::debug_log_message(ts('API Error %1', array(
          'domain' => 'com.aghstrategies.activityemail',
          1 => $error,
        )));
      }
      if (!empty($from['email'])) {
        $fromEmail = $from['email'];
      }
      else {
        CRM_Core_Session::setStatus(ts('Activity Emails not sent because no From email is set'), ts('Activity Email'), 'error');
      }

      // Assemble Template Params
      $tplParams = [];
      $tplParams['activity_type_name'] = CRM_Core_PseudoConstant::getLabel('CRM_Activity_DAO_Activity', 'activity_type_id', $objectRef->activity_type_id);
      foreach ($objectRef as $key => $value) {
        if (substr($key, 0, 1) !== '_') {
          $tplParams['activity_' . $key] = $value;
        }
      }
      $custom = CRM_Utils_Token::getCustomFieldTokens('Activity');
      $tplParams = array_merge($tplParams, $custom);

      // Get all the members of the relevant group
      try {
        $pplInGroup = civicrm_api3('Contact', 'get', [
          'sequential' => 1,
          'return' => ["email", 'id', 'display_name'],
          'group' => ['IN' => $groups],
          'email' => ['IS NOT NULL' => 1],
          'do_not_email' => 0,
          'options' => ['limit' => ""],
        ]);
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
        CRM_Core_Error::debug_log_message(ts('API Error %1', array(
          'domain' => 'com.aghstrategies.activityemail',
          1 => $error,
        )));
      }

      // send an email to each member of the group
      if (!empty($pplInGroup['values'])) {
        foreach ($pplInGroup['values'] as $key => $values) {
          if (!empty($values['email'])) {
            // Send an email to each member of the group using a message template
            $sendTemplateParams = [
              'from' => $fromEmail,
              'messageTemplateID' => $messageTemplateID,
              'toEmail' => $values['email'],
              'toName' => $values['display_name'],
              'contactId' => $values['id'],
              'tplParams' => $tplParams,
            ];
            list($sent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplate::sendTemplate($sendTemplateParams);

            //Create an activity recording the sending of this email
            $activityParams = array();
            $activityParams['source_record_id'] = $objectId;
            $activityParams['source_contact_id'] = $settings[$objectRef->activity_type_id]['from'];
            $activityParams['activity_type_id'] = CRM_Core_PseudoConstant::getKey('CRM_Activity_DAO_Activity', 'activity_type_id', 'Email');
            $activityParams['activity_date_time'] = date('YmdHis');
            $activityParams['status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Activity_DAO_Activity', 'activity_status_id', 'Completed');
            $activityParams['medium_id'] = CRM_Core_PseudoConstant::getKey('CRM_Activity_DAO_Activity', 'encounter_medium', 'email');
            $activityParams['is_auto'] = 0;
            $activityParams['target_contact_id'] = $values['id'];
            $activityParams['subject'] = ts('details of %1 - sent to %2', [1 => $tplParams['activity_subject'], 2 => $values['display_name']]);
            $activityParams['details'] = $message;
            $activity = CRM_Activity_BAO_Activity::create($activityParams);
          }
        }
      }
    }
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function activityemail_civicrm_config(&$config) {
  _activityemail_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function activityemail_civicrm_xmlMenu(&$files) {
  _activityemail_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function activityemail_civicrm_install() {
  _activityemail_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function activityemail_civicrm_postInstall() {
  _activityemail_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function activityemail_civicrm_uninstall() {
  _activityemail_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function activityemail_civicrm_enable() {
  _activityemail_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function activityemail_civicrm_disable() {
  _activityemail_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function activityemail_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _activityemail_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function activityemail_civicrm_managed(&$entities) {
  _activityemail_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function activityemail_civicrm_caseTypes(&$caseTypes) {
  _activityemail_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function activityemail_civicrm_angularModules(&$angularModules) {
  _activityemail_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function activityemail_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _activityemail_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function activityemail_civicrm_entityTypes(&$entityTypes) {
  _activityemail_civix_civicrm_entityTypes($entityTypes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function activityemail_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function activityemail_civicrm_navigationMenu(&$menu) {
  _activityemail_civix_insert_navigation_menu($menu, 'Mailings', array(
    'label' => E::ts('New subliminal message'),
    'name' => 'mailing_subliminal_message',
    'url' => 'civicrm/mailing/subliminal',
    'permission' => 'access CiviMail',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _activityemail_civix_navigationMenu($menu);
} // */
