<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\fields;

use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\TagQuery;
use craft\elements\Tag;
use craft\gql\arguments\elements\Tag as TagArguments;
use craft\gql\interfaces\elements\Tag as TagInterface;
use craft\gql\resolvers\elements\Tag as TagResolver;
use craft\helpers\Gql;
use craft\helpers\Gql as GqlHelper;
use craft\models\GqlSchema;
use craft\models\TagGroup;
use craft\services\Gql as GqlService;
use GraphQL\Type\Definition\Type;

/**
 * Tags represents a Tags field.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.0
 */
class Tags extends BaseRelationField
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Tags');
    }

    /**
     * @inheritdoc
     */
    protected static function elementType(): string
    {
        return Tag::class;
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('app', 'Add a tag');
    }

    /**
     * @inheritdoc
     */
    public static function valueType(): string
    {
        return sprintf('\\%s|\\%s[]', TagQuery::class, Tag::class);
    }

    /**
     * @inheritdoc
     */
    public $allowMultipleSources = false;

    /**
     * @inheritdoc
     */
    public $allowLimit = false;

    /**
     * @var
     */
    private $_tagGroupId;

    /**
     * @inheritdoc
     */
    protected function inputHtml($value, ElementInterface $element = null): string
    {
        if ($element !== null && $element->hasEagerLoadedElements($this->handle)) {
            $value = $element->getEagerLoadedElements($this->handle);
        }

        if ($value instanceof ElementQueryInterface) {
            $value = $value
                ->anyStatus()
                ->all();
        } elseif (!is_array($value)) {
            $value = [];
        }

        $tagGroup = $this->_getTagGroup();

        if ($tagGroup) {
            return Craft::$app->getView()->renderTemplate('_components/fieldtypes/Tags/input',
                [
                    'elementType' => static::elementType(),
                    'id' => $this->getInputId(),
                    'name' => $this->handle,
                    'elements' => $value,
                    'tagGroupId' => $tagGroup->id,
                    'targetSiteId' => $this->targetSiteId($element),
                    'sourceElementId' => $element !== null ? $element->id : null,
                    'selectionLabel' => $this->selectionLabel ? Craft::t('site', $this->selectionLabel) : static::defaultSelectionLabel(),
                    'allowSelfRelations' => (bool)$this->allowSelfRelations,
                ]);
        }

        return '<p class="error">' . Craft::t('app', 'This field is not set to a valid source.') . '</p>';
    }

    /**
     * @inheritdoc
     */
    public function includeInGqlSchema(GqlSchema $schema): bool
    {
        return Gql::canQueryTags($schema);
    }

    /**
     * @inheritdoc
     * @since 3.3.0
     */
    public function getContentGqlType()
    {
        return [
            'name' => $this->handle,
            'type' => Type::listOf(TagInterface::getType()),
            'args' => TagArguments::getArguments(),
            'resolve' => TagResolver::class . '::resolve',
            'complexity' => GqlHelper::relatedArgumentComplexity(GqlService::GRAPHQL_COMPLEXITY_EAGER_LOAD),
        ];
    }

    /**
     * @inheritdoc
     * @since 3.3.0
     */
    public function getEagerLoadingGqlConditions()
    {
        $allowedEntities = Gql::extractAllowedEntitiesFromSchema();
        $tagGroupUids = $allowedEntities['taggroups'] ?? [];

        if (empty($tagGroupUids)) {
            return false;
        }

        $tagsService = Craft::$app->getTags();
        $tagGroupIds = array_filter(array_map(function(string $uid) use ($tagsService) {
            $tagGroup = $tagsService->getTagGroupByUid($uid);
            return $tagGroup->id ?? null;
        }, $tagGroupUids));

        return [
            'groupId' => $tagGroupIds,
        ];
    }

    /**
     * Returns the tag group associated with this field.
     *
     * @return TagGroup|null
     */
    private function _getTagGroup()
    {
        $tagGroupId = $this->_getTagGroupId();

        if ($tagGroupId !== false) {
            return Craft::$app->getTags()->getTagGroupByUid($tagGroupId);
        }

        return null;
    }

    /**
     * Returns the tag group ID this field is associated with.
     *
     * @return int|false
     */
    private function _getTagGroupId()
    {
        if ($this->_tagGroupId !== null) {
            return $this->_tagGroupId;
        }

        if (!preg_match('/^taggroup:([0-9a-f\-]+)$/', $this->source, $matches)) {
            return $this->_tagGroupId = false;
        }

        return $this->_tagGroupId = $matches[1];
    }
}
