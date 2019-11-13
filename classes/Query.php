<?php namespace ExternalModules;

class Query{
    private $sql = '';
    private $parameters = [];

    function add($sql, $parameters = []){
        if(!is_array($parameters)){
            $parameters = [];
        }

        $this->sql .= " $sql ";
        $this->parameters = array_merge($this->parameters, $parameters);
        
        return $this;
    }

    function addInClause($columnName, $values){
        list($sql, $parameters) = ExternalModules::getSQLInClause($columnName, $values, true);
        return $this->add($sql, $parameters);
    }

    function execute(){
        return ExternalModules::query($this->sql, $this->parameters);
    }
}