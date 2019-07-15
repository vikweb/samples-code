<?php

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $model app\models\LoginForm */

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use  app\helpers\MyHtml;

$this->title =Yii::t('app/user','Вход');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="site-login">
    <h1><?= MyHtml::encode($this->title) ?></h1>

    <p><?=Yii::t('app/user','Введите свои данные для авторизации:')?></p>

    <?php $form = ActiveForm::begin([
        'id' => 'login-form',
       'layout' => 'horizontal',
        'fieldConfig' => [
            'template' => "{label}\n<div class=\"col-lg-3\">{input}</div>\n<div class=\"col-lg-7\">{error}</div>",
            'labelOptions' => ['class' => 'col-lg-2 control-label'],
        ],
    ]); ?>
        <?= MyHtml::activeHiddenInput($model, '_returnUrl') ?>
        <?= $form->field($model, 'useremail')->textInput(['autofocus' => true]) ?>
        <?= $form->field($model, 'password')->passwordInput() ?>
        <?= $form->field($model, 'rememberMe')->checkbox([
            'template' => "<div class=\"col-lg-offset-1 col-lg-3\">{input} {label}</div>\n<div class=\"col-lg-8\">{error}</div>",
        ]) ?>

        <div class="form-group">
            <div class="col-lg-offset-1 col-lg-11">
                <?= MyHtml::submitButton(Yii::t('app/user','Login'), ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
            </div>
        </div>
 <?php ActiveForm::end(); ?>
    <ul><li>
<?= \app\helpers\MyHtml::a(Yii::t('app/user','Зарегистрироваться'),['login/create'])?>
        <li>
<?= \app\helpers\MyHtml::a(Yii::t('app/user','Забыли пароль?'),['login/forgot'])?>
            </ul>

</div>

