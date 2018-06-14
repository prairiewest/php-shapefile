<?php

class DbfModel
{
    public $schema = [];


    public function __construct()
    {
        $dbf_schema = [
            ["id", "N", 4, 0],
            ["deleted", "N", 4, 0]
        ];

        if(empty($this->schema)){
            $this->schema = $dbf_schema;
        }
    }

    public function getSchema()
    {
        return $this->schema;
    }
}