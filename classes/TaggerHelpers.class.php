<?php

class TaggerHelpers {

  /**
   * Taken from:
   *  http://php.net/manual/en/function.array-merge-recursive.php
   *  walf 26-May-2011 05:23
   *
   **/
  public static function arrayMergeRecursiveSimple() {
    if (func_num_args() < 2) {
      if (func_num_args() == 1) {
        $arrays = func_get_args();
        return $arrays[0];
      }
      else {
        return array();
      }
    }
    $arrays = func_get_args();
    $merged = array();
    while ($arrays) {
      $array = array_shift($arrays);
      if (!is_array($array)) {
        trigger_error(__FUNCTION__ .' encountered a non array argument', E_USER_WARNING);
        return;
      }
      //if (!$array) {
      //  continue;
      //}
      foreach ($array as $key => $value) {
        if (is_string($key)) {
          if (is_array($value) && array_key_exists($key, $merged) && is_array($merged[$key])) {
            $merged[$key] = call_user_func(array(__CLASS__, __FUNCTION__), $merged[$key], $value);
          } else {
            $merged[$key] = $value;
          }
        } else {
          $merged[] = $value;
        }
      }
    }
    return $merged;
  }


  /**
   * Taken from:
   *  http://php.net/manual/en/function.array-merge-recursive.php
   *  walf 26-May-2011 05:23
   *
   **/
  public static function arrayMergeRecursiveOverride() {
    if (func_num_args() < 2) {
      trigger_error(__FUNCTION__ .' must have at least 2 arguments', E_USER_WARNING);
      return array();
    }

    $arrays = func_get_args();
    $override = $arrays[0];
    //var_dump($override);
    array_shift($arrays);
    //var_dump($override);

    $merged = array();
    while ($arrays) {
      $array = array_shift($arrays);
      if (!is_array($array)) {
        trigger_error(__FUNCTION__ .' encountered a non array argument', E_USER_WARNING);
        return;
      }
      //if (!$array) {
      //  continue;
      //}
      foreach ($array as $key => $value) {
        if (is_string($key)) {
          if (is_array($value) && array_key_exists($key, $merged) &&
              is_array($merged[$key]) && !in_array($key, $override)) {
            $merged[$key] = call_user_func(array(__CLASS__, __FUNCTION__),
                                           $override, $merged[$key], $value);
          } else {
            $merged[$key] = $value;
          }
        } else {
          $merged[] = $value;
        }
      }
    }
    return $merged;
  }



}
