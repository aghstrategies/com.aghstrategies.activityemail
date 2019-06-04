<?php

use CRM_Activityemail_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Activityemail_Form_Activityemailsettings extends CRM_Core_Form {

  public function activityEmailDefaults() {
    $defaults = [];
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
      foreach ($existingSetting['activityemail_setting'] as $key => $value) {
        $defaults['activity_type_' . $key] = $key;
        $defaults['groups_' . $key] = explode(',', $value['group']);
      }
    }

    return $defaults;
  }

  public function buildQuickForm() {
    $defaults = self::activityEmailDefaults();
    // Add exsisting types/groups
    foreach ($defaults as $key => $value) {
      if (substr($key, 0, 4) === "acti") {
        $this->addEntityRef($key, ts('Activity Type'), array(
          'entity' => 'option_value',
          'api' => array(
            'params' => array('option_group_id' => 'activity_type'),
          ),
          'select' => array('minimumInputLength' => 0),
        ));
      }
      if (substr($key, 0, 4) === "grou") {
        $this->addEntityRef($key, ts('Groups to Email'), array(
          'entity' => 'Group',
          'multiple' => TRUE,
          'select' => array('minimumInputLength' => 0),
        ));
      }
    }

    // add new types/group
    $this->addEntityRef('activity_type_new', ts('Activity Type'), array(
      'entity' => 'option_value',
      'api' => array(
        'params' => array('option_group_id' => 'activity_type'),
      ),
      'select' => array('minimumInputLength' => 0),
    ));

    $this->addEntityRef('groups_new', ts('Groups to Email'), array(
      'entity' => 'Group',
      'multiple' => TRUE,
      'select' => array('minimumInputLength' => 0),
    ));

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit/Add More'),
        'isDefault' => TRUE,
      ),
    ));

    $this->setDefaults($defaults);
    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    $setting = [];
    foreach ($values as $fieldName => $value) {
      if (substr($fieldName, 0, 7) === "groups_" && substr($fieldName, 7) !== 'new' && !empty($value)) {
        $setting[substr($fieldName, 7)] = ['group' => $value];
      }
      // code...
    }
    if (!empty($values['groups_new']) && !empty($values['activity_type_new'])) {
      $setting[$values['activity_type_new']] = ['group' => $values['groups_new']];
    }
    $params = [
      'activityemail_setting' => $setting,
    ];
    try {
      $existingSetting = civicrm_api3('Setting', 'create', $params);
      CRM_Core_Session::setStatus(ts('Settings Successfully Saved'), ts('Activity Email'), 'success');
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      CRM_Core_Error::debug_log_message(
        ts('API Error: %1', array(1 => $error, 'domain' => 'com.aghstrategies.activityemail'))
      );
    }
    parent::postProcess();
    $url = CRM_Utils_System::url('civicrm/activityemail', 'reset=1');
    CRM_Utils_System::redirect($url);
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;

    // $rows = array();
    // foreach ($this->_elements as $element) {
    //   /** @var HTML_QuickForm_Element $element */
    //   $label = $element->getLabel();
    //   if (!empty($label) && substr($element->getName(), 0, 7) == 'groups_') {
    //     $rows[substr($element->getName(), 7)]['groups'] = $element->getName();
    //   }
    //   if (!empty($label) && substr($element->getName(), 0, 14) == 'activity_type_') {
    //     $rows[substr($element->getName(), 14)]['act_type'] = $element->getName();
    //   }
    // }
    // // print_r($rows); die();
    // return $rows;
  }

}
