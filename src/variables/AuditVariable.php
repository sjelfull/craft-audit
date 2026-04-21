<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\variables;

use craft\base\Component;

use superbig\audit\Audit;
use superbig\audit\services\DiffRenderer;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class AuditVariable extends Component
{
    /**
     * @param null $ipAddress
     *
     * @return mixed|null
     */
    public function getLocationInfoForIp($ipAddress = null)
    {
        return Audit::$plugin->geo->getLocationInfoForIp($ipAddress);
    }

    /**
     * Expose the DiffRenderer service to Twig.
     *
     * Usage: {{ craft.audit.diff.renderHtmlDiff(from, to)|raw }}
     */
    public function getDiff(): DiffRenderer
    {
        return Audit::$plugin->diffRenderer;
    }

    /**
     * Alias so both {{ craft.audit.diff }} and {{ craft.audit.diff() }} work.
     */
    public function diff(): DiffRenderer
    {
        return Audit::$plugin->diffRenderer;
    }
}
