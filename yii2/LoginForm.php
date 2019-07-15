<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * LoginForm is the model behind the login form.
 *
 * @property User|null $user This property is read-only.
 *
 */
class LoginForm extends Model
{
    public $userlogin;
    public $password;
    public $_returnUrl;
    public $rememberMe = true;

    private $_user = false;


    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['userlogin', 'password'], 'required'],
            // rememberMe must be a boolean value
            ['rememberMe', 'boolean'],
            // password is validated by validatePassword()
            ['password', 'validatePassword'],
            // user activated is validated by validateActivated()
            ['userlogin', 'validateActivated'],
        ];
    }

        public function attributeLabels()
    {
        return [
            'userlogin' => Yii::t('app', 'User Login'),
            'password' => Yii::t('app', 'User Password'),
            'rememberMe' => Yii::t('app', 'Remember Me'),
          ];
    }
    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();
            if (!$user || !$user->validatePassword($this->password)) {
                $this->addError($attribute, Yii::t('app/error','Incorrect username or password.'));
            }
        }
    }
    /**
     * проверка активации пользователя
     * @param type $attribute
     * @param type $params
     */
    public function validateActivated($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();
            if (!$user || !$user->validateActivated()) {
                $this->addError($attribute, Yii::t('app/error','Вы не прошли активацию и не подтвердили свой email. '));
            }
        }
    }
    /**
     * Logs in a user using the provided username and password.
     * @return bool whether the user is logged in successfully
     */
    public function login()
    {
        if ($this->validate()) {
            if(Yii::$app->user->login($this->getUser(), $this->rememberMe ? 3600*24*30 : 0)){
// Yii::trace(print_r(Yii::$app->user->getIdentity(),1),__METHOD__."::".__LINE__); 
                   return true;
            }
        }
        return false;
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    public function getUser()
    {
        if ($this->_user === false) {
             $this->_user = UserIdentity::findByUsername($this->userlogin);

        }
        return $this->_user;
    }
}
