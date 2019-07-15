<?php
/**
 */
namespace app\widgets;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\web\View;
/**
* Специальный виджет для создания оболочки для добавления джаваскриптов в представлениях
* Позволяет исключить скрипт из представления и перенести его в раздел ресурсов в нужное место загрузки
* $script=RegisterJSWidget::begin(['position'=>  \yii\web\View::POS_HEAD]); 
* ... вставляем джаваскрипт ,обрамленный <script>...</script>
* $script->end(); 
* 
* @author Viktor Serobaba <vikwebas@gmail.com>
*/
class RegisterJSWidget extends Widget
{
    /**
     * По умолчанию виджет удаляет все теги внутри срипта. 
     * Если надо изменить поведение то менем эту переменную
     * @var bool
     */
    public $deleteTags=true;
   /**
    * Позиция где потом надо будет разместить этот джаваскрипт
    * Используем контанты View::POS_...
    * @var type 
    */
    public $position=View::POS_READY;
    /**
     * Ключ с которым регистрируемся скрипт в общем массиве исполняемых скриптов registerJs
     * По умолчанию подставится автоматически уникальный
     * @var type 
     */
    public $key=null;
    /**
     *
     * @var type 
     */
     public $_ob_void=null;
    public function init()
    {
        ob_start();
        parent::init();

    }
/**
 * 
 * @return type
 */
    public function run()
    {
// Yii::trace(print_r($this,1),__CLASS__."::".__LINE__);
        $content =ob_get_contents();
        ob_end_clean();

        if(!preg_match("{^[^<]*[<]script}i",$content) ){
           return Html::encode($content);
        }
        if($this->deleteTags)
        {
            $content = strip_tags($content,'script');
        }else{
            $content = preg_replace('{<[/]*script[^>]*>}','',$content);
        }
        Yii::$app->view->registerJs($content,$this->position,$this->key);
        return '';
    }
}