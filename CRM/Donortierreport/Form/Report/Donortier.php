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
   * Name for the temp table holding sums per donor.
   */
  protected $_newTableName = '';

  /**
   * Is the "tier" field selected in a column, filter, or group by?
   */
  protected $_tierField = FALSE;

  /**
   * Is the "is new" field selected in a column, filter, or group by?
   */
  protected $_isNewField = FALSE;

  /**
   * Same as $_from but without the join on the temp tables.
   */
  protected $_noTierFrom = '';

  /**
   * Mostly borrow from summary report.
   */
  public function __construct() {
    parent::__construct();

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

    $newColumns = array(
      'donortier' => array(
        'alias' => 'donortier',
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
        'filters' => array(
          'tier' => array(
            'title' => ts('Tier', array('domain' => 'com.aghstrategies.donortierreport')),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->getTierLabels(),
          ),
        ),
      ),
      'donornew' => array(
        'alias' => 'donornew',
        'fields' => array(
          'not_new' => array(
            'title' => ts('Is new?', array('domain' => 'com.aghstrategies.donortierreport')),
            'type' => CRM_Utils_Type::T_BOOLEAN,
            'dbAlias' => 'COALESCE(donornew_civireport.not_new, 0)',
            'default' => TRUE,
          ),
        ),
        'group_bys' => array(
          'not_new' => array(
            'title' => ts('Is new?', array('domain' => 'com.aghstrategies.donortierreport')),
            'default' => TRUE,
          ),
        ),
        'filters' => array(
          'not_new' => array(
            'title' => ts('Is new?', array('domain' => 'com.aghstrategies.donortierreport')),
          ),
        ),
      ),
    );
    $this->_columns = array_merge($newColumns, $this->_columns);

    // AGH #15088 If core Contribution Summary Report does not include a contact type filter add one
    if (empty($this->_columns['civicrm_contact']['filters'])) {
      $this->_columns['civicrm_contact']['filters'] = array('contact_type' => array('title' => ts('Contact Type')));
    }

    // Unset defaults from donor summary:
    $this->_columns['civicrm_contribution']['fields']['receive_date']['default'] = FALSE;
    $this->_columns['civicrm_contribution']['group_bys']['receive_date']['default'] = FALSE;
    $this->_columns['civicrm_contribution']['fields']['contribution_status_id']['default'] = FALSE;
    $this->_columns['civicrm_contribution']['group_bys']['contribution_status_id']['default'] = FALSE;
    $this->_columns['civicrm_contribution']['fields']['campaign_id']['default'] = FALSE;
    $this->_columns['civicrm_address']['fields']['country_id']['default'] = FALSE;
  }

  /**
   * Basically lifted from CRM_Report_Form, except adding handling for text options.
   */
  public function addOptions() {
    if (!empty($this->_options)) {
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
   * Loops in the temp tables.
   */
  public function buildQuery($applyLimit = TRUE) {
    $randomNum = md5(uniqid());
    $this->_tierTableName = "civicrm_temp_donortier_{$randomNum}";
    $this->_newTableName = "civicrm_temp_donortier_new_{$randomNum}";

    if (!empty($this->_params['fields']['not_new'])
      || !empty($this->_params['not_new_op'])
      || !empty($this->_params['not_new_value'])
      || !empty($this->_params['group_bys']['not_new'])) {
      $this->_isNewField = TRUE;
    }

    if (!empty($this->_params['fields']['tier']) || !empty($this->_params['tier_value']) || !empty($this->_params['group_bys']['tier'])) {
      $this->_tierField = TRUE;
    }

    $sql = parent::buildQuery($applyLimit);

    if ($this->_tierField) {
      // Figure out the tiers.
      $filteredOptions = filter_var_array($this->_params, FILTER_SANITIZE_NUMBER_FLOAT);

      $tierselect3 = $filteredOptions['tier3threshold'] ? "IF(SUM({$this->_aliases['civicrm_contribution']}.total_amount) >= {$filteredOptions['tier3threshold']}, 3, 4)" : 3;
      $tierselect2 = $filteredOptions['tier2threshold'] ? "IF(SUM({$this->_aliases['civicrm_contribution']}.total_amount) >= {$filteredOptions['tier2threshold']}, 2, $tierselect3)" : 2;
      $tierselect1 = $filteredOptions['tier1threshold'] ? "IF(SUM({$this->_aliases['civicrm_contribution']}.total_amount) >= {$filteredOptions['tier1threshold']}, 1, $tierselect2)" : 1;

      $newWhere = $this->cleanWhere();

      // Populate the temp table.
      $tempSql = "CREATE TEMPORARY TABLE {$this->_tierTableName} ( INDEX(contact_id) ) ENGINE=HEAP AS (
        SELECT {$this->_aliases['civicrm_contact']}.id as contact_id, SUM({$this->_aliases['civicrm_contribution']}.total_amount) as total_amount_sum, $tierselect1 as tier
        {$this->_noTierFrom} {$newWhere}
        GROUP BY contact_id
        {$this->_having} )";
      CRM_Core_DAO::executeQuery($tempSql);
    }

    if ($this->_isNewField) {
      $this->newWhere();
    }

    return $sql;
  }

  /**
   * Fix where clause to exclude tiers and not_new temp tables.
   */
  private function cleanWhere() {
    $newWhereClauses = array();
    foreach ($this->_whereClauses as $whereClause) {
      if (strpos($whereClause, $this->_aliases['donortier']) === FALSE && strpos($whereClause, $this->_aliases['donornew']) === FALSE) {
        $newWhereClauses[] = $whereClause;
      }
    }

    if (empty($newWhereClauses)) {
      $newWhere = "WHERE ( 1 ) ";
    }
    else {
      $newWhere = "WHERE " . implode(' AND ', $newWhereClauses);
    }

    if ($this->_aclWhere) {
      $newWhere .= " AND {$this->_aclWhere} ";
    }
    return $newWhere;
  }

  /**
   * Extends from function to join the donor tier table.
   */
  public function from($entity = NULL) {
    parent::from($entity);

    $this->_noTierFrom = $this->_from;

    if ($this->_tierField) {
      $this->_from .= "LEFT JOIN {$this->_tierTableName} {$this->_aliases['donortier']}
          ON {$this->_aliases['donortier']}.contact_id = {$this->_aliases['civicrm_contact']}.id\n";
    }

    if ($this->_isNewField) {
      $this->_from .= "LEFT JOIN {$this->_newTableName} {$this->_aliases['donornew']}
          ON {$this->_aliases['donornew']}.contact_id = {$this->_aliases['civicrm_contact']}.id\n";
    }
  }

  /**
   * Prepare the where for calculating "new" donors.
   *
   * Mostly borrowed from CRM_Report_Form::where().
   */
  public function newWhere() {
    $newWhereClauses = array();

    foreach ($this->_whereClauses as $whereClause) {
      if (strpos($whereClause, $this->_aliases['civicrm_contribution'] . '.receive_date') === FALSE
        && strpos($whereClause, $this->_aliases['donortier']) === FALSE
        && strpos($whereClause, $this->_aliases['donornew']) === FALSE) {
        $newWhereClauses[] = $whereClause;
      }
    }

    $clause = NULL;
    $relative = CRM_Utils_Array::value("receive_date_relative", $this->_params);
    $from = CRM_Utils_Array::value("receive_date_from", $this->_params);
    $to = CRM_Utils_Array::value("receive_date_to", $this->_params);
    $fromTime = CRM_Utils_Array::value("receive_date_from_time", $this->_params);
    $toTime = CRM_Utils_Array::value("receive_date_to_time", $this->_params);

    list($from, $to) = $this->getFromTo($relative, $from, $to, $fromTime, $toTime);

    if ($from) {
      $from = ($type == CRM_Utils_Type::T_DATE) ? substr($from, 0, 8) : $from;
      $newWhereClauses[] = "( receive_date < $from )";
    }

    if (empty($newWhereClauses)) {
      $newWhere = "WHERE ( 1 ) ";
    }
    else {
      $newWhere = "WHERE " . implode(' AND ', $newWhereClauses);
    }

    if ($this->_aclWhere) {
      $newWhere .= " AND {$this->_aclWhere} ";
    }

    // Populate the temp table.
    $tempSql = "CREATE TEMPORARY TABLE {$this->_newTableName} ( INDEX(contact_id) ) ENGINE=HEAP AS (
      SELECT {$this->_aliases['civicrm_contact']}.id as contact_id, if({$this->_aliases['civicrm_contribution']}.id IS NULL, 0, 1) as not_new
      {$this->_noTierFrom} {$newWhere}
      GROUP BY contact_id
      {$this->_having} )";

    CRM_Core_DAO::executeQuery($tempSql);
  }

  /**
   * Provides the labels for the four tiers.
   */
  public function getTierLabels() {
    $filteredOptions = filter_var_array($this->_submitValues, FILTER_SANITIZE_STRING);
    $tiers = array(
      1 => CRM_Utils_Array::value('tier1label', $filteredOptions, $this->_options['tier1label']['default']),
      2 => CRM_Utils_Array::value('tier2label', $filteredOptions, $this->_options['tier2label']['default']),
      3 => CRM_Utils_Array::value('tier3label', $filteredOptions, $this->_options['tier3label']['default']),
      4 => CRM_Utils_Array::value('tier4label', $filteredOptions, $this->_options['tier4label']['default']),
    );
    return $tiers;
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
    $prevRow = array();

    $tiers = $this->getTierLabels();

    $pastTiersNew = array();
    foreach ($rows as $rowNum => $row) {
      if (!empty($row['donornew_not_new'])) {
        $rows[$rowNum]['donornew_not_new'] = ts('No', array('domain' => 'com.aghstrategies.donortierreport'));
      }
      else {
        $rows[$rowNum]['donornew_not_new'] = ts('Yes', array('domain' => 'com.aghstrategies.donortierreport'));
      }

      if (!empty($row['donortier_tier'])) {
        $rows[$rowNum]['donortier_tier'] = $tiers[$row['donortier_tier']];

        // Handle repeating labels on rollup.
        if ($this->_rollup && $this->_params['group_bys'] == array('tier' => 1, 'not_new' => 1)) {
          $rowOppositeNew = $row;
          $rowOppositeNew['donornew_not_new'] = empty($row['donornew_not_new']) ? 1 : 0;
          if ((!empty($pastTiersNew[$row['donortier_tier']]) && in_array(CRM_Utils_Array::value('donornew_not_new', $row), $pastTiersNew[$row['donortier_tier']]))
            || $row == $prevRow || $rowOppositeNew == $prevRow) {
            $rows[$rowNum]['donornew_not_new'] = '';
          }
          $pastTiersNew[$row['donortier_tier']][] = CRM_Utils_Array::value('donornew_not_new', $row);
        }
      }
      $prevRow = $row;
    }
    parent::alterDisplay($rows);
  }

  /**
   * Get operators to display on form.
   *
   * Adds the specific display for the Is New field.
   *
   * @param string $type
   *   The field type.
   * @param string $fieldName
   *   The field name.
   *
   * @return array
   *   Sends an array of operations to appear in the filter
   */
  public function getOperationPair($type = "string", $fieldName = NULL) {
    $result = parent::getOperationPair($type, $fieldName);
    if ($fieldName == 'not_new') {
      $result = array(
        '' => ts('Any', array('domain' => 'com.aghstrategies.donortierreport')),
        'nnll' => ts('No', array('domain' => 'com.aghstrategies.donortierreport')),
        'nll' => ts('Yes', array('domain' => 'com.aghstrategies.donortierreport')),
      );
    }
    return $result;
  }

  /**
   * Replace the filter fields for Is New.
   */
  public function addFilters() {
    parent::addFilters();
    foreach ($this->_filters as $table => $attributes) {
      foreach ($attributes as $fieldName => $field) {
        $operations = $this->getOperationPair(CRM_Utils_Array::value('operatorType', $field), $fieldName);
        if ($fieldName == 'not_new') {
          $this->removeElement("{$fieldName}_op");
          $this->removeElement("{$fieldName}_value");

          $this->addElement('select', "{$fieldName}_op", ts('Operator:'), $operations);
          break;
        }
      }
    }
  }

}
