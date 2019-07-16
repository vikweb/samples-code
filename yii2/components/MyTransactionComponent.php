<?php

/**
 * Component MyTransactionComponent
 * Класс по управлению добавлением записей  в журнал управления счетом  пользователя 
 * 
 * @author   Serobaba Viktor  <vikwebas@gmail.com>
 */
/*
 * Tr_type
 *  'addMoney','createContract'
 *  'addMoneyBalance','withdrawalCreate','withdrawalPaid','withdrawalError'
 *
 */

namespace app\components;

use Yii;
use app\models\UserBalancesModel;
use app\helpers\DateHelper;
use yii\helpers\ArrayHelper;

class MyTransactionComponent extends \yii\base\Component
{

    private $_jurnal = null;
    private $_user_id = null;
    private $_errors = [];


    /**
     * Инициализайция класса
     */
    public function init()
    {
        parent::init();
        $this->_user_id = Yii::$app->user->identity->getID();
        YII::trace(print_r($this, 1), __METHOD__ . ":" . __LINE__);
    }



    /**
     * Добавление к заявке ученика разрешений по ключу для доступа к заявке
     * @param int $user_id
     * @param int $order_id
     * @param string $hash_128
     * @return boolean
     * @throws \Exception
     */
    public function orderAddAccessKey($user_id, $order_id, $hash_128)
    {
        // читаем пользователя для добавления
        /* @var  $user \app\models\UsersModel */
        $user = \app\models\UsersModel::find()->where(['id_user' => $user_id])->one();
        /* @var  $order \app\models\orders\OrdersModel */
        $order = \app\models\orders\OrdersModel::find()->where(['id_order' => $order_id])->one();
        /* @var  $access \app\models\lms\LmsAccessModel */
        $access = null;        
        // проверка на наличие найденных записей user и order
        if (!($order AND $user AND $order->user_id == $user->id_user)) {
            $this->addError('orderCreateAccessKeyProgram', Yii::t('app/error', 'Не удалось найти заявку пользователя.'));
            return false;
        }
        
        // запускаем транзакцию для контроля выполнения добавления данных во много таблиц 
        $transaction = Yii::$app->db->beginTransaction();
        try {

            // добавляем дополнительные параметры в журнал операций
            $params['order_id'] = $order_id;
            $params['hash_128'] = $hash_128;
            $params['user_fullname'] = $user->fullname;
            // поиск ключя из списка ключей
            /*@var $accessHashes \app\models\lms\LmsAccessSetHashesModel|null */
            if (!($accessHashes = \app\models\lms\LmsAccessSetHashesModel::findHash($hash_128))) {
                $this->addError('orderCreateAccessKeyProgram', Yii::t('app/error', 'Не удалось найти ключ доступа {hash_128}.', ['hash_128' => $hash_128]));
            }
            else {
                
                Yii::trace(print_r($accessHashes->getAttributes(), 1), __METHOD__ . ':' . __LINE__);
                $access = $accessHashes->access;
                //проверка на наличие  в базе существующего активного разрешения с другим ключем доступын с сегодняшней датой
                //*** пока не сделано
                /* @var $accessHashes \app\models\lms\LmsAccessSetHashesModel */
                $hashes = $accessHashes->getHashes128();
                
                $index = array_search($hash_128, $hashes);
                Yii::trace([$index,$hash_128,$hashes], __METHOD__ . '::' . __LINE__);
                if ( ($index!==false) ){
                    //подготовка удаления ключа из списка доступных ключей
                    unset($hashes[$index]);
                    Yii::trace([$index,$hash_128,$hashes], __METHOD__ . '::' . __LINE__);
                    $accessHashes->setScenario($accessHashes::SCENARIO_UPDATE_HASHES);
                    $accessHashes->setHashes128($hashes);
                    
                    // подготовка добавления ключа в список доступных ключей ученика 
                    $key = new \app\models\lms\LmsAccessKeysModel();
                    $key->setScenario($key::SCENARIO_CREATE);
                    $key->user_id = $order->user_id;
                    $key->access_id = $accessHashes->access_id;
                    $key->hash_128 = (string) $hash_128;
                    $key->access_type = $accessHashes->access->access_type;
                    if ($accessHashes->date_start) {
                        $key->key_date_start = $accessHashes->date_start." 00:00:00";
                    }
                    else {
                        $key->key_date_start = DateHelper::asDateGMT();
                    }
                    if($key->access_type!=='unlimited'){
                        $timestampEnd = DateHelper::gmtToLocaleTime(DateHelper::asTimestamp($key->key_date_start) + $accessHashes->day * 24 * 60 * 60);
                        $key->key_date_end = DateHelper::asDate($timestampEnd);
                    }

                    if (!( $key->save() && $accessHashes->save())) {
                        $this->addError('orderCreateAccessKeyProgram', Yii::t('app/error', 'Не удалось добавить ключ {hash_128}.', ['hash_128' => $hash_128]));
                        Yii::error([$key->getErrors(), $this->getErrors()], __METHOD__ . ":" . __LINE__);
                    }
                    // подготовка изменения статуса ордера на "доступный ученику"
                    $order->status = '100';
                    $order->setScenario($order::SCENARIO_UPDATE_STATUS);
                    // подготовка при добавлении (или изменении) значения привязанного ключа
                    $meta_key=$order->getMetaKeyModel('meta_access_key_id');
                    $meta_key->o_meta_value=(string)$key->getPrimaryKey();
                    if (!( $meta_key->save() && $order->save() )) {
                        $this->addError('orderCreateAccessKeyProgram', Yii::t('app/error', 'Не удалось внести изменения в ордер при оформлении ключа.'));
                        Yii::error([$meta_key->getErrors(), $this->getErrors()], __METHOD__ . ":" . __LINE__);
                    }
                }
                else {
                    $this->addError('orderCreateAccessKeyProgram', Yii::t('app/error', 'Не удалось найти ключ доступа {hash_128}.', ['hash_128' => $hash_128]));
                }
                // создаем запись в журнале транзакций пользователя
                /*    if(($modelTransaction=TransactionsModel::newRow(
                  $UserBalance->id_balance,
                  'addMoneyBalance',
                  'account',
                  $sum*(+1),
                  $UserBalance->sum_account,
                  $UserBalance->sum_contract
                  )->addObject('tr_in',$modelImport->id_in)->addParams($params)) AND !$modelTransaction->save()){
                  $this->addErrors(array('addMoneyBalance'=> \app\helpers\MyHtml::implodeAllErrors(",",$modelTransaction->getErrors())));
                  } */
            }
            // проверяем наличие ошибок для отмены совершенных операций
            if ($this->hasErrors('orderCreateAccessKeyProgram')) {
                Yii::error( $this->getErrors(), __METHOD__ . ":" . __LINE__);
                $transaction->rollBack();
            }
            else {
                // создаем событие для записи изменений в других таблицах
                $event = new TransactionEvent();
                $event->user = $user;
                //  $event->modelTransaction = @$modelTransaction;
                $event->component = $this;
                $event->result=true;
                $this->trigger('orderCreateAccessKeyProgram', $event);
                if (!$event->result) {
                    Yii::error( ['result'=>$event->result,$this->getErrors()], __METHOD__ . ":" . __LINE__);
                    $transaction->rollBack();
                    return false;
                }
                $transaction->commit();
                // отправляем сообщение пользователю $transactioncallback
                $message = Yii::$app->mailer->compose('tr-order-add-access-key-html', [
                    'user' => $user,
                    'access' => $access,
                    'program' => ArrayHelper::getValue($access->program_all, '0', []),
                ]);
                $message->setTo($user->email)
                        ->setSubject(Yii::t('app/email', 'Вам предоставлен доступ к программе обучения {access[access_name]}.'))
                        ->send();
                return $user;
            }
        }
        catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
        return false;
    }

    
    /**
     * Добавление разрешения для студента по ключу доступа
     * @param int $user_id
     * @param string $hash_128
     * @return boolean
     * @throws \Exception
     */
    public function userAddAccessKey($user_id, $hash_128)
    {
        // читаем пользователя для добавления
        /* @var  $user \app\models\UsersModel */
        $user = \app\models\UsersModel::find()->where(['id_user' => $user_id])->one();
        /* @var  $access \app\models\lms\LmsAccessModel */
        $access = null;        
        // проверка на наличие найденных записей user и order
        if (!($user)) {
            $this->addError('userAddAccessKey', Yii::t('app/error', 'Не удалось найти пользователя.'));
            return false;
        }
        
        // запускаем транзакцию для контроля выполнения добавления данных во много таблиц 
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // добавляем дополнительные параметры в журнал операций
            $params['hash_128'] = $hash_128;
            $params['user_fullname'] = $user->fullname;
            // поиск ключя из списка ключей
            if (!($accessHashes = \app\models\lms\LmsAccessSetHashesModel::findHash($hash_128))) {
                $this->addError('userAddAccessKey', Yii::t('app/error', 'Не удалось найти ключ доступа {hash_128}.', ['hash_128' => $hash_128]));
            }
            else {
                
                Yii::trace(print_r($accessHashes->getAttributes(), 1), __METHOD__ . ':' . __LINE__);
                $access = $accessHashes->access;
                //проверка на наличие  в базе существующего активного разрешения с другим ключем доступын с сегодняшней датой
                //*** пока не сделано
                /* @var $accessHashes \app\models\lms\LmsAccessSetHashesModel */
                $hashes = $accessHashes->getHashes128();
                $index = array_search($hash_128, $hashes);
                if ( ($index!==false) ){
                    //подготовка удаления ключа из списка доступных ключей
                    unset($hashes[$index]);
                    $accessHashes->setScenario($accessHashes::SCENARIO_UPDATE_HASHES);
                    $accessHashes->setHashes128($hashes);
                    
                    // подготовка добавления ключа в список доступных ключей ученика 
                    $key = new \app\models\lms\LmsAccessKeysModel();
                    $key->setScenario($key::SCENARIO_CREATE);
                    $key->user_id = $user->id_user;
                    $key->access_id = $accessHashes->access_id;
                    $key->hash_128 = (string) $hash_128;
                    $key->access_type = $accessHashes->access->access_type;
                    if ($accessHashes->date_start) {
                        $key->key_date_start = $accessHashes->date_start." 00:00:00";
                    }
                    else {
                        $key->key_date_start = DateHelper::asDateGMT();
                    }
                    if($key->access_type!=='unlimited'){
                        $timestampEnd = DateHelper::gmtToLocaleTime(DateHelper::asTimestamp($key->key_date_start) + $accessHashes->day * 24 * 60 * 60);
                        $key->key_date_end = DateHelper::asDate($timestampEnd);
                    }

                    if (!( $key->save() && $accessHashes->save())) {
                        $this->addError('userAddAccessKey', Yii::t('app/error', 'Не удалось добавить ключ {hash_128}.', ['hash_128' => $hash_128]));
                        Yii::error([$key->getErrors(), $this->getErrors()], __METHOD__ . ":" . __LINE__);
                    }
                }
                else {
                    $this->addError('userAddAccessKey', Yii::t('app/error', 'Не удалось найти ключ доступа {hash_128}.', ['hash_128' => $hash_128]));
                }
                // создаем запись в журнале транзакций пользователя
                /*    if(($modelTransaction=TransactionsModel::newRow(
                  $UserBalance->id_balance,
                  'addMoneyBalance',
                  'account',
                  $sum*(+1),
                  $UserBalance->sum_account,
                  $UserBalance->sum_contract
                  )->addObject('tr_in',$modelImport->id_in)->addParams($params)) AND !$modelTransaction->save()){
                  $this->addErrors(array('addMoneyBalance'=> \app\helpers\MyHtml::implodeAllErrors(",",$modelTransaction->getErrors())));
                  } */
            }
            // проверяем наличие ошибок для отмены совершенных операций
            if ($this->hasErrors('userAddAccessKey')) {
                Yii::error( $this->getErrors(), __METHOD__ . ":" . __LINE__);
                $transaction->rollBack();
            }
            else {
                // создаем событие для записи изменений в других таблицах
                $event = new TransactionEvent();
                $event->user = $user;
                //  $event->modelTransaction = @$modelTransaction;
                $event->component = $this;
                $event->result=true;
                $this->trigger('userAddAccessKey', $event);
                if (!$event->result) {
                    Yii::error( ['result'=>$event->result,$this->getErrors()], __METHOD__ . ":" . __LINE__);
                    $transaction->rollBack();
                    return false;
                }
                $transaction->commit();
                // отправляем сообщение пользователю $transactioncallback
                $message = Yii::$app->mailer->compose('tr-user-add-access-key-html', [
                    'user' => $user,
                    'access' => $access,
                    'key'=>$key,
                    'program' => ArrayHelper::getValue($access->program_all, '0', []),
                ]);
                $message->setTo($user->email)
                        ->setSubject(Yii::t('app/email', 'Вам предоставлен доступ к программе обучения {access[access_name]}.'))
                        ->send();
                return $user;
            }
        }
        catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
        return false;
    }
    
    
    
    /*     * *************************************************************************
     * Returns a value indicating whether there is any validation error.
     * @param string attribute name. Use null to check all attributes.
     * @return boolean whether there is any error.
     */

    public function hasErrors($attribute = null)
    {
        if ($attribute === null) {
            return $this->_errors !== array();
        }
        else {
            return isset($this->_errors[$attribute]);
        }
    }

    /**
     * Returns the errors for all attribute or a single attribute.
     * @param string attribute name. Use null to retrieve errors for all attributes.
     * @return array errors for all attributes or the specified attribute. Empty array is returned if no error.
     */
    public function getErrors($attribute = null)
    {
        if ($attribute === null) {
            return $this->_errors;
        }
        else {
            return isset($this->_errors[$attribute]) ? $this->_errors[$attribute] : array();
        }
    }

    /**
     * Adds a new error to the specified attribute.
     * @param string attribute name
     * @param string new error message
     */
    public function addError($attribute, $error)
    {
        $this->_errors[$attribute][] = $error;
        Yii::error($error, __METHOD__ . ':' . __LINE__);
    }

    /**
     * Adds a list of errors.
     * @param array a list of errors. The array keys must be attribute names.
     * The array values should be error messages. If an attribute has multiple errors,
     * these errors must be given in terms of an array.
     * You may use the result of {@link getErrors} as the value for this parameter.
     * @since 1.0.5
     */
    public function addErrors($errors)
    {
        if (is_array($errors)) {
            foreach ($errors as $attribute => $error) {
                if (is_array($error)) {
                    foreach ($error as $e) {
                        $this->addError($attribute, $e);
                    }
                }
                else {
                    $this->addError($attribute, $error);
                }
            }
        }
    }

}

class TransactionEvent extends \yii\base\Event
{
    public $user;
    public $balance;
    public $modelTransaction;
    public $params = [];
    public $component;
    public $result = false;

}
