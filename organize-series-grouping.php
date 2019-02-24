<?php
/*
Plugin Name: Organize Series Grouping
Description: This addon gives you the ability to group series together by category.  It modifies the ui for the "Manage Series" page to add series to various categories (you can add to more than one category).  It also provides various template tags for getting (and outputting) the series data from the database within a certain group.
Version: 2.2.6.rc.001
Author: Darren Ethier
Author URI: http://organizeseries.com
*/

$orgseries_groups_ver = '2.2.6.rc.001';
global $orgseries_groups_ver;
require __DIR__ . '/vendor/autoload.php';

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
$os_grouping_path = plugin_dir_path(__FILE__);
define('OS_GROUPING_VERSION', $orgseries_groups_ver);

/**
 * This takes allows OS core to take care of the PHP version check
 * and also ensures we're only using the new style of bootstrapping if the verison of OS core with it is active.
 */
add_action('AHOS__bootstrapped', function($os_grouping_path) {
    require $os_grouping_path . 'bootstrap.php';
});

//fallback on loading legacy-includes.php in case the bootstrapped stuff isn't ready yet.
if (! defined('OS_GROUPING_LEGACY_LOADED')) {
    require_once $os_grouping_path . 'legacy-includes.php';
}