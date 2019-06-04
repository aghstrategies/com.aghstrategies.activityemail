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
        $defaults['activity_type'] = $key;
        $defaults['groups'] = explode(',', $value);
      }
    }

    return $defaults;
  }

  public function buildQuickForm() {
    // Use the 'option_value' entity for most "option" lists, e.g. event types, activity types, gender, individual_prefix, custom field options, etc.
    $this->addEntityRef('activity_type', ts('Activity Type'), array(
      'entity' => 'option_value',
      'api' => array(
        'params' => array('option_group_id' => 'activity_type'),
      ),
      'select' => array('minimumInputLength' => 0),
    ));

    $this->addEntityRef('groups', ts('Groups to Email'), array(
      'entity' => 'Group',
      'multiple' => TRUE,
      'select' => array('minimumInputLength' => 0),
    ));

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    $defaults = self::activityEmailDefaults();
    $this->setDefaults($defaults);
    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();

    $params = [
      'activityemail_setting' => [
        $values['activity_type'] => $values['groups'],
      ],
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
  }

}
