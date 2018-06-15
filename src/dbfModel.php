<?php

class DbfModel
{
    //@see http://php.net/manual/ru/intro.dbase.php - fields types
    const MEMO_TYPE = 'M'; // n/a
    const DATE_TYPE = 'D'; // YYYYMMDD
    const DATETIME_TYPE = 'T'; // YYYYMMDDhhmmss.uuu
    const NUMBER_TYPE = 'N'; // integer
    const FLOAT_TYPE = 'F'; // float
    const STRING_TYPE = 'C'; //  string
    const BOOLEAN_TYPE = 'L'; // T or Y for true, F or N fpr false, ? - for null
    // memory address. Field data address (address is set in memory; not useful on disk).
    const MEMORY_ADDRESS = 4;
    // length in binary
    const FIELD_LENGTH = 0;

    /**
     * @var array schema for dbf
     */
    public $schema = [];

    /**
     * @var array of errors from dbf
     */
    private $errors = [];


    /**
     * DbfModel constructor.
     * Set schema by default
     */
    public function __construct()
    {
        $dbf_default_schema = [
            ["id", self::NUMBER_TYPE, self::MEMORY_ADDRESS, self::FIELD_LENGTH],
        ];

        if (empty($this->schema)) {
            $this->schema = $dbf_default_schema;
        }
    }

    /**
     * @return array schema of dbf file
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * Set and check schema of dbf file
     * @param array $schema
     * @return $this
     * @throws ErrorException
     */
    public function setSchema($schema)
    {
        if($this->checkSchema($schema)){
            $this->schema = $schema;
        }

        return $this;
    }

    /**
     * Check and compare dbf data with dbf schema
     * @param array $bdfData
     * @return bool
     * @throws ErrorException
     */
    public function checkDataWithSchema($bdfData)
    {
        if (!is_array($bdfData) || empty($bdfData)) {
            $this->addError('dbf data in wrong format.');
            return false;
        }
        $isFieldNameExist = false;

        foreach ($bdfData as $fieldName => $fieldDatum) {
            foreach ($this->schema as $fieldFromSchema) {
                $fieldNameFromSchema = $fieldFromSchema[0];
                $fieldTypeFromSchema = $this->getTypeByDbfType($fieldFromSchema[1]);

                if ($fieldName === $fieldNameFromSchema) {
                    $isFieldNameExist = true;
                    $fieldType = gettype($fieldDatum);
                    if ($fieldTypeFromSchema !== $fieldType) {
                        $this->addError('Wrong type for dbf record');
                    }
                }
            }
            if ($isFieldNameExist === false) {
                $this->addError('Not found field name "' . $fieldName . '" for dbf record');
            }
            $isFieldNameExist = false;
        }

        if (empty($this->getErrors())) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed $dbfType
     * @return string name of type
     * @throws ErrorException
     */
    private function getTypeByDbfType($dbfType)
    {
        switch ($dbfType) {
            case self::NUMBER_TYPE:
                return 'integer';
            case self::FLOAT_TYPE:
                return 'double';
            case self::STRING_TYPE:
                return 'string';
            case self::DATE_TYPE:
                return 'string';
            case self::DATETIME_TYPE:
                return 'string';
            case self::BOOLEAN_TYPE:
                return 'boolean';
        }
        throw new ErrorException('This type not supported by php');
    }

    /**
     * @param string $message
     * @return array of dbf errors
     */
    private function addError($message)
    {
        return $this->errors[] = [
            'message' => $message,
        ];
    }

    /**
     * @return array of dbf errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Check on valid schema
     * @param array $schema
     * @return bool
     * @throws ErrorException
     */
    private function checkSchema($schema)
    {
        $isCurrentFieldName = false;
        $isCurrentFieldType = false;
        $isCurrentMemoryAddress = false;
        $isCurrentFieldLength = false;

        if (is_array($schema) && !empty($schema)) {
            foreach ($schema as $item) {
                $isCurrentFieldName = gettype($item[0]) === 'string';
                $isCurrentFieldType = $this->getTypeByDbfType($item[1]);
                $isCurrentMemoryAddress = is_int($item[2]);
                $isCurrentFieldLength = is_int($item[3]);
            }
        }

        $isHasErrors = !$isCurrentFieldName || !$isCurrentFieldType || !$isCurrentMemoryAddress || !$isCurrentFieldLength;
        if($isHasErrors){
            throw new ErrorException('Wrong schema format');
        }

        return true;
    }
}