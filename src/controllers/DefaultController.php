<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\controllers;

use superbig\audit\Audit;

use Craft;
use craft\web\Controller;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['index', 'do-something'];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $records = AuditRecord::find()->all();
        $models = [];

        if ( $records ) {
            foreach ($records as $record) {
                $models[] = AuditModel::createFromRecord($record);
            }
        }

        return $this->renderTemplate('audit/index', [ 'logs' => $models ]);
    }

    /**
     * @return mixed
     */
    public function actionDoSomething()
    {
        $result = 'Welcome to the DefaultController actionDoSomething() method';

        return $result;
    }
}
