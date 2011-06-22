<?php

interface TaggerQuery {
  public function taggerQuery(string $sql, array $args);
  public static function instance();
}