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

use craft\helpers\Template;
use craft\web\UrlManager;
use superbig\audit\Audit;

use Craft;
use craft\web\Controller;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;
use yii\data\Pagination;
use yii\widgets\LinkPager;

use JasonGrimes\Paginator;

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
    protected $allowAnonymous = [];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $itemsPerPage = 20;
        $currentPage  = Craft::$app->getRequest()->getParam('page', 1);
        $urlPattern   = '/admin/audit?page=(:num)';
        $query        = AuditRecord::find()
                                   ->orderBy('dateCreated desc')
                                   ->with('user');
        $countQuery   = clone $query;
        $totalItems   = $countQuery->count();
        $paginator    = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);

        $records = $query
            ->offset(($currentPage - 1) * $itemsPerPage)
            ->limit($itemsPerPage)
            ->all();
        $models  = [];

        if ($records) {
            foreach ($records as $record) {
                $models[] = AuditModel::createFromRecord($record);
            }
        }

        return $this->renderTemplate('audit/index', [
            'logs'      => $models,
            'paginator' => $paginator,
        ]);
    }

    /**
     * @param int|null $id
     *
     * @return mixed
     * @internal param array $variables
     *
     */
    public function actionDetails(int $id = null)
    {
        $log           = Audit::$plugin->auditService->getEventById($id);
        $logsInSession = Audit::$plugin->auditService->getEventsBySessionId($log->sessionId);

        return $this->renderTemplate('audit/_view', [
            'log'           => $log,
            'logsInSession' => $logsInSession,
        ]);
    }
}
