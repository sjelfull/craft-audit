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
    protected $allowAnonymous = [ 'index', 'do-something' ];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionIndex ()
    {
        $query        = AuditRecord::find()
                                   ->orderBy('dateCreated desc')
                                   ->with('user');
        $countQuery   = clone $query;
        $totalItems   = $countQuery->count();
        $itemsPerPage = 20;
        $currentPage  = Craft::$app->getRequest()->getParam('page', 1);
        $urlPattern   = '/admin/audit?page=(:num)';
        $paginator    = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);


        $countQuery = clone $query;
        $pages      = new Pagination([
            'totalCount' => $countQuery->count(),
            'route'      => 'audit',
            'urlManager' => Craft::$app->getUrlManager(),
        ]);
        $records    = $query
            ->offset(($currentPage - 1) * $itemsPerPage)
            ->limit($itemsPerPage)
            ->all();

        $models = [];

        if ( $records ) {
            foreach ($records as $record) {
                $models[] = AuditModel::createFromRecord($record);
            }
        }

        $pager = LinkPager::widget([
            'pagination' => $pages,
        ]);

        return $this->renderTemplate('audit/index', [
            'logs'       => $models,
            'pagination' => Template::raw($pager),
            'paginator'  => $paginator,
        ]);
    }

    /**
     * @param int|null $id
     *
     * @return mixed
     * @internal param array $variables
     *
     */
    public function actionDetails (int $id = null)
    {
        //$id = Craft::$app->getRequest()->getRequiredParam('id');

        $log = Audit::$plugin->auditService->getEventById($id);

        return $this->renderTemplate('audit/_view', [
            'log' => $log,
        ]);
    }
}
