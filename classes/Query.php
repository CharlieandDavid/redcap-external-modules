<?php namespace ExternalModules;

class Query{
    private $sql = '';
    private $parameters = [];
    private $oldQueryMethod = false;
    private $statement;

    function add($sql, $parameters = []){
        if(!is_array($parameters)){
            $parameters = [$parameters];
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
        return ExternalModules::query($this);
    }

    function getSQL(){
        return $this->sql;
    }

    function getParameters(){
        return $this->parameters;
    }

    function getStatement(){
        return $this->statement;
    }

    function setStatement($statement){
        $this->statement = $statement;
    }

    function isOldQueryMethod(){
        return $this->oldQueryMethod;
    }

    function setOldQueryMethod($oldQueryMethod){
        $this->oldQueryMethod = $oldQueryMethod;
    }
}
