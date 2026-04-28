<?php

namespace superbig\audit\helpers;

use craft\helpers\Html;

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
            } else {
                $uriDisplayHtml .= Html::encodeParams('<span class="token" data-name="{name}" data-value="{value}"><span>{name}</span></span>',
                    [
                        'name' => $part[0],
                        'value' => $part[1],
                    ]);
            }
        }

        return $uriDisplayHtml;
    }
}
