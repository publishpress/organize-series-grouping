<?php
/*
Plugin Name: Organize Series Grouping
Description: This addon gives you the ability to group series together by category.  It modifies the ui for the "Manage Series" page to add series to various categories (you can add to more than one category).  It also provides various template tags for getting (and outputting) the series data from the database within a certain group.
Version: 2.2
Author: Darren Ethier
Author URI: http://organizeseries.com
*/

$orgseries_groups_ver = '2.2';
global $orgseries_groups_ver;

/* LICENSE */
//"Organize Series Plugin" and all addons for it created by this author are copyright (c) 2007-2012 Darren Ethier. This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
//
//It goes without saying that this is a plugin for WordPress and I have no interest in developing it for other platforms so please, don't ask ;).


/* NOTES */
//version 0 - 1.5 in the code there is a lot of reference to "groups".  There is actually NO "group" taxonomy created. It is just a way of describing the categories a series belongs to.  Categories are used as a way of Organizing Series into "groups".  At some point in the future I may make this so you can actually assign/create a specific taxonomy for grouping series but for now this is how it will be done.  You can still use the category you use for posts for series as well without worrying about seeing series show up in the queries for that category when referencing posts and vice versa.

//version 1.5+ - Series Groups are now a custom taxonomy that only apply to the series_groups post type.  Series Groups no longer show up on the manage_category page but instead have their own taxonomy (and related management pages).  This creates more flexibility going forward for display of groups information.
//
/** END NOTES **/

global $pagenow, $wp_version;
$checkpage= $pagenow;
global $checkpage;

// ALWAYS CHECK TO MAKE SURE ORGANIZE SERIES IS RUNNING FIRST //
add_action('plugins_loaded', 'orgseries_check_seriesgrouping');

//All inits
add_action('init', 'orgseries_seriesgrouping_register_textdomain');
add_action('init', 'orgseries_grouping_posttype');
add_action('init', 'orgseries_grouping_import_existing_series');
add_action('init', 'orgseries_grouping_taxonomy');
add_action('admin_init', 'orgseries_upgrade_check');
add_action('init', 'orgseries_manage_grouping_columns');

//allow filtering on manage_series page
add_filter('init', 'orgseries_manage_grouping_filter_setup');

//Create Admin Menu item under "Posts" for easy groups management
add_action('admin_menu', 'orgseries_groups_admin_menu');

//hook into the existing Organize Series Options page to add grouping options.
add_action('admin_init', 'orgseries_grouping_settings_setup'); 

//all scripts and css
add_action('admin_print_scripts', 'orgseries_groups_scripts');
add_action('admin_print_styles', 'orgseries_groups_styles');

//hook in to existing Series Management page
add_filter('manage_edit-series_columns', 'manage_series_grouping_columns',10);
add_filter('manage_series_custom_column', 'manage_series_grouping_columns_inside',10,3);
add_action('series_add_form_fields', 'add_orgseries_group_fields',1);
add_action('series_edit_form', 'edit_orgseries_group_fields',2,2);
if ($wp_version < '3.1')
	add_filter('manage_edit-tags_columns', 'manage_series_grouping_columns');


//add new queryvar and custom joins for the group filter (on manage series page) - TODO DISABLED currently - still working for future version.
//add_action('parse_query', 'orgseries_group_parsequery');
//add_filter('query_vars', 'orgseries_group_add_queryvars');
//add_filter('posts_where', 'orgseries_group_where');


//hook into terms api
add_action('created_series', 'wp_insert_series_group', 1, 2);
add_action('edited_series', 'wp_update_series_group', 1, 2);
add_action('delete_series', 'wp_delete_series_group', 1, 2);

function orgseries_check_seriesgrouping() {
	if (!class_exists('orgSeries') ) {
		add_action('admin_notices', 'orgseries_seriesgrouping_warning');
		add_action('admin_notices', 'orgseries_seriesgrouping_deactivate');
		return;
	}
	return;
}

function orgseries_upgrade_check() {
	global $orgseries_groups_ver;
	//below is where I will indicate any upgrade routines that need to be run
	$version_check = get_option('orgseries_grouping_version');
	if ( !$version_check )  { //this may be the first time orgseries is used
		if ( $is_imported = get_option('orgseries_grouping_import_completed') ) // we know a version 1.5 and earlier was previously installed (before we saved version numbers) - update needed
			upgrade_orgseries_grouping_from_one_five();
		add_option('orgseries_grouping_version', $orgseries_groups_ver);
		add_option('orgser_grp_upgrade_'.$orgseries_groups_ver);
		return;
	}
	
	orgseries_grouping_upgrade($orgseries_groups_ver, $version_check);
	
	update_option('orgseries_grouping_version', $orgseries_groups_ver);
}

/*
*
* This is the function for doing any upgrades necessary
*/

function orgseries_grouping_upgrade($this_version, $old_version) {
	global $wpdb;
	
	if ( $old_version == '1.6' ) {
		//let's fix up any potential errors in the database from a bad 1.5-1.6 import
		//First up is a fix for object_id == 0;
		$object_id = 0;
		$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->term_relationships WHERE object_id = %d", $object_id) );
		
		//next up is reset the term_counts for all series_groups so they are correct.
		$args = array(
			'hide_empty' => false,
			'fields' => 'ids'
		);
		
		$groups = get_series_groups($args);
		$groups = array_map('intval', $groups);
		$groups = implode(', ',$groups);
		$query = "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy WHERE term_id IN ( $groups ) AND taxonomy = 'series_group'";
		$terms = $wpdb->get_results( $wpdb->prepare($query) );
		while ( $group = array_shift($terms) )
			$_groups[] = $group->term_taxonomy_id;
		$series_groups = $_groups;
		update_series_group_count($series_groups, 'series_group');
		exit;
		return true;
	}
	return true;
}

function upgrade_orgseries_grouping_from_one_five() {
	//if ( !taxonomy_exists('series_group') )
		//orgseries_grouping_taxonomy();
	
	//let's get all the existing series groups in the old category system
	$args = array(
		'hide_empty' => false,
		'fields' => 'ids',
		'taxonomy' => 'category'
	);
	
	
	$old_groups = get_old_series_groups($args); //list of category ids that are groups
	
	$args_b = array(
		'include' => $old_groups,
		'hide_empty' => false
		);
		
	$_old_groups = get_terms('category', $args_b); //need to do this in order to get the description field.
	
	$args_c = array(
		'hide_empty' => false,
		'taxonomy' => 'category'
	);
	
	//let's set up the new groups in the new taxonomy system
	if ( empty($_old_groups) ) return;
	foreach ( $_old_groups as $new_group ) {
		wp_insert_term(
			$new_group->name,
			'series_group',
			array(
				'description' => $new_group->description,
				'slug' => $new_group->slug
			)
		);
		
		//let's get the series from the old groups, add to the new taxonomy, and then remove them from the old groups.  We'll leave the old groups (categories) in case there are regular posts added to them.
		$get_series = get_series_in_group($new_group->term_id, $args_c);
		$ser_term_id = (int) $new_group->term_id;

		if ( empty($get_series) ) continue;
		foreach ( $get_series as $serial ) {
			$id = orgseries_group_id($serial);
			
			$post_arr = array(
				'ID' => $id,
				'post_status' => 'publish',
			);
			wp_update_post($post_arr);
			wp_set_object_terms($id, $ser_term_id, 'series_group', true);
		}
	}
	
	$group_ids = get_objects_in_term( $old_groups, 'category', array( hide_empty=> false));
	
	if ( empty($group_ids) ) return;
	foreach ($group_ids as $p_id) {
		wp_delete_object_term_relationships($p_id,'category');
	}
}

function orgseries_seriesgrouping_deactivate() {
	deactivate_plugins('organize-series-grouping/organize-series-grouping.php', true);
}

function orgseries_seriesgrouping_warning() {
	global $orgsergrpdomain;
	$msg = '<div id="wpp-message" class="error fade"><p>'.__('The <strong>Series Grouping</strong> addon for Organize Series requires the Organize Series plugin to be installed and activated in order to work.  Addons won\'t activate until this condition is met.', $orgsergrpdomain).'</p></div>';
	echo $msg;
}

function orgseries_seriesgrouping_register_textdomain() {
	$orgsergrpdomain = 'organize-series-grouping';
	global $orgsergrpdomain;
	$dir = basename(dirname(__FILE__)).'/lang';
	load_plugin_textdomain($orgsergrpdomain, false, $dir);
}

function orgseries_grouping_posttype() {
	global $checkpage, $_GET;
	
	$args = array(
		'description' => 'Used for associating Series with groups',
		'public' => false,
		'public_queryable' => true,
		'taxonomies' => array('category', 'series_group'),
		'rewrite' => array('slug' => 'seriesgroup')
	);
	
	register_post_type('series_grouping', $args);
	
	if ( 'edit-tags.php' == $checkpage && 'series' == $_GET['taxonomy'] ) {
		require_once(ABSPATH.'wp-admin/includes/meta-boxes.php');
		add_action('quick_edit_custom_box', 'orgseries_group_inline_edit', 9,3);

	}
	
}

function orgseries_grouping_taxonomy() {
	global $orgsergrpdomain;
	$permalink_slug = 'series_group';
	$object_type = array('series_grouping');
	$capabilities = array(
		'manage_terms' => 'manage_series',
		'edit_terms' => 'manage_series',
		'delete_terms' => 'manage_series',
		'assign_terms' => 'manage_series'
		);
	$labels = array(
		'name' => _x('Manage Series Groups', 'taxonomy general name', $orgsergrpdomain),
		'singular_name' => _x('Series Group', 'taxonomy singular name', $orgsergrpdomain),
		'search_items' => __('Search Series Groups', $orgsergrpdomain),
		'popular_items' => __('Popular Series Groups', $orgsergrpdomain),
		'all_items' => __('All Series Groups', $orgsergrpdomain),
		'edit_item' => __('Edit Series Group', $orgsergrpdomain),
		'update_item' => __('Update Series Group', $orgsergrpdomain),
		'add_new_item' => __('Add New Series Group', $orgsergrpdomain),
		'new_item_name' => __('New Series Group', $orgsergrpdomain),
		'menu_name' => __('Series Groups', $orgsergrpdomain)
		);
	$args = array(
		'update_count_callback' => 'update_series_group_count',
		'labels' => $labels,
		'rewrite' => array( 'slug' => $permalink_slug, 'with_front' => true ),
		'show_ui' => true,
		'public' => true,
		'capabilities' => $capabilities,
		'query_var' => 'series_group',
		'hierarchical' => true
		);
	register_taxonomy( 'series_group', $object_type, $args );
}

function update_series_group_count($terms, $taxonomy) {
	global $wpdb;
	if ( !is_array($terms) ) $terms = (array) $terms;
	$terms = array_map('intval', $terms);
	$taxonomy = 'series_group';
	foreach ( (array) $terms as $term) {
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term) );
		$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
	}
	
	clean_term_cache($terms, '', false);
	return true;
}


function orgseries_grouping_import_existing_series() {
	//do a check to see if there are existing series and NO existing posts in the 'series_grouping' post_type.  If this is the case then we need to do the import.  If not, then we break out.
	if ( !($is_imported = get_option('orgseries_grouping_import_completed')) ) {
			$series = get_terms( 'series', array('hide_empty'=>false, 'fields' => 'ids') );
								
			foreach ( $series as $this_series ) {
				$post_args = array(
					'post_type' => 'series_grouping',
					'to_ping' => 0,
					'post_title' => 'series_grouping_'.$this_series,
					'post_name' => 'series_grouping_'.$this_series
				);
				wp_insert_post($post_args);
			}
			add_option('orgseries_grouping_import_completed','1');

	}		
}

function orgseries_groups_admin_menu() {
	add_submenu_page( 'edit.php', 'Manage Series Groups', 'Series Groups', 'manage_series', 'edit-tags.php?taxonomy=series_group');
}

function orgseries_group_add_queryvars($qvs) {
	$qvs[] = 'series_group';
	return $qvs;
}

function orgseries_group_parsequery() {
	global $wp_query;
	if ( isset($wp_query->query_vars['series_group']) ) {
		$wp_query->is_series_group = true;
	} else {
		$wp_query->is_series_group = false;
	}
}

function orgseries_group_where($where) {
	global $wpdb, $wp_query;
	if ( $wp_query->is_series_group && is_admin() ) {
		$series_group = $wp_query->query_vars['series_group'];
		$series_array = get_series_in_group($series_group);
		$series_string = implode(",", $series);
		$where .= " AND t.term_id IN ({$series_string})";
	}
	return $where;
}

function orgseries_manage_grouping_filter_setup() {
	global $_GET, $wp_version;
	if ( !empty($_GET['ser_grp']) && is_admin() && $wp_version >= 3.1 ) {
		add_filter('get_terms_args', 'orgseries_grp_term_filter', 10, 2);
	} 
}

function orgseries_grp_term_filter($args, $taxonomies) {
	global $_GET;
	if ( in_array('series', $taxonomies) ) {
		$group_id = (int) $_GET['ser_grp'];
		$series_array = get_series_in_group($group_id);
		$args['include'] = $series_array;
	}
	return $args;
}

function orgseries_manage_grouping_columns() {
	global $wp_version;
	//hook into manage-series-groups page
	add_filter('manage_edit-series_group_columns', 'series_grouping_columns', 10);
	add_filter('manage_series_group_custom_column', 'series_grouping_columns_inside',1,3);
	add_filter('manage_edit-series_group_sortable_columns', 'series_group_sortable_columns');
	if ($wp_version >= '3.1')
		add_action('after-series-table', 'select_series_group_filter');
}

function orgseries_grouping_settings_setup() {
	add_settings_field('series_grouping_delete_settings','Series Grouping Addon Settings','series_grouping_delete_output', 'orgseries_options_page','series_automation_settings');
	register_setting('orgseries_options', 'org_series_options');
	add_filter('orgseries_options', 'series_grouping_options_validate', 10, 2);
}

function series_grouping_options_validate($newinput, $input) {
	$newinput['kill_grouping_on_delete'] = ( isset($input['kill_grouping_on_delete']) && $input['kill_grouping_on_delete'] == 1 ? 1 : 0 );
	return $newinput;
}

function series_grouping_delete_output() {
	global $orgseries, $orgsergrpdomain;
	$org_opt = $orgseries->settings;
	$org_name = 'org_series_options';
	?>
		<span style="background-color: #ff3366; padding: 5px; padding-bottom: 8px;">
			<input name="<?php echo $org_name; ?>[kill_grouping_on_delete]" id="kill_grouping_on_delete" type="checkbox" value="1" <?php checked('1', $org_opt['kill_grouping_on_delete']); ?> /> <?php _e('Delete all Organize Series GROUPING addon related data from the database when deleting the addon? (BE CAREFUL!)', $orgsergrpdomain); ?></span>
	<?php
}

function orgseries_groups_scripts() {
	global $checkpage;
	$url = WP_PLUGIN_URL.'/organize-series-grouping/js/';

	if ( 'edit-tags.php' == $checkpage && 'series' == $_GET['taxonomy'] ) {
		wp_enqueue_script( 'wp_ajax_response' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'editor' );
		wp_enqueue_script( 'postbox' );
		wp_enqueue_script( 'post' );
		wp_register_script('inline-edit-groups', $url.'series-groups.js');
		wp_enqueue_script('inline-edit-groups');
	}
}

function orgseries_groups_styles() {
	global $checkpage;
	$plugin_path = WP_PLUGIN_URL . '/organize-series-grouping/';
	$csspath = $plugin_path . 'orgseries-grouping.css';
	$csspath_min = $plugin_path.'orgseries-grouping-small.css';
	wp_register_style('orgseries_group_main_style', $csspath, array('global'), get_bloginfo('version'),'screen and (min-width: 1100px)');
	wp_register_style('orgseries_group_small_style', $csspath_min, array('global'), get_bloginfo('version'), 'screen and (max-width: 1100px)');
	wp_register_style('orgseries_group_small_on_edit', $csspath_min);
	
	if ( 'edit-tags.php' == $checkpage && 'series' == $_GET['taxonomy'] && isset($_GET['action'] ) && 'edit' == $_GET['action'] ) {
		wp_enqueue_style('orgseries_group_main_style');
		wp_enqueue_style('orgseries_group_small_style');
	}
	
	if ( 'edit-tags.php' == $checkpage && 'series' == $_GET['taxonomy'] ) {
		wp_enqueue_style('orgseries_group_small_on_edit');
	}
}

function series_grouping_columns($columns) {
	global $orgsergrpdomain;
	unset($columns['posts']);
	$columns['series'] =  __('Series', $orgsergrpdomain);
	return $columns;
}

function series_group_sortable_columns($sortable) {
	$sortable['series'] = 'count';
	return $sortable;
}

function select_series_group_filter($taxonomy) {
	//TODO: would be much better if WordPress provided a way of simply adding this in via a do_action.  But for the time being we'll add this as a hidden form after the table and use jQuery to move it to the top of the table after page load.
	if ( !empty($_GET['ser_grp']) ) $group_id = (int) $_GET['ser_grp'];
	if ( empty($group_id) ) $group_id = -1;
	$dropdown_args = array(
		'show_option_all' => 'View all Groups',
		'selected' => $group_id,
		'taxonomy' => 'series_group',
		'name' => 'ser_grp',
		'hide_empty' => false
	);
	?>
	<div style="display:none;">
		<form id="series_group_filter" style="float:right" action method="get">
			<input type="hidden" name="taxonomy" value="series" />
			<?php wp_dropdown_categories($dropdown_args); ?>
			<input type="submit" name="group_filter" id="filter-query-submit" class="button-secondary" value="Filter">
		</form>
	</div>
	<?php
}

function series_grouping_columns_inside($content, $column_name, $id) {
	global $orgsergropdomain, $wp_version;
	$column_return = $content;
	if ($column_name == 'series') {
		$get = get_series_in_group($id);
		if ( $get == '' ) $count = '0';
		else $count = count($get);
		if ( $wp_version >= '3.1' ) 
			$g_link = '<a href="edit-tags.php?taxonomy=series&ser_grp='.$id.'">'.$count.'</a>';
		else
			$g_link = $count;
		$column_return = '<p style="width: 40px; text-align: center;">'.$g_link.'</p>';
	}
	return $column_return;
}

function manage_series_grouping_columns($columns) {
	global $orgsergrpdomain, $pagenow;
	$columns['group'] = __('Group(s)', $orgsergrpdomain);
	return $columns;
}

function manage_series_grouping_columns_inside($content, $column_name, $id) {
	global $orgsergrpdomain;
	$group_id = orgseries_group_id($id);
	$column_return = $content;
	if ($column_name == 'group') {
		
		$column_return .= '<div class="group_column">';
	
		if ( $groups = wp_get_object_terms($group_id, 'series_group') ) {
			foreach ( $groups as $group ) {
				$column_return .= '<div class="series-group">'.$group->name . '</div> ';
				$cat_id[] = $group->term_id;
				$cat_name[] = $group->name;
			}
			$category_ids = implode(",",$cat_id);
			$category_names = implode(",",$cat_name);
			$column_return .= '<div class="hidden" id="inline_group_'.$id.'"><div class="group_inline_edit" id="sergroup_'.$id.'">'.$category_ids.'</div><div class="group_inline_name">'.$category_names.'</div></div>';
		} else {
			$column_return .= __('No group', $orgsergrpdomain);
			$column_return .= '<div class="hidden" id="inline_group_"><div class="group_inline_edit">0</div><div class="group_inline_name"></div></div>';
		}
		$column_return .= '</div>';
	}
	return $column_return;
}

function add_orgseries_group_fields($taxonomy) { 
	global $orgsergrpdomain;
	$empty = '';
	$empty = (object) $empty;
	$empty->ID = '';
	$box['args'] = array(
			'taxonomy' => 'series_group'
		);
	?>
	<div id="poststuff" class="metabox-holder has-right-sidebar"> 
			<div id="side-info-column" class="inner-sidebar"> 
				<div id="side-sortables" class="meta-box-sortables">
					<div id="categorydiv" class="postbox"> 
						<div class="handlediv" title="<?php _e('Click to toggle'); ?>"><br /></div><h3 class='hndle'><span><?php _e('Groups', $orgsergrpdomain); ?></span></h3> 
							<div class="inside"> 
								<?php post_categories_meta_box( $empty, $box ); ?>
							</div>
						
					</div>	
				</div>
			</div>
	</div>	
	<?php
}

function edit_orgseries_group_fields($series, $taxonomy) {
	global $orgseries;
	$series_ID = $series->term_id;
	$groupID = orgseries_group_id($series_ID);
	$post_arr = array( 'ID' => $groupID, 'post_type' => 'series_grouping' );
	$groups = wp_get_object_terms(array($groupID), 'series_group', array('fields' => 'ids'));
	
	?>
	<div id="poststuff" class="metabox-holder has-right-sidebar"> 
			<div id="side-info-column" class="inner-sidebar"> 
				<div id="side-sortables" class="meta-box-sortables">
					<div id="categorydiv" class="postbox"> 
						<div class="handlediv" title="<?php _e('Click to toggle'); ?>"><br /></div><h3 class='hndle'><span><?php _e('Groups'); ?></span></h3> 
							<div class="inside"> 
								<div id="taxonomy-category" class="categorydiv">
									<ul id="category-tabs" class="category-tabs">
										<li class="tabs"><a href="#category-all" tabindex="3">All Groups</a></li>
									</ul>
									<div id="category-all" class="tabs-panel">
										<ul id="categorychecklist" class="list:category categorychecklist form-no-clear">
										<?php wp_terms_checklist($groupID,array('selected_cats' => $groups, 'taxonomy' => 'series_group' )); ?>
										</ul>
									</div>
								</div>
							</div>
						
					</div>	
				</div>
			</div>
	</div>	
	<?php
}

function orgseries_group_inline_edit($column_name, $type, $taxonomy) {
	global $orgsergrpdomain;
	if ( empty($taxonomy) && $type != 'edit-tags' )
		$taxonomy = $type; //this takes care of WP 3.1 changes
	if ( $taxonomy == 'series' && $column_name == 'group' ) { //takes care of WP3.1 changes (prevents duplicate output)
		?>
		<fieldset class="inline-edit-col-right inline-edit-categories"><div class="inline-edit-col">
			<span class="title inline-edit-categories-label"><?php _e('Groups', $orgsergrpdomain); ?>
				<span class="catshow"><?php _e('[more]'); ?></span>
				<span class="cathide" style="display:none;"><?php _e('[less]'); ?></span>
			</span>
			<div class="inline_edit_group_">
			<input type="hidden" name="post_category[]" value="0" />
			<input type="hidden" name="series_group_id" class="series_group_id" value="" />
				<ul class="cat-checklist category-checklist">
					<?php wp_terms_checklist(null, array('taxonomy' => 'series_group'))	 ?>
				</ul>
			</div>
			</div></fieldset>
		<?php
	}
}

function wp_insert_series_group($series_id, $taxonomy_id) {
	global $_POST;
	extract($_POST, EXTR_SKIP);
	if ( !empty($tax_input['series_group']) ) 
		$terms = os_stringarray_to_intarray($tax_input['series_group']);
		
	$post_arr = array(
		'post_type' => 'series_grouping',
		'to_ping' => 0,
		'post_title' => 'series_grouping_'.$series_id,
		'post_name' => 'series_grouping_'.$series_id
	);
	$group_id = wp_insert_post($post_arr);
	if ( !empty($tax_input['series_group']) )
		wp_set_object_terms($group_id, $terms, 'series_group', true);
	return $group_id;
}

function wp_update_series_group($series_id, $taxonomy_id) {
	global $_POST;
	
	extract($_POST, EXTR_SKIP);
	
	$terms = os_stringarray_to_intarray((array) $tax_input['series_group']);
	$id = orgseries_group_id($series_id);
	wp_set_object_terms($id, $terms, 'series_group');
	return $id;
}

function wp_delete_series_group($series_id, $taxonomy_id) {
	global $_POST;
	extract($_POST, EXTR_SKIP);
	$id = orgseries_group_id($series_id);
	wp_delete_post($id,true); 
	//TODO check, do we need wp_delete_post_term_relationship here?
}

function orgseries_group_id($series_id) {
	$post_title = 'series_grouping_'.$series_id;
	$query = array('name' => $post_title, 'post_type' => 'series_grouping');
	query_posts($query);
	the_post();
	$groupid = get_the_ID();
	if ( !empty($series_id) && empty($groupid) ) {
		//looks like the series didn't get added as a custom post for some reason.  Let's fix that
		$groupid = wp_insert_series_group($series_id, '');
	}	
	wp_reset_query();
	return $groupid;
}

function orgseries_get_seriesid_from_group($group_id) {	
	$grouppost = &get_post($group_id);
	if (!$grouppost || $grouppost->post_type != 'series_grouping' ) return false;
		$series_name = $grouppost->post_name;
		$series_id = ltrim($series_name, 'series_grouping_');
		$series_id = (int) $series_id;
	return $series_id;
}

//INCLUDE TEMPLATE TAGS FILE//
require_once(WP_PLUGIN_DIR . '/organize-series-grouping/orgseries-grouping-template-tags.php');

//Automatic Upgrades stuff
if ( file_exists(WP_PLUGIN_DIR . '/organize-series/inc/pue-client.php') ) {
	//let's get the client api key for updates
	$series_settings = get_option('org_series_options');
	$api_key = $series_settings['orgseries_api'];
	$host_server_url = 'http://organizeseries.com';
	$plugin_slug = 'organize-series-grouping';
	$options = array(
		'apikey' => $api_key,
		'lang_domain' => 'organize-series'
	);
	
	require( WP_PLUGIN_DIR . '/organize-series/inc/pue-client.php' );
	$check_for_updates =  new PluginUpdateEngineChecker($host_server_url, $plugin_slug, $options);
}

//helper functions
function os_stringarray_to_intarray($array) {
	function to_int(&$val, $key) {
		$val = (int) $val;
	}
	
	array_walk($array, 'to_int');
	
	return $array;
}
//require_once(WP_PLUGIN_DIR . '/organize-series-grouping/for-testing.php');