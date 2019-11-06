<?php

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class TestSniff implements Sniff
{
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
        $file->addFixableWarning($languageKey, $position, 'Language Key Found');
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
}