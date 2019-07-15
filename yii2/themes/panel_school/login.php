<?php

/* @var $this yii\web\View */
/* @var $model app\models\LoginForm */
/* @var $form yii\widgets\ActiveForm */

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\helpers\MyHtml;
use kartik\typeahead\Typeahead;
use yii\helpers\Url;
use app\widgets\RegisterJSWidget;
use app\widgets\Breadcrumbs;

$this->title =Yii::t('app/user','Вход');
$this->params['breadcrumbs'][] = $this->title;

//page class
$this->params['class']='user-login';
//SEO
$this->registerMetaTag(['name'=>'description','content'=>Yii::t('app/user', 'Введите свои данные для авторизации.')],'description');

?>
<div id="main" role="main" class="user-login">
          <header class="heading">
            <div class="container">
              <div class="row row-15">
                <div class="col-xs-12 text-center">
                  <h1><?= MyHtml::encode($this->title) ?></h1>
                </div>
              </div>
            </div>
          </header>

          <section>
            <div class="container">
              <div class="row">
                <div class="col-xs-12">
    <?php $form = ActiveForm::begin([
        'id' => 'login-form',
        'fieldConfig' => [
            'template' => "<div class=\"col-xs-12 mb15 p10\">{label}\n{input}\n<span class=\"errorsHolder\">{error}</span></div>",
            'labelOptions' => ['class' => ''],
            'errorOptions' => ['class' => 'help-block errorField'],
        ],
        'options'=>[
            'class'=>"form-horizontal form-newstyle bg-lightgray p15 border-rounded stepForm",
            ],
  //      'enableClientValidation'=>false
    ]); ?>   
                    <div class="form-group ph15">
                      <div class="col-xs-12 col-md-6 mb30">
                        <div class="p15">
                            <h3><?=Yii::t('app/user', 'Добро пожаловать в Краснодарскую школу!');?></h3>
                          <p><?=Yii::t('app/user', 'Авторизируйтесь, чтобы получить доступ к вашим курсам.');?></p>
                          <div class="alert alert-info"><?=Yii::t('app/user', 'Еще не зарегистрированны в нашей школе?&nbsp;<a href="{url}">Регистрация</a>',['url'=>"//".Url::toRoute(['@globalBaseUrl/login/create'])]);?></div>
                        </div>
                      </div>
                      <div class="col-xs-12 col-md-6 mb30" id="users-model-form">
                 
                        <div class="row">
                          <div class="col-xs-12 mb15 p10">
                            <h4 class="mb15 mt30 borderedTitle"><?=Yii::t('app/user', 'Форма авторизации');?></h4>
                              <p><?=Yii::t('app/user','Введите свои данные для авторизации:')?></p>
                          </div>         
         <?= MyHtml::activeHiddenInput($model, '_returnUrl') ?>
        <?= $form->field($model, 'userlogin')->textInput(['autofocus' => true]) ?>
        <?= $form->field($model, 'password')->passwordInput() ?>
        <?= $form->field($model, 'rememberMe')->checkbox([
            'template' => "<div class=\"col-lg-offset-1 col-lg-3\">{input} {label}</div>\n<div class=\"col-lg-8\">{error}</div>",
        ]) ?>          

    <?= MyHtml::submitButton(Yii::t('app/user', 'Войти'), ['class' => 'btn btn-red pull-left']) ?>
       <?= \app\helpers\MyHtml::a(Yii::t('app/user','Забыли пароль?'),"//".Url::toRoute(['@globalBaseUrl/login/forgout']),['class'=>'btn btn-link pull-right'])?>
  </div>
                 
                      </div>
                    </div>
    <?php ActiveForm::end(); ?>         
                </div>
              </div>
            </div>
          </section>
        </div>
