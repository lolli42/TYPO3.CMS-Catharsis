<?php
namespace TYPO3\CMS\Backend\Form\Container;

use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FlexFormContainerContainer extends AbstractContainer {

	/**
	 * @return array As defined in initializeResultArray() of AbstractNode
	 */
	public function render() {

		$table = $this->globalOptions['table'];
		$row = $this->globalOptions['databaseRow'];
		$fieldName = $this->globalOptions['fieldName'];
		$flexFormFormPrefix = $this->globalOptions['flexFormFormPrefix'];
		$flexFormContainerElementCollapsed = $this->globalOptions['flexFormContainerElementCollapsed'];
		$flexFormContainerTitle = $this->globalOptions['flexFormContainerTitle'];
		$flexFormFieldIdentifierPrefix = $this->globalOptions['flexFormFieldIdentifierPrefix'];
		$parameterArray = $this->globalOptions['parameterArray'];

		$toggleIcons = IconUtility::getSpriteIcon(
			'actions-move-down',
			array(
				'class' => 't3-flex-control-toggle-icon-open',
				'style' => $flexFormContainerElementCollapsed ? 'display: none;' : '',
			)
		);
		$toggleIcons .= IconUtility::getSpriteIcon(
			'actions-move-right',
			array(
				'class' => 't3-flex-control-toggle-icon-close',
				'style' => $flexFormContainerElementCollapsed ? '' : 'display: none;',
			)
		);

		list($formPrefix, $elementNumber) = GeneralUtility::revExplode('[]', $flexFormFormPrefix, 2);
		// @todo: This is used in the "action" hidden input field - both the fieldName and the id in HTML is still wrong
		$actionFieldName = '_ACTION_FLEX_FORM' . $parameterArray['itemFormElName'] . $formPrefix . '][_ACTION][' . $elementNumber;

		$moveAndDeleteContent = array();
		$userHasAccessToDefaultLanguage = $this->getBackendUserAuthentication()->checkLanguageAccess(0);
		if ($userHasAccessToDefaultLanguage) {
			$moveAndDeleteContent[] = '<div class="pull-right">';
			$moveAndDeleteContent[] = IconUtility::getSpriteIcon(
				'actions-move-move',
				array(
					'title' => 'Drag to Move', // @todo: hardcoded title ...
					'class' => 't3-js-sortable-handle'
				)
			);
			$moveAndDeleteContent[] = IconUtility::getSpriteIcon(
				'actions-edit-delete',
				array(
					'title' => 'Delete', // @todo: hardcoded title ...
					'class' => 't3-delete'
				)
			);
			$moveAndDeleteContent[] = '</div>';
		}

		$options = $this->globalOptions;
		/** @var FlexFormElementContainer $containerContent */
		$containerContent = GeneralUtility::makeInstance(FlexFormElementContainer::class);
		$containerContentResult = $containerContent->setGlobalOptions($options)->render();

		$html = array();
		$html[] = '<div id="' . $flexFormFieldIdentifierPrefix . '" class="t3-form-field-container-flexsections t3-flex-section">';
		$html[] = 	'<input class="t3-flex-control t3-flex-control-action" type="hidden" name="' . htmlspecialchars($actionFieldName) . '" value="" />';
		$html[] = 	'<div class="t3-form-field-header-flexsection t3-flex-section-header">';
		$html[] = 		'<div class="pull-left">';
		$html[] = 			'<a href="#" class="t3-flex-control-toggle-button">' . $toggleIcons . '</a>';
		$html[] = 			'<span class="t3-record-title">' . $flexFormContainerTitle . '</span>';
		$html[] = 		'</div>';
		$html[] = 		implode(LF, $moveAndDeleteContent);
		$html[] = 	'</div>';
		$html[] = 	'<div class="t3-form-field-record-flexsection t3-flex-section-content"' . ($flexFormContainerElementCollapsed ? ' style="display:none;"' : '') . '>';
		$html[] = 		$containerContentResult['html'];
		$html[] = 	'</div>';
		$html[] = 	'<input';
		$html[] = 		'class="t3-flex-control t3-flex-control-toggle"';
		$html[] = 		'id="' . $flexFormFormPrefix . '-toggleClosed"';
		$html[] = 		'type="hidden"';
		$html[] = 		'name="' . htmlspecialchars('data[' . $table . '][' . $row['uid'] . '][' . $fieldName . ']' . $flexFormFormPrefix . '[_TOGGLE]') . '"';
		$html[] = 		'value="' . ($flexFormContainerElementCollapsed ? '1' : '0') . '"';
		$html[] = 	'/>';
		$html[] = '</div>';

		$containerContentResult['html'] = '';
		$resultArray = $this->initializeResultArray();
		$resultArray['html'] = implode(LF, $html);
		$resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $containerContentResult);

		return $resultArray;
	}

	/**
	 * @return BackendUserAuthentication
	 */
	protected function getBackendUserAuthentication() {
		return $GLOBALS['BE_USER'];
	}

}
