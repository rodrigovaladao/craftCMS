<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\services;

use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use DateTime;
use yii\base\Component;
use yii\base\Event;
use yii\base\Exception;

/**
 * Template Caches service.
 *
 * An instance of the service is available via [[\craft\base\ApplicationTrait::getTemplateCaches()|`Craft::$app->templateCaches`]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.0
 */
class TemplateCaches extends Component
{
    /**
     * @event SectionEvent The event that is triggered before template caches are deleted.
     * @since 3.0.2
     * @deprecated in 3.5.0
     */
    const EVENT_BEFORE_DELETE_CACHES = 'beforeDeleteCaches';

    /**
     * @event SectionEvent The event that is triggered after template caches are deleted.
     * @since 3.0.2
     * @deprecated in 3.5.0
     */
    const EVENT_AFTER_DELETE_CACHES = 'afterDeleteCaches';

    /**
     * @var bool Whether template caching should be enabled for this request
     * @see _isTemplateCachingEnabled()
     */
    private $_enabled;

    /**
     * @var bool Whether global template caches should be enabled for this request
     * @see _isTemplateCachingEnabled()
     */
    private $_enabledGlobally;

    /**
     * @var string|null The current request's path
     * @see _path()
     */
    private $_path;

    /**
     * Returns a cached template by its key.
     *
     * @param string $key The template cache key
     * @param bool $global Whether the cache would have been stored globally.
     * @param bool $registerScripts Whether JS and CSS code coptured with the cache should be registered
     * @return string|null
     * @throws Exception if this is a console request and `false` is passed to `$global`
     */
    public function getTemplateCache(string $key, bool $global, bool $registerScripts = false)
    {
        // Make sure template caching is enabled
        if ($this->_isTemplateCachingEnabled($global) === false) {
            return null;
        }

        $cacheKey = $this->_cacheKey($key, $global);
        $data = Craft::$app->getCache()->get($cacheKey);

        if ($data === false) {
            return null;
        }

        [$body, $tags, $bufferedJs, $bufferedScripts, $bufferedCss] = array_pad($data, 5, null);

        // If we're actively collecting element cache tags, add this cache's tags to the collection
        Craft::$app->getElements()->collectCacheTags($tags);

        // Register JS and CSS tags
        if ($registerScripts) {
            $this->_registerScripts($bufferedJs ?? [], $bufferedScripts ?? [], $bufferedCss ?? []);
        }

        return $body;
    }

    /**
     * Starts a new template cache.
     *
     * @param bool $withScripts Whether JS and CSS code registered with [[\craft\web\View::registerJs()]],
     * [[\craft\web\View::registerScript()]], and [[\craft\web\View::registerCss()]] should be captured and
     * included in the cache. If this is `true`, be sure to pass `$withScripts = true` to [[endTemplateCache()]]
     * as well.
     * @param bool $global Whether the cache should be stored globally.
     */
    public function startTemplateCache(bool $withScripts = false, bool $global = false)
    {
        // Make sure template caching is enabled
        if ($this->_isTemplateCachingEnabled($global) === false) {
            return;
        }

        Craft::$app->getElements()->startCollectingCacheTags();

        if ($withScripts) {
            $view = Craft::$app->getView();
            $view->startJsBuffer();
            $view->startScriptBuffer();
            $view->startCssBuffer();
        }
    }

    /**
     * Includes an element criteria in any active caches.
     *
     * @param Event $event The 'afterPrepare' element query event
     * @deprecated in 3.5.0
     */
    public function includeElementQueryInTemplateCaches(Event $event)
    {
    }

    /**
     * Includes an element in any active caches.
     *
     * @param int $elementId The element ID.
     * @deprecated in 3.5.0
     */
    public function includeElementInTemplateCaches(int $elementId)
    {
    }

    /**
     * Ends a template cache.
     *
     * @param string $key The template cache key.
     * @param bool $global Whether the cache should be stored globally.
     * @param string|null $duration How long the cache should be stored for. Should be a [relative time format](https://php.net/manual/en/datetime.formats.relative.php).
     * @param mixed|null $expiration When the cache should expire.
     * @param string $body The contents of the cache.
     * @param bool $withScripts Whether JS and CSS code registered with [[\craft\web\View::registerJs()]],
     * [[\craft\web\View::registerScript()]], and [[\craft\web\View::registerCss()]] should be captured and
     * included in the cache.
     * @throws Exception if this is a console request and `false` is passed to `$global`
     * @throws \Throwable
     */
    public function endTemplateCache(string $key, bool $global, ?string $duration, $expiration, string $body, bool $withScripts = false)
    {
        // Make sure template caching is enabled
        if ($this->_isTemplateCachingEnabled($global) === false) {
            return;
        }

        $dep = Craft::$app->getElements()->stopCollectingCacheTags();

        if ($withScripts) {
            $view = Craft::$app->getView();
            $bufferedJs = $view->clearJsBuffer(false, false);
            $bufferedScripts = $view->clearScriptBuffer();
            $bufferedCss = $view->clearCssBuffer();
        }

        // If there are any transform generation URLs in the body, don't cache it.
        // stripslashes($body) in case the URL has been JS-encoded or something.
        $saveCache = !StringHelper::contains(stripslashes($body), 'assets/generate-transform');

        if ($saveCache) {
            // Always add a `template` tag
            $dep->tags[] = 'template';

            $cacheValue = [$body, $dep->tags];
        }

        if ($withScripts) {
            // Parse the JS/CSS code and tag attributes out of the <script> and <style> tags
            $bufferedScripts = array_map(function($tags) {
                return array_map(function($tag) {
                    $tag = Html::parseTag($tag);
                    return [$tag['children'][0]['value'], $tag['attributes']];
                }, $tags);
            }, $bufferedScripts);
            $bufferedCss = array_map(function($tag) {
                $tag = Html::parseTag($tag);
                return [$tag['children'][0]['value'], $tag['attributes']];
            }, $bufferedCss);

            if ($saveCache) {
                array_push($cacheValue, $bufferedJs, $bufferedScripts, $bufferedCss);
            }

            // Re-register the JS and CSS
            $this->_registerScripts($bufferedJs, $bufferedScripts, $bufferedCss);
        }

        if (!$saveCache) {
            return;
        }

        $cacheKey = $this->_cacheKey($key, $global);

        if ($duration !== null) {
            $expiration = (new DateTime($duration));
        }

        if ($expiration !== null) {
            $duration = DateTimeHelper::toDateTime($expiration)->getTimestamp() - time();
        }

        Craft::$app->getCache()->set($cacheKey, $cacheValue, $duration, $dep);
    }

    private function _registerScripts(array $bufferedJs, array $bufferedScripts, array $bufferedCss): void
    {
        $view = Craft::$app->getView();

        foreach ($bufferedJs as $pos => $scripts) {
            foreach ($scripts as $key => $script) {
                $view->registerJs($script, $pos, $key);
            }
        }

        foreach ($bufferedScripts as $pos => $tags) {
            foreach ($tags as $key => $tag) {
                [$script, $options] = $tag;
                $view->registerScript($script, $pos, $options, $key);
            }
        }

        foreach ($bufferedCss as $key => $tag) {
            [$css, $options] = $tag;
            $view->registerCss($css, $options, $key);
        }
    }

    /**
     * Deletes a cache by its ID(s).
     *
     * @param int|int[] $cacheId The cache ID(s)
     * @return bool
     * @deprecated in 3.5.0
     */
    public function deleteCacheById($cacheId): bool
    {
        return false;
    }

    /**
     * Deletes caches by a given element class.
     *
     * @param string $elementType The element class.
     * @return bool
     * @deprecated in 3.5.0. Use [[\craft\services\Elements::invalidateCachesForElementType()]] instead.
     */
    public function deleteCachesByElementType(string $elementType): bool
    {
        Craft::$app->getElements()->invalidateCachesForElementType($elementType);
        return true;
    }

    /**
     * Deletes caches that include a given element(s).
     *
     * @param ElementInterface|ElementInterface[] $elements The element(s) whose caches should be deleted.
     * @return bool
     * @deprecated in 3.5.0. Use [[\craft\services\Elements::invalidateCachesForElement()]] instead.
     */
    public function deleteCachesByElement($elements): bool
    {
        $elementsService = Craft::$app->getElements();
        if (is_array($elements)) {
            foreach ($elements as $element) {
                $elementsService->invalidateCachesForElement($element);
            }
        } else {
            $elementsService->invalidateCachesForElement($elements);
        }
        return true;
    }

    /**
     * Deletes caches that include an a given element ID(s).
     *
     * @param int|int[] $elementId The ID of the element(s) whose caches should be cleared.
     * @param bool $deleteQueryCaches Whether a DeleteStaleTemplateCaches job
     * should be added to the queue, deleting any query caches that may now
     * involve this element, but hadn't previously. (Defaults to `true`.)
     * @return bool
     * @deprecated in 3.5.0. Use [[\craft\services\Elements::invalidateCachesForElement()]] instead.
     */
    public function deleteCachesByElementId($elementId, bool $deleteQueryCaches = true): bool
    {
        $elementsService = Craft::$app->getElements();
        $element = Craft::$app->getElements()->getElementById($elementId);
        if (!$element) {
            return false;
        }
        $elementsService->invalidateCachesForElement($element);
        return true;
    }

    /**
     * Queues up a Delete Stale Template Caches job
     *
     * @deprecated in 3.5.0
     */
    public function handleResponse()
    {
    }

    /**
     * Deletes caches that include elements that match a given element query's parameters.
     *
     * @param ElementQuery $query The element query that should be used to find elements whose caches
     * should be deleted.
     * @return bool
     * @deprecated in 3.5.0. Use [[\craft\services\Elements::invalidateCachesForElementType()]] instead.
     */
    public function deleteCachesByElementQuery(ElementQuery $query): bool
    {
        if (!$query->elementType) {
            return false;
        }
        Craft::$app->getElements()->invalidateCachesForElementType($query->elementType);
        return true;
    }

    /**
     * Deletes a cache by its key(s).
     *
     * @param string|string[] $key The cache key(s) to delete.
     * @param bool|null $global Whether the template caches are stored globally.
     * @param int|null $siteId The site ID to delete caches for.
     * @return bool
     * @throws Exception if this is a console request and `null` or `false` is passed to `$global`
     */
    public function deleteCachesByKey($key, ?bool $global = null, ?int $siteId = null): bool
    {
        $cache = Craft::$app->getCache();

        if ($global === null) {
            $this->deleteCachesByKey($key, true, $siteId);
            $this->deleteCachesByKey($key, false, $siteId);
            return true;
        }

        foreach ((array)$key as $k) {
            $cache->delete($this->_cacheKey($k, $global, $siteId));
        }

        return true;
    }

    /**
     * Deletes any expired caches.
     *
     * @return bool
     * @deprecated in 3.5.0
     */
    public function deleteExpiredCaches(): bool
    {
        return true;
    }

    /**
     * Deletes any expired caches.
     *
     * @return bool
     * @deprecated in 3.2.0
     */
    public function deleteExpiredCachesIfOverdue(): bool
    {
        return true;
    }

    /**
     * Deletes all the template caches.
     *
     * @return bool
     * @deprecated in 3.5.0. Use [[\craft\services\Elements::invalidateAllCaches()]] instead.
     */
    public function deleteAllCaches(): bool
    {
        Craft::$app->getElements()->invalidateAllCaches();
        return true;
    }

    /**
     * Returns whether template caching is enabled, based on the 'enableTemplateCaching' config setting.
     *
     * @param bool $global Whether this is for a globally-scoped cache
     * @return bool Whether template caching is enabled
     */
    private function _isTemplateCachingEnabled(bool $global): bool
    {
        if ($this->_enabled === null) {
            if (!Craft::$app->getConfig()->getGeneral()->enableTemplateCaching) {
                $this->_enabled = $this->_enabledGlobally = false;
            } else {
                // Don't enable template caches for tokenized requests
                $request = Craft::$app->getRequest();
                if ($request->getHadToken()) {
                    $this->_enabled = $this->_enabledGlobally = false;
                } else {
                    $this->_enabled = !$request->getIsConsoleRequest();
                    $this->_enabledGlobally = true;
                }
            }
        }
        return $global ? $this->_enabledGlobally : $this->_enabled;
    }

    /**
     * Defines a data cache key that should be used for a template cache.
     *
     * @param string $key
     * @param bool $global
     * @param int|null $siteId
     * @throws Exception if this is a console request and `false` is passed to `$global`
     */
    private function _cacheKey(string $key, bool $global, int $siteId = null): string
    {
        $cacheKey = "template::$key::" . ($siteId ?? Craft::$app->getSites()->getCurrentSite()->id);

        if (!$global) {
            $cacheKey .= '::' . $this->_path();
        }

        return $cacheKey;
    }

    /**
     * Returns the current request path, including a "site:" or "cp:" prefix.
     *
     * @return string
     * @throws Exception if this is a console request
     */
    private function _path(): string
    {
        if ($this->_path !== null) {
            return $this->_path;
        }

        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            throw new Exception('Not possible to determine the request path for console commands.');
        }

        $isCpRequest = $request->getIsCpRequest();
        if ($isCpRequest) {
            $this->_path = 'cp:';
        } else {
            $this->_path = 'site:';
        }

        $this->_path .= $request->getPathInfo();
        if (Craft::$app->getDb()->getIsMysql()) {
            $this->_path = StringHelper::encodeMb4($this->_path);
        }

        $pageNum = $request->getPageNum();
        if ($pageNum !== 1) {
            $pageTrigger = $isCpRequest ? 'p' : Craft::$app->getConfig()->getGeneral()->getPageTrigger();
            $this->_path .= sprintf('/%s%s', $pageTrigger, $pageNum);
        }

        return $this->_path;
    }
}
