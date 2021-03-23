<?php namespace ExternalModules;

class Query{
    private $sql = '';
    private $parameters = [];
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
    
    function setStatement($statement){
        $this->statement = $statement;
    }

    function __get($name){
        if($name === 'affected_rows'){
            return $this->statement->affected_rows;
        }

        throw new \Exception('Not yet implemented: ' . $name);
    }
}
