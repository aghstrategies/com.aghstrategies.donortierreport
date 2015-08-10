<?php
/**
 * @file
 * Donor tier report.
 *
 * Group donors into tiers by their giving and then group by that.
 */

/**
 * Extend the summary report class because it's most of the way there.
 */
class CRM_Donortierreport_Form_Report_Donortier extends CRM_Report_Form_Contribute_Summary {

  /**
   * Name for the temp table holding sums per donor.
   */
  protected $_tierTableName = '';

  /**
   * Same as $_from but without the join on the temp table.
   */
  protected $_noTierFrom = '';

  /**
   * Mostly borrow from summary report.
   */
  public function __construct() {
    parent::__construct();
    $newColumns = array(
      'donortier' => array(
        'fields' => array(
          'tier' => array(
            'title' => ts('Tier', array('domain' => 'com.aghstrategies.donortierreport')),
            'type' => CRM_Utils_Type::T_STRING,
            'default' => TRUE,
          ),
        ),
        'group_bys' => array(
          'tier' => array(
            'title' => ts('Tier', array('domain' => 'com.aghstrategies.donortierreport')),
            'default' => TRUE,
          ),
        ),
      ),
    );
    $this->_columns = array_merge($newColumns, $this->_columns);

    // Unset defaults from donor summary.

    $this->_columns['civicrm_contribution']['fields']['receive_date']['default'] = FALSE;
    $this->_columns['civicrm_contribution']['group_bys']['receive_date']['default'] = FALSE;
    $this->_columns['civicrm_contribution']['fields']['contribution_status_id']['default'] = FALSE;
    $this->_columns['civicrm_contribution']['group_bys']['contribution_status_id']['default'] = FALSE;
    $this->_columns['civicrm_contribution']['fields']['campaign_id']['default'] = FALSE;
    $this->_columns['civicrm_address']['fields']['country_id']['default'] = FALSE;

    $this->_options = array(
      'tier1label' => array(
        'title' => ts('Highest donor tier', array('domain' => 'com.aghstrategies.donortierreport')),
        'type' => 'text',
        'default' => ts('Major donor', array('domain' => 'com.aghstrategies.donortierreport')),
      ),
      'tier1threshold' => array(
        'title' => ts('Lower threshold for highest donor tier', array('domain' => 'com.aghstrategies.donortierreport')),
        'type' => 'text',
        'default' => 5000,
      ),
      'tier2label' => array(
        'title' => ts('Second donor tier', array('domain' => 'com.aghstrategies.donortierreport')),
        'type' => 'text',
        'default' => ts('Mid-level donor', array('domain' => 'com.aghstrategies.donortierreport')),
      ),
      'tier2threshold' => array(
        'title' => ts('Lower threshold for second donor tier', array('domain' => 'com.aghstrategies.donortierreport')),
        'type' => 'text',
        'default' => 1000,
      ),
      'tier3label' => array(
        'title' => ts('Third donor tier', array('domain' => 'com.aghstrategies.donortierreport')),
        'type' => 'text',
        'default' => ts('Small donor', array('domain' => 'com.aghstrategies.donortierreport')),
      ),
      'tier3threshold' => array(
        'title' => ts('Lower threshold for third donor tier', array('domain' => 'com.aghstrategies.donortierreport')),
        'type' => 'text',
        'default' => 100,
      ),
      'tier4label' => array(
        'title' => ts('Lowest donor tier', array('domain' => 'com.aghstrategies.donortierreport')),
        'type' => 'text',
        'default' => ts('Micro donor', array('domain' => 'com.aghstrategies.donortierreport')),
      ),
    );
  }

  /**
   * Basically lifted from CRM_Report_Form, except adding handling for text options.
   */
  public function addOptions() {
    if (!empty($this->_options)) {
      // FIXME: For now lets build all elements as checkboxes.
      // Once we clear with the format we can build elements based on type

      $defaults = array();
      foreach ($this->_options as $fieldName => $field) {
        $options = array();

        switch ($field['type']) {
          case 'select':
            $this->addElement('select', "{$fieldName}", $field['title'], $field['options']);
            break;

          case 'checkbox':
            $options[$field['title']] = $fieldName;
            $this->addCheckBox($fieldName, NULL,
              $options, NULL,
              NULL, NULL, NULL, $this->_fourColumnAttribute
            );
            break;

          case 'text':
            $this->add('text', $fieldName, ts($field['title']));
            break;

          default:
        }
        if (CRM_Utils_Array::value('default', $field)) {
          $defaults[$fieldName] = $field['default'];
        }
      }
      $this->setDefaults($defaults);
    }
    if (!empty($this->_options)) {
      $this->tabs['ReportOptions'] = array(
        'title' => ts('Display Options', array('domain' => 'com.aghstrategies.donortierreport')),
        'tpl' => 'ReportOptions',
        'div_label' => 'other-options',
      );
    }
    $this->assign('otherOptions', $this->_options);
  }

  /**
   * @param bool $applyLimit
   *
   * @return string
   */
  public function buildQuery($applyLimit = TRUE) {
    $randomNum = md5(uniqid());
    $this->_tierTableName = "civicrm_temp_donortier_{$randomNum}";

    $sql = parent::buildQuery($applyLimit);

    // Figure out the tiers.
    // $this->_params['options']['tier1threshold']
    $filteredOptions = filter_var_array($this->_params, FILTER_SANITIZE_NUMBER_FLOAT);

    $tierselect3 = $filteredOptions['tier3threshold'] ? "IF(SUM({$this->_aliases['civicrm_contribution']}.total_amount) >= {$filteredOptions['tier3threshold']}, 3, 4)" : 3;
    $tierselect2 = $filteredOptions['tier2threshold'] ? "IF(SUM({$this->_aliases['civicrm_contribution']}.total_amount) >= {$filteredOptions['tier2threshold']}, 2, $tierselect3)" : 2;
    $tierselect1 = $filteredOptions['tier1threshold'] ? "IF(SUM({$this->_aliases['civicrm_contribution']}.total_amount) >= {$filteredOptions['tier1threshold']}, 1, $tierselect2)" : 1;

    // Populate the temp table.
    $tempSql = "CREATE TEMPORARY TABLE {$this->_tierTableName} ( INDEX(contact_id) ) ENGINE=HEAP AS (
      SELECT {$this->_aliases['civicrm_contact']}.id as contact_id, SUM({$this->_aliases['civicrm_contribution']}.total_amount) as total_amount_sum, $tierselect1 as tier
      {$this->_noTierFrom} {$this->_where}
      GROUP BY contact_id
      {$this->_having} )";
    CRM_Core_DAO::executeQuery($tempSql);

    return $sql;
  }

  /**
   * Select additional tier field.
   */
  public function select() {
    parent::select();
    // $this->_select = substr($this->_select, 0, -1) . ', "hello" AS hellofield';
  }

  /**
   * Extends from to join the donor tier table
   */
  public function from($entity = NULL) {
    parent::from($entity);

    $this->_noTierFrom = $this->_from;

    $this->_from .= "LEFT JOIN {$this->_tierTableName} {$this->_aliases['donortier']}
        ON {$this->_aliases['donortier']}.contact_id = {$this->_aliases['civicrm_contact']}.id\n";
  }

  /**
   * Alter display of rows.
   *
   * Iterate through the rows retrieved via SQL and make changes for display purposes,
   * such as rendering contacts as links.
   *
   * @param array $rows
   *   Rows generated by SQL, with an array for each row.
   */
  public function alterDisplay(&$rows) {
    $filteredOptions = filter_var_array($this->_params, FILTER_SANITIZE_STRING);
    $tiers = array(
      1 => CRM_Utils_Array::value('tier1label', $filteredOptions, 1),
      2 => CRM_Utils_Array::value('tier2label', $filteredOptions, 2),
      3 => CRM_Utils_Array::value('tier3label', $filteredOptions, 3),
      4 => CRM_Utils_Array::value('tier4label', $filteredOptions, 4),
    );

    foreach ($rows as $rowNum => $row) {
      if (!empty($row['donortier_tier'])) {
        $rows[$rowNum]['donortier_tier'] = $tiers[$row['donortier_tier']];
      }
    }
    parent::alterDisplay($rows);
  }

}
