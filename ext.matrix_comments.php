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
	var $version        = '1.0.0';
	var $description    = 'Enables per-Matrix-row commenting';
	var $settings_exist = 'n';
	var $docs_url       = 'http://github.com/brandonkelly/matrix_comments';

	var $column_name    = 'matrix_row_id';
	var $table_name     = 'comments';

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
	private function _column_exists()
	{
		return $this->EE->db->field_exists($this->column_name, $this->table_name);
	}

	// --------------------------------------------------------------------

	/**
	 * Activate Extension
	 */
	function activate_extension()
	{
        // no need to add our custom column if it already exists.
		if (! $this->_column_exists())
		{
			$this->EE->load->dbforge();

			$this->EE->dbforge->add_column(
				$this->table_name,
				array(
					$this->column_name => array(
						'type' => 'INT',
						'constraint' => 10,
						'unsigned' => TRUE,
						'null' => TRUE)
				)
			);
		}

		// add the rows to exp_extensions

		$this->EE->db->insert('extensions', array(
			'class'    => 'Matrix_comments_ext',
			'method'   => 'insert_comment_insert_array',
			'hook'     => 'insert_comment_insert_array',
			'settings' => '',
			'priority' => 10,
			'version'  => $this->version,
			'enabled'  => 'y'
		));

        $this->EE->db->insert('extensions', array(
			'class'    => 'Matrix_comments_ext',
			'method'   => 'comment_entries_query',
			'hook'     => 'comment_entries_query',
			'settings' => '',
			'priority' => 10,
			'version'  => $this->version,
			'enabled'  => 'y'
		));
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
		if ($this->_column_exists())
		{
			$this->EE->load->dbforge();
			$this->EE->dbforge->drop_column($this->table_name, $this->column_name);
		}

		// Remove all Matrix_comments_ext rows from exp_extensions
		$this->EE->db->where('class', 'Matrix_comments_ext')
		             ->delete('exp_extensions');
	}

	// --------------------------------------------------------------------

	/**
	 * comment_entries_query ext hook
	 */
	function comment_entries_query()
	{
		$row_id = $this->EE->TMPL->fetch_param($this->column_name);

		if ($row_id)
		{
			if ($row_id == 'IS_EMPTY')
			{
				$this->EE->db->where('`'.$this->column_name.'', NULL, FALSE);
			}
			else
			{
				$this->EE->db->where($this->column_name, $row_id);
			}
		}
	}

	/**
	 * insert_comment_insert_array ext hook
	 */
	function insert_comment_insert_array($data)
	{
		// If another extension shares the same hook,
		// we need to get the latest and greatest config
		if ($this->EE->extensions->last_call !== FALSE)
		{
			$data = $this->EE->extensions->last_call;
		}

		$row_id = $this->EE->input->post($this->column_name);

		if ($row_id)
		{
			$data[$this->column_name] = $row_id;
		}

		return $data;
	}

}