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
   * Mostly borrow from summary report.
   */
  public function __construct() {
    parent::__construct();
    $this->_columns['donortier']['fields']['tier'] = array(
      'title' => ts('Tier', array('domain' => 'com.aghstrategies.donortierreport')),
      'type' => CRM_Utils_Type::T_STRING,
    );
    $this->_columns['donortier']['group_bys']['tier'] = array(
      'title' => ts('Tier', array('domain' => 'com.aghstrategies.donortierreport')),
    );
    $this->_options = array(
      'tier1label' => array(
        'title' => ts('Highest donor tier'),
        'type' => 'text',
      ),
      'tier1threshold' => array(
        'title' => ts('Lower threshold for highest donor tier'),
        'type' => 'text',
      ),
      'tier2label' => array(
        'title' => ts('Second donor tier'),
        'type' => 'text',
      ),
      'tier2threshold' => array(
        'title' => ts('Lower threshold for second donor tier'),
        'type' => 'text',
      ),
      'tier3label' => array(
        'title' => ts('Third donor tier'),
        'type' => 'text',
      ),
      'tier3threshold' => array(
        'title' => ts('Lower threshold for third donor tier'),
        'type' => 'text',
      ),
      'tier4label' => array(
        'title' => ts('Lowest donor tier'),
        'type' => 'text',
      ),
    );
  }

  /**
   * Add options defined in $this->_options to the report.
   */
  public function addOptions() {
    if (!empty($this->_options)) {
      // FIXME: For now lets build all elements as checkboxes.
      // Once we clear with the format we can build elements based on type

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
      }
    }
    if (!empty($this->_options)) {
      $this->tabs['ReportOptions'] = array(
        'title' => ts('Display Options'),
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
    $sql = "CREATE  TABLE {$this->_tierTableName} ( contact_id int, total_amount_sum decimal(20,2), tier int ) ENGINE=HEAP";
    CRM_Core_DAO::executeQuery($sql);

    $sql = parent::buildQuery($applyLimit);

    // Figure out the tiers.
    // $this->_params['options']['tier1threshold']
    $filteredOptions = filter_var_array($this->_params, FILTER_SANITIZE_NUMBER_FLOAT);

    $tierselect3 = $filteredOptions['tier3threshold'] ? "IF(SUM({$this->_aliases['civicrm_contribution']}.total_amount) >= {$filteredOptions['tier3threshold']}, 3, 4)" : 3;
    $tierselect2 = $filteredOptions['tier2threshold'] ? "IF(SUM({$this->_aliases['civicrm_contribution']}.total_amount) >= {$filteredOptions['tier2threshold']}, 2, $tierselect3)" : 2;
    $tierselect1 = $filteredOptions['tier1threshold'] ? "IF(SUM({$this->_aliases['civicrm_contribution']}.total_amount) >= {$filteredOptions['tier1threshold']}, 1, $tierselect2)" : 1;

    // Populate the temp table.
    $tempSql = "INSERT INTO {$this->_tierTableName}
      SELECT {$this->_aliases['civicrm_contact']}.id as contact_id, SUM({$this->_aliases['civicrm_contribution']}.total_amount) as total_amount_sum, $tierselect1 as tier
      {$this->_from} {$this->_where}
      GROUP BY contact_id
      {$this->_having}";
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

    $this->_from .= "LEFT JOIN {$this->_tierTableName} {$this->_aliases['donortier']}
        ON {$this->_aliases['donortier']}.contact_id = {$this->_aliases['civicrm_contact']}.id\n";
  }
}
