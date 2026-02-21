<?php

/**
 * @class  plusadModel
 * @brief  PlusAd module model class
 */
class plusadModel extends plusad
{
	/**
	 * @brief Initialization
	 */
	function init()
	{
	}

	/**
	 * @brief Get module information
	 * @return object|void Module information
	 */
	function getplusadinfo()
	{
		$output = executeQuery('plusad.getplusadinfo');
		if (!$output->data->module_srl)
		{
			return;
		}

		$module_info = moduleModel::getModuleInfoByModuleSrl($output->data->module_srl);
		return $module_info;
	}

	/**
	 * @brief Get valid ad list
	 * @param object $args Arguments
	 * @return Object Query result
	 */
	function getadlist($args = null)
	{
		if (!$args)
		{
			$args = new stdClass();
		}
		// Set current time for comparison with remaining time
		$args->nowdate = date('Y-m-d H:i:s');
		$output = executeQuery('plusad.getadlist', $args);
		return $output;
	}

	/**
	 * @brief Delete ad
	 * @return Object Query result
	 */
	function deletead()
	{
		$args = new stdClass();
		$args->ad_srl = Context::get('ad_srl');
		// Execute delete
		$output = executeQuery("plusad.deletead", $args);
		return $output;
	}

	/**
	 * @brief Delete ad log (individual)
	 * @return Object Query result
	 */
	function deleteadponit()
	{
		$args = new stdClass();
		$args->ad_srl = Context::get('ad_srl');
		// Execute delete
		$output = executeQuery('plusad.deletead', $args);
		return $output;
	}

	/**
	 * @brief Delete all ad logs
	 * @return Object Query result
	 */
	function deleteadall()
	{
		$output = executeQuery('plusad.deleteadall');
		return $output;
	}

	/**
	 * @brief Delete expired ad logs
	 * @return Object Query result
	 */
	function deleteadlast()
	{
		$args = new stdClass();
		// Set current time for comparison with remaining time
		$args->nowdate = date('Y-m-d H:i:s');
		$output = executeQuery('plusad.deleteadlast', $args);
		return $output;
	}
}