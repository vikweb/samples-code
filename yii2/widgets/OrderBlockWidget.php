<?php
/**
 * Специальный виджет для создания и обработки заявок разбросанных в разных местах
* @author   Serobaba Viktor  <vikwebas@gmail.com>
 */
namespace app\widgets;
use \Yii;
use app\helpers\MyHtml;
use yii\web\View;
use app\models\orders\OrderTrialLessonForm;

class OrderBlockWidget extends \app\components\MyWidget
{
    /**
     * при наличии активного продукта поле заполняется в view 
     * @var  app\models\CoursesModel|false 
     */
    public $modelCourses=false;
    /**
     * статус запроса  change/submit
     * 
     * @var string 
     */
    public $action='change';
    /**
     * Тип заявки course|trial_lesson|lesoon
     * @var string 
     */
    public $order_type='trial_lesson';
    public $redirectUrl='';
    /**
     * Кто подает заявку
     * @var null|int 
     */
    private $_user_id=null;
    
/**
 * запуск виджета
 * @return text
 */
    public function run()
    {
          if(!Yii::$app->user->isGuest){
             $this->_user_id=Yii::$app->user->getId();
          }
          if(!$this->modelCourses AND $this->order_type=='course'){
              Yii::trace('Нет данных о модели продукта',__METHOD__.":".__LINE__);
              return false;
          }
         $widgetData=['modelCourses'=>$this->modelCourses];
         if($this->action=='change'){
             $form=$this->change_form($widgetData);
         }else{
              Yii::trace('Нет данных о action',__METHOD__.":".__LINE__);
         }
         return $form;
    }
   /**
    * выводим форму подачи заявки
    * @param type $widgetData
    * @return type
    */
      function change_form($widgetData){
          Yii::trace('Start change_form',__METHOD__.":".__LINE__);
          
           $dataForm= new OrderTrialLessonForm();
           $dataForm->user_id=$this->_user_id;
           $dataForm->order_type=$this->order_type;
Yii::trace($dataForm->toArray(),__METHOD__.":".__LINE__);
           $widgetData['formOrder']=&$dataForm;
           if ($dataForm->send(Yii::$app->request->post()) ) {
                $widgetData['form_submit']=true;
                return $this->render('order-trial_lesson-form-block',$widgetData);
           }
Yii::trace($dataForm->getErrors(),__METHOD__.":".__LINE__);
           return $this->render('order-trial_lesson-form-block',$widgetData);
      }

    
}
