<?php

namespace superbig\audit\assetbundles\audit;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class AuditAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@superbig/audit/assetbundles/audit/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Audit.js',
        ];

        $this->css = [
            'css/Audit.css',
        ];

        parent::init();
    }
}
