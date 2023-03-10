<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\assetpreviews;

use Craft;
use craft\base\AssetPreviewHandler;
use craft\helpers\Image as ImageHelper;
use craft\helpers\UrlHelper;

/**
 * Provides functionality to preview images.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.4.0
 */
class Image extends AssetPreviewHandler
{
    /**
     * @inheritdoc
     */
    public function getPreviewHtml(): string
    {
        if (
            ImageHelper::isWebSafe($this->asset->getExtension()) &&
            $this->asset->getVolume()->hasUrls
        ) {
            $url = $this->asset->getUrl();
        } else {
            $url = UrlHelper::actionUrl('assets/thumb', [
                'uid' => $this->asset->uid,
                'width' => $this->asset->getWidth(),
                'height' => $this->asset->getHeight(),
            ], null, false);
        }

        return Craft::$app->getView()->renderTemplate('assets/_previews/image', [
            'asset' => $this->asset,
            'url' => $url,
        ]);
    }
}
