<?php

declare(strict_types=1);

namespace superbig\audit\web\assets\diff;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * AuditDiffAsset — CSS bundle for rendering diffs in the Craft Audit CP.
 *
 * Namespaced under .audit-diff-* classes to avoid CP conflicts.
 * Provides light + dark-mode styles via CSS custom properties.
 */
class AuditDiffAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@superbig/audit/web/assets/diff/dist';
        $this->depends = [CpAsset::class];
        $this->css = ['diff.css'];
        parent::init();
    }
}
