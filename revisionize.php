<?php
/*
 Plugin Name: Revisionize
 Plugin URI: https://github.com/jamiechong/revisionize
 Description: Stage revisions or variations of live, published content. Publish the staged content manually or with the built-in scheduling system. 
 Version: 1.0
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


define('REVISIONIZE_I18N_DOMAIN', 'revisionize');


if (is_admin() || revisionize_is_cron()) {
  add_filter('display_post_states', 'revisionize_post_status_label');
  add_filter('post_row_actions', 'revisionize_admin_actions', 10, 2);
  add_filter('page_row_actions', 'revisionize_admin_actions', 10, 2);

  add_action('admin_action_revisionize_create', 'revisionize_create');
  add_action('transition_post_status', 'revisionize_on_publish_post', 10, 3);
}

function revisionize_is_cron() {
  return defined('DOING_CRON') && DOING_CRON;
}

function revisionize_is_ajax() {
  return defined( 'DOING_AJAX' ) && DOING_AJAX;
}

function revisionize_post_status_label($states) {
  global $post;
  if (get_post_meta($post->ID, '_post_revision_of')) {
    array_unshift($states, 'Revision');
  }
  if (get_post_meta($post->ID, '_post_original')) {
    array_unshift($states, 'Original');
  }
  return $states;
}

function revisionize_on_publish_post($new_status, $old_status, $post) {
  if ($post && $new_status == 'publish') {
    $id = get_post_meta($post->ID, '_post_revision_of', true);
    if ($id) {
      $original = get_post($id);
      if ($original) {
        revisionize_publish($post, $original);
      }
      
    }
  }
}

function revisionize_create() {
  $id = $_REQUEST['post'];

  if ($id) {
    $post = get_post($id);

    if ($post) {
      $new_id = revisionize_create_revision($post);
      wp_redirect(admin_url('post.php?action=edit&post=' . $new_id));
      exit;
    }
  }
  // if we didn't redirect out, then we fail.
  wp_die(__('Invalid Post ID', REVISIONIZE_I18N_DOMAIN)); 
}



function revisionize_admin_actions ($actions, $post) {
  $actions['create_revision'] = '<a href="'.admin_url("admin.php?action=revisionize_create&post=".$post->ID).'" title="'
    . esc_attr(__("Create a Revision", REVISIONIZE_I18N_DOMAIN))
    . '">' .  __('Create Revision', REVISIONIZE_I18N_DOMAIN) . '</a>';
  return $actions;
}

function revisionize_create_revision($post, $is_original=false) {
  $new_id = revisionize_copy_post($post, null, $post->ID);
  update_post_meta($new_id, '_post_revision_of', $post->ID);     // mark the new post as a variation of the old post. 
  if ($is_original) {
    update_post_meta($new_id, '_post_original', true);
  }
  return $new_id;
}

function revisionize_publish($post, $original) {
  $clone_id = revisionize_create_revision($original, true);                           // keep a backup copy of the live post.

  delete_post_meta($post->ID, '_post_revision_of');                       // remove the variation tag so the meta isn't copied
  delete_post_meta($post->ID, '_post_original');                          // remove the original tag so the meta isn't copied
  revisionize_copy_post($post, $original, $original->post_parent);        // copy the variation into the live post
  
  wp_delete_post($post->ID, true);                                        // delete the variation

  if (!revisionize_is_ajax() && !revisionize_is_cron()) {
    wp_redirect(admin_url('post.php?action=edit&post=' . $original->ID));   // take us back to the live post
    exit;
  }

  if (revisionize_is_ajax()) {
    echo "<script type='text/javascript'>location.reload();</script>";
  }
}


function revisionize_copy_post($post, $to=null, $parent_id=null, $status='draft') {
  if ($post->post_type == 'revision') {
    return;
  }

  $author_id = $post->post_author;
  $post_status = $post->post_status;

  if (!$to) {
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
    wp_update_post($data);
    revisionize_clear_post_meta($new_id);  
  } else {
    $new_id = wp_insert_post($data);
  }

  revisionize_copy_post_taxonomies($new_id, $post);
  revisionize_copy_post_meta_info($new_id, $post);
  
  return $new_id;
}


function revisionize_copy_post_taxonomies($new_id, $post) {
  global $wpdb;

  if (isset($wpdb->terms)) {
    // Clear default category (added by wp_insert_post)
    wp_set_object_terms( $new_id, NULL, 'category' );

    $taxonomies = get_object_taxonomies($post->post_type);

    foreach ($taxonomies as $taxonomy) {
      $post_terms = wp_get_object_terms($post->ID, $taxonomy, array( 'orderby' => 'term_order' ));
      $terms = array();

      for ($i=0; $i<count($post_terms); $i++) {
        $terms[] = $post_terms[$i]->slug;
      }

      wp_set_object_terms($new_id, $terms, $taxonomy);
    }
  }
}

function revisionize_clear_post_meta($id) {
  $meta_keys = get_post_custom_keys($id);
  foreach ($meta_keys as $meta_key) {
    delete_post_meta($id, $meta_key);
  }
}


function revisionize_copy_post_meta_info($new_id, $post) {
  $meta_keys = get_post_custom_keys($post->ID);

  foreach ($meta_keys as $meta_key) {
    $meta_values = get_post_custom_values($meta_key, $post->ID);
    foreach ($meta_values as $meta_value) {
      $meta_value = maybe_unserialize($meta_value);
      add_post_meta($new_id, $meta_key, $meta_value);
    }
  }
}

