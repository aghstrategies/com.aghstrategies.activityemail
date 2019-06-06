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
        $defaults['from_' . $key] = $value['from'];
        $defaults['message_template_' . $key] = $value['message_template'];
      }
    }

    return $defaults;
  }

  public function buildQuickForm() {
    $defaults = self::activityEmailDefaults();
    // print_r($defaults); die();
    // Add exsisting types/groups
    foreach ($defaults as $key => $value) {
      if (substr($key, 0, 14) === "activity_type_") {
        $actId = substr($key, 14);
        $this->addEntityRef($key, ts('Activity Type'), array(
          'entity' => 'option_value',
          'api' => array(
            'params' => array('option_group_id' => 'activity_type'),
          ),
          'select' => array('minimumInputLength' => 0),
        ));
        $this->addEntityRef('groups_' . $actId, ts('Groups to Email'), array(
          'entity' => 'Group',
          'multiple' => TRUE,
          'select' => array('minimumInputLength' => 0),
        ));
        $this->addEntityRef('message_template_' . $actId, ts('Message Template'), array(
          'entity' => 'MessageTemplate',
          'api' => array(
            "label_field" => "msg_title",
            'input' => "msg_title",
            'params' => [
              'is_active' => 1,
              'is_default' => 1,
              'options' => ['limit' => ""],
            ],
          ),
          'select' => array('minimumInputLength' => 0),
        ));
        $this->add('text', 'from_' . $actId, ts('From Header'));
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

    $this->addEntityRef('message_template_new', ts('Message Template'), array(
      'entity' => 'MessageTemplate',
      'api' => array(
        "label_field" => "msg_title",
        'input' => "msg_title",
        'params' => [
          'is_active' => 1,
          'is_default' => 1,
          'options' => ['limit' => ""],
        ],
      ),
      'select' => array('minimumInputLength' => 0),
    ));
    $this->add('text', 'from_new', ts('From Header'));

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
      if (substr($fieldName, 0, 7) === "groups_"
        && substr($fieldName, 7) !== 'new'
        && !empty($value)
        && !empty($values['activity_type_' . substr($fieldName, 7)])
      ) {
        $setting[$values['activity_type_' . substr($fieldName, 7)]] = ['group' => $value];
        if (!empty($values['from_' . substr($fieldName, 7)])) {
          $setting[$values['activity_type_' . substr($fieldName, 7)]]['from'] = $values['from_' . substr($fieldName, 7)];
        }
        if (!empty($values['message_template_' . substr($fieldName, 7)])) {
          $setting[$values['activity_type_' . substr($fieldName, 7)]]['message_template'] = $values['message_template_' . substr($fieldName, 7)];
        }
      }
      // code...
    }
    if (!empty($values['groups_new']) && !empty($values['activity_type_new'])) {
      $setting[$values['activity_type_new']] = ['group' => $values['groups_new']];
      if (!empty($values['from_new'])) {
        $setting[$values['activity_type_new']]['from'] = $values['from_new'];
      }
      if (!empty($values['message_template_new'])) {
        $setting[$values['activity_type_new']]['message_template'] = $values['message_template_new'];
      }
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
  }

}
