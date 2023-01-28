<?php

namespace backend\controllers;

use common\models\BalanceReq;
use common\models\BalanceReqEmployee;
use common\models\Client;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * BalanceReqController implements the CRUD actions for BalanceReq model.
 */
class BalanceReqController extends Controller
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'accept', 'decline', 'history'],
                        'allow' => true,
                        'roles' => ['admin', 'supervisor'],
                    ],
                    [
                        'actions' => ['index', 'accept', 'decline', 'history'],
                        'allow' => false,
                        'roles' => ['ticketOperator', 'client', '?'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        if (!\Yii::$app->user->can('listBalanceReq'))
            throw new \yii\web\ForbiddenHttpException('Access denied');


        $dataProvider = new ActiveDataProvider([
            'query' => BalanceReq::find()->where('status="Ongoing"'),
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }
    public function actionHistory()
    {
        if (!\Yii::$app->user->can('listBalanceReq'))
            throw new \yii\web\ForbiddenHttpException('Access denied');


        $dataProvider = new ActiveDataProvider([
            'query' => BalanceReq::find()->where('status="Accepted" OR status="Declined"'),
        ]);

        return $this->render('history', [
            'dataProvider' => $dataProvider,
        ]);
    }


    public function actionAccept($id)
    {
        if (!\Yii::$app->user->can('updateBalanceReq'))
            throw new \yii\web\ForbiddenHttpException('Access denied');


        $balanceReq = $this->findModel($id);

        if ($balanceReq->status != 'Ongoing') {
            \Yii::$app->session->setFlash('error', "Decision was already made");
            return $this->redirect(['index']);
        }

        $employee_id = \Yii::$app->user->getId();

        // add balance to account
        $client = Client::findOne(['user_id' => $balanceReq->client_id]);
        $client->addBalance($balanceReq->amount);

        // assign responsible employee
        $balanceReqEmployee = new BalanceReqEmployee($id, $employee_id);
        $balanceReq->status = 'Accepted';
        $balanceReq->decisionDate = date('Y-m-d H:i:s');

        if ($balanceReq->validate() && $balanceReqEmployee->validate() && $client->validate()) {
            $balanceReq->save() && $balanceReqEmployee->save() && $client->save();
            \Yii::$app->session->setFlash('success', "Accepted successfuly");
        } else
            \Yii::$app->session->setFlash('error', "Error while trying to save");


        return $this->redirect('index');
    }
    public function actionDecline($id)
    {
        if (!\Yii::$app->user->can('updateBalanceReq'))
            throw new \yii\web\ForbiddenHttpException('Access denied');


        $balanceReq = $this->findModel($id);

        if ($balanceReq->status != 'Ongoing') {
            \Yii::$app->session->setFlash('error', "Decision was already made");
            return $this->redirect(['index']);
        }

        $employee_id = \Yii::$app->user->getId();

        // assign responsible employee
        $balanceReqEmployee = new BalanceReqEmployee($id, $employee_id);
        $balanceReq->status = 'Declined';
        $balanceReq->decisionDate = date('Y-m-d H:i:s');

        if (!$balanceReqEmployee->save() || !$balanceReq->save())
            \Yii::$app->session->setFlash('error', "Error while trying to save");
        else
            \Yii::$app->session->setFlash('success', "Declined successfuly");

        return $this->redirect('index');
    }


    protected function findModel($id)
    {
        if (($model = BalanceReq::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
