<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "{{%user_counters}}".
 *
 * @property integer $id_counter
 * @property integer $subaccountid	
 * @property integer $user_id
 * @property integer $currency_id
 * @property double $sum_account
 * @property double $sum_contract
 */
class UserBalancesModel extends \app\components\MyActiveRecord
{
    const FIELD_SUM_ACCOUNT='sum_account';
    const FIELD_SUM_CONTRACT='sum_contract';
    
    
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%user_balances}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'currency_id'], 'required'],
            [['user_id', 'currency_id','company_id'], 'integer'],
            [['sum_account', 'sum_contract'], 'number'],
            [['sum_contract'], 'required'],
            [['currency_id'], 'validateCurrency','on'=>'create'],
            [['user_id'], 'validateUser'],
            [['company_id'], 'validateCompany','on'=>'create'],
            [['user_id'], 'validateUnicalBalance','on'=>'create'],
            [['sum_account', 'sum_contract'], 'number','on'=>'update-balance'],
            [['subaccountid'], 'validateSubaccountid','skipOnEmpty'=>false,'on'=>'create'],
              [['subaccountid'], 'required','on'=>'create'],            
        ];
    }
         /**
          * проверка типа валюты для баланса у пользователя
          * @param type $attribute
          * @param type $params
          * @return boolean
          */
         function validateCurrency($attribute,$params) {
           if($this->isNewRecord  AND self::find()->where('currency_id=:currency_id AND user_id=:user_id AND company_id=:company_id',
                   [':currency_id'=>$this->currency_id,':user_id'=>$this->user_id,':company_id'=>intval($this->company_id)])->count()){
                   $this->addError('currency_id', Yii::t('app/error', 'У пользователя уже есть баланс для учета этой  валюты.'));
                   return false;
           }
           return true;
         }
        /**
         * добавляем и проверяем субсчет пользователю  на наличие свободного уникального номера
         * @param type $attribute
         * @param type $params
         */
         public function validateSubaccountid($model,$attribute){
                    if(!($user= UsersModel::findOne($this->user_id))){
                        $this->addError('user_id', Yii::t('app/error','Пользователь для создания субсчета не найден.'));
                        return false;
                    };
                    $fulluserid=$user->fulluserid;
Yii::trace(print_r([$fulluserid,$this->currency_id,$this->company_id],1),__METHOD__."::".__LINE__);  
   
                    //вычисляем идентификатор субсчет для физлица
                    if(!(intval($this->company_id)>0)){
                            $identify=$fulluserid."00".sprintf("%'.02d",$this->currency_id);
                            $this->subaccountid=1*$identify;
                            if(self::find()->where('subaccountid=:subaccountid',[':subaccountid'=>$this->subaccountid])->count()){
                                $this->addError('user_id', Yii::t('app/error','Cубсчет для пользователя уже существуюет. Проверьте данные своего профиля.'));                        
                                return false;
                            }
                        return ;
                    }
                    //вычисляем идентификатор для субсчета компании пользователя
                    //считаем сколько субсчетов в конкретной валюте открыто у пользователя
                    $count= self::find()->where('user_id=:user_id AND currency_id=:currency_id AND company_id>0',[':user_id'=>$this->user_id,':currency_id'=>$this->currency_id])->count();
                    //создаем цикл для перебора для защиты от случайного совпадения идентификаторов при одномоментном добавлении
                    do{
                            $count=$count+1;
                            // создаем специальный идентификатор пользователя
                            $identify=$fulluserid.sprintf("%'.02d",$count).sprintf("%'.02d",$this->currency_id);
                            $this->subaccountid=1*$identify;
Yii::trace(print_r([$this->subaccountid,$this->currency_id],1),__METHOD__."::".__LINE__);            
                    }while($count<100 AND (self::find()->where('subaccountid=:subaccountid',[':subaccountid'=>$this->subaccountid])->count()));
                    if($count>=100){
                        $this->addError('user_id', Yii::t('app/error','Лимит субсчетов для компаний пользователя исчерпан. Обратитесь к администрации сайта.'));
                        return false;
                    }
                    return  true;
        }         
         /**
          * Проверка пользователя на реальное существование в нашей базе
          * @param type $attribute
          * @param type $params
          * @return boolean
          */
        function validateUser($attribute,$params){
            if(!UsersModel::findOne($this->user_id)){
                      $this->addError('user_id', Yii::t('app/error', 'Не найден пользователь с указанным ID.'));
                   return false;
            }
            return true;
         }
         /**
          * проверка на наличие уникального значения [user_is,currency_id,company_id]
          * @param type $attribute
          * @param type $params
          * @return boolean
          */
        function validateUnicalBalance($attribute,$params){
            if(self::find()->where('user_id=:user_id AND currency_id=:currency_id AND company_id=:company_id',
                    array(':user_id'=>$this->user_id,':currency_id'=>$this->currency_id,':company_id'=>intval($this->company_id)))->count()){
                      $this->addError('user_id', Yii::t('app/error', 'Уже существует баланс для указанной компании или пользователя.'));
                   return false;
            }
            return true;
         }         
         /**
          * Проверка наличия компании в базе данных
          * @param type $attribute
          * @param type $params
          * @return boolean
          */
        function validateCompany($attribute,$params){
            // если $this->conpany_id не равно 0 и компания не найдена в списке компаний тогда ошибка
            if($this->company_id AND !CompaniesModel::find()->where('id_company=:company_id AND user_id=:user_id',
                    array(':company_id'=>$this->company_id,':user_id'=>$this->user_id))->count()){
                      $this->addError('user_id', Yii::t('app/error', 'Не найдена компания  с указанным ID.'));
                   return false;
            }

            return true;
         }         
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_balance' => Yii::t('app/balance', 'Id Balance'),
            'user_id' => Yii::t('app/balance', 'User ID'),
            'currency_id' => Yii::t('app/balance', 'Currency ID'),
            'company_id' => Yii::t('app/balance', 'Company ID'),            
            'sum_account' => Yii::t('app/balance', 'Sum Account'),
            'sum_contract' => Yii::t('app/balance', 'Sum Contract'),
            //вычисляемые поля
            'sumAccountCur' => Yii::t('app/balance', 'Sum Account'),
             'sumContactCur' => Yii::t('app/balance', 'Sum Contract'),
            'companyName' => Yii::t('app/balance', 'Company Name'),
            'user.fullname' => Yii::t('app/balance', 'User Name'),  
            
        ];
    }
    /**
     * связываем с таблицой валют
     * @return type
     */
    public function getCurrency()
    {
        return $this->hasOne(CurrencyModel::className(), [ 'id_currency'=>'currency_id'])->alias('currency');
    }
    /**
     * связываем с таблицой пользователей
     * @return type
     */
    public function getUser()
    {
        return $this->hasOne(UsersModel::className(), [ 'id_user'=>'user_id'])->alias('user');
    }    
    
    /**
     * связываем с таблицой компаний
     * @return type
     */
    public function getCompany()
    {
        return $this->hasOne(CompaniesModel::className(), [ 'id_company'=>'company_id'])->alias('company');
    }  
    
    /**
     *  сохранение данных баланса пользователя или компании 
     * @param type $balance_in - integet|object|array Подробно описано в self::modelRow($balance_in);
     * @param type $field
     * @param type $addValue
     * @return boolean
     */
          static public function saveBalanceRow($balance_in, $field='', $addValue=0){
                  if($balance=self::modelRow($balance_in)){
//   Yii::trace(print_r($balance->getAttributes(),true),__METHOD__.':'.__LINE__);
                        if(array_key_exists($field,$balance->getAttributes())){
                            $balance->setScenario('update-balance');
                            // изменение баланса без протокола
                             $balance->$field=$balance->$field+$addValue;
    //Yii::trace(print_r($balance->getAttributes(),true),__METHOD__.':'.__LINE__);
                             if( $balance->save() ){
                                    return $balance;
                             }
                        }else{
                            Yii::error("No field `{$field}` in table.",__METHOD__.':'.__LINE__);
                            return false;
                        }
                   }
               Yii::error("No find `\$balance_in` in table.",__METHOD__.':'.__LINE__);
               return false;
           }

        /**
         * создает новый баланс только для проверенного сочитания
         * @param type $user_id
         * @param type $currency_id
         * @param type $company_id
         * @return boolean|\self
         */
         static public function addNewBalance($params=array()){
                $balance=new self();
                $balance->load($params,'');
                if(intval($balance->company_id) AND !($balance->user_id) AND $company= CompaniesModel::findOne($balance->company_id)){
                    $balance->user_id=$company->user_id;
                }
                $balance->sum_account=0;
                $balance->sum_contract=0;
                $balance->setScenario('create');
 Yii::trace(\yii\helpers\ArrayHelper::toArray($balance), __METHOD__ . '::' . __LINE__);      
                if($balance->save()){
                    return $balance;
                }
Yii::trace($balance->getErrors(), __METHOD__ . '::' . __LINE__);
                return false;
        }
    /**
     * возвращает объект - баланс субсчета по его id , или возвращаем сам object
     * если $balance =objcet то возврашаем данные без изменений
     * если $balance =id баланса то ищем и возвращаем баланс по его ID
     * если $balance =[user_id,'currency_id] то ищет и возвращает баланс физлица в указанной валюте
     * если $balance =[country_id,'currency_id] то ищет и возвращает баланс компании в указанной валюте
     * @param integer|object|array  $balance_in 
     */
    static public function modelRow($balance_in){
        
        // если передан сам баланс то возвращаем его 
        if(is_object($balance_in) AND get_class($balance_in)==self::className()){
Yii::trace($balance_in->attributes, __METHOD__ . '::' . __LINE__);            
            return $balance_in;
        }else{
Yii::trace($balance_in, __METHOD__ . '::' . __LINE__);            
        }
        //если передан идентификатор субсчета subaccountid то  и возвращаем баланс
        if(is_string($balance_in) AND preg_match('/^[0-9]{11}$/',$balance_in) AND (1*$balance_in>10000000000)){
             return self::find()->joinWith(['user','currency','company'])->where('subaccountid=:subaccountid',
                              [':subaccountid'=>$balance_in])->one() ;
        }
        // если передан id баланса то ищем и возвращаем баланс
        if(is_int($balance_in)){
            return self::find()->joinWith(['user','currency','company'])->where('id_balance=:id_balance',
                              [':id_balance'=>$balance_in])->one() ;
        }

        // если передены параметры для поиска баланса физлица [user_id,'currency_id] или компании ['currency_id,company_id]
        $params=[];
        if(is_array($balance_in) AND array_key_exists('currency_id',$balance_in) AND $params[':currency_id']=$balance_in['currency_id']){
                if(array_key_exists('company_id',$balance_in) AND $params[':company_id']=$balance_in['company_id']){
                       // поиск по данны компании и валюте (субсчет компании)
                        return self::find()->alias('balance')->joinWith(['user','currency','company'])->where('company_id=:company_id AND currency_id=:currency_id',$params)->one() ;        
                }else if(!array_key_exists('company_id',$balance_in)  AND array_key_exists('user_id',$balance_in) AND $params[':user_id']=$balance_in['user_id']){
                    // поиск по данным пользователя и валюте (субсчет физиче6ского лица)
                        return self::find()->alias('balance')->joinWith(['user','currency','company'])->where('balance.user_id=:user_id AND currency_id=:currency_id AND company_id=0',$params)->one() ;        
                }else if(@$params[':company_id']==='0' AND array_key_exists('user_id',$balance_in) AND $params[':user_id']=$balance_in['user_id']){
                    // поиск по пользователю и валюте если данные компании равны 0 (субсчет физического лица)
                        return self::find()->alias('balance')->joinWith(['user','currency','company'])->where('balance.user_id=:user_id AND currency_id=:currency_id AND company_id=:company_id',$params)->one() ;        
                }
                return false;               
        }
        Yii::error("Непонятный тип переменной передан в \$balance_in.",__METHOD__.':'.__LINE__);
        throw new NotFoundHttpException(Yii::t('app/error', 'Для поиска баланса переданы неопределенные данные.'));
    }
        
        
    /**
     * выводим название компании или пользователя в зависимости от того , чей это баланс
     * @return string
     */
    public function getCompanyName(){
        if($this->company){
            return $this->company->name;
        }else if($this->user){
            return $this->user->fullName;
        }
        return '';
    }
    /** 
     * Выводит остаток на субсчете с валютой субсчета
     * @return type
     */    
    public function getSumAccountCur(){
        return sprintf('%01.2f '.$this->currency->value_currency, $this->sum_account);
    }
    /** 
     * Выводит остаток замороженных средств в контрактах  с валютой субсчета
     * @return type
     */    
    public function getSumContactCur(){
        return sprintf('%01.2f '.$this->currency->value_currency, $this->sum_contract);
    }
        /**
         * находит абсолютно все валюты и все балансы пользователя.
         * Результат - массив типа balance[company][currency]
         * @param <type> $user_id
         * @return <type>
         */
       static public function findAllUserBalances($user_id){
          if($moneys=self::find()->with(['currency','company'])->where('user_id=:user_id AND currency.id_currency IS NOT NULL',[':user_id'=>$user_id])->all() ){
              $result=array();
              foreach ($moneys AS $value){
                 $result[$value->company_id][$value->currency_id]=$value;
              }
              return $result;
          }
          return false;
        }
        
        /**
         * находит абсолютно все балансы пользователя.
         * Результат - массив типа balance[company][currency]
         * @param <type> $user_id
         * @return <type>
         */
       static public function findAllSubaccountSelected($user_id){
           
          if($moneys=self::find()->alias('balance')->joinWith(['currency','company'])
                  ->where('(company.del<>1 OR balance.company_id=0) AND balance.user_id=:user_id',[':user_id'=>$user_id])
                  ->orderBy('subaccountid ASC')->all() ){
              $result=array();
              foreach ($moneys AS $value){
                 $result[$value->subaccountid]=$value->getCompanyName();
              }
              return $result;
          }
          return false;
        }
}
