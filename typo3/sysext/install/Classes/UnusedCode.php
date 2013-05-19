<?php
namespace TYPO3\CMS\Install\Action;
/**
 * This might be put in use with auto configurator code again
 */
class foo {

	public function bar() {
		if ($this->INSTALL['checkIM']['path']) {
			$paths[] = trim($this->INSTALL['checkIM']['path']);
		}

		if ($this->INSTALL['checkIM']['lzw']) {
			$this->checkIMlzw = 1;
		}

		if (isset($index[$v]['convert']) && $this->checkIMlzw) {
			$index[$v]['gif_capability'] = '' . $this->_checkImageMagickGifCapability($v);
		}


	}

	/**
	 * Checking for existing ImageMagick installs.
	 *
	 * This tries to find available ImageMagick installations and tries to find the version
	 * numbers by executing "convert" without parameters..
	 *
	 * @param array $paths Possible ImageMagick paths
	 * @return void
	 */
	protected function checkImageMagick($paths) {
		$ext = 'Check Image Magick';
		$this->message($ext);
		// Get the template file
		$templateFile = @file_get_contents((PATH_site . $this->templateFilePath . 'CheckImageMagick.html'));
		$paths = array_unique($paths);
		$programs = explode(',', 'gm,convert,combine,composite,identify');
		$isExt = TYPO3_OS == 'WIN' ? '.exe' : '';
		$this->config_array['im_combine_filename'] = 'combine';
		$index = array();
		foreach ($paths as $v) {
			if (!preg_match('/[\\/]$/', $v)) {
				$v .= '/';
			}
			foreach ($programs as $filename) {
				if (ini_get('open_basedir') || file_exists($v) && @is_file(($v . $filename . $isExt))) {
					$version = $this->_checkImageMagick_getVersion($filename, $v);
					if ($version > 0) {
						// Assume GraphicsMagick
						if ($filename == 'gm') {
							$index[$v]['gm'] = $version;
							// No need to check for "identify" etc.
							continue;
						} else {
							// Assume ImageMagick
							$index[$v][$filename] = $version;
						}
					}
				}
			}
			if (count($index[$v]) >= 3 || $index[$v]['gm']) {
				$this->config_array['im'] = 1;
			}
			if ($index[$v]['gm'] || !$index[$v]['composite'] && $index[$v]['combine']) {
				$this->config_array['im_combine_filename'] = 'combine';
			} elseif ($index[$v]['composite'] && !$index[$v]['combine']) {
				$this->config_array['im_combine_filename'] = 'composite';
			}
		}
		$this->config_array['im_versions'] = $index;
		if (!$this->config_array['im']) {
			$this->message($ext, 'No ImageMagick installation available', '
				<p>
					It seems that there is no adequate ImageMagick installation
					available at the checked locations (' . implode(', ', $paths) . ')
					<br />
					An \'adequate\' installation for requires \'convert\',
					\'combine\'/\'composite\' and \'identify\' to be available
				</p>
			', 2);
		} else {
			// Get the subpart for the ImageMagick versions
			$theCode = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###VERSIONS###');
			// Get the subpart for each ImageMagick version
			$rowsSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($theCode, '###ROWS###');
			$rows = array();
			foreach ($this->config_array['im_versions'] as $p => $v) {
				$ka = array();
				reset($v);
				while (list($ka[]) = each($v)) {

				}
				// Define the markers content
				$rowsMarkers = array(
					'file' => $p,
					'type' => implode('<br />', $ka),
					'version' => implode('<br />', $v)
				);
				// Fill the markers in the subpart
				$rows[] = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($rowsSubPart, $rowsMarkers, '###|###', TRUE, FALSE);
			}
			// Substitute the subpart for the ImageMagick versions
			$theCode = \TYPO3\CMS\Core\Html\HtmlParser::substituteSubpart($theCode, '###ROWS###', implode(LF, $rows));
			// Add the content to the message array
			$this->message($ext, 'Available ImageMagick/GraphicsMagick installations:', $theCode, -1);
		}
		// Get the template file
		$formSubPart = \TYPO3\CMS\Core\Html\HtmlParser::getSubpart($templateFile, '###FORM###');
		// Define the markers content
		$formMarkers = array(
			'actionUrl' => $this->action,
			'lzwChecked' => $this->INSTALL['checkIM']['lzw'] ? 'checked="checked"' : '',
			'lzwLabel' => 'Check LZW capabilities.',
			'checkPath' => 'Check this path for ImageMagick installation:',
			'imageMagickPath' => htmlspecialchars($this->INSTALL['checkIM']['path']),
			'comment' => '(Eg. "D:\\wwwroot\\im537\\ImageMagick\\" for Windows or "/usr/bin/" for Unix)',
			'send' => 'Send'
		);
		// Fill the markers
		$formSubPart = \TYPO3\CMS\Core\Html\HtmlParser::substituteMarkerArray($formSubPart, $formMarkers, '###|###', TRUE, FALSE);
		// Add the content to the message array
		$this->message($ext, 'Search for ImageMagick:', $formSubPart, 0);
	}

	/**
	 * Extracts the version number for ImageMagick
	 *
	 * @param string $file The program name to execute in order to find out the version number
	 * @param string $path Path for the above program
	 * @return string Version number of the found ImageMagick instance
	 */
	protected function _checkImageMagick_getVersion($file, $path) {
		// Temporarily override some settings
		$im_version = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'];
		$combine_filename = $GLOBALS['TYPO3_CONF_VARS']['GFX']['im_combine_filename'];
		$parameters = '';
		if ($file == 'gm') {
			$GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'] = 'gm';
			// Work-around, preventing execution of "gm gm"
			$file = 'identify';
			// Work-around - GM doesn't like to be executed without any arguments
			$parameters = '-version';
		} else {
			$GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'] = 'im5';
			// Override the combine_filename setting
			if ($file == 'combine' || $file == 'composite') {
				$GLOBALS['TYPO3_CONF_VARS']['GFX']['im_combine_filename'] = $file;
			}
		}
		$cmd = GeneralUtility::imageMagickCommand($file, $parameters, $path);
		$retVal = FALSE;
		\TYPO3\CMS\Core\Utility\CommandUtility::exec($cmd, $retVal);
		$string = $retVal[0];
		list(, $ver) = explode('Magick', $string);
		list($ver) = explode(' ', trim($ver));
		// Restore the values
		$GLOBALS['TYPO3_CONF_VARS']['GFX']['im_version_5'] = $im_version;
		$GLOBALS['TYPO3_CONF_VARS']['GFX']['im_combine_filename'] = $combine_filename;
		return trim($ver);
	}




	/**
	 * Checking GIF-compression capabilities of ImageMagick install
	 *
	 * @param string $path Path of ImageMagick installation
	 * @return string Type of compression
	 */
	protected function _checkImageMagickGifCapability($path) {
		if ($this->config_array['dir_typo3temp']) {
			$tempPath = PATH_site . 'typo3temp/';
			$uniqueName = md5(uniqid(microtime()));
			$dest = $tempPath . $uniqueName . '.gif';
			$src = $this->backPath . 'gfx/typo3logo.gif';
			if (@is_file($src) && !strstr($src, ' ') && !strstr($dest, ' ')) {
				$cmd = GeneralUtility::imageMagickCommand('convert', $src . ' ' . $dest, $path);
				\TYPO3\CMS\Core\Utility\CommandUtility::exec($cmd);
			} else {
				die('No typo3/gfx/typo3logo.gif file!');
			}
			$out = '';
			if (@is_file($dest)) {
				$new_info = @getimagesize($dest);
				clearstatcache();
				$new_size = filesize($dest);
				$src_info = @getimagesize($src);
				clearstatcache();
				$src_size = @filesize($src);
				if ($new_info[0] != $src_info[0] || $new_info[1] != $src_info[1] || !$new_size || !$src_size) {
					$out = 'error';
				} else {
					// NONE-LZW ratio was 5.5 in test
					if ($new_size / $src_size > 4) {
						$out = 'NONE';
					} elseif ($new_size / $src_size > 1.5) {
						$out = 'RLE';
					} else {
						$out = 'LZW';
					}
				}
				unlink($dest);
			}
			return $out;
		}
		return '';
	}


}
?>
