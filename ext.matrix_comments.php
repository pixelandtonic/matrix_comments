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
	var $description    = 'Enables per-Matrix-row commenting with EE’s Comment module';
	var $settings_exist = 'n';
	var $docs_url       = 'http://github.com/brandonkelly/matrix_comments';

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
	 * Activate Extension
	 */
	function activate_extension()
	{
		// -------------------------------------------
		//  Add the matrix_row_id column to exp_comments
		// -------------------------------------------

		if (! $this->EE->db->field_exists('matrix_row_id', 'comments'))
		{
			$this->EE->load->dbforge();

			$this->EE->dbforge->add_column('comments', array(
				'matrix_row_id' => array(
					'type' => 'INT',
					'constraint' => 10,
					'unsigned' => TRUE,
					'null' => TRUE
				)
			));
		}

		// -------------------------------------------
		//  Add the extension hooks
		// -------------------------------------------

		$hooks = array(
			'comment_entries_query',
			'insert_comment_insert_array'
		);

		foreach($hooks as $hook)
		{
			$this->EE->db->insert('extensions', array(
				'class'    => get_class($this),
				'method'   => $hook,
				'hook'     => $hook,
				'settings' => '',
				'priority' => 10,
				'version'  => $this->version,
				'enabled'  => 'y'
			));
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
		// -------------------------------------------
		//  Drop the matrix_row_id column from exp_comments
		// -------------------------------------------

		if ($this->EE->db->field_exists('matrix_row_id', 'comments'))
		{
			$this->EE->load->dbforge();
			$this->EE->dbforge->drop_column('comments', 'matrix_row_id');
		}

		// -------------------------------------------
		//  Delete the extension hooks
		// -------------------------------------------

		$this->EE->db->where('class', get_class($this))
		             ->delete('exp_extensions');
	}

	// --------------------------------------------------------------------

	/**
	 * comment_entries_query ext hook
	 */
	function comment_entries_query()
	{
		// get the row ID right from the {exp:comment:entries}' matrix_row_id= param
		$row_id = $this->EE->TMPL->fetch_param('matrix_row_id');

		$this->_apply_row_id_to_query($row_id);
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

		$row_id = $this->EE->input->post('matrix_row_id');

		if ($row_id)
		{
			$data['matrix_row_id'] = $row_id;
		}

		return $data;
	}

}