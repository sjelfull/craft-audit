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

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            ['enabled', 'boolean'],
            ['pruneDays', 'integer'],
        ]);
    }
}
