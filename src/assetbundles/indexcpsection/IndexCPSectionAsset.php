<?php

namespace superbig\audit\assetbundles\indexcpsection;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class IndexCPSectionAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@superbig/audit/assetbundles/indexcpsection/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Index.js',
        ];

        $this->css = [
            'css/Index.css',
        ];

        parent::init();
    }
}
