<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\helpers;

use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\Plugin;
use craft\base\PluginInterface;
use craft\fields\Assets;
use craft\fields\Entries;
use craft\fields\Lightswitch;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\models\EntryDraft;
use superbig\audit\Audit;

use Craft;
use craft\base\Component;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;
use yii\base\Exception;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class Route
{
    /**
     * @param array $uriParts
     *
     * @return string
     */
    public static function getUriDisplayHtml($uriParts = [])
    {
        $uriDisplayHtml = '';

        foreach ($uriParts as $part) {
            if (is_string($part)) {
                $uriDisplayHtml .= Html::encode($part);
            }
            else {
                $uriDisplayHtml .= Html::encodeParams('<span class="token" data-name="{name}" data-value="{value}"><span>{name}</span></span>',
                    [
                        'name'  => $part[0],
                        'value' => $part[1],
                    ]);
            }
        }

        return $uriDisplayHtml;
    }
}