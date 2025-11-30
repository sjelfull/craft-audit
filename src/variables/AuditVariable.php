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
}
