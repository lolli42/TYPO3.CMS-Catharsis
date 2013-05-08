<?php
namespace TYPO3\CMS\Install\StepInstaller\Step;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Christian Kuhn <lolli@schwarzbu.ch>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Database connect step:
 * - Needs execution if database credentials are not set or fail to connect
 * - Renders fields for database connection fields
 * - Sets database credentials in LocalConfiguration
 * - Loads ext:dbal and ext:adodb if requested
 */
class DatabaseConnect implements StepInterface {

	/**
	 * Default constructor
	 */
	public function __construct() {
	}

	/**
	 * Execute database step:
	 * - Set database connect credentials in LocalConfiguration
	 *
	 * @return array<\TYPO3\CMS\Install\Status\StatusInterface>
	 */
	public function execute() {
	}

	/**
	 * Step needs to be executed if LocalConfiguration file does not exist.
	 *
	 * @return boolean
	 */
	public function needsExecution() {
		return TRUE;
	}

	/**
	 * Render this step
	 *
	 * @return string
	 */
	public function render() {
		return 'foo';
	}


}
?>