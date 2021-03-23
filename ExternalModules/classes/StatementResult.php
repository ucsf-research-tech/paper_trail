<?php namespace ExternalModules;

use Exception;

class StatementResult // extends \mysqli_result
{    
    public $current_field = 0;
    public $field_count;
    public $lengths;
    public $num_rows;

    private $statement;
    private $fields;
    private $row = [];
    private $i = 0;
    private $closed = false;

    function __construct($s, $metadata){
        $this->statement = $s;

        if(!$s->store_result()){
            // TODO - tt
            throw new Exception("The mysqli_stmt::store_result() method failed.");
        }

        $this->num_rows = $s->num_rows;
        $this->fields = $fields = $metadata->fetch_fields();
        if(!$fields){
            // TODO - tt
            throw new Exception("The mysqli_stmt metadata fetch_fields() method failed.");
        }

        $this->field_count = count($fields);

        $this->row = [];
        $refs = [];
        for($i=0; $i<count($fields); $i++){
            // Set up the array of references required for bind_result().
            $refs[$i] = null;
            $this->row[$i] = &$refs[$i];
        }
        
        if(!call_user_func_array([$s, 'bind_result'], $this->row)){
            // TODO - tt
            throw new Exception("Binding statement results failed.");
        }
    }

    function fetch_assoc(){
        return $this->fetch_array(MYSQLI_ASSOC);
    }

    function fetch_row(){
        return $this->fetch_array(MYSQLI_NUM);
    }

    function fetch_object($class_name = 'stdClass', $params = []){
        $row = $this->fetch_assoc();
        if(!$row){
            return $row;
        }

        $reflector = new \ReflectionClass($class_name);
        $object = $reflector->newInstanceArgs($params);
        
        foreach($row as $key=>$value){
            $object->$key = $value;
        }

        return $object;
    }

    function fetch_array($resultType = MYSQLI_BOTH){
        if($this->closed){
            return $this->getClosedReturnValue();
        }

        $s = $this->statement;

        if($this->i === $this->num_rows){
            return null;
        }
        
        $s->data_seek($this->i);
        $this->i++;

        $s->fetch();        

        $dereferencedRow = [];
        foreach($this->row as $index=>$value){
            $this->lengths[$index] = strlen($value);

            if($resultType !== MYSQLI_ASSOC){
                $dereferencedRow[$index] = $value;
            }

            if($resultType !== MYSQLI_NUM){
                $columnName = $this->fields[$index]->name;
                $dereferencedRow[$columnName] = $value;
            }
        }

        return $dereferencedRow;
    }

    function fetch_field_direct($fieldnr){
        return $this->fields[$fieldnr];
    }

    function fetch_fields(){
        if($this->closed){
            return $this->getClosedReturnValue();
        }

        return $this->fields;
    }

    function free(){
        $this->free_result();
    }

    function close(){
        $this->free_result();
    }

    function free_result(){
        $this->statement->free_result();
        
        $this->closed = true;

        $this->current_field = false;
        $this->lengths = false;
        
        $returnValue = $this->getClosedReturnValue();
        $this->field_count = $returnValue;
        $this->num_rows = $returnValue;

        return $returnValue;
    }

    function fetch_field(){
        $field = $this->fields[$this->current_field];
        
        $this->current_field++;

        return $field;
    }

    function data_seek($i){
        $this->i = $i;
    }

    private function throwNotImplementedException($message){
        throw new Exception('Not yet implemented: ' . $message);
    }

    function __get($name){
        $this->throwNotImplementedException($name);
    }

    function __call($name, $args){
        $this->throwNotImplementedException($name);
    }

    private function getClosedReturnValue(){
        if(PHP_MAJOR_VERSION === 5){
            return null;
        }
        else{
            return false;
        }
    }
}