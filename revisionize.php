<?php
/*
 Plugin Name: Revisionize
 Plugin URI: https://github.com/jamiechong/revisionize
 Description: Stage revisions or variations of live, published content. Publish the staged content manually or with the built-in scheduling system.
 Version: 1.3.5
 Author: Jamie Chong
 Author URI: http://jamiechong.ca
 Text Domain: revisionize
 */

/*  Copyright 2016 Jamie Chong

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

define('REVISIONIZE_I18N_DOMAIN', 'revisionize');

add_action('init', __NAMESPACE__.'\\init');

function init() {
  // Only add filters and actions for admin who can actually edit posts
  if (is_admin() && user_can_revisionize() && is_post_type_enabled()) {
    add_filter('display_post_states', __NAMESPACE__.'\\post_status_label');
    add_filter('post_row_actions', __NAMESPACE__.'\\admin_actions', 10, 2);
    add_filter('page_row_actions', __NAMESPACE__.'\\admin_actions', 10, 2);

    add_action('post_submitbox_start', __NAMESPACE__.'\\post_button');
    add_action('admin_action_revisionize_create', __NAMESPACE__.'\\create');
    add_action('admin_notices', __NAMESPACE__.'\\notice');

    add_action('before_delete_post', __NAMESPACE__.'\\on_delete_post');
  }

  // For users who can publish.
  if (is_admin() && show_dashboard_widget() && user_can_publish_revision()) {
    add_action('wp_dashboard_setup', __NAMESPACE__.'\\add_dashboard_widget');
  }

  // For Cron and users who can publish
  if (is_admin() && user_can_publish_revision() || is_cron()) {
    if (!is_cron()) {
      add_action('acf/save_post', __NAMESPACE__.'\\acf_on_publish_post', 130, 1);
    }

    add_action('transition_post_status', __NAMESPACE__.'\\on_publish_post', 10, 3);
  }

}

// Action for ACF users. Will publish the revision only if user_can_publish_revision.
function acf_on_publish_post($post_id) {
  $post = get_post($post_id);
  $new_status = get_post_status($post_id);
  on_publish_post($new_status, '', $post, "ACF");
}

// Action for transition_post_status. Will publish the revision only if user_can_publish_revision.
function on_publish_post($new_status, $old_status, $post, $from="TPS") {

  // fix issue where revisions were not published when ACF 5 was installed, but this post type didn't have any custom fields.
  if ($from=="TPS" && !is_cron() && is_acf_post()) {
    return;
  }


  if ($post && $new_status == 'publish') {
    $id = get_revision_of($post);
    if ($id) {
      $original = get_post($id);
      if ($original) {
        publish($post, $original);
      }

    }
  }
}

function create() {
  $id = intval($_REQUEST['post']);

  // make sure the clicked link is a valid nonce. Make sure the user can revisionize.
  if (user_can_revisionize() && check_admin_referer('revisionize-create-'.$id)) {
    if ($id) {
      $post = get_post($id);

      if ($post && is_create_enabled($post)) {
        $new_id = create_revision($post, !is_revision_post($post) || is_original_post($post));
        wp_redirect(admin_url('post.php?action=edit&post=' . $new_id));
        exit;
      }
    }
  }

  // if we didn't redirect out, then we fail.
  wp_die(__('Invalid Post ID', REVISIONIZE_I18N_DOMAIN));
}

function create_revision($post, $is_original=false) {
  $new_id = copy_post($post, null, $post->ID);
  update_post_meta($new_id, '_post_revision_of', $post->ID);      // mark the new post as a variation of the old post.
  update_post_meta($new_id, '_post_revision', true);

  if ($is_original) {
    update_post_meta($post->ID, '_post_original', true);
    delete_post_meta($new_id, '_post_original');                    // a revision is never an original
  } else {
    delete_post_meta($post->ID, '_post_original');
  }

  return $new_id;
}

function publish($post, $original) {
  if (user_can_publish_revision() || is_cron()) {

    if (keep_original_on_publish()) {
      create_revision($original);    // keep a backup copy of the live post.
    }

    delete_post_meta($post->ID, '_post_revision_of');                       // remove the variation tag so the meta isn't copied
    copy_post($post, $original, $original->post_parent);                    // copy the variation into the live post

    delete_post_meta($post->ID, '_post_original');                          // original tag is copied, but remove from source.


    wp_delete_post($post->ID, true);                                        // delete the variation

    if (!is_ajax() && !is_cron()) {
      wp_redirect(admin_url('post.php?action=edit&post=' . $original->ID));   // take us back to the live post
      exit;
    }

    if (is_ajax()) {
      echo "<script type='text/javascript'>location.reload();</script>";
    }
  }
}

// if we delete the original post, make the current parent the new original.
function on_delete_post($post_id) {
  $post = get_post($post_id);
  $parent_id = get_revision_of($post);
  if ($parent_id && is_original_post($post)) {
    update_post_meta($parent_id, '_post_original', true);
  }
}

function copy_post($post, $to=null, $parent_id=null, $status='draft') {
  if ($post->post_type == 'revision') {
    return;
  }

  $author_id = $post->post_author;
  $post_status = $post->post_status;

  if ($to) {
    $author_id = $to->post_author;  // maintain original author.
  }
  else {
    $author = wp_get_current_user();
    $author_id = $author->ID;
    $post_status = $status;
  }

  $data = array(
    'menu_order' => $post->menu_order,
    'comment_status' => $post->comment_status,
    'ping_status' => $post->ping_status,
    'post_author' => $author_id,
    'post_content' => $post->post_content,
    'post_excerpt' => $post->post_excerpt,
    'post_mime_type' => $post->post_mime_type,
    'post_parent' => !$parent_id ? $post->post_parent : $parent_id,
    'post_password' => $post->post_password,
    'post_status' => $post_status,
    'post_title' => $post->post_title,
    'post_type' => $post->post_type,
    'post_date' => $post->post_date,
    'post_date_gmt' => get_gmt_from_date($post->post_date)
  );


  if ($to) {
    $data['ID'] = $to->ID;
    $new_id = $to->ID;

    // maintain original date. Fixes scheduled revisions overwriting the date. see issue #9
    if (is_post_date_preserved($to->ID)) {
      $data['post_date'] = $to->post_date;
      $data['post_date_gmt'] = get_gmt_from_date($to->post_date);
    }

    // fixes PR #4
    if (is_cron()) {
      kses_remove_filters();
    }

    if (is_acf_fields_different($to, $post)) {
      // this will force WP to create a new revision. 
      add_filter('wp_save_post_revision_post_has_changed', '__return_true');
    }

    $revision_before = get_latest_wp_revision($new_id);

    wp_update_post($data);

    $revision_after = get_latest_wp_revision($new_id);

    if (is_wp_revision_different($revision_before, $revision_after) && $revision_after) {
      copy_post_meta_info($revision_after->ID, $post);  
    }

    if (is_cron()) {
      kses_init_filters();
    }
  } else {
    $new_id = wp_insert_post($data);
  }

  copy_post_taxonomies($new_id, $post);
  // apply revisionized post_meta to the original post.
  copy_post_meta_info($new_id, $post);

  return $new_id;
}

function copy_post_taxonomies($new_id, $post) {
  global $wpdb;

  if (isset($wpdb->terms)) {
    // Clear default category (added by wp_insert_post)
    wp_set_object_terms($new_id, NULL, 'category');

    $taxonomies = get_object_taxonomies($post->post_type);

    foreach ($taxonomies as $taxonomy) {
      $post_terms = wp_get_object_terms($post->ID, $taxonomy, array('orderby' => 'term_order'));
      $terms = array();

      for ($i=0; $i<count($post_terms); $i++) {
        $terms[] = $post_terms[$i]->slug;
      }

      wp_set_object_terms($new_id, $terms, $taxonomy);
    }
  }
}

function clear_post_meta($id) {
  $meta_keys = get_post_custom_keys($id);
  if (!empty($meta_keys)) {
    foreach ($meta_keys as $meta_key) {
      delete_metadata('post', $id, $meta_key);
    }
  }
}

function copy_post_meta_info($new_id, $post) {
  clear_post_meta($new_id);

  $meta_keys = get_post_custom_keys($post->ID);

  foreach ($meta_keys as $meta_key) {
    $meta_values = get_post_custom_values($meta_key, $post->ID);
    foreach ($meta_values as $meta_value) {
      $meta_value = maybe_unserialize($meta_value);
      add_metadata('post', $new_id, $meta_key, $meta_value);
    }
  }
}

function is_acf_fields_different($a, $b) {
  $afields = get_field_objects($a->ID, array('format_value' => false));
  $bfields = get_field_objects($b->ID, array('format_value' => false));
  return $afields != $bfields;
}

// -- Admin UI (buttons, links, etc)

// Action for post_submitbox_start which is only added if user_can_revisionize
function post_button() {
  global $post;
  $parent = get_parent_post($post);
  if (!$parent): ?>
    <div style="text-align: right; margin-bottom: 10px;">
      <a class="button"
        href="<?php echo get_create_link($post) ?>"><?php echo apply_filters('revisionize_create_revision_button_text', __('Revisionize', REVISIONIZE_I18N_DOMAIN)); ?>
      </a>
    </div>
  <?php else: ?>
    <div><em><?php echo sprintf(__('WARNING: Publishing this revision will overwrite %s.'), get_parent_editlink($parent, __('its original')))?></em></div>
  <?php endif;
}

// Filter for post_row_actions/page_row_actions which is only added if user_can_revisionize
function admin_actions($actions, $post) {
  if (is_create_enabled($post)) {
    $actions['create_revision'] = '<a href="'.get_create_link($post).'" title="'
      . esc_attr(__("Create a Revision", REVISIONIZE_I18N_DOMAIN))
      . '">' .  apply_filters('revisionize_create_revision_button_text', __('Revisionize', REVISIONIZE_I18N_DOMAIN)) . '</a>';
  }
  return $actions;
}

// Filter for display_post_states which is only added if user_can_revisionize
function post_status_label($states) {
  global $post;
  if (get_revision_of($post)) {
    array_unshift($states, 'Revision');
  }
  if (is_original_post($post) && get_revision_of($post)) {
    array_unshift($states, 'Original');
  }
  return $states;
}

function notice() {
  global $post;
  $parent = get_parent_post($post);
  $screen = get_current_screen();
  if ($screen->base == 'post' && $parent):
  ?>
  <div class="notice notice-warning">
      <p><?php echo sprintf(__('Currently editing a revision of %s. Publishing this post will overwrite it.', REVISIONIZE_I18N_DOMAIN), get_parent_permalink($parent)); ?></p>
  </div>
  <?php
  endif;
}

// Add a dashboard widget showing posts needing review
function add_dashboard_widget() {
  wp_add_dashboard_widget(
    'revisionize-posts-needing-review',    // ID of the widget.
    __('Revisionized Posts Needing Review'),                // Title of the widget.
    __NAMESPACE__.'\\do_dashboard_widget'  // Callback.
  );
}

// Echo the content of the dashboard widget.
function do_dashboard_widget() {
  $posts = get_posts(array(
    'post_type'   => 'any',
    'post_status' => 'pending',
    'meta_query'  => array(
      array(
        'key'     => '_post_revision',
        'compare' => 'EXISTS',
        )
      )
    ));

  if (empty($posts)) {
    _e('No posts need reviewed at this time!', 'revisionize');
  }

  echo '<ul>';

  foreach ($posts as $post) {
    printf('<li><a href="%s">%s</a> - %s</li>',
      get_edit_post_link($post->ID),
      get_the_title($post->ID),
      get_the_author_meta('nicename', $post->post_author)
    );
  }

  echo '</ul>';
}

// -- Helpers

function user_can_revisionize() {
  return apply_filters('revisionize_user_can_revisionize', current_user_can('edit_posts'));
}

function user_can_publish_revision() {
  return apply_filters('revisionize_user_can_publish_revision', current_user_can('publish_posts') || current_user_can('publish_pages'));
}

function keep_original_on_publish() {
  return apply_filters('revisionize_keep_original_on_publish', true);
}

function is_cron() {
  return defined('DOING_CRON') && DOING_CRON;
}

function is_ajax() {
  return defined('DOING_AJAX') && DOING_AJAX;
}

function is_post_type_enabled() {
  $type = get_current_post_type();
  $excluded = apply_filters('revisionize_exclude_post_types', array('acf'));
  return empty($type) || !in_array($type, $excluded);
}

function is_create_enabled($post) {
  $is_enabled = !get_revision_of($post) && current_user_can('edit_post', $post->ID);
  return apply_filters('revisionize_is_create_enabled', $is_enabled, $post);
}

function is_original_post($post) {
  return get_post_meta($post->ID, '_post_original', true);
}

function is_revision_post($post) {
  return get_post_meta($post->ID, '_post_revision', true);
}

function is_acf_post() {
  return has_action('acf/save_post') && (!empty($_POST['acf']) || !empty($_POST['fields']));
}

function is_post_date_preserved($id) {
  return apply_filters('revisionize_preserve_post_date', true, $id) === true;
}

function show_dashboard_widget() {
  return apply_filters('revisionize_show_dashboard_widget', false);
}

function get_revision_of($post) {
  return get_post_meta($post->ID, '_post_revision_of', true);
}

function get_create_link($post) {
  return wp_nonce_url(admin_url("admin.php?action=revisionize_create&post=".$post->ID), 'revisionize-create-'.$post->ID);
}

function get_parent_editlink($parent, $s=null) {
  return sprintf('<a href="%s">%s</a>', get_edit_post_link($parent->ID), $s ? $s : $parent->post_title);
}

function get_parent_permalink($parent) {
  return sprintf('<a href="%s" target="_blank">%s</a>', get_permalink($parent->ID), $parent->post_title);
}

function get_parent_post($post) {
  $id = $post ? get_revision_of($post) : false;
  return $id ? get_post($id) : false;
}

function get_current_post_type() {
  global $post, $typenow, $current_screen;
  $type = null;

  if ($post && $post->post_type) {
    $type = $post->post_type;
  } else if ($typenow) {
    $type = $typenow;
  } else if ($current_screen && $current_screen->post_type) {
    $type = $current_screen->post_type;
  } else if (isset($_REQUEST['post_type'])) {
    $type = sanitize_key($_REQUEST['post_type']);
  } else if (isset($_REQUEST['post'])) {
    $type = get_post_type($_REQUEST['post']);
  }

  return $type;
}

function get_latest_wp_revision($id) {
  $revisions = wp_get_post_revisions($id);
  return !empty($revisions) ? current($revisions) : null;
}

function is_wp_revision_different($a, $b) {
  return $a && !$b || !$a && $b || $a->ID != $b->ID;
}
