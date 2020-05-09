<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\models;

use superbig\audit\Audit;

use Craft;
use craft\base\Model;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * How many days to keep log entries around
     */
    public $pruneDays = 30;

    /**
     * Enabled status
     */
    public $enabled = true;

    /**
     * Prune old records when a admin is logged in
     */
    public $pruneRecordsOnAdminRequests = false;

    /**
     * Enabled status
     */
    public $enabledGeolocation = true;

    /**
     * Update authentication key
     */
    public $updateAuthKey = '';

    /**
     * Where to save Maxmind DB files
     */
    public $dbPath = '';

    public $logPluginEvents  = true;
    public $logDraftEvents   = false;
    public $logElementEvents = true;
    public $logUserEvents    = true;
    public $logRouteEvents   = true;

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        if (empty($this->dbPath)) {
            $this->dbPath = Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'audit' . DIRECTORY_SEPARATOR;
        }
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            ['enabled', 'boolean'],
            ['enabledGeolocation', 'boolean'],
            ['pruneDays', 'integer'],
        ]);
    }
}
