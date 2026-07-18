<?php

ini_set('memory_limit', '512M');

if (!defined('CIVICRM_SYSTEM')) {
  define('CIVICRM_SYSTEM', 1);
}

if (!class_exists('Civi')) {
  class Civi {
    public static $stubSettings = [];
    public static $statics = [];

    public static function settings() {
      return new class {

        public function get(string $name) {
          return Civi::$stubSettings[$name] ?? NULL;
        }

      };
    }

    public static function log() {
      return new class {

        public function info(string $message): void {}

        public function debug(string $message): void {}

        public function warning(string $message): void {}

        public function error(string $message): void {}

      };
    }

  }
}

if (!class_exists('CRM_Batchreminders_ExtensionUtil')) {
  class CRM_Batchreminders_ExtensionUtil {

    public static function ts($string, $params = []) {
      return $string;
    }

  }
}

/**
 * _batchreminders_paused() reads civicrm_setting directly (bypassing Civi::settings(),
 * see its doc-comment), so tests control it via $stubPausedRaw — a PHP-serialized
 * value like the real column would hold, not the raw Civi::settings() shape. NULL
 * means the row is absent (never set), which must behave as "not paused".
 */
if (!class_exists('CRM_Core_DAO')) {
  class CRM_Core_DAO {
    public static $stubPausedRaw = NULL;

    public static function singleValueQuery(string $sql, $params = []) {
      if (stripos($sql, 'civicrm_setting') !== FALSE) {
        return self::$stubPausedRaw;
      }
      return 0;
    }

  }
}

if (!class_exists('CRM_Core_Config')) {
  class CRM_Core_Config {

    public static function domainID(): int {
      return 1;
    }

  }
}

require_once __DIR__ . '/../../batchreminders.php';
