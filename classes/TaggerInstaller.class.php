<?php

require_once __ROOT__ . 'db/TaggerQueryManager.class.php';

class TaggerInstaller {

  public function __construct($tagger) {
    $this->install($tagger);
  }

  private function install($tagger) {
    $linked_data_table = $tagger->getConfiguration('db', 'linked_data_table');
    TaggerQueryManager::query("
    CREATE TABLE `$linked_data_table` (
      `dsid` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `dstid` int(11) unsigned NOT NULL,
      `tid` int(11) unsigned NOT NULL,
      `uri` varchar(255) NOT NULL,
      `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`dsid`),
      KEY `dstid` (`dstid`),
      KEY `tid` (`tid`),
      KEY `uri` (`uri`)
    ) DEFAULT CHARSET=utf8;

    ");

    $unmatched_table = $tagger->getConfiguration('db', 'unmatched_table');
    TaggerQueryManager::query("
    CREATE TABLE `$unmatched_table` (
      `name` varchar(255) NOT NULL,
      `count` int(11) NOT NULL,
      `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
      PRIMARY KEY (`name`)
    ) DEFAULT CHARSET=utf8;
    ");

    $disambiguation_table = $tagger->getConfiguration('db', 'disambiguation_table');
    TaggerQueryManager::query("
    CREATE TABLE `$disambiguation_table` (
      `tid` int(11) unsigned NOT NULL,
      `name` varchar(255) NOT NULL DEFAULT ''
    ) DEFAULT CHARSET=utf8;
    ");

    $lookup_table = $tagger->getConfiguration('db', 'lookup_table');
    TaggerQueryManager::query("
    CREATE TABLE `$lookup_table` (
      `tid` int(11) unsigned NOT NULL,
      `vid` int(10) unsigned NOT NULL,
      `name` varchar(255) NOT NULL DEFAULT '',
      `canonical` tinyint(1) NOT NULL,
      KEY `tid` (`tid`),
      KEY `name` (`name`)
    ) DEFAULT CHARSET=utf8;
    ");

    $word_relations_table = $tagger->getConfiguration('db', 'word_relations_table');
    TaggerQueryManager::query("
      CREATE TABLE IF NOT EXISTS `$word_relations_table` (
        `word` varchar(255) NOT NULL,
        `tid` varchar(255) NOT NULL,
        `score` decimal(30,20) unsigned NOT NULL,
        `pass` bigint(20) unsigned NOT NULL,
        KEY (`word`),
        KEY (`tid`)
      )  DEFAULT CHARSET=utf8;
      ");

    $docstats_table = $tagger->getConfiguration('db', 'docstats_table');
    TaggerQueryManager::query("
      CREATE TABLE IF NOT EXISTS `$docstats_table` (
        `word_count` bigint(20) unsigned NOT NULL,
        `doc_count` bigint(20) unsigned NOT NULL
      )
    ");
    //TaggerQueryManager::query("TRUNCATE TABLE `$docstats_table`;");

    $wordstats_table = $tagger->getConfiguration('db', 'wordstats_table');
    TaggerQueryManager::query("
      CREATE TABLE IF NOT EXISTS `$wordstats_table` (
        `word` varchar(255) NOT NULL,
        `word_count` bigint(20) unsigned NOT NULL,
        `doc_count` bigint(20) unsigned NOT NULL,
        `word_freq` decimal(30,20) unsigned NOT NULL,
        `doc_freq` decimal(30,20) unsigned NOT NULL,
        PRIMARY KEY (`word`)
      )  DEFAULT CHARSET=utf8;
    ");
    //TaggerQueryManager::query("TRUNCATE TABLE `$wordstats_table`;");
    
  }


}
?>
