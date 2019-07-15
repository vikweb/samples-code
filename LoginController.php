<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\models\UsersModel;
use yii\helpers\ArrayHelper;
use \yii\helpers\Url;

class LoginController extends \app\components\MyMembersController
{

    public $layout = 'nologin';

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return parent::behaviors() + [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'forgot', 'login', 'create', 'activate', 'city-list'],
                'rules' => [
                    [
                        'actions' => ['logout', 'city-list', 'login', 'create'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['forgot', 'login', 'create', 'activate', 'city-list'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    //   'logout' => ['','get'],    
                    //         'forgot' => ['','post'],
                    'login' => ['', 'get', 'post'],
                    //    'create' => ['','post'],
                    'activate' => ['', 'get'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->actionLogin();
    }

    /**
     * Login action.
     *
     * @return string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }
        return $this->render('login', [
                    'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return string
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();
        return $this->redirectLogin();
    }

    /**
     *  Восстановление пароля пользователя через отправку сообщения на зарегистрированный емайл
     * @param type $token
     * @return type
     */
    public function actionForgot($token = false, $tokien2 = '')
    {
        if ((!Yii::$app->user->isGuest)) {
            $this->goHome();
        }
        $request = Yii::$app->request;
        $pageData = array();
        $user = new UsersModel();
        $pageData['user'] = &$user;
        Yii::trace($request->post('UsersModel', false), __METHOD__ . "::" . __LINE__);
        // collect user input data
        if (($token&&$tokien2)) {
            // сохранение нового пароля 
            if (UsersModel::isPasswordResetTokenValid($token)) {
                $newpw = UsersModel::find()->where("active=1 AND auth_key=:auth_key", array(':auth_key' => $token))->one();
                if ($newpw && md5($newpw->email)==$tokien2) {
                    $user = $newpw;
                    $user->setScenario(UsersModel::SCENARIO_FORGOT2);
                    if ($request->isPost && $newpw->load( $request->post() ) && $user->save()) {
                                $pageData['message'] = Yii::t('app/login', 'Ваш пароль был удачно изменён.');
 //отправляем емайл
                                $hasSendMail = Yii::$app->mailer->compose('user-forgot-ok-html', [
                                                        'user' => $user,
                                                ])
                                                ->setTo($user->email)
                                                ->setFrom([Yii::$app->params['from_email'] => Yii::$app->params['from_name']])
                                                ->setTextBody('')
                                                ->setSubject(Yii::t('app/email', 'Ваш пароль удачно изменен.'))->send();
                                if ($hasSendMail) {
                                    Yii::info('user_forgot' . ': Письмо отправлено.', __METHOD__ . ":" . __LINE__);
                                }
                                else {
                                    Yii::info('user_forgot' . ': Письмо не отправлено. Ошибка при отправке.', __METHOD__ . ":" . __LINE__);
                                }
                    }
                    return $this->render('forgot2', $pageData);
                }
                else {
                    $user->addError('f_login', Yii::t('app/error', 'Неверный код активации для изменения пароля. Попробуйте еще раз. '));
                }
            }
            else {
                $user->addError('f_login', Yii::t('app/error', 'Неверный код активации для изменения пароля.Попробуйте еще раз. '));
            }
        }
        else if (is_array($userPost = $request->post('UsersModel', false))) {
            if (($f_login = ArrayHelper::getValue($userPost, 'f_login', null))) {
                /* сохраняем только нужные переменные */
                // проверяем изменение пароля
                $user->f_login = $f_login;
                // validate user input and redirect to previous page if valid
                $user->setScenario(UsersModel::SCENARIO_FORGOT);
                Yii::trace(print_r($user, 1), __METHOD__ . ":" . __LINE__);
                // проверяем рузультат  и ишим данные 
                if ($user->validate()) {
                    //проверка данных на наличие в базе
                    if (($userPassword = UsersModel::find()->where("login=:login", array(":login" => $user->f_login))->one())) {
                        Yii::trace(print_r($userPassword, 1), __METHOD__ . ":" . __LINE__);
                        if ($userPassword->active != '1') {
                            $user->addError('f_login', Yii::t('app/error', 'Пользователь не активирован.Пройдите повторную активацию {linkactivation}', [
                                        'linkactivation' => \app\helpers\MyHtml::a(Yii::t('app/login', 'Выслать повторно код активации'), ['login/activate', 'userid' => $userPassword->id_user,'update_code'=>$userPassword->auth_key])
                            ]));
                        }
                        else {
                            // создаем новую метку
                            $userPassword->auth_key =$userPassword->generatePasswordResetToken();
                            if ($userPassword->update(false,array('auth_key'))) {
                                //отправляем емайл
                                $hasSendMail = Yii::$app->mailer->compose('user-forgot-html', [
                                                        'user' => $userPassword,
                                                        'tokien'=>$userPassword->auth_key,
                                                        'tokien2'=>md5($userPassword->email)
                                                ])
                                                ->setTo($userPassword->email)
                                                ->setFrom([Yii::$app->params['from_email'] => Yii::$app->params['from_name']])
                                                ->setTextBody('')
                                                ->setSubject(Yii::t('app/email', 'Вы запросили восстановление пароля.'))->send();
                                if ($hasSendMail) {
                                    Yii::info('user_forgot' . ': Письмо отправлено.', __METHOD__ . ":" . __LINE__);
                                    $pageData['message'] = Yii::t('app/login', 'На Ваш емайл выслан код для восстановления пароля.');
                                }
                                else {
                                    Yii::info('user_forgot' . ': Письмо не отправлено. Ошибка при отправке.', __METHOD__ . ":" . __LINE__);
                                    $user->addError('f_login', Yii::t('app/error', 'Сообщение не отправлено.'));
                                }
                                $user->f_login = $user->f_login = '';
                            }
                        }
                    }
                    else {
                        $user->addError('f_login', Yii::t('app/error', 'Пользователь с таким login не найден.'));
                    }
                }
            }
            else {
                $user->addError('f_login', Yii::t('app/error', 'Необходимо заполнить email.'));
            }
        }
        // display the login form
        return $this->render('forgot', $pageData);
    }

    /**
     * Создание нового пользователя
     *  @return unknown_type
     */
    public function actionCreate()
    {
        if ((!Yii::$app->user->isGuest)) {
            $this->redirectAccount();
        }
        $request = Yii::$app->getRequest();
        $pageData = array();
        $user = new UsersModel();
        $user->setScenario(UsersModel::SCENARIO_CREATE);
// collect user input data
        if (is_array($userPost = $request->post('UsersModel', false))) {
            Yii::trace(print_r($userPost, 1), __METHOD__ . ":" . __LINE__);
            $user->setAttributes($userPost, false);
            $user->city_name = ArrayHelper::getValue($userPost, 'city_name', null);
            $user->role = 'client';
            $user->password_1 = $user->password_2 = ArrayHelper::getValue($userPost, 'password_1', null);
            //   $user->verifyCode=ArrayHelper::getValue($userPost,'verifyCode',null);
            $user->agree = ArrayHelper::getValue($userPost, 'agree', null);
            // validate user input and redirect to previous page if valid
            $userValidate = $user->validate();
            if ($userValidate) {
                if ($user->save(false)) {

//отправляем емайл
                    $hasSendMail = Yii::$app->mailer->compose('user-create-html', ['user' => $user, 'USER_NAME' => $user->getFullName()])
                            ->setTo($user->email)
                            ->setFrom([Yii::$app->params['from_email'] => Yii::$app->params['from_name']])
                            ->setSubject(Yii::t('app/email', 'Вы зарегистрировались на сайте {sitename}.', ['sitename' => Yii::$app->request->serverName]))
                            ->send();
                    if ($hasSendMail) {
                        Yii::info('user_create' . ': Письмо отправлено.', __METHOD__ . ":" . __LINE__);
                        $pageData['message'] = Yii::t('app/login', 'На указанный адрес электронной почты отправлено письмо.');
                        $pageData['message'] .= Yii::t('app/login', 'Для завершения регистрации перейдите по содержащейся в нем ссылке и возвращайтес к нам!');
                    }
                    else {
                        Yii::info('user_create' . ': Письмо не отправлено. Ошибка при отправке.', __METHOD__ . ":" . __LINE__);
                        $user->addError('email', Yii::t('app/error', 'Сообщение с кодом активации не оправлено.Обратитесь к администратору.'));
                    }
                    // сохраняем пользователя в партнерке если он по партнерке зашел
                    // Yii::$app->affiliate->setSponsor($user->id);
                    // Yii::$app->session->setFlash('createUser','Пользователь добавлен.');
                }
                if ($user->hasErrors()) {
                    $user->addError('email', Yii::t('app/error', 'Новый пользоватлеь не добавлен.Проверьте все ошибки заполнения.'));
                }
                $pageData['user'] = $user;
                return $this->render('create-ok', $pageData);
            }
            $user->password_1 = $user->password_2 = '';
        }
        $pageData['user'] = $user;
        // display the login form
        return $this->render('create', $pageData);
    }

    /**
     * Активация записи и при проверке сообщения отправленного на действительный емайл
     * @return type
     */
    public function actionActivate($userid, $code = null)
    {
        Yii::trace(print_r(array($userid, $code), 1), __METHOD__ . '::' . __LINE__);
        if (!Yii::$app->user->isGuest){
            return $this->redirectAccount();
        }
        $pageData = array();
        $pageData['code'] = false;
        //повторная активация
        if ($update_code = Yii::$app->request->getQueryParam('update_code', false) AND $userid) {
            $user = UsersModel::find()->where("id_user=:id_user AND active='0'", array(':id_user' => intval($userid)))->one();
            if ($user) {
                if ($user->auth_key !== $update_code) {
                    $pageData['message'] = '<div class="blockheader">' . Yii::t('app/error', 'Ошибка кода для повторной активации.') . '</div>';
                }
                else {
                    $user->auth_key =$user->generateAuthCode();
                    if($user->update(false,array('auth_key'))){
                        $hasSendMail = Yii::$app->mailer->compose('user-create-html', ['user' => $user, 'USER_NAME' => $user->getFullName()])
                                        ->setTo($user->email)
                                        ->setFrom([Yii::$app->params['from_email'] => Yii::$app->params['from_name']])
                                        ->setSubject(Yii::t('app/email', 'Вы повторно запросили код активации на сайте {sitename}.', ['sitename' => Yii::$app->request->serverName]))->send();
                        if ($hasSendMail) {
                            Yii::info('user_activate' . ': Письмо отправлено.', __METHOD__ . ":" . __LINE__);
                            $pageData['message'] = '<div class="blockheader">' . Yii::t('app/error', 'Вам выслан повторно код активации.') . '</div>';
                        }
                        else {
                            Yii::info('user_activate' . ': Письмо не отправлено. Ошибка при отправке.', __METHOD__ . ":" . __LINE__);
                            $pageData['message'] = '<div class="blockheader">' . Yii::t('app/login', 'Не удалось выслать код активации.Обратитесь к администратору.') . '</div>';
                        }
                    }else {
                        $pageData['message'] = '<div class="blockheader">' . Yii::t('app/login', 'Не удалось выслать код активации.Обратитесь к администратору.') . '</div>';
                    }
                }
            }
            else {
                $pageData['message'] = '<div class="blockheader">' . Yii::t('app/login', 'Невозможно выслать код активации. Возможно Вы уже прошли активацию.') . '</div>';
            }
        }
        else if ($code) {
            //проверка активационного ключа и перевод в статус активации пользователя
            if (preg_match('|([0-9a-zA-Z]+)|si', $code)) {
                $user = UsersModel::find()->where("auth_key=:activation_code AND active='0'", array(':activation_code' => $code))->one();
                if ($user AND $user->id_user == $userid) {
                    $user->setScenario(UsersModel::SCENARIO_ACTIVATE);
                    $user->active = 1;
                    if ($user->update()) {
                        $pageData['message'] = '<div class="blockheader">' . Yii::t('app/login', 'Активация прошла успешно.') . '</div>';
                        $pageData['code'] = true;
                    }
                }
                else {
                    Yii::error('($user->id_user==$userid)=' . ($user->id_user == $userid), __METHOD__ . "::" . __LINE__);
                    $pageData['message'] = '<div class="blockheader">' . Yii::t('app/login', 'Активация невозможна. Возможно Вы уже прошли активацию.') . '</div>';
                }
            }
            else {
                $pageData['message'] = '<div class="blockheader">' . Yii::t('app/login', 'Активация невозможна. Нет кода активации или он неверный.') . '</div>';
            }
        }
        else {
            $pageData['message'] = '<div class="blockheader">' . Yii::t('app/login', 'Неверная ссылка. Нет ожидаемых для активации параметров.') . '</div>';
        }
        return $this->render('activate', $pageData);
    }

    /**
     *  возвращает список найденных городов по буквам или слову
     * @param type $q
     */
    public function actionCityList($q = '', $c = null)
    {
        if (!@$q) {
            $this->asJson([]);
            return;
        }
        $query = \app\models\GeoCityModel::find()->alias('city')->joinWith('country');
        // добавляем ограничение на поиск только в разрешенных странах
        //$query->joinWith('country.mycompany')->where('mycompany.id>0');
        //добавляем ограничение на вывод ячеек из таблицы
        $query->select("id_city,city.country_id, city.name as city_name, country.name as country_name")
                ->orderBy('country_id,city.name')
                ->limit(10);
        if ($c) {
            $query->andWhere('city.country_id=:country_id', [':country_id' => $c]);
        }
        $query->andWhere('city.name LIKE  :name ', [':name' => '' . $q . '%']);
        $command = $query->createCommand();
        $data = $command->queryAll();
        $out = [];
        foreach ($data as $d) {
            $out[$d['id_city']] = ['value' => $d['city_name'], 'city_id' => $d['id_city'], 'country_id' => $d['country_id'], 'country' => $d['country_name']];
        }
        $this->asJson($out);
    }

}
