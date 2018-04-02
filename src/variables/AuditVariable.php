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
class AuditVariable extends Component
{
    // Public Methods
    // =========================================================================

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
