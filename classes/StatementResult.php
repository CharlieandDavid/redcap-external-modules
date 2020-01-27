<?php namespace ExternalModules;

use Exception;

class StatementResult // extends \mysqli_result
{    
    public $num_rows;

    private $statement;
    private $fields;
    private $row = [];
    private $i = 0;

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

        $this->row = [];
        for($i=0; $i<count($fields); $i++){
            // Set up the array of references required for bind_result().
            $this->row[$i] = &$this->row[$i];
        }
        
        if(!call_user_func_array([$s, 'bind_result'], $this->row)){
            // TODO - tt
            throw new Exception("Binding statement results failed.");
        }
    }

    function fetch_assoc(){
        return $this->fetch_internal(true);
    }

    function fetch_row(){
        return $this->fetch_internal(false);
    }

    private function fetch_internal($assoc){
        $s = $this->statement;

        if($this->i === $this->num_rows){
            return null;
        }
        
        $s->data_seek($this->i);
        $this->i++;

        $s->fetch();        

        $dereferencedRow = [];
        foreach($this->row as $key=>$value){
            if($assoc){
                $key = $this->fields[$key]->name;
            }

            $dereferencedRow[$key] = $value;
        }

        return $dereferencedRow;
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
}