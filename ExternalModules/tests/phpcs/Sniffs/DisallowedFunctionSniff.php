<?php

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class DisallowedFunctionSniff implements Sniff
{
    private $errorsByFunction = [];
    private $referenceCounts = [];
    private $referenceLimits = [
        'db_query' => 1, // All other calls should use ExternalModules::query() or $module->query() to encourage parameter use
        'EDOC_PATH' => 1, // All other calls should use ExternalModules::getEdocPath() to ensure that getSafePath() is used.
        'error_log' => 1, // All other calls should use ExternalModules::errorLog() to ensure that long logs are chunked.
    ];
    
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

        $referenceLimit = @$this->referenceLimits[$string];
        if($referenceLimit){
            $referenceCount = @++$this->referenceCounts[$string];
            if($referenceCount > $referenceLimit){
                $file->addError("The '$string' function/constant is only allowed to be referenced $referenceLimit time(s).", $position, self::class);
                return;
            }
        }
        else{
            $error = @$this->errorsByFunction[$string];
            if($error){
                $file->addError("The '$string' function is not allowed since it $error", $position, self::class);
            }
        }
    }
}