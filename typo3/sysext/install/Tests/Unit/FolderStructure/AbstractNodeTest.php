<?php
namespace TYPO3\CMS\Install\Tests\Unit\FolderStructure;

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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Test case
 */
class AbstractNodeTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $node;

	/**
	 * Set up
	 */
	public function setUp() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$this->node = $this->getAccessibleMock(
			'TYPO3\\CMS\\Install\\FolderStructure\\AbstractNode',
			array('dummy'),
			array(),
			'',
			FALSE
		);
	}

	/**
	 * @test
	 */
	public function isWritableCallsParentIsWritable() {
		$parentMock = $this->getMock('TYPO3\\CMS\\Install\\FolderStructure\\NodeInterface', array(), array(), '', FALSE);
		$parentMock->expects($this->once())->method('isWritable');
		$this->node->_set('parent', $parentMock);
		$this->node->isWritable();
	}

	/**
	 * @test
	 */
	public function isWritableCallsReturnsWritableStatusOfParent() {
		$parentMock = $this->getMock('TYPO3\\CMS\\Install\\FolderStructure\\NodeInterface', array(), array(), '', FALSE);
		$parentMock->expects($this->once())->method('isWritable')->will($this->returnValue(TRUE));
		$this->node->_set('parent', $parentMock);
		$this->assertTrue($this->node->isWritable());
	}

	/**
	 * @test
	 * @expectedException \TYPO3\CMS\Install\FolderStructure\Exception\InvalidArgumentException
	 */
	public function getRelativePathBelowSiteRootThrowsExceptionIfGivenPathIsNotBelowPathSiteConstant() {
		$this->node->_call('getRelativePathBelowSiteRoot', '/tmp');
	}

	/**
	 * @test
	 */
	public function getRelativePathCallsGetAbsolutePathIfPathIsNull() {
		/** @var $node \TYPO3\CMS\Install\FolderStructure\AbstractNode|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface|\PHPUnit_Framework_MockObject_MockObject */
		$node = $this->getAccessibleMock(
			'TYPO3\\CMS\\Install\\FolderStructure\\AbstractNode',
			array('getAbsolutePath'),
			array(),
			'',
			FALSE
		);
		$node->expects($this->once())->method('getAbsolutePath')->will($this->returnValue(PATH_site));
		$node->_call('getRelativePathBelowSiteRoot', NULL);
	}

	/**
	 * @test
	 */
	public function getRelativePathBelowSiteRootReturnsSingleForwardSlashIfGivenPathEqualsPathSiteConstant() {
		$result = $this->node->_call('getRelativePathBelowSiteRoot', PATH_site);
		$this->assertSame('/', $result);
	}

	/**
	 * @test
	 */
	public function getRelativePathBelowSiteRootReturnsSubPath() {
		$result = $this->node->_call('getRelativePathBelowSiteRoot', PATH_site . 'foo/bar');
		$this->assertSame('/foo/bar', $result);
	}
}
