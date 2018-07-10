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

// TODO: Really need to tidy this file up and organize it better. 

namespace Revisionize;

require_once 'addon.php';

add_action('init', __NAMESPACE__.'\\settings_init');

if (is_admin() || is_cron()) {
  add_action('init', __NAMESPACE__.'\\check_for_addon_updates');
  add_action('admin_menu', __NAMESPACE__.'\\settings_menu');
  add_action('admin_init', __NAMESPACE__.'\\settings_admin_init');
  add_action('network_admin_menu', __NAMESPACE__.'\\network_settings_menu');
  add_action('network_admin_edit_revisionize_network_settings', __NAMESPACE__.'\\network_update_settings');
  add_filter('plugin_action_links_'.REVISIONIZE_BASE, __NAMESPACE__.'\\settings_link');
  add_filter('network_admin_plugin_action_links_'.REVISIONIZE_BASE, __NAMESPACE__.'\\network_settings_link');
  add_filter('revisionize_keep_original_on_publish', __NAMESPACE__.'\\filter_keep_backup');
  add_filter('revisionize_preserve_post_date', __NAMESPACE__.'\\filter_preserve_date');
}

function settings_init() {
  if (is_admin() || is_cron() || is_admin_bar_showing()) {
    load_addons();
  }
}

function settings_admin_init() {
  if (is_on_settings_page()) {
    set_setting('has_seen_settings', true);
  } else if (get_setting('has_seen_settings', false) === false) {
    add_action('admin_notices', __NAMESPACE__.'\\notify_new_settings');
  }

  if (is_on_network_settings_page() && isset($_GET['updated'])) {
    add_action('network_admin_notices', __NAMESPACE__.'\\notify_updated_settings');
  }
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

  register_setting('revisionize', 'revisionize_settings', array(
    "sanitize_callback" => __NAMESPACE__.'\\on_settings_saved'
  ));

  setup_basic_settings();

  if (!is_multisite()) {
    setup_addon_settings();  
  }
}

function network_settings_menu() {
  add_submenu_page (
    'settings.php',
    'Revisionize Network Settings',
    'Revisionize',
    'manage_network_options',
    'revisionize',
    __NAMESPACE__.'\\network_settings_page'
  );  

  register_setting('revisionize_network', 'revisionize_network_settings', array(
    "sanitize_callback" => __NAMESPACE__.'\\on_settings_saved'
  ));

  setup_addon_settings('revisionize_network');
}

function network_update_settings() {
  check_admin_referer('revisionize_network-options');

  // save files. 
  on_settings_saved();

    if (isset($_POST['revisionize_network_settings'])) {
    update_site_option('revisionize_network_settings', $_POST['revisionize_network_settings']);  
  }

  // need to schedule  an admin notice. 

  wp_redirect(add_query_arg(array('page'=>'revisionize', 'updated'=>'true'), network_admin_url('settings.php')));
  exit;
}

function settings_page() {
  if (!current_user_can('manage_options')) {
    echo 'Not Allowed.';
    return;
  }
  ?>
  <div class="wrap">
    <?php settings_css(); ?>
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" enctype="multipart/form-data" method="post" class="rvz-settings-form">
    <?php
      settings_fields('revisionize');
  
      do_fields_section('revisionize_section_basic');

      // settings from Addons
      do_action('revisionize_settings_fields');

      do_fields_section('revisionize_section_addons');

      submit_button('Save Settings');

      if (!is_multisite()) {
        addons_html();

        submit_button('Save Settings');
      }
    ?>
    </form>
  </div>
  <?php 
}

function network_settings_page() {
  if (!current_user_can('manage_network_options')) {
    echo 'Not Allowed.';
    return;
  }
  ?>
  <div class="wrap">
    <?php settings_css(); ?>
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <p>Note that site specific settings for Revisionize can be found when viewing a site. Such as <a href="<?php echo admin_url('options-general.php?page=revisionize')?>">here</a>.</p>

    <form action="edit.php?action=revisionize_network_settings" enctype="multipart/form-data" method="post" class="rvz-settings-form">
    <?php
      settings_fields('revisionize_network');
  
      do_fields_section('revisionize_section_addons', 'revisionize_network');

      submit_button('Save Settings');

      addons_html();

      submit_button('Save Settings');
    ?>
    </form>
  </div>
  <?php   
}

function do_fields_section($key, $group="revisionize") {  
  echo '<table class="form-table">';
  do_settings_fields($group, $key);
  echo '</table>';
}

function setup_basic_settings() {
  add_settings_section('revisionize_section_basic', '', '__return_null', 'revisionize');  

  input_setting('checkbox', 'Keep Backup', 'keep_backup', "After publishing the revision, the previously live post will be kept around and marked as a backup revision of the new version.", true, 'revisionize_section_basic');

  input_setting('checkbox', 'Preserve Date', 'preserve_date', "The date of the original post will be maintained even if the revisionized post date changes. In particular, a scheduled revision won't modify the post date once it's published.", true, 'revisionize_section_basic');
}

function setup_addon_settings($group="revisionize") {
  add_settings_section('revisionize_section_addons', '', '__return_null', $group);

  // These fields are displayed
  add_settings_field('revisionize_addon_file', __('Upload Addon', REVISIONIZE_I18N_DOMAIN), __NAMESPACE__.'\\settings_addon_file_html', $group, 'revisionize_section_addons', array('label_for' => 'revisionize_addon_file'));  
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
  $hasUpdates = false;
  ?>
  <h1>Revisionize Addons</h1>
  <p>Improve the free Revisionize plugin with these official addons.<br/>Visit <a href="https://revisionize.pro" target="_blank">revisionize.pro</a> for more info.</p>
  <div class="rvz-addons rvz-cf">
    <?php foreach (get_available_addons() as $addon) {
      addon_html($addon); 
      $hasUpdates = $hasUpdates || $addon["update_available"];
    }?>
  </div>
  <?php if ($hasUpdates): ?>
  <p>* To install an addon update, visit <a href="https://revisionize.pro/account/" target="_blank">https://revisionize.pro/account/</a> to login to your account.
    <br/>Find the relevant purchase confirmation and download the updated <em>.rvz</em> file. 
    <br/>Come back here and upload the addon.</p>
  <?php
  endif;
}

function addon_html($addon) {
  $id = $addon['id'];
  $active = "addon_${id}_active";
  $remove = "addon_${id}_delete";
  $active_checked = is_addon_active($id) ? 'checked' : '';
  $group = is_multisite() ? "revisionize_network_settings" : "revisionize_settings";
  ?>
  <div class="rvz-addon-col">
    <div class="rvz-addon<?php if ($addon['installed']) echo " rvz-installed" ?>">
      <h3><a href="<?php echo $addon['url']?>" target="_blank"><?php echo $addon['name'];?></a></h3>
      <?php echo $addon['description']; ?>
      <div class="rvz-meta rvz-cf">
      <?php if ($addon['installed']): ?>
        <label>Installed: <?php echo $addon['installed']?></label>
        <label>
          <input type="hidden" name="<?php echo $group?>[_<?php echo $active?>_set]" value="1"/>
          <input type="checkbox" name="<?php echo $group?>[<?php echo $active?>]" <?php echo $active_checked?> /> Active
        </label>
        <label>
          <input type="hidden" name="<?php echo $group?>[_<?php echo $remove?>_set]" value="1"/>
          <input type="checkbox" name="<?php echo $group?>[<?php echo $remove?>]" /> Delete
        </label>
        <?php if ($addon["update_available"]): ?>
        <div class="rvz-update-available rvz-cf">
          <a class="rvz-button button" href="https://revisionize.pro/account/" target="_blank">Update Available: <?php echo $addon['version']?></a>    
        </div>
        <?php endif; ?>
      <?php else: ?>
        <a class="rvz-button button" href="<?php echo $addon['url']?>" target="_blank"><?php echo $addon['price']?> - <?php echo $addon['button']?></a>
      <?php endif; ?>
      </div>
    </div>
  </div>
  <?php
}

// access settings
function get_setting($key, $default='', $multisite=false) {
  $settings = $multisite ? get_site_option('revisionize_network_settings') : get_option('revisionize_settings');  
  return !empty($settings[$key]) ? $settings[$key] : $default;
}

function set_setting($key, $value) {
  $settings = get_option('revisionize_settings');  
  $settings[$key] = $value;
  update_option('revisionize_settings', $settings);  
}

function remove_setting($keys, $multisite=false) {
  $settings = $multisite ? get_site_option('revisionize_network_settings') : get_option('revisionize_settings');    
  if (!is_array($keys)) {
    $keys = array($keys);
  }
  foreach ($keys as $key) {
    unset($settings[$key]);
  }
  if ($multisite) {
    update_site_option('revisionize_network_settings', $settings);
  } else {
    update_option('revisionize_settings', $settings);    
  }
}

function is_on_settings_page() {
  global $pagenow;
  return $pagenow == 'options-general.php' && isset( $_GET['page'] ) && $_GET['page'] == 'revisionize';
}

function is_on_network_settings_page() {
  global $pagenow;
  return $pagenow == 'settings.php' && isset( $_GET['page'] ) && $_GET['page'] == 'revisionize';
}

function on_settings_saved($settings=null) {
  if (!empty($_FILES['revisionize_addon_file']['tmp_name'])) {
    install_addon($_FILES['revisionize_addon_file']['tmp_name']);
  }
  return $settings;
}

function install_addon($filename) {
  // make sure the directory exists
  $target_path = get_addons_root();
  wp_mkdir_p($target_path);

  $data = file_get_contents($filename);
  $data = json_decode(base64_decode($data), true);

  // TODO: check to see if addon already installed and if this version is newer. Maybe send warning if not (downgrading)
  file_put_contents($target_path.'/'.$data['name'].'.php', base64_decode($data['code']));

  $installed = get_installed_addons();
  $installed[] = $data['name'];
  set_installed_addons($installed);
}

function uninstall_addon($id, $file) {
  remove_setting(array(
    "addon_${id}_active",
    "addon_${id}_delete",
    "_addon_${id}_active_set",
    "_addon_${id}_delete_set",
  ), is_multisite());
  
  unlink($file);

  $installed = get_installed_addons();
  if (($key = array_search($id, $installed)) !== false) {
    array_splice($installed, $key, 1);
  }
  set_installed_addons($installed);
}

function get_available_addons() {
  $registered = apply_filters('revisionize_registered_addons', array());
  $addons = get_transient('revisionize_available_addons');

  if ($addons === false) {
    $response = wp_remote_get("https://revisionize.pro/rvz-addons/");
    $json = is_array($response) && !empty($response['body']) ? $response['body'] : '';
    $payload = !empty($json) ? json_decode($json, true) : array();
    $addons = !empty($payload['addons']) ? $payload['addons'] : array();

    if (remote_addons_valid($addons)) {
      set_transient('revisionize_available_addons', $addons, 6 * 60 * 60); // cache for 6 hours
    } else {
      // for some reason our addons list is empty. cache this for a shorter time so site perfomance
      // isn't impacted by repeated network calls.
      set_transient('revisionize_available_addons', $addons, 5 * 60); // cache for 5 minutes
    }
  }

  // failsafe - really make sure we have valid addons
  if (empty($addons) || !is_array($addons) || !remote_addons_valid($addons)) {
    $addons = array();
  }

  foreach ($addons as &$addon) {
    $addon["installed"] = array_key_exists($addon["id"], $registered) ? $registered[$addon["id"]] : false;
    $addon["update_available"] = $addon["installed"] && version_compare($addon["version"], $addon["installed"]) > 0;
  } 

  return $addons;
}

function remote_addons_valid($addons) {
  return !empty($addons) && count($addons) > 0 && all_keys_set($addons, "id") && all_keys_set($addons, "version");
}

function all_keys_set($arr, $key) {
  $s = implode('', array_map(function($obj) use ($key) { return empty($obj[$key]) ? "" : $obj[$key]; }, $arr));
  return !empty($s);
}

function check_for_addon_updates() {
  $addons = get_available_addons();

  foreach ($addons as $addon) {
    if ($addon["update_available"]) {
      add_action(is_multisite() ? 'network_admin_notices' : 'admin_notices', __NAMESPACE__.'\\notify_needs_update');
    }
  }
}

function get_installed_addons() {
  $addons = is_multisite() ? get_site_option('revisionize_installed_addons', array()) : get_option('revisionize_installed_addons', array());
  return $addons ? $addons : array();
}

function get_addons_root() {
  // version 2.2.3 - move the addons_root to a safe directory
  $uploads = wp_upload_dir();
  $path = $uploads['basedir'];

  if (is_multisite() && !is_network_admin()) {
    // when network admin we get back /wp-content/uploads/
    // when in a Site we get back /wp-content/uploads/sites/site-ID
    $path .= '/../..';
  }
  return apply_filters('revisionize_addons_root', $path.'/revisionize/addons');
}

function set_installed_addons($installed) {
  if (is_multisite()) {
    update_site_option('revisionize_installed_addons', array_unique($installed));  
  } else {
    update_option('revisionize_installed_addons', array_unique($installed));
  }
}

function load_addons() {
  $addons = get_installed_addons();
  foreach ($addons as $id) {
    $file = get_addons_root().'/'.$id.'.php';

    if (file_exists($file)) {
      if (is_addon_pending_delete($id)) {
        uninstall_addon($id, $file);
      } else {
        require_once $file;
        \RevisionizeAddon::create($id);        
      }
    } else {
      // system thinks addon is installed, but the file doesn't exist. Probably because it got wiped during a core plugin update. 
      // v2.2.3 changes the addons_root directory to wp-content/uploads/revisionize/addons
      add_action(is_multisite() ? 'network_admin_notices' : 'admin_notices', __NAMESPACE__.'\\notify_fix_addons');
    }
  }

  do_action('revisionize_addons_loaded');
}

function is_addon_active($id) {
  return is_checkbox_checked('addon_'.$id.'_active', true, is_multisite());
}

function is_addon_pending_delete($id) {
  return is_checkbox_checked('addon_'.$id.'_delete', false, is_multisite());  
}

function filter_keep_backup($b) {
  return is_checkbox_checked('keep_backup', $b);
}

function filter_preserve_date($b) {
  return is_checkbox_checked('preserve_date', $b);
}

// basic inputs for now
// $type: text|email|number|checkbox
function input_setting($type, $name, $key, $description, $default, $section) {
  add_settings_field('revisionize_setting_'.$key, $name, __NAMESPACE__.'\\field_input', 'revisionize', $section, array(
    'type' => $type,
    'label_for' => 'revisionize_setting_'.$key,
    'key' => $key,
    'description' => $description,
    'default' => $default
  ));
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

function is_checkbox_checked($key, $default, $multisite=false) {
  return is_checkbox_set($key, $multisite) ? is_checkbox_on($key, $multisite) : $default;
}

function is_checkbox_on($key, $multisite=false) {
  return get_setting($key, '', $multisite) == "on";    
}

function is_checkbox_set($key, $multisite=false) {
  return get_setting('_'.$key.'_set', '', $multisite) == "1";    
}

function notify_new_settings() {
  $notice = '<strong>Revisionize</strong> has a new settings panel. <strong><a href="'.admin_url('options-general.php?page=revisionize').'">Check it out!</a></strong>';
  echo '<div class="notice notice-info is-dismissible"><p>'.$notice.'</p></div>';  
}

function notify_updated_settings() {
  echo '<div class="notice updated is-dismissible"><p><strong>Settings saved.</strong></p></div>';  
}

function notify_needs_update() {
  if (!is_on_settings_page() && !is_on_network_settings_page()) {
    $url = is_multisite() ? network_admin_url('settings.php?page=revisionize') : admin_url('options-general.php?page=revisionize');
    echo '<div class="notice updated is-dismissible"><p>Revisionize has 1 or more updates available for your installed addons. <a href="'.$url.'">View settings</a> for details.</p></div>';    
  }
}

function settings_css() {
  ?>
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
    margin-bottom: 20px;
  }
  .rvz-addon h3 {
    margin-top: 0;
    line-height: 30px;
    text-transform: uppercase;
    width: 100%;
  }
  .rvz-addon ul {
    list-style: disc;
    padding-bottom: 15px;
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
  .rvz-update-available {
    clear: both;
    margin-top: 8px;
    text-align: center;
  }
  </style>  
  <?php
}

function settings_link($links) {
  return array_merge($links, array('<a href="'.admin_url('options-general.php?page=revisionize').'">Settings</a>'));
}

function network_settings_link($links) {
  return array_merge($links, array('<a href="'.network_admin_url('settings.php?page=revisionize').'">Settings</a>'));
}

function notify_fix_addons() {
  echo '<div class="notice notice-error is-dismissible"><p>Please re-install your <a href="https://revisionize.pro/account/" target="_blank">Revisionize addons</a>.<br/>There was a problem where updates to the core Revisionize plugin would inadvertantly delete your installed addons.<br/>This has been fixed in version 2.2.3. Sorry for the inconvenience!</p></div>';
}

