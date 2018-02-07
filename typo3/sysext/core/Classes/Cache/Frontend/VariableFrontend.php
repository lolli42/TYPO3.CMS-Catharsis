<?php
namespace TYPO3\CMS\Core\Cache\Frontend;

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

use TYPO3\CMS\Core\Cache\Backend\TransientBackendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A cache frontend for any kinds of PHP variables
 */
class VariableFrontend extends AbstractFrontend
{
    /**
     * Saves the value of a PHP variable in the cache. Note that the variable
     * will be serialized if necessary.
     *
     * @param string $entryIdentifier An identifier used for this cache entry
     * @param mixed $variable The variable to cache
     * @param array $tags Tags to associate with this cache entry
     * @param int $lifetime Lifetime of this cache entry in seconds. If NULL is specified, the default lifetime is used. "0" means unlimited lifetime.
     * @throws \InvalidArgumentException if the identifier or tag is not valid
     * @api
     */
    public function set($entryIdentifier, $variable, array $tags = [], $lifetime = null)
    {
        if (!$this->isValidEntryIdentifier($entryIdentifier)) {
            throw new \InvalidArgumentException(
                '"' . $entryIdentifier . '" is not a valid cache entry identifier.',
                1233058264
            );
        }
        foreach ($tags as $tag) {
            if (!$this->isValidTag($tag)) {
                throw new \InvalidArgumentException('"' . $tag . '" is not a valid tag for a cache entry.', 1233058269);
            }
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/cache/frontend/class.t3lib_cache_frontend_variablefrontend.php']['set'] ?? [] as $_funcRef) {
            $params = [
                'entryIdentifier' => &$entryIdentifier,
                'variable' => &$variable,
                'tags' => &$tags,
                'lifetime' => &$lifetime
            ];
            GeneralUtility::callUserFunction($_funcRef, $params, $this);
        }
        if (!$this->backend instanceof TransientBackendInterface) {
            $variable = serialize($variable);
        }
        $this->backend->set($entryIdentifier, $variable, $tags, $lifetime);
    }

    /**
     * Finds and returns a variable value from the cache.
     *
     * @param string $entryIdentifier Identifier of the cache entry to fetch
     *
     * @return mixed The value
     * @throws \InvalidArgumentException if the identifier is not valid
     * @api
     */
    public function get($entryIdentifier)
    {
        if (!$this->isValidEntryIdentifier($entryIdentifier)) {
            throw new \InvalidArgumentException(
                '"' . $entryIdentifier . '" is not a valid cache entry identifier.',
                1233058294
            );
        }
        $rawResult = $this->backend->get($entryIdentifier);
        if ($rawResult === false) {
            return false;
        }
        return $this->backend instanceof TransientBackendInterface ? $rawResult : unserialize($rawResult);
    }

    /**
     * Finds and returns all cache entries which are tagged by the specified tag.
     *
     * @param string $tag The tag to search for
     *
     * @return array An array with the content of all matching entries. An empty array if no entries matched
     * @throws \InvalidArgumentException if the tag is not valid
     * @api
     * @deprecated since TYPO3 v9, Avoid using this method since it is not compliant to PSR-6
     */
    public function getByTag($tag)
    {
        trigger_error('This method will be removed in TYPO3 v10. Avoid using this method since it is not compliant to PSR-6.', E_USER_DEPRECATED);
        if (!$this->isValidTag($tag)) {
            throw new \InvalidArgumentException('"' . $tag . '" is not a valid tag for a cache entry.', 1233058312);
        }
        $entries = [];
        $identifiers = $this->backend->findIdentifiersByTag($tag);
        foreach ($identifiers as $identifier) {
            $rawResult = $this->backend->get($identifier);
            if ($rawResult !== false) {
                $entries[] = $this->backend instanceof TransientBackendInterface ? $rawResult : unserialize($rawResult);
            }
        }
        return $entries;
    }
}
