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
use craft\helpers\UrlHelper;
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
    protected $allowAnonymous = [];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $this->requirePermission(Audit::PERMISSION_VIEW_LOGS);

        $itemsPerPage = 100;
        $query    = AuditRecord::find()
                               ->orderBy('dateCreated desc')
                               ->with('user')
                               ->limit($itemsPerPage)
                               ->where(['parentId' => null]);
        $models   = [];
        $paginate = Template::paginateCriteria($query);
        list($pageInfo, $records) = $paginate;

        if ($records) {
            foreach ($records as $record) {
                $models[] = AuditModel::createFromRecord($record);
            }
        }

        return $this->renderTemplate('audit/index', [
            'logs'     => $models,
            'pageInfo' => $pageInfo,
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
        $this->requirePermission(Audit::PERMISSION_VIEW_LOGS);

        $service       = Audit::$plugin->auditService;
        $log           = $service->getEventById($id);
        $logsInSession = $service->getEventsBySessionId($log->sessionId);

        return $this->renderTemplate('audit/_view', [
            'settings'      => Audit::$plugin->getSettings(),
            'log'           => $log,
            'logsInSession' => $logsInSession,
        ]);
    }

    public function actionPruneLogs()
    {
        $this->requirePermission(Audit::PERMISSION_CLEAR_LOGS);

        $count = Audit::$plugin->auditService->pruneLogs();

        Craft::$app->getSession()->setNotice('Deleted ' . $count . ' records');

        return $this->goBack(UrlHelper::cpUrl('audit'));
    }
}
