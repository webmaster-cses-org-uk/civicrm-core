<?php

/**
 * Class CRM_Import_ImportProcessor.
 *
 * Import processor class. This is intended to provide a sanitising wrapper around
 * the form-oriented import classes. In particular it is intended to provide a clear translation
 * between the saved mapping field format and the quick form & parser formats.
 *
 * In the first instance this is only being used in unit tests but the intent is to migrate
 * to it on a trajectory similar to the ExportProcessor so it is not in the tests.
 */
class CRM_Import_ImportProcessor {

  /**
   * An array of fields in the format used in the table civicrm_mapping_field.
   *
   * @var array
   */
  protected $mappingFields = [];

  /**
   * @var array
   */
  protected $metadata = [];

  /**
   * Metadata keyed by field title.
   *
   * @var array
   */
  protected $metadataByTitle = [];

  /**
   * Get contact type being imported.
   *
   * @var string
   */
  protected $contactType;

  /**
   * Saved Mapping ID.
   *
   * @var int
   */
  protected $mappingID;

  /**
   * @return array
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * @param array $metadata
   */
  public function setMetadata(array $metadata) {
    $this->metadata = $metadata;
  }

  /**
   * @return int
   */
  public function getMappingID(): int {
    return $this->mappingID;
  }

  /**
   * @param int $mappingID
   */
  public function setMappingID(int $mappingID) {
    $this->mappingID = $mappingID;
  }

  /**
   * @return string
   */
  public function getContactType(): string {
    return $this->contactType;
  }

  /**
   * @param string $contactType
   */
  public function setContactType(string $contactType) {
    $this->contactType = $contactType;
  }

  /**
   * Get Mapping Fields.
   *
   * @return array
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function getMappingFields(): array {
    if (empty($this->mappingFields) && !empty($this->getMappingID())) {
      $this->loadSavedMapping();
    }
    return $this->mappingFields;
  }

  /**
   * @param array $mappingFields
   */
  public function setMappingFields(array $mappingFields) {
    $this->mappingFields = $this->rekeyBySortedColumnNumbers($mappingFields);
  }

  /**
   * Get the names of the mapped fields.
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function getFieldNames() {
    return CRM_Utils_Array::collect('name', $this->getMappingFields());
  }

  /**
   * Get the IM Provider ID.
   *
   * @param int $columnNumber
   *
   * @return int
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function getIMProviderID($columnNumber) {
    return $this->getMappingFields()[$columnNumber]['im_provider_id'] ?? NULL;
  }

  /**
   * Get the Phone Type
   *
   * @param int $columnNumber
   *
   * @return int
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function getPhoneTypeID($columnNumber) {
    return $this->getMappingFields()[$columnNumber]['phone_type_id'] ?? NULL;
  }

  /**
   * Get the Website Type
   *
   * @param int $columnNumber
   *
   * @return int
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function getWebsiteTypeID($columnNumber) {
    return $this->getMappingFields()[$columnNumber]['website_type_id'] ?? NULL;
  }

  /**
   * Get the Location Type
   *
   * Returning 0 rather than null is historical.
   *
   * @param int $columnNumber
   *
   * @return int
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function getLocationTypeID($columnNumber) {
    return $this->getMappingFields()[$columnNumber]['location_type_id'] ?? 0;
  }

  /**
   * Get the IM or Phone type.
   *
   * We have a field that would be the 'relevant' type - which could be either.
   *
   * @param int $columnNumber
   *
   * @return int
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function getPhoneOrIMTypeID($columnNumber) {
    return $this->getIMProviderID($columnNumber) ?? $this->getPhoneTypeID($columnNumber);
  }

  /**
   * Get the location types of the mapped fields.
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function getFieldLocationTypes() {
    return CRM_Utils_Array::collect('location_type_id', $this->getMappingFields());
  }

  /**
   * Get the phone types of the mapped fields.
   */
  public function getFieldPhoneTypes() {
    return CRM_Utils_Array::collect('phone_type_id', $this->getMappingFields());
  }

  /**
   * Get the names of the im_provider fields.
   */
  public function getFieldIMProviderTypes() {
    return CRM_Utils_Array::collect('im_provider_id', $this->getMappingFields());
  }

  /**
   * Get the names of the website fields.
   */
  public function getFieldWebsiteTypes() {
    return CRM_Utils_Array::collect('im_provider_id', $this->getMappingFields());
  }

  /**
   * Get an instance of the importer object.
   *
   * @return CRM_Contact_Import_Parser_Contact
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function getImporterObject() {
    $importer = new CRM_Contact_Import_Parser_Contact(
      $this->getFieldNames(),
      $this->getFieldLocationTypes(),
      $this->getFieldPhoneTypes(),
      $this->getFieldIMProviderTypes(),
      // @todo - figure out related mappings.
      // $mapperRelated = [], $mapperRelatedContactType = [], $mapperRelatedContactDetails = [], $mapperRelatedContactLocType = [], $mapperRelatedContactPhoneType = [], $mapperRelatedContactImProvider = [],
      [],
      [],
      [],
      [],
      [],
      [],
      $this->getFieldWebsiteTypes()
      // $mapperRelatedContactWebsiteType = []
    );
    $importer->init();
    $importer->_contactType = $this->getContactType();
    return $importer;
  }

  /**
   * @throws \CiviCRM_API3_Exception
   */
  protected function loadSavedMapping() {
    $fields = civicrm_api3('MappingField', 'get', [
      'mapping_id' => $this->getMappingID(),
      'options' => ['limit' => 0]
    ])['values'];
    foreach ($fields as $index => $field) {
      // Fix up the fact that for lost reasons we save by label not name.
      $fields[$index]['label'] = $field['name'];
      if (empty($field['relationship_type_id'])) {
        $fields[$index]['name'] = $this->getNameFromLabel($field['name']);
      }
      else {
        // Honour legacy chaos factor.
        $fields[$index]['name'] = strtolower(str_replace(" ", "_", $field['name']));
        // fix for edge cases, CRM-4954
        if ($fields[$index]['name'] === 'image_url') {
          $fields[$index]['name'] = str_replace('url', 'URL', $fields[$index]['name']);
        }
      }

    }
    $this->mappingFields = $this->rekeyBySortedColumnNumbers($fields);
  }

  /**
   * Get the titles from metadata.
   */
  public function getMetadataTitles() {
    if (empty($this->metadataByTitle)) {
      $this->metadataByTitle = CRM_Utils_Array::collect('title', $this->getMetadata());
    }
    return $this->metadataByTitle;
  }

  /**
   * Rekey the array by the column_number.
   *
   * @param array $mappingFields
   *
   * @return array
   */
  protected function rekeyBySortedColumnNumbers(array $mappingFields) {
    $this->mappingFields = CRM_Utils_Array::rekey($mappingFields, 'column_number');
    ksort($this->mappingFields);
    return array_values($this->mappingFields);
  }

  /**
   * Get the field name from the label.
   *
   * @param string $label
   *
   * @return string
   */
  protected function getNameFromLabel($label) {
    $titleMap = array_flip($this->getMetadataTitles());
    return $titleMap [$label] ?? '';
  }

}
