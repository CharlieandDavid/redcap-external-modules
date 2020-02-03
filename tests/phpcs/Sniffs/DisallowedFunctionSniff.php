<?php

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class DisallowedFunctionSniff implements Sniff
{
    private $errorsByFunction = [];
    private $dbQueryCallCount = 0;
    
    function __construct(){
        $this->addErrors(
            [
                '_query',
                '_multi_query',
                '_multi_query_rc'
            ],
            'does not support query parameters.  Please use ExternalModules::query() or $module->query() instead.'
        );

        $this->addErrors(
            [
                '_fetch_row',
                '_fetch_assoc',
                '_fetch_array',
                '_free_result',
                '_fetch_field_direct',
                '_fetch_fields',
                '_num_fields',
                '_fetch_object',
                '_result',
                '_transaction_active',
            ],
            'will not work with our custom StatementResult object.  Please use object oriented syntax instead (ex: $result->some_method()).'
        );

        $this->addErrors(
            [
                '_affected_rows'
            ],
            'will not work with prepared statements.  Please see the External Module query documentation for an alternative.'
        );
    }

    private function addErrors($suffixes, $error){
        foreach(['db', 'mysql', 'mysqli'] as $prefix){
            foreach($suffixes as $suffix){
                $this->errorsByFunction[$prefix.$suffix] = $error;
            }
        }
    }

    function register()
    {
        return [T_STRING];
    }

    function process(File $file, $position)
    {
        $string = $file->getTokens()[$position]['content'];

        if($string === 'db_query'){
            $this->dbQueryCallCount++;
            if($this->dbQueryCallCount === 1){
                // Allow one and only one call to db_query() from the framework.
                return;
            }
        }

        $error = @$this->errorsByFunction[$string];
        if($error){
            $file->addError("The '$string' function is not allowed since it $error", $position, self::class);
        }
    }
}