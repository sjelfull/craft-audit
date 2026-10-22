<?php

namespace superbig\audit\controllers;

use Craft;
use craft\helpers\Template;
use craft\helpers\UrlHelper;

use craft\web\Controller;
use superbig\audit\assetbundles\indexcpsection\IndexCPSectionAsset;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;
use superbig\audit\models\LogFilter;
use superbig\audit\records\AuditRecord;
use superbig\audit\web\assets\diff\AuditDiffAsset;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class DefaultController extends Controller
{
    /**
     * @var array<int|string>|bool|int Allows anonymous access to this controller's actions.
     *                                  The actions must be in 'kebab-case'
     */
    protected array|int|bool $allowAnonymous = [];

    public function actionIndex(): Response
    {
        $this->requirePermission(Audit::PERMISSION_VIEW_LOGS);

        $request = Craft::$app->getRequest();
        $filter = LogFilter::fromRequest($request);

        $query = AuditRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->with('user')
            ->limit(100);
        $filter->apply($query);

        [$pageInfo, $records] = Template::paginateCriteria($query);
        $logs = array_map(fn(AuditRecord $record) => AuditModel::createFromRecord($record), $records);

        $variables = [
            'logs' => $logs,
            'pageInfo' => $pageInfo,
            'filter' => $filter,
        ];

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'html' => $this->getView()->renderTemplate('audit/_results', $variables),
                'url' => $filter->url(),
            ]);
        }

        return $this->renderTemplate('audit/index', $variables + [
            'options' => Audit::$plugin->auditService->getFilterOptions(),
        ]);
    }

    public function actionDetails(int $id): Response
    {
        $this->requirePermission(Audit::PERMISSION_VIEW_LOGS);

        $service = Audit::$plugin->auditService;
        $log = $service->getEventById($id);

        if (!$log) {
            throw new NotFoundHttpException('Log entry not found');
        }

        $view = $this->getView();
        $view->registerAssetBundle(AuditDiffAsset::class);
        $view->registerAssetBundle(IndexCPSectionAsset::class);

        $variables = [
            'settings' => Audit::$plugin->getSettings(),
            'log' => $log,
            'logsInSession' => $log->sessionId ? $service->getEventsBySessionId($log->sessionId) : [],
            'fieldMap' => $log->getFieldMap(),
        ];

        return $this->asCpScreen()
            ->title($log->getEventLabel() . ($log->title ? ': ' . $log->getDisplayTitle() : ''))
            ->docTitle($log->getEventLabel())
            ->editUrl($log->getCpEditUrl())
            ->addCrumb(Craft::t('audit', 'Audit'), UrlHelper::cpUrl('audit'))
            ->contentTemplate('audit/_detail', $variables)
            ->metaSidebarTemplate('audit/_detail-meta', $variables);
    }

    public function actionPruneLogs()
    {
        $this->requirePermission(Audit::PERMISSION_CLEAR_LOGS);

        $count = Audit::$plugin->auditService->pruneLogs();

        Craft::$app->getSession()->setNotice('Deleted ' . $count . ' records');

        return $this->goBack(UrlHelper::cpUrl('audit'));
    }
}
