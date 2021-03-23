<?php

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use ExternalModules\ExternalModules;

final class UndefinedLanguageKeySniff implements Sniff
{
    private $languageKeyCount = 0;

    public function register()
    {
        return [T_DOUBLE_COLON];
    }

    public function process(File $file, $position)
    {
        $position = $file->findNext([T_STRING], $position);
        $functionName = $file->getTokens()[$position]['content'];
        if($functionName !== 'tt'){
            return;
        }

        $position = $file->findNext([T_OPEN_PARENTHESIS, T_WHITESPACE], $position+1, null, true);
        $firstArg = $file->getTokens()[$position];
        $code = $firstArg['code'];

        if(in_array($code, [T_VARIABLE, T_COMMENT])){
            // Saved for future testing
            // var_dump([
            //     $file->path,
            //     $file->getTokens()[$position]
            // ]);

            // There's not a realistic way to test if it's not a simple string being passed in.
            return;
        }
        else if($code !== T_CONSTANT_ENCAPSED_STRING){
            throw new Exception("Expected T_CONSTANT_ENCAPSED_STRING but found {$firstArg['type']}");
        }

        $languageKey = $this->stripQuotes($firstArg['content']);
        if(strpos($languageKey, 'em_') !== 0){
            throw new Exception("The following language key did not have the expected 'em_' prefix: $languageKey");
        }
       
        $languageValue = @$GLOBALS['lang'][$languageKey];

        if(empty($languageValue)){
            $file->addError("Language key '$languageKey' was used but is not defined.", $position, self::class);
        }
        else if($languageValue !== ExternalModules::tt($languageKey)){
            $file->addError("Language key '$languageKey' did not return the expected language value.", $position, self::class);
        }

        $this->languageKeyCount++;
    }

     /**
     * Copied from PHPCompatibility\Sniff.php
     * 
     * Strip quotes surrounding an arbitrary string.
     *
     * Intended for use with the contents of a T_CONSTANT_ENCAPSED_STRING / T_DOUBLE_QUOTED_STRING.
     *
     * @param string $string The raw string.
     *
     * @return string String without quotes around it.
     */
    public function stripQuotes($string)
    {
        return preg_replace('`^([\'"])(.*)\1$`Ds', '$2', $string);
    }

    public function __destruct()
    {
        if($this->languageKeyCount < 175){
            throw new Exception("The language key sniffer is not working properly.");
        }
    }
}