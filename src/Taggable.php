<?php
/**
 * @link https://github.com/2amigos/yii2-taggable-behavior
 * @copyright Copyright (c) 2013-2015 2amigOS! Consulting Group LLC
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace sunwellight\taggable;

use yii\base\Behavior;
use yii\base\Event;
use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * @author Alexander Kochetov <creocoder@gmail.com>
 */
class Taggable extends Behavior
{
    /**
     * @var ActiveRecord the owner of this behavior.
     */
    public $owner;
    /**
     * @var string
     */
    public $attribute = 'tagNames';
    /**
     * @var string
     */
    public $name = 'name';
    /**
     * @var string
     */
    public $frequency = 'frequency';
    /**
     * @var string
     */
    public $relation = 'tags';
    /**
     * Tag values
     * @var array|string
     */
    public $tagValues;
    /**
     * @var bool
     */
    public $asArray = false;

    /**
     * An additional field to link relational data, to be able to bind multiple fields of the same table without
     * creating separate fields in the binding table for communication mnoe to many.
     * @var string
     */
    public $relationAdditionalField = 'type';

    /**
     * Values are set for a bunch of specific field from the source table
     * @var int
     */
    public $relationAdditionalValue = NULL;

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        if ($name === $this->attribute) {
            return true;
        }

        return parent::canGetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        return $this->getTagNames();
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        if ($name === $this->attribute) {
            return true;
        }

        return parent::canSetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        $this->tagValues = $value;
    }

    /**
     * @inheritdoc
     */
    private function getTagNames()
    {
        $items = [];

        foreach ($this->owner->{$this->relation} as $tag) {
            $items[] = $tag->{$this->name};
        }

        return $this->asArray ? $items : implode(', ', $items);
    }

    /**
     * @param Event $event
     */
    public function afterSave($event)
    {
        if ($this->tagValues === null) {
            $this->tagValues = $this->owner->{$this->attribute};
        }

        if (!$this->owner->getIsNewRecord()) {
            $this->beforeDelete($event);
        }

        $names = array_unique(preg_split(
            '/\s*,\s*/u',
            preg_replace(
                '/\s+/u',
                ' ',
                is_array($this->tagValues)
                    ? implode(',', $this->tagValues)
                    : $this->tagValues
            ),
            -1,
            PREG_SPLIT_NO_EMPTY
        ));

        $relation = $this->owner->getRelation($this->relation);
        $pivot = $relation->via->from[0];
        /** @var ActiveRecord $class */
        $class = $relation->modelClass;
        $rows = [];
        $updatedTags = [];

        foreach ($names as $name) {
            $tag = $class::findOne([$this->name => $name]);

            if ($tag === null) {
                $tag = new $class();
                $tag->{$this->name} = $name;
            }

            $tag->{$this->frequency}++;

            if ($tag->save()) {
                $updatedTags[] = $tag;
                $rows[] = [$this->owner->getPrimaryKey(), $tag->getPrimaryKey(), $this->relationAdditionalValue];
            }
        }

        if (!empty($rows)) {
            $this->owner->getDb()
                ->createCommand()
                ->batchInsert($pivot, [key($relation->via->link), current($relation->link), $this->relationAdditionalField], $rows)
                ->execute();
        }

        $this->owner->populateRelation($this->relation, $updatedTags);
    }

    /**
     * @param Event $event
     */
    public function beforeDelete($event)
    {
        $relation = $this->owner->getRelation($this->relation);
        $pivot = $relation->via->from[0];
        /** @var ActiveRecord $class */
        $class = $relation->modelClass;
        $query = new Query();
        $pks = $query
            ->select(current($relation->link))
            ->from($pivot)
            ->where([key($relation->via->link) => $this->owner->getPrimaryKey()])
            ->column($this->owner->getDb());

        if (!empty($pks)) {
            $class::updateAllCounters([$this->frequency => -1], ['in', $class::primaryKey(), $pks]);
        }

        $this->owner->getDb()
            ->createCommand()
            ->delete($pivot, [key($relation->via->link) => $this->owner->getPrimaryKey()])
            ->execute();
    }
}
