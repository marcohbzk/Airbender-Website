<?php

namespace backend\controllers;

use common\models\Ticket;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

class TicketController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'update', 'view', 'checkin'],
                        'allow' => true,
                        'roles' => ['admin', 'supervisor', 'ticketOperator'],
                    ],

                    [
                        'actions' => ['index', 'update', 'view', 'checkin'],
                        'allow' => false,
                        'roles' => ['client', '?'],
                    ],

                ],
            ],
        ];
    }

    public function actionIndex($flight_id = null, $employee_id = null, $client_id = null)
    {
        if (!\Yii::$app->user->can('listTicket'))
            throw new \yii\web\ForbiddenHttpException('Access denied');

        $dataProvider = new ActiveDataProvider(['query' => Ticket::find()]);

        if ($flight_id != null)
            $dataProvider = new ActiveDataProvider(['query' => Ticket::find()->where(['flight_id' => $flight_id])->innerJoinWith('receipt', 'tickets.receipt_id = receipts.id')->andWhere(['receipts.status' => 'Complete']),]);

        if ($employee_id != null)
            $dataProvider = new ActiveDataProvider(['query' => Ticket::find()->where(['checkedin' => $employee_id]),]);

        if ($client_id != null)
            $dataProvider = new ActiveDataProvider(['query' => Ticket::find()->where(['tickets.client_id' => $client_id])->innerJoinWith('receipt', 'tickets.receipt_id = receipts.id')->andWhere(['receipts.status' => 'Complete'])]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        if (!\Yii::$app->user->can('readTicket'))
            throw new \yii\web\ForbiddenHttpException('Access denied');


        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionCheckin($id)
    {
        if (!\Yii::$app->user->can('updateTicket'))
            throw new \yii\web\ForbiddenHttpException('Access denied');


        $model = $this->findModel($id);


        if ($model->receipt->status != 'Complete') {
            \Yii::$app->session->setFlash('error', "Ticket was not paid for yet");
            return $this->redirect(['index']);
        }

        $model->checkedIn = \Yii::$app->user->identity->getId();

        if ($model->save())
            \Yii::$app->session->setFlash('success', "Ticket checked in successfully");
        else
            \Yii::$app->session->setFlash('error', "There was an error while trying to check in the ticket!");

        return $this->redirect(['index']);
    }

    protected function findModel($id)
    {
        if (($model = Ticket::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
