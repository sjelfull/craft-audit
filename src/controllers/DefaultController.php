<?php

namespace superbig\audit\controllers;

use Craft;
use craft\helpers\Template;
use craft\helpers\UrlHelper;

use craft\web\Controller;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;
use superbig\audit\web\assets\diff\AuditDiffAsset;

class DefaultController extends Controller
{
    /**
     * @var array<int|string>|bool|int Allows anonymous access to this controller's actions.
     *                                  The actions must be in 'kebab-case'
     */
    protected array|int|bool $allowAnonymous = [];

    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $this->requirePermission(Audit::PERMISSION_VIEW_LOGS);

        Craft::$app->view->registerAssetBundle(AuditDiffAsset::class);

        $itemsPerPage = 100;
        $query = AuditRecord::find()
                               ->orderBy('dateCreated desc')
                               ->with('user')
                               ->limit($itemsPerPage)
                               ->where(['parentId' => null]);
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

        Craft::$app->view->registerAssetBundle(AuditDiffAsset::class);

        $service = Audit::$plugin->auditService;
        $log = $service->getEventById($id);
        $logsInSession = $service->getEventsBySessionId($log->sessionId);

        return $this->renderTemplate('audit/_view', [
            'settings' => Audit::$plugin->getSettings(),
            'log' => $log,
            'logsInSession' => $logsInSession,
            'fieldMap' => $log->getFieldMap(),
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
