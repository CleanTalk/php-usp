<?php

namespace Cleantalk\USP\Layout;

use Cleantalk\USP\Common\State;
use Cleantalk\USP\DB;
use Cleantalk\USP\Variables\Post;

class ListTable
{
    static $NUMBER_ELEMENTS_TO_VIEW = 20;

	public $args = array(); // Input arguments
	
	public $id = ''; // Table id	
	
	// SQL query params
	public $sql = array(
		'add_col'            => array(), // Additional cols to select
		'except_cols'        => array(), // Cols to except from query
		'table'              => '',      // Table name
		'where'              => '',      // Where clause
		'order_by'           => '',      // Order By
		'order_by_direction' => '',      // Desc / Asc
		'group_by'           => '',
		'offset'             => 0,       // Limit from
		'limit'              => 20,      // Limit till
		'get_array'          => false,   // Give an array on output
	);
	public $rows = array(); // SQL Result
	
	// Pagination params
	public $pagination = array();
	
	// Callbacks
	public $func_data_prepare = null; // Function to process items before output
	public $func_data_get     = null; // Function to receive data
	public $func_data_total   = null; // Function to get total items
	
	// ROWS
	public $items = array(); // Items to output
	public $items_count = 0; // Amount to output
	public $items_total = 0; // Amount total
	
	// COLS
	public $columns = array();
	
	// Misc
	public $sortable = array();     // Sortable columns
	public $order_by = array();     // Current sorting
	public $actions = array();      // Row actions
	public $bulk_actions = array(); // Bulk actions
	
	// HTML output
	public $html_before    = ''; // HTML before table
	public $html_after     = ''; // HTML after table
	public $if_empty_items = 'No data given.'; // HTML message
	
	/**
     * DB Handler
     *
	 * @var DB|\PDO|mixed
	 */
	private $db;
	
	function __construct($db, $args = array()){
	    
        $this->db = $db;
	    
		$this->args = json_encode($args);
		
		$this->id      = !empty($args['id'])      ? $args['id']      : 'table_' . rand(0, 10000);
		
		$this->items   = !empty($args['items'])   ? $args['items']   : $this->items;
		$this->columns = !empty($args['columns']) ? $args['columns'] : $this->columns;
		
		$this->items_count = count((array)$this->items);
		$this->items_total = count((array)$this->items);
		
		// HTML output
		$this->html_before = !empty($args['html_before']) ? $args['html_before'] : $this->html_before;
		$this->html_after  = !empty($args['html_after'])  ? $args['html_after']  : $this->html_after;
		$this->if_empty_items = !empty($args['if_empty_items']) ? $args['if_empty_items'] : $this->if_empty_items;
		
		if(isset($args['pagination']['page']))    $this->pagination['page']     = $args['pagination']['page'];
		if(isset($args['pagination']['per_page']))$this->pagination['per_page'] = $args['pagination']['per_page'];
		
		// SQL shit
		
			if(!empty($args['sql'])){
				$this->sql['add_col']     = !empty($args['sql']['add_col'])     ? $args['sql']['add_col']     : $this->sql['add_col'];
				$this->sql['except_cols'] = !empty($args['sql']['except_cols']) ? $args['sql']['except_cols'] : $this->sql['except_cols'];
				$this->sql['table']       = !empty($args['sql']['table'])       ? $args['sql']['table']       : $this->sql['table'];
				$this->sql['where']       = !empty($args['sql']['where'])       ? $args['sql']['where']       : $this->sql['where'];
				$this->sql['group_by']    = !empty($args['sql']['group_by'])    ? $args['sql']['group_by']    : $this->sql['group_by'];
				$this->sql['offset']      = !empty($args['sql']['offset'])      ? $args['sql']['offset']      : $this->sql['offset'];
				$this->sql['limit']       = !empty($args['sql']['limit'])       ? $args['sql']['limit']       : $this->sql['limit'];
				$this->sql['get_array']   = !empty($args['sql']['get_array'])   ? $args['sql']['get_array']   : $this->sql['get_array'];
			}
			
			$this->sql['offset'] = isset($this->pagination['page']) ? ($this->pagination['page']-1)*$this->pagination['per_page'] : $this->sql['offset'];
			$this->sql['limit']  = isset($this->pagination['per_page']) ? $this->pagination['per_page'] : $this->sql['limit'];
			
			$this->func_data_prepare = !empty($args['func_data_prepare']) ? $args['func_data_prepare'] : $this->func_data_prepare;
			$this->func_data_get     = !empty($args['func_data_get'])     ? $args['func_data_get']     : $this->func_data_get;
			$this->func_data_total   = !empty($args['func_data_total'])   ? $args['func_data_total']   : $this->func_data_total;
			
			$this->columns_names  = array_keys($this->columns);
			$this->columns_amount = count($this->columns);
			
			if(in_array('cb', $this->columns_names)) $this->columns_names = array_slice($this->columns_names, 1);
			if(!empty($this->sql['add_col']))        $this->columns_names = array_merge($this->columns_names, $this->sql['add_col']);
			if(!empty($this->sql['except_cols']))     $this->columns_names = array_diff( $this->columns_names, $this->sql['except_cols']);
			
			$this->sortable = !empty($args['sortable']) ? $args['sortable'] : $this->sortable;
			$this->order_by = !empty($args['order_by']) ? $args['order_by'] : $this->order_by;
			
		// END OF SQL shit
		
		$this->actions      = !empty($args['actions'])      ? $args['actions']      : $this->actions;
		$this->bulk_actions = !empty($args['bulk_actions']) ? $args['bulk_actions'] : $this->bulk_actions;
		
	}

	public function get_data(){

		// Getting total of items
		// by using given function
		if($this->func_data_total && function_exists($this->func_data_total)){
			$this->items_total = call_user_func_array($this->func_data_total, array());
		// by using direct SQL request
		}else{
			$total = $this->db->fetch_all(
				sprintf(
					'SELECT COUNT(*) as cnt'
						.' FROM %s'
						.'%s',
					$this->sql['table'], // TABLE
					$this->sql['where']  // WHERE
				),
				'obj'
			);
			$this->items_total = $total[0]->cnt;
		}
		
		// Getting data
		// by using given function
		if($this->func_data_get && function_exists($this->func_data_get)){
			$param = array($this->sql['offset'], $this->sql['limit']);
			if($this->order_by) $param[] = current($this->order_by);
			if($this->order_by) $param[] = key($this->order_by);
			$this->rows = call_user_func_array($this->func_data_get, $param);
		// by using direct SQL request
		}else{
		    $columns = array();
		    foreach ( $this->columns_names as $columns_name ) {
			    $columns[] = $this->sql['table'] . '.' . $columns_name;
            }
			$this->rows = $this->db->fetch_all(
				sprintf(
					'SELECT %s'
						.' FROM %s'
						.'%s'
						.' ORDER BY %s %s'
						.' LIMIT %s%d',
					implode(', ', $columns),                  // COLUMNS
					$this->sql['table'],                            // TABLE
					$this->sql['where'],                            // WHERE
					key($this->order_by), current($this->order_by), // ORDER BY
					$this->sql['offset'].',', $this->sql['limit']   // LIMIT	
				),
				$this->sql['get_array'] === true ? 'array' : 'obj'
			);
		}
		
		// Adding actions to each row 
		foreach($this->rows as &$row){
			if(is_object($row)) $row->actions   = array_flip(array_keys($this->actions));
			if(is_array($row))  $row['actions'] = array_flip(array_keys($this->actions));
		} unset($row);

		$this->items_count = count((array)$this->rows);
		
		// Execute given function to prepare data
		if($this->func_data_prepare && function_exists($this->func_data_prepare))
			call_user_func_array($this->func_data_prepare, array(&$this)); // Changing $this in function
		else{
			$this->preapre_data__default();
		}
		
		return $this;
		
	}
	
	private function preapre_data__default(){
		if($this->items_count){
			foreach($this->rows as $key => $row){
				foreach($this->columns as $column_name => $column){
					$this->items[$key][$column_name] = $row->$column_name;
					if(isset($column['primary']))
						$this->items[$key]['uid'] = $row->$column_name;
					if(!empty($this->actions))
						$this->items[$key]['actions'] = $this->actions;
				}
			}
		}
	}
	
	public function display()
	{
		if($this->items_count == 0){
			echo $this->if_empty_items;
			return;
		}
		
		echo '<form method="post" id="'.$this->id.'" class="tbl-root">';
			
			echo $this->html_before;
			
			echo "<script>/*<![CDATA[*/var args_{$this->id} = {$this->args};/*]]>*/</script>";
			
			$this->display__bulk_action_controls();
			$this->display__pagination_controls();
			
			?>
				<table class="tbl">
					<thead>
					<tr>
						<?php $this->display__column_headers(); ?>
					</tr>
					</thead>

					<tbody>
						<?php $this->display__rows(); ?>
					</tbody>

					<tfoot>
					<tr>
						<?php $this->display__column_headers(); ?>
					</tr>
					</tfoot>

				</table>
			<?php
		
		echo $this->html_after;
		
		$this->display__bulk_action_controls();
		$this->display__pagination_controls();
		
		echo '</form>';
		
	}
	
	public function display__bulk_action_controls()
	{
		if(!empty($this->bulk_actions)){
			echo '<div class="tbl-bulk_actions--wrapper">';
				echo '<select class="tbl-select">';
				foreach($this->bulk_actions as $action_key => $action){
					echo "<option value='{$action_key}'>{$action['name']}</option>";
				}
				echo '</select>';
				echo '<button type="button" name="bulk_perform" class="tbl-button tbl-bulk_actions--apply">'.__('Apply').'</button>';
				echo '<img class="tbl-preloader--small" src="' . CT_USP_URI . '/img/preloader_tiny.gif" />';
			echo '</div>';
		}
	}
	
	public function display__pagination_controls()
	{
		if(!empty($this->pagination) && $this->items_total > $this->pagination['per_page']){
			$next_page = $this->pagination['page']+1>ceil($this->items_total / $this->pagination['per_page']) ? $this->pagination['page']: $this->pagination['page']+1;
			echo '<div class="tbl-pagination--wrapper"
				start_page="1"
				prev_page="' . ($this->pagination['page']-1?$this->pagination['page']-1:1) . '"
				next_page="' . $next_page . '"
				last_page="' . ceil($this->items_total / $this->pagination['per_page']) . '"
			>';
				echo "<span class='tbl-pagination--total'>{$this->items_total} Entries</span>";
				echo '<button type="button" class="tbl-button tbl-pagination--button tbl-pagination--start"><i class="icon-to-start"></i></button>';
				echo '<button type="button" class="tbl-button tbl-pagination--button tbl-pagination--prev"><i class="icon-fast-bw"></i></button>';
				echo "<input type='text' class='tbl-pagination--curr_page' value='{$this->pagination['page']}'/>";
				echo '<span class="tbl-pagination--total"> of '.ceil($this->items_total / $this->pagination['per_page']).'</span>';
				echo '<button type="button" class="tbl-button tbl-pagination--button tbl-pagination--go">'.__('Go').'</button>';
				echo '<button type="button" class="tbl-button tbl-pagination--button tbl-pagination--next"><i class="icon-fast-fw"></i></button>';
				echo '<button type="button" class="tbl-button tbl-pagination--button tbl-pagination--end"><i class="icon-to-end"></i></button>';
				echo '<img class="tbl-preloader--small" src="' . CT_USP_URI . '/img/preloader_tiny.gif" />';
			echo '</div>';
		}
	}
	
	public function display__column_headers(){
		
		foreach($this->columns as $column_key => $column){
			
			$tag = ( 'cb' === $column_key ) ? 'td' : 'th';
			
			$id = $column_key;
			
			$classes  = "tbl-head_column column-$column_key";
			$classes .= isset($column['primary']) ? ' column-primary' : '';
			$classes .= isset($column['class'])   ? ' '.$column['class'] : '';
			
			// Sorting
			if(in_array($column_key, $this->sortable)){
				
				$classes .= ' tbl-column-sortable';
				$classes .= isset($this->order_by[$column_key]) ? ' tbl-column-sorted' : '';
				
				$sort_direction = isset($this->order_by[$column_key]) && $this->order_by[$column_key] == 'asc' ? 'desc' :  'asc';
				$sort_direction_attr = 'sort_direction="'.$sort_direction.'"';
				
				$sort_classes = 'tbl-sorting_indicator';
				$sort_classes .=  isset($this->order_by[$column_key]) ? ' tbl-sorting_indicator--sorted' : '';
				$sort_classes .=  isset($this->order_by[$column_key]) ? ' icon-sort-alt-'.($sort_direction == 'desc' ? 'up' : 'down') : ' icon-sort-alt-down';
				
				$sortable = "<i class='$sort_classes'></i>";
				
			}else{
				$sortable = '';
				$sort_direction_attr = '';
			}
			
			$hint = isset($column['hint']) ? '<i class="spbc_hint--icon icon-help-circled"></i><span class="spbc_hint--text">'.$column['hint'].'</span>' : '';
			
			// Out
			echo "<$tag id='$id' class='$classes' $sort_direction_attr>{$column['heading']}$sortable$hint</$tag>";
			
		} unset($column_key, $column_name);
		
	}
	
	public function display__rows($return = false)
	{
		$out = '';
		
		foreach($this->items as $item){
			
			$item = (array)$item;
			
			$out .= '<tr>';
			
			foreach($this->columns as $column_key => $column){
								
				$classes  = "$column_key column-$column_key";
				$classes .= isset($column['primary']) ? ' column-primary'    : '';
				$classes .= isset($column['class'])   ? ' '.$column['class'] : '';
				
				if ( 'cb' === $column_key ) {
					$out .= '<th scope="row" class="check-column">';
					$out .= $this->display__column_cb($item['cb']);
					$out .= '</th>';
				}elseif ( method_exists( $this, 'display__column_' . $column['heading'] ) ) {
					$out .= call_user_func(
						array( $this, '_column_' . $column['heading'] ),
						$item,
						$classes
					);
				}else{
					$out .= "<td class='$classes'>";
					
						$out .= isset($item[$column_key]) ? $item[$column_key] : 'Unknown';
						
						if(isset($column['primary']) && !empty($this->actions) && !empty($item['uid']))
							$out .= $this->display__row_actions($item['uid'], $item);

						
					$out .= '</td>';
				}
				
			} unset($column_key, $column['heading']);
			
			$out .= '</tr>';
			
		} unset($item);
		
		if($return) return $out; else echo $out; 
	}
	
	function display__column_cb($id)
	{
		return  '<input type="checkbox" name="item[]" class="cb-select" id="cb-select-'. $id .'" value="'. $id .'" />';
	}

	public function display__row_actions($uid, $item)
	{
		$home_url = '';
		$out = "<div class='row-actions' uid='{$uid}' cols_amount='{$this->columns_amount}'>";
			foreach($this->actions as $action_key => $action){
				if(!isset($item['actions'][$action_key])) continue;
				if(isset($action['type']) && $action['type'] == 'link'){
					$href   = !empty($action['local'])  ? $home_url.$action['href'] : $action['href'];
					$href  .= !empty($action['uid'])    ? $uid                      : '';
					$target  = !empty($action['target']) ? $action['target']         : '_self';
					$out   .= "<a href='$href' target='$target'>{$action['name']}</a> | ";
				}else{
					$classes = "tbl-row_action tbl-row_action--{$action_key}" . (!isset($action['handler']) ? ' tbl-row_action--ajax' : '');
					$handler = isset($action['handler']) ? " onclick='{$action['handler']}'" : " row-action='{$action_key}'";
					$out .= "<span class='$classes' $handler>{$action['name']}</span> | ";
				}
			}
			$out = isset($classes) ? substr($out, 0, -3) : $out;
		$out .= '</div>';
		$out .= '<img class="tbl-preloader--tiny" src="' . CT_USP_URI . '/img/preloader_tiny.gif" />';
		return $out;
	}
	
	public static function ajax__row_action_handler(){

		require_once CT_USP_INC . 'scanner.php';

		// Executing given function
		if(!empty($_POST['function'])){
			
			if(!empty($_POST['class']) && class_exists($_POST['class']) && method_exists($_POST['class'], $_POST['function']))
				$function_to_call = $_POST['class'].'::'.$_POST['function'];
			
			if(function_exists($_POST['function']))
				$function_to_call = $_POST['function'];
			
			if(!empty($function_to_call))
				call_user_func($function_to_call);
		
		// Executing predefined table action
		}else{
			$_POST['add_action'] = isset($_POST['add_action']) ? $_POST['add_action'] : 'unknown';
			$colspan = isset($_POST['cols']) ? "colspan='{$_POST['cols']}'" : '';
			switch($_POST['add_action']){
				case 'delete':     self::ajax__row_action_handler___delete();                        break;
				case 'send':       self::ajax__row_action_handler___send();                          break;
				default: die(json_encode(array('temp_html' => "<td $colspan>UNKNOWN ACTION</td>"))); break;
			}
		}
	}

	public static function ajax__row_action_handler___delete()
	{
		$result = spbc_scanner_file_delete( $_POST['id'] );
		if(empty($result['error'])){
			$colspan = isset($_POST['cols']) ? "colspan='{$_POST['cols']}'" : '';
			$out = array(
				'html' => "<td $colspan>File deleted</td>",
				'success' => true,
				'color' => 'black',
				'background' => 'rgba(240, 110, 110, 0.7)',
			);
		}else
			$out = $result;
		die(json_encode($out));
	}

	public static function ajax__row_action_handler___send()
	{
		$result = spbc_scanner_file_send( $_POST['id'] );
		if(empty($result['error'])){
			$colspan = isset($_POST['cols']) ? "colspan='{$_POST['cols']}'" : '';
			$out = array(
				'temp_html' => "<td $colspan>"
                    . sprintf(
                           __('We will check the file(s) and let you know results by email to %s', 'security-malware-firewall'),
                           \Cleantalk\USP\Common\State::getInstance()->data->email
                    )
                    . '</td>',
				'success' => true,
				'color' => 'black',
				'background' => 'rgba(110, 110, 240, 0.7)',
			);
		}else
			$out = $result;
		die(json_encode($out));
	}

	public static function ajax__pagination_handler()
	{
	    require_once CT_USP_INC . 'scanner.php';

	    $usp = State::getInstance();
	    
		$page = intval($_POST['page']);
		$args = self::stripslashes__array( Post::get( 'args' ) );
		$args['pagination']['page'] = $page;
		$table = new ListTable(
            DB::getInstance(
                $usp->data->db_request_string,
                $usp->data->db_user,
                $usp->data->db_password
            ),
            $args
        );
		$table->get_data();
		$table->display();
		die();
	}
	
	public static function stripslashes__array($arr)
	{
		foreach($arr as $key => &$value){
			if(is_string($value))
				$value = stripslashes($value);
			elseif(is_array($value))
				$value = self::stripslashes__array($value);
			else
				continue;
		} unset($key, $value);
		return $arr;
	}
}
