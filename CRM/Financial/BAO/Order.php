<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 5                                                  |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2019                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2019
 */

/**
 * This class contains functions for the Order pseudo-entity.
 *
 * Eventually the functionality associated with Orders should be sensibly built in this class.
 *
 * Documentation is here https://docs.civicrm.org/dev/en/latest/financial/orderAPI/
 *
 * WARNING this class is expected to undergo frequent refactorings as a part of a code transition.
 * Do not call any functions directly from outside core tested code.
 */
class CRM_Financial_BAO_Order {

  /**
   * Selected price items in the format we see in forms.
   *
   * ie.
   * [price_3 => 4, price_10 => 7]
   * is equivalent to 'option_value 4 for radio price field 4 and
   * a quantity of 7 for text price field 10.
   *
   * @var array
   */
  protected $priceSelection = [];

  /**
   * Price options the simplified price fields selections.
   *
   * ie. the 'price_' is stripped off the key name and the field ID
   * is cast to an integer.
   *
   * @return array
   */
  public function getPriceOptions() {
    $priceOptions = [];
    foreach ($this->getPriceSelection() as $fieldName => $value) {
      $fieldID = substr($fieldName, 6);
      $priceOptions[(int) $fieldID] = $value;
    }
    return $priceOptions;
  }

  /**
   * @return array
   */
  public function getPriceSelection(): array {
    return $this->priceSelection;
  }

  /**
   * @param array $priceSelection
   */
  public function setPriceSelection(array $priceSelection) {
    $this->priceSelection = $priceSelection;
  }

  /**
   * Set the price field selection from an array of params containing price fields.
   *
   * This function takes the sort of 'anything & everything' parameters that come in from the
   * form layer and filters them before assigning them to the priceSelection property.
   *
   * @param array $input
   */
  public function setPriceSelectionFromUnfilteredInput($input) {
    foreach ($input as $fieldName => $value) {
      if (strpos($fieldName, 'price_') === 0) {
        $fieldID = substr($fieldName, 6);
        if (is_numeric($fieldID)) {
          $this->priceSelection[$fieldName] = $value;
        }
      }
    }
  }

  /**
   * Get the total amount for the selected items.
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function getTotalAmount() {
    // This is the first cut of this function. The
    $fields = civicrm_api3('PriceField', 'get', ['options' => ['limit' => 0]])['values'];
    $lines = [];
    $params = $this->getPriceSelection();
    CRM_Price_BAO_PriceSet::processAmount($fields, $params, $lines);
    return $params['amount'];
  }

}
