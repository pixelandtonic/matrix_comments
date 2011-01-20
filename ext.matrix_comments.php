<?php if (! defined('APP_VER')) exit('No direct script access allowed');


/**
 * Matrix Comments extension for EE2
 *
 * @author    Brad Bell <brad@pixelandtonic.com>
 * @copyright Copyright (c) 2011 Pixel & Tonic
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */

class Matrix_comments_ext {

	var $name           = 'Matrix Comments';
	var $version        = '1.0.1';
	var $description    = 'Enables per-Matrix-row commenting';
	var $settings_exist = 'n';
	var $docs_url       = 'http://github.com/brandonkelly/matrix_comments';

	var $db_column_name                     = 'matrix_row_id';
	var $template_key_name                  = 'matrix_row_id';
	var $comments_table_name                = 'comments';
	var $comment_subscriptions_table_name   = 'comment_subscriptions';

	/**
	 * Class Constructor
	 */
	function __construct()
	{
		// Make a local reference to the ExpressionEngine super object
		$this->EE =& get_instance();
	}

	// --------------------------------------------------------------------

	/**
	 * Column Exists?
	 */
	private function _column_exists($column, $table)
	{
		return $this->EE->db->field_exists($column, $table);
	}

	/**
	 *
	 */
	private function _get_rowid()
	{
		$row_id = NULL;
		if(isset($this->EE->TMPL))
		{
			$row_id = $this->EE->TMPL->fetch_param($this->template_key_name);
		}

		if($row_id !== TRUE)
		{
			$row_id = $this->EE->input->post($this->template_key_name);

			if(! $row_id)
			{
				$row_id = $this->_get_rowid_from_querystring();
			}
		}

		return $row_id;
	}

	/**
	 * Get row_id from querystring
	 */
	private function _get_rowid_from_querystring()
	{
		$qstring = $this->EE->uri->query_string;

		if (preg_match("#(^|/)P(\d+)(/|$)#", $qstring, $match))
		{
			$qstring = trim($this->EE->functions->remove_double_slashes(str_replace($match['0'], '/', $qstring)), '/');
		}

		// Figure out the right row ID
		$row_seg = trim($qstring);

		$row_seg = preg_replace("#.+/#", "", $row_seg);

		if (is_numeric($row_seg))
		{
			$row_id = $row_seg;
		}
		else
		{
			$row_id = '';
		}

		return $row_id;
	}

	private function _add_ext_hook_to_db($method_name, $hook_name)
	{
		$this->EE->db->insert('extensions', array(
			'class'    => 'Matrix_comments_ext',
			'method'   => $method_name,
			'hook'     => $hook_name,
			'settings' => '',
			'priority' => 10,
			'version'  => $this->version,
			'enabled'  => 'y'
		));
	}

	private function _add_db_column($column_name, $table_name)
	{
		// no need to add our custom column if it already exists.
		if (! $this->_column_exists($column_name, $table_name))
		{
			$this->EE->load->dbforge();

			$this->EE->dbforge->add_column(
				$table_name,
				array(
					$column_name => array(
						'type' => 'INT',
						'constraint' => 10,
						'unsigned' => TRUE,
						'null' => TRUE)
				)
			);
		}
	}

	private function _drop_db_column($column_name, $table_name)
	{
		if ($this->_column_exists($column_name, $table_name))
		{
			$this->EE->load->dbforge();
			$this->EE->dbforge->drop_column($table_name, $column_name);
		}

	}

	// --------------------------------------------------------------------

	/**
	 * Activate Extension
	 */
	function activate_extension()
	{
		$this->_add_db_column($this->db_column_name, $this->comments_table_name);
		$this->_add_db_column($this->db_column_name, $this->comment_subscriptions_table_name);

		$ext_hook_data = array(
								array(
									'method' => 'comment_entries_insert',
									'hook' => 'comment_entries_insert'
								),
								array(
									'method' => 'comment_entries_query',
									'hook' => 'comment_entries_query'
								),
								array(
									'method' => 'comment_subscription_insert',
									'hook' => 'comment_subscription_insert'
								),
								array(
									'method' => 'comment_subscription_issubscribed_query',
									'hook' => 'comment_subscription_issubscribed_query'
								),
								array(
									'method' =>'comment_subscription_query',
									'hook' => 'comment_subscription_query'
								),
								array(
									'method' => 'comment_subscription_notificationsent_update',
									'hook' => 'comment_subscription_notificationsent_update'
								)
							);
		// add necessary rows to exp_extensions
		foreach($ext_hook_data as $row)
		{
			$this->_add_ext_hook_to_db($row['method'], $row['hook']);
		}
	}

	/**
	 * Update Extension
	 */
	function update_extension($current = '')
	{
		// Nothing to change...
		return FALSE;
	}

	/**
	 * Disable Extension
	 */
	function disable_extension()
	{
		$this->_drop_db_column($this->db_column_name, $this->comments_table_name);
		$this->_drop_db_column($this->db_column_name, $this->comment_subscriptions_table_name);

		// Remove all Matrix_comments_ext rows from exp_extensions
		$this->EE->db->where('class', 'Matrix_comments_ext')
		             ->delete('exp_extensions');
	}

	// --------------------------------------------------------------------

	/*
	 * Multiple hooks use this same logic
	 */
	function apply_rowid_logic_to_query()
	{
		$row_id = $this->_get_rowid();

		// was it actually set to anything?
		if ($row_id)
		{
			// only show comments for that row
			$this->EE->db->where($this->db_column_name, $row_id);
		}
		else
		{
			// only grab comments that aren't for a Matrix row
			$this->EE->db->where("`{$this->db_column_name}` IS NULL", NULL, FALSE);
		}
	}

	/**
	* comment_entries_query ext hook
	*/
	function comment_entries_query()
	{
		$this->apply_rowid_logic_to_query();
	}

	/**
	* comment_subscription_issubscribed_query ext hook
	*/
	function comment_subscription_issubscribed_query()
	{
		$this->apply_rowid_logic_to_query();
	}

	/**
	* comment_subscription_query ext hook
	*/
	function comment_subscription_query()
	{
		$this->apply_rowid_logic_to_query();
	}

	/**
	* comment_subscription_notificationsent_update ext hook
	*/
	function comment_subscription_notificationsent_update()
	{
		$this->apply_rowid_logic_to_query();
	}

	/**
	 * insert_comment_insert_array ext hook
	 */
	function comment_entries_insert($data)
	{
		// If another extension shares the same hook,
		// we need to get the latest and greatest config
		if ($this->EE->extensions->last_call !== FALSE)
		{
			$data = $this->EE->extensions->last_call;
		}

		$row_id = $this->_get_rowid();

		if ($row_id)
		{
			$data[$this->db_column_name] = $row_id;
		}

		return $data;
	}

	/**
	 * comment_subscription_insert ext hook
	 */
	function comment_subscription_insert($data)
	{
		// If another extension shares the same hook,
		// we need to get the latest and greatest config
		if ($this->EE->extensions->last_call !== FALSE)
		{
			$data = $this->EE->extensions->last_call;
		}

		$row_id = $this->_get_rowid();

		// did they pass a matrix_row_id= param?
		if ($row_id !== FALSE)
		{
			// was it actually set to anything?
			if ($row_id)
			{
				$data[0][$this->db_column_name] = $row_id;
			}
		}

		return $data;
	}

}