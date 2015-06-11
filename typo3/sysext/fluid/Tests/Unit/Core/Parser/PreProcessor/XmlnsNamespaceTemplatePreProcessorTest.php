<?php
namespace TYPO3\CMS\Fluid\Tests\Unit\Core\Parser\PreProcessor;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Fluid\Core\Parser\PreProcessor\XmlnsNamespaceTemplatePreProcessor;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3\CMS\Fluid\Tests\Unit\Core\Rendering\RenderingContextFixture;

/**
 * Class XmlnsNamespaceTemplatePreProcessorTest
 */
class XmlnsNamespaceTemplatePreProcessorTest extends UnitTestCase
{
    /**
     * @param string $source
     * @param array $expectedNamespaces
     * @param string $expectedSource
     * @test
     * @dataProvider getPreProcessSourceData
     */
    public function preProcessSourceExtractsNamespaces($source, array $expectedNamespaces, $expectedSource)
    {
        $subject = new XmlnsNamespaceTemplatePreProcessor();
        $resolver = $this->getMock(ViewHelperResolver::class, array('addNamespace'));
        $context = $this->getMock(RenderingContextFixture::class, array('getViewHelperResolver'));
        if (empty($expectedNamespaces)) {
            $context->expects($this->never())->method('getViewHelperResolver');
            $resolver->expects($this->never())->method('addNamespace');
        } else {
            $context->expects($this->once())->method('getViewHelperResolver')->willReturn($resolver);
            foreach ($expectedNamespaces as $index => $expectedNamespaceParts) {
                list($prefix, $phpNamespace) = $expectedNamespaceParts;
                $resolver->expects($this->at($index))->method('addNamespace')->with($prefix, $phpNamespace);
            }
        }
        $subject->setRenderingContext($context);
        $result = $subject->preProcessSource($source);
        if ($expectedSource === null) {
            $this->assertEquals($source, $result);
        } else {
            $this->assertEquals($expectedSource, $result);
        }
    }

    /**
     * @return array
     */
    public function getPreProcessSourceData()
    {
        return array(
            'Empty source raises no errors' => array(
                '', array(), null,
            ),
            'Tags without xmlns remain untouched' => array(
                '<div class="not-touched">...</div>', array(), null
            ),
            'Third-party namespace not detected' => array(
                '<html xmlns:notdetected="http://thirdparty.org/ns/Foo/Bar/ViewHelpers">...</html>', array(), null
            ),
            'Detects and removes Fluid namespaces by namespace URL' => array(
                '<html xmlns:detected="http://typo3.org/ns/Foo/Bar/ViewHelpers">...</html>',
                array(array('detected', 'Foo\\Bar\\ViewHelpers')),
                '...'
            ),
            'Skips invalid namespace prefixes' => array(
                '<html xmlns:bad-prefix="http://typo3.org/ns/Foo/Bar/ViewHelpers">...</html>',
                array(),
                null
            ),
        );
    }
}
