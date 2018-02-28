<?php

abstract class RevisionizeAddon {
  abstract function name();
  abstract function version();

  private static $loaded = array();

  function __construct() {
    add_filter('revisionize_installed_addons', array($this, 'register'));
  }
  
  function register($addons) {
    $addons[$this->name()] = $this->version();
    return $addons;
  }

  static function addon_to_classname($addon, $prefix='Revisionize') {
    return $prefix.implode('', array_map('ucfirst', explode('_', $addon)));
  }

  static function create($name) {
    $class = RevisionizeAddon::addon_to_classname($name);
    $obj = new $class;
    RevisionizeAddon::$loaded[] = $obj;
    return $obj;
  }

}