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

use Craft;
use craft\helpers\Template;
use craft\helpers\UrlHelper;

use craft\web\Controller;
use superbig\audit\Audit;
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
     * @var array<int|string>|bool|int Allows anonymous access to this controller's actions.
     *                                  The actions must be in 'kebab-case'
     */
    protected array|int|bool $allowAnonymous = [];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $this->requirePermission(Audit::PERMISSION_VIEW_LOGS);

        $request = Craft::$app->getRequest();
        $startDate = $request->getQueryParam('startDate');
        $endDate = $request->getQueryParam('endDate');

        $itemsPerPage = 100;
        $query = AuditRecord::find()
                               ->orderBy('dateCreated desc')
                               ->with('user')
                               ->limit($itemsPerPage)
                               ->where(['parentId' => null]);

        // Apply date range filters if provided
        if ($startDate) {
            $startDateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
            // Validate that date was parsed correctly and matches the input format
            if ($startDateTime && $startDateTime->format('Y-m-d') === $startDate) {
                $startDateTime->setTime(0, 0, 0);
                $query->andWhere(['>=', 'dateCreated', $startDateTime->format('Y-m-d H:i:s')]);
            }
        }

        if ($endDate) {
            $endDateTime = \DateTime::createFromFormat('Y-m-d', $endDate);
            // Validate that date was parsed correctly and matches the input format
            if ($endDateTime && $endDateTime->format('Y-m-d') === $endDate) {
                $endDateTime->setTime(23, 59, 59);
                $query->andWhere(['<=', 'dateCreated', $endDateTime->format('Y-m-d H:i:s')]);
            }
        }

        $models = [];
        $paginate = Template::paginateCriteria($query);
        list($pageInfo, $records) = $paginate;

        if ($records) {
            foreach ($records as $record) {
                $models[] = AuditModel::createFromRecord($record);
            }
        }

        return $this->renderTemplate('audit/index', [
            'logs' => $models,
            'pageInfo' => $pageInfo,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * @param int $id
     *
     * @return mixed
     */
    public function actionDetails(int $id)
    {
        $this->requirePermission(Audit::PERMISSION_VIEW_LOGS);

        $service = Audit::$plugin->auditService;
        $log = $service->getEventById($id);
        $logsInSession = $service->getEventsBySessionId($log->sessionId);

        return $this->renderTemplate('audit/_view', [
            'settings' => Audit::$plugin->getSettings(),
            'log' => $log,
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
