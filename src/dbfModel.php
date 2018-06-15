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
    // memory address
    const MEMORY_ADDRESS = 4;
    // length in binary
    const FIELD_LENGTH = 0;

    /**
     * @var array schema fot dbf
     */
    public $schema = [];


    public function __construct()
    {
        $dbf_schema = [
            ["id", self::NUMBER_TYPE, self::MEMORY_ADDRESS, self::FIELD_LENGTH]
        ];

        if(empty($this->schema)){
            $this->schema = $dbf_schema;
        }
    }

    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * @param $bdfData
     * @return bool
     * @throws ErrorException
     */
    public function checkDataWithSchema($bdfData)
    {
        if(!is_array($bdfData)){
            throw new ErrorException('dbf data in wrong format.');
        }
        $isFieldNameExist = false;

        foreach ($bdfData as $fieldName => $fieldDatum) {
            foreach ($this->schema as $fieldFromSchema) {
                $fieldNameFromSchema = $fieldFromSchema[0];
                $fieldTypeFromSchema = $this->getTypeByDbfType($fieldFromSchema[1]);

                if($fieldName === $fieldNameFromSchema){
                    $isFieldNameExist = true;
                    $fieldType = gettype($fieldDatum);
                    if($fieldTypeFromSchema !== $fieldType){
                        throw new ErrorException('Wrong type for dbf record');
                    }
                }
            }
            if($isFieldNameExist === false){
                throw new ErrorException('Not found field name "'. $fieldName . '" for dbf record');
            }
            $isFieldNameExist = false;
        }

        return true;
    }

    /**
     * @param $dbfType
     * @return string
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
}