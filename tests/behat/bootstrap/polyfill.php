<?php

/**
 * @file
 * Polyfills for missing PHP functions.
 */

namespace Behat\Behat\Definition\Pattern;

/**
 * Polyfill for mb_lcfirst() which doesn't exist in PHP's mbstring extension.
 *
 * @param string $string
 *   The input string.
 * @param string $encoding
 *   The character encoding.
 *
 * @return string
 *   The string with the first character lowercased.
 */
if (!function_exists('Behat\Behat\Definition\Pattern\mb_lcfirst')) {
  function mb_lcfirst($string, $encoding = 'UTF-8') {
    $firstChar = mb_substr($string, 0, 1, $encoding);
    $rest = mb_substr($string, 1, null, $encoding);
    return mb_strtolower($firstChar, $encoding) . $rest;
  }
}
