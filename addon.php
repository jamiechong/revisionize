<?php

abstract class RevisionizeAddon {
  abstract function name();
  abstract function version();
  abstract function init();

  private static $loaded = array();

  function __construct() {
    add_filter('revisionize_registered_addons', array($this, 'register'));

    if ($this->is_active()) {
      $this->init();
    }
  }
  
  function register($addons) {
    $addons[$this->name()] = $this->version();
    return $addons;
  }

  function is_active() {
    return \Revisionize\is_addon_active($this->name());
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