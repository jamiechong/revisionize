<?php
/*  
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace Revisionize;

require_once 'addon.php';

load_addons();

add_action('admin_init', __NAMESPACE__.'\\settings_admin_init');
add_action('admin_menu', __NAMESPACE__.'\\settings_menu');

function settings_admin_init() {
  setup_settings();
}

function settings_menu() {
  add_submenu_page (
    'options-general.php',
    'Revisionize Settings',
    'Revisionize',
    'manage_options',
    'revisionize',
    __NAMESPACE__.'\\settings_page'
  );
}

function settings_page() {
  if (!current_user_can('manage_options')) {
    echo 'Not Allowed.';
    return;
  }
  ?>
  <div class="wrap">
    <style type="text/css">
    .rvz-cf:after {
      content: "";
      display: table;
      clear: both;
    }
    .rvz-settings-form {
      margin-top: 15px;
    }
    .rvz-settings-form .form-table {
      margin-top: 0;
    }
    .rvz-settings-form .form-table th, .rvz-settings-form .form-table td {
      padding-top: 12px;
      padding-bottom: 12px;
    }
    .rvz-settings-form .form-table p {
      margin-top: 0;
    }
    .rvz-addons { margin-top: 30px; }
    .rvz-addons * { box-sizing: border-box; }
    .rvz-addon-col {
      float: left;
      width: 100%;
      padding: 0;
    }
    @media (min-width: 783px) {
      .rvz-addon-col {
        padding-right: 25px;
        width: 50%;
      }
    }
    @media (min-width: 1366px) {
      .rvz-addon-col {
        width: 33.3333%;
      }
    }
    @media (min-width: 1600px) {
      .rvz-addon-col {
        width: 25%;
      }
    }
    .rvz-addon {
      background-color: #e0e0e0;
      border-radius: 4px;
      padding: 15px 15px 55px;
      min-height: 300px;
      position: relative;
      margin-bottom: 30px;
    }
    .rvz-addon h3 {
      margin-top: 0;
      line-height: 30px;
      text-transform: uppercase;
      width: 100%;
    }
    .rvz-addon ul {
      list-style: disc;
      padding-left: 25px;
    }
    .rvz-addon .rvz-meta {
      position: absolute;
      bottom: 0;
      left: 0;
      padding: 15px;
      width: 100%;
      text-align: right;
    }
    .rvz-addon .rvz-meta label {
      line-height: 22px;
      margin-left: 15px;
    }
    .rvz-addon .rvz-meta label:first-child {
      margin-left: 0;
      float: left;
    }
    </style>
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" enctype="multipart/form-data" method="post" class="rvz-settings-form">
    <?php
      settings_fields('revisionize');
  
      do_fields_section('revisionize_section_basic');

      // settings from Addons
      do_action('revisionize_settings_fields');

      do_fields_section('revisionize_section_addons');

      submit_button('Save Settings');

      addons_html();

      submit_button('Save Settings');
    ?>
    </form>
  </div>
  <?php 
}

function do_fields_section($key) {  
  echo '<table class="form-table">';
  do_settings_fields('revisionize', $key);
  echo '</table>';
}

function setup_settings() {
  register_setting('revisionize', 'revisionize_settings', array(
    "sanitize_callback" => __NAMESPACE__.'\\on_settings_saved'
  ));

  setup_basic_settings();
  setup_addon_settings();

}

function setup_basic_settings() {
  add_settings_section('revisionize_section_basic', '', '__return_null', 'revisionize');  

  input_setting('checkbox', 'Keep Backup', 'keep_backup', "After publishing the revision, the previously live post will be kept around and marked as a backup revision of the new version.", true, 'revisionize_section_basic', 'revisionize_keep_original_on_publish', __NAMESPACE__.'\\filter_keep_backup');

  input_setting('checkbox', 'Preserve Date', 'preserve_date', "The date of the original post will be maintained even if the revisionized post date changes. In particular, a scheduled revision won't modify the post date once it's published.", true, 'revisionize_section_basic', 'revisionize_preserve_post_date', __NAMESPACE__.'\\filter_preserve_date');
}

function setup_addon_settings() {
  add_settings_section('revisionize_section_addons', '', '__return_null', 'revisionize');

  // These fields are displayed
  add_settings_field('revisionize_addon_file', __('Upload Addon', REVISIONIZE_I18N_DOMAIN), __NAMESPACE__.'\\settings_addon_file_html', 'revisionize', 'revisionize_section_addons', array('label_for' => 'revisionize_addon_file'));  
}

function settings_addon_file_html($args) {
  $id = esc_attr($args['label_for']);
  ?>
  <div>
    <input id="<?php echo $id?>" type="file" name="revisionize_addon_file" style="width:320px" accept=".rvz"/> 
    <p>To install or update an addon, choose a <em>.rvz</em> file and click <em>Save Settings</em></p>
  </div>  
  <?php  
}

function addons_html() {
  ?>
  <h1>Revisionize Addons</h1>
  <p>Improve the free Revisionize plugin with these official addons.<br/>Visit <a href="https://revisionize.pro" target="_blank">revisionize.pro</a> for more info.</p>
  <div class="rvz-addons rvz-cf">
    <?php foreach (get_available_addons() as $addon) addon_html($addon); ?>
  </div>
  <?php
}

function addon_html($addon) {
  $id = $addon['id'];
  $active = "addon_${id}_active";
  $remove = "addon_${id}_delete";
  $active_checked = is_addon_active($id) ? 'checked' : '';
  ?>
  <div class="rvz-addon-col">
    <div class="rvz-addon<?php if ($addon['installed']) echo " rvz-installed" ?>">
      <h3><a href="<?php echo $addon['url']?>" target="_blank"><?php echo $addon['name'];?></a></h3>
      <p><?php echo nl2br($addon['description']); ?></p>
      <div class="rvz-meta rvz-cf">
        <?php if ($addon['installed']): ?>
        <label>Installed: <?php echo $addon['installed']?></label>
        <label>
          <input type="hidden" name="revisionize_settings[_<?php echo $active?>_set]" value="1"/>
          <input type="checkbox" name="revisionize_settings[<?php echo $active?>]" <?php echo $active_checked?> /> Active
        </label>
        <label>
          <input type="hidden" name="revisionize_settings[_<?php echo $remove?>_set]" value="1"/>
          <input type="checkbox" name="revisionize_settings[<?php echo $remove?>]" /> Delete
        </label>
        <?php else: ?>
        <a class="rvz-button button" href="<?php echo $addon['url']?>" target="_blank">$<?php echo $addon['price']?> - <?php echo $addon['button']?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php
}

// access settings
function get_setting($key, $default='') {
  $settings = get_option('revisionize_settings');  
  return !empty($settings[$key]) ? $settings[$key] : $default;
}

function set_setting($key, $value) {
  $settings = get_option('revisionize_settings');  
  $settings[$key] = $value;
  update_option('revisionize_settings', $settings);  
}

function remove_setting($keys) {
  $settings = get_option('revisionize_settings');    
  if (!is_array($keys)) {
    $keys = array($keys);
  }
  foreach ($keys as $key) {
    unset($settings[$key]);
  }
  update_option('revisionize_settings', $settings);
}

function is_on_settings_page() {
  global $pagenow;
  return $pagenow == 'options-general.php' && $_GET['page'] == 'revisionize';
}

function on_settings_saved($settings) {
  if (!empty($_FILES['revisionize_addon_file']['tmp_name'])) {
    install_addon($_FILES['revisionize_addon_file']['tmp_name']);
  }
  return $settings;
}

function install_addon($filename) {
  // make sure the directory exists
  $target_path = REVISIONIZE_ROOT.'/addons';
  wp_mkdir_p($target_path);

  $data = file_get_contents($filename);
  $data = json_decode(base64_decode($data), true);

  // TODO: check to see if addon already installed and if this version is newer. Maybe send warning if not (downgrading)
  file_put_contents($target_path.'/'.$data['name'].'.php', base64_decode($data['code']));

  $installed = get_installed_addons();
  $installed[] = $data['name'];
  update_option('revisionize_installed_addons', array_unique($installed));
}

function uninstall_addon($id, $file) {
  remove_setting(array(
    "addon_${id}_active",
    "addon_${id}_delete",
    "_addon_${id}_active_set",
    "_addon_${id}_delete_set",
  ));
  unlink($file);

  $installed = get_installed_addons();
  if (($key = array_search($id, $installed)) !== false) {
    array_splice($installed, $key, 1);
  }
  update_option('revisionize_installed_addons', array_unique($installed));
}

function get_registered_addons() {
  return apply_filters('revisionize_registered_addons', array());
}

function fetch_addons() {
  $addons = get_transient('revisionize_available_addons');
  if ($addons === false) {
    $url = defined('REVISIONIZE_DEV_API_URL') ? REVISIONIZE_DEV_API_URL : "https://revisionize.pro/addons.php";
    $json = file_get_contents($url);
    $addons = json_decode($json, true);
    set_transient('revisionize_available_addons', $addons, 4 * 60 * 60); // cache for 4 hours
  }
  return $addons;
}

function get_available_addons() {
  $addons = fetch_addons();
  $registered = get_registered_addons();
  foreach ($addons as &$addon) {
    $addon["installed"] = array_key_exists($addon["id"], $registered) ? $registered[$addon["id"]] : false;
  } 
  return $addons;
}

function get_installed_addons() {
  return get_option('revisionize_installed_addons', array());
}

function load_addons() {
  $addons = get_installed_addons();
  foreach ($addons as $id) {
    $file = REVISIONIZE_ROOT.'/addons/'.$id.'.php';
    if (file_exists($file)) {
      if (is_addon_pending_delete($id)) {
        uninstall_addon($id, $file);
      } else {
        require_once $file;
        \RevisionizeAddon::create($id);        
      }
    }
  }

  do_action('revisionize_addons_loaded');
}

function is_addon_active($id) {
  return is_checkbox_checked('addon_'.$id.'_active', true);
}

function is_addon_pending_delete($id) {
  return is_checkbox_checked('addon_'.$id.'_delete', false);  
}

function filter_keep_backup($b) {
  return is_checkbox_checked('keep_backup', $b);
}

function filter_preserve_date($b) {
  return is_checkbox_checked('preserve_date', $b);
}

// basic inputs for now
// $type: text|email|number|checkbox
function input_setting($type, $name, $key, $description, $default, $section, $filter=null, $handler=null) {
  add_settings_field('revisionize_setting_'.$key, $name, __NAMESPACE__.'\\field_input', 'revisionize', $section, array(
    'type' => $type,
    'label_for' => 'revisionize_setting_'.$key,
    'key' => $key,
    'description' => $description,
    'default' => $default
  ));

  if ($filter && $handler) {
    add_filter($filter, $handler);
  }
}

function field_input($args) {
  $type = $args['type'];
  $id = esc_attr($args['label_for']);
  $key = esc_attr($args['key']);
  $value = '';

  if ($type == 'checkbox') {
    if (is_checkbox_checked($key, $args['default'])) {
      $value = 'checked';
    }
  } else {
    $value = 'value="'.get_setting($key, $args['default']).'"';
  }
  ?>
  <div>
    <?php if ($type=="checkbox"): ?>
    <input type="hidden" name="revisionize_settings[_<?php echo $key?>_set]" value="1"/>
    <?php endif; ?>
    <label>
      <input id="<?php echo $id?>" type="<?php echo $type?>" name="revisionize_settings[<?php echo $key?>]" <?php echo $value?>/> 
      <?php echo $args['description']?>
    </label>
  </div>  
  <?php  
}

function is_checkbox_checked($key, $default) {
  return is_checkbox_set($key) ? is_checkbox_on($key) : $default;
}

function is_checkbox_on($key) {
  return get_setting($key) == "on";    
}

function is_checkbox_set($key) {
  return get_setting('_'.$key.'_set') == "1";    
}
