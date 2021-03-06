<?php
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * View all bugs include file
 *
 * @package MantisBT
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses category_api.php
 * @uses columns_api.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses current_user_api.php
 * @uses event_api.php
 * @uses filter_api.php
 * @uses gpc_api.php
 * @uses helper_api.php
 * @uses html_api.php
 * @uses lang_api.php
 * @uses print_api.php
 */

if( !defined( 'VIEW_ALL_INC_ALLOW' ) ) {
	return;
}

require_api( 'category_api.php' );
require_api( 'columns_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'current_user_api.php' );
require_api( 'event_api.php' );
require_api( 'filter_api.php' );
require_api( 'gpc_api.php' );
require_api( 'helper_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );

$g_filter = current_user_get_bug_filter();
# NOTE: this check might be better placed in current_user_get_bug_filter()
if( $g_filter === false ) {
	$g_filter = filter_get_default();
}

list( $t_sort, ) = explode( ',', $g_filter['sort'] );
list( $t_dir, ) = explode( ',', $g_filter['dir'] );

$g_checkboxes_exist = false;

$t_icon_path = config_get( 'icon_path' );

# Improve performance by caching category data in one pass
if( helper_get_current_project() > 0 ) {
	category_get_all_rows( helper_get_current_project() );
} else {
	$t_categories = array();
	foreach ( $t_rows as $t_row ) {
		$t_categories[] = $t_row->category_id;
	}
	category_cache_array_rows( array_unique( $t_categories ) );
}
$g_columns = helper_get_columns_to_view( COLUMNS_TARGET_VIEW_PAGE );

$t_col_count = count( $g_columns );

$t_filter_position = config_get( 'filter_position' );

# -- ====================== FILTER FORM ========================= --
if( ( $t_filter_position & FILTER_POSITION_TOP ) == FILTER_POSITION_TOP ) {
	filter_draw_selection_area( $f_page_number );
}
# -- ====================== end of FILTER FORM ================== --


# -- ====================== BUG LIST ============================ --

$t_status_legend_position = config_get( 'status_legend_position' );

if( $t_status_legend_position == STATUS_LEGEND_POSITION_TOP || $t_status_legend_position == STATUS_LEGEND_POSITION_BOTH ) {
	html_status_legend();
}
?>
<br />
<form id="bug_action" method="get" action="bug_actiongroup_page.php">
<?php # CSRF protection not required here - form does not result in modifications ?>
<table id="buglist" class="width100" cellspacing="1">
<thead>
<tr class="buglist-nav">
	<td class="form-title" colspan="<?php echo $t_col_count; ?>">
		<span class="floatleft">
		<?php
			# -- Viewing range info --
			$v_start = 0;
			$v_end   = 0;

			if( count( $t_rows ) > 0 ) {
				$v_start = $g_filter['per_page'] * ($f_page_number - 1) + 1;
				$v_end = $v_start + count( $t_rows ) - 1;
			}

			echo lang_get( 'viewing_bugs_title' );
			echo ' (' . $v_start . ' - ' . $v_end . ' / ' . $t_bug_count . ')';
		?> </span>

		<span class="floatleft small">
		<?php
			# -- Print and Export links --
			echo '&#160;';
			print_bracket_link( 'print_all_bug_page.php', lang_get( 'print_all_bug_page_link' ) );
			echo '&#160;';
			print_bracket_link( 'csv_export.php', lang_get( 'csv_export' ) );
			echo '&#160;';
			print_bracket_link( 'excel_xml_export.php', lang_get( 'excel_export' ) );

			$t_event_menu_options = $t_links = event_signal( 'EVENT_MENU_FILTER' );

			foreach ( $t_event_menu_options as $t_plugin => $t_plugin_menu_options ) {
				foreach ( $t_plugin_menu_options as $t_callback => $t_callback_menu_options ) {
					if( !is_array( $t_callback_menu_options ) ) {
						$t_callback_menu_options = array( $t_callback_menu_options );
					}

					foreach ( $t_callback_menu_options as $t_menu_option ) {
						if( $t_menu_option ) {
							print_bracket_link_prepared( $t_menu_option );
						}
					}
				}
			}
		?> </span>

		<span class="floatright small"><?php
			# -- Page number links --
			$f_filter	= gpc_get_int( 'filter', 0 );
			print_page_links( 'view_all_bug_page.php', 1, $t_page_count, (int)$f_page_number, $f_filter );
		?> </span>
	</td>
</tr>
<?php # -- Bug list column header row -- ?>
<tr class="buglist-headers row-category">
<?php
	$t_title_function = 'print_column_title';
	foreach( $g_columns as $t_column ) {
		helper_call_custom_function( $t_title_function, array( $t_column ) );
	}
?>
</tr>

<?php # -- Spacer row -- ?>
<tr class="spacer">
	<td colspan="<?php echo $t_col_count; ?>"></td>
</tr>
</thead><tbody>

<?php
/**
 * Output Bug Rows
 *
 * @param array $p_rows An array of bug objects.
 * @return void
 */
function write_bug_rows( array $p_rows ) {
	global $g_columns, $g_filter;

	$t_in_stickies = ( $g_filter && ( 'on' == $g_filter[FILTER_PROPERTY_STICKY] ) );

	# pre-cache custom column data
	columns_plugin_cache_issue_data( $p_rows );

	# -- Loop over bug rows --

	$t_rows = count( $p_rows );
	for( $i=0; $i < $t_rows; $i++ ) {
		$t_row = $p_rows[$i];

		if( ( 0 == $t_row->sticky ) && ( 0 == $i ) ) {
			$t_in_stickies = false;
		}
		if( ( 0 == $t_row->sticky ) && $t_in_stickies ) {	# demarcate stickies, if any have been shown
?>
		   <tr>
				   <td class="left sticky-header" colspan="<?php echo count( $g_columns ); ?>">&#160;</td>
		   </tr>
<?php
			$t_in_stickies = false;
		}

		# choose color based on status
		$t_status_label = html_get_status_css_class( $t_row->status, auth_get_current_user_id(), $t_row->project_id );

		echo '<tr class="' . $t_status_label . '">';

		$t_column_value_function = 'print_column_value';
		foreach( $g_columns as $t_column ) {
			helper_call_custom_function( $t_column_value_function, array( $t_column, $t_row ) );
		}

		echo '</tr>';
	}
}


write_bug_rows( $t_rows );
# -- ====================== end of BUG LIST ========================= --

# -- ====================== MASS BUG MANIPULATION =================== --
# @@@ ideally buglist-footer would be in <tfoot>, but that's not possible due to global g_checkboxes_exist set via write_bug_rows()
?>
	<tr class="buglist-footer">
		<td class="left" colspan="<?php echo $t_col_count; ?>">
			<span class="floatleft">
<?php
		if( $g_checkboxes_exist ) {
			echo '<input type="checkbox" id="bug_arr_all" name="bug_arr_all" value="all" class="check_all" />';
			echo '<label for="bug_arr_all">' . lang_get( 'select_all' ) . '</label>';
		}

		if( $g_checkboxes_exist ) {
?>
			<select name="action">
				<?php print_all_bug_action_option_list( $t_unique_project_ids ) ?>
			</select>
			<input type="submit" class="button" value="<?php echo lang_get( 'ok' ); ?>" />
<?php
		} else {
			echo '&#160;';
		}
?>			</span>
			<span class="floatright small">
				<?php
					$f_filter	= gpc_get_int( 'filter', 0 );
					print_page_links( 'view_all_bug_page.php', 1, $t_page_count, (int)$f_page_number, $f_filter );
				?>
			</span>
		</td>
	</tr>
<?php # -- ====================== end of MASS BUG MANIPULATION ========================= -- ?>
</tbody>
</table>
</form>

<?php

if( $t_status_legend_position == STATUS_LEGEND_POSITION_BOTTOM || $t_status_legend_position == STATUS_LEGEND_POSITION_BOTH ) {
	html_status_legend();
}

# -- ====================== FILTER FORM ========================= --
if( ( $t_filter_position & FILTER_POSITION_BOTTOM ) == FILTER_POSITION_BOTTOM ) {
	filter_draw_selection_area( $f_page_number );
}
# -- ====================== end of FILTER FORM ================== --
