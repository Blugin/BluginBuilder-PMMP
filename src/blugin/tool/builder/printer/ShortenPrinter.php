<?php

/*
 *
 *  ____  _             _         _____
 * | __ )| |_   _  __ _(_)_ __   |_   _|__  __ _ _ __ ___
 * |  _ \| | | | |/ _` | | '_ \    | |/ _ \/ _` | '_ ` _ \
 * | |_) | | |_| | (_| | | | | |   | |  __/ (_| | | | | | |
 * |____/|_|\__,_|\__, |_|_| |_|   |_|\___|\__,_|_| |_| |_|
 *                |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  Blugin team
 * @link    https://github.com/Blugin
 * @license https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 */

declare(strict_types=1);

namespace blugin\tool\builder\printer;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

/**
 * TODO: Implements feature without depending on Pretty
 */
class ShortenPrinter extends Standard implements IPrinter{
    /**
     * @param Node[] $stmts
     *
     * @return string
     */
    public function print(array $stmts) : string{
        $code = $this->prettyPrintFile($stmts);
        return $this->removeWhitespace($code);
    }

    /**
     * @url http://php.net/manual/en/function.php-strip-whitespace.php#82437
     *
     * @param string $originalCode
     *
     * @return string
     */
    private function removeWhitespace(string $originalCode) : string{
        // Whitespaces left and right from this signs can be ignored
        $ignoreWhitespaceTokenList = [
            T_CONCAT_EQUAL,             // .=
            T_DOUBLE_ARROW,             // =>
            T_BOOLEAN_AND,              // &&
            T_BOOLEAN_OR,               // ||
            T_IS_EQUAL,                 // ==
            T_IS_NOT_EQUAL,             // != or <>
            T_IS_SMALLER_OR_EQUAL,      // <=
            T_IS_GREATER_OR_EQUAL,      // >=
            T_INC,                      // ++
            T_DEC,                      // --
            T_PLUS_EQUAL,               // +=
            T_MINUS_EQUAL,              // -=
            T_MUL_EQUAL,                // *=
            T_DIV_EQUAL,                // /=
            T_IS_IDENTICAL,             // ===
            T_IS_NOT_IDENTICAL,         // !==
            T_DOUBLE_COLON,             // ::
            T_PAAMAYIM_NEKUDOTAYIM,     // ::
            T_OBJECT_OPERATOR,          // ->
            T_DOLLAR_OPEN_CURLY_BRACES, // ${
            T_AND_EQUAL,                // &=
            T_MOD_EQUAL,                // %=
            T_XOR_EQUAL,                // ^=
            T_OR_EQUAL,                 // |=
            T_SL,                       // <<
            T_SR,                       // >>
            T_SL_EQUAL,                 // <<=
            T_SR_EQUAL,                 // >>=
        ];
        $tokens = token_get_all($originalCode);

        $stripedCode = "";
        $c = count($tokens);
        $ignoreWhitespace = false;
        $lastSign = "";
        $openTag = null;
        for($i = 0; $i < $c; $i++){
            $token = $tokens[$i];
            if(!is_array($token)){
                if(($token != ";" && $token != ":") || $lastSign != $token){
                    $stripedCode .= $token;
                    $lastSign = $token;
                }
                $ignoreWhitespace = true;
                continue;
            }

            list($tokenNumber, $tokenString) = $token; // tokens: number, string, line
            if(in_array($tokenNumber, $ignoreWhitespaceTokenList)){
                $stripedCode .= $tokenString;
                $ignoreWhitespace = true;
            }elseif($tokenNumber == T_INLINE_HTML){
                $stripedCode .= $tokenString;
                $ignoreWhitespace = false;
            }elseif($tokenNumber == T_OPEN_TAG){
                if(strpos($tokenString, " ") || strpos($tokenString, "\n") || strpos($tokenString, "\t") || strpos($tokenString, "\r")){
                    $tokenString = rtrim($tokenString);
                }
                $tokenString .= " ";
                $stripedCode .= $tokenString;
                $openTag = T_OPEN_TAG;
                $ignoreWhitespace = true;
            }elseif($tokenNumber == T_OPEN_TAG_WITH_ECHO){
                $stripedCode .= $tokenString;
                $openTag = T_OPEN_TAG_WITH_ECHO;
                $ignoreWhitespace = true;
            }elseif($tokenNumber == T_CLOSE_TAG){
                if($openTag == T_OPEN_TAG_WITH_ECHO){
                    $stripedCode = rtrim($stripedCode, "; ");
                }else{
                    $tokenString = " " . $tokenString;
                }
                $stripedCode .= $tokenString;
                $openTag = null;
                $ignoreWhitespace = false;
            }elseif($tokenNumber == T_CONSTANT_ENCAPSED_STRING || $tokenNumber == T_ENCAPSED_AND_WHITESPACE){
                if($tokenString[0] == "\""){
                    $tokenString = addcslashes($tokenString, "\n\t\r");
                }
                $stripedCode .= $tokenString;
                $ignoreWhitespace = true;
            }elseif($tokenNumber == T_WHITESPACE){
                $nt = @$tokens[$i + 1];
                if(!$ignoreWhitespace && (!is_string($nt) || $nt == "\$") && !in_array($nt[0], $ignoreWhitespaceTokenList)){
                    $stripedCode .= " ";
                }
                $ignoreWhitespace = false;
            }elseif($tokenNumber == T_START_HEREDOC){
                $stripedCode .= "<<<S\n";
                $ignoreWhitespace = false;
            }elseif($tokenNumber == T_END_HEREDOC){
                $stripedCode .= "S;";
                $ignoreWhitespace = true;
                for($j = $i + 1; $j < $c; $j++){
                    if(is_string($tokens[$j]) && $tokens[$j] == ";"){
                        $i = $j;
                        break;
                    }

                    if($tokens[$j][0] == T_CLOSE_TAG)
                        break;
                }
            }else{
                $stripedCode .= $tokenString;
                $ignoreWhitespace = false;
            }
            $lastSign = "";
        }
        return $stripedCode;
    }
}