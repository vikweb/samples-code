<?php

namespace app\models;

use Yii;
use yii\helpers\Json;

/**
 * This is the model class for table "{{%users_meta}}".
 *
 * @property string $id
 * @property string $user_id
 * @property string $u_meta_key
 * @property string $u_meta_value
 */
class UsersMetaModel extends \app\components\MyActiveRecord
{

    const SCENARIO_AVATAR = 'avatar';

    static $_cache = array();

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%users_meta}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'u_meta_key'], 'required', 'on' => ['create', 'update', 'avatar']],
            [['user_id'], 'integer', 'on' => ['create', 'avatar']],
            [['u_meta_value'], 'string', 'on' => ['create', 'update']],
            [['u_meta_key'], 'string', 'max' => 255, 'on' => ['create', 'update', 'avatar']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app/users', 'ID'),
            'user_id' => Yii::t('app/users', 'User ID'),
            'u_meta_key' => Yii::t('app/users', 'U Meta Key'),
            'u_meta_value' => Yii::t('app/users', 'U Meta Value'),
        ];
    }

    /**
     * добавляет новые метаданные пользователя или обновляет старые
     * @param type $user_id
     * @param type $key
     * @param type $value
     * @return boolean
     */
    static function updateKey($user_id, $key, $value)
    {
        $user_id = (int) $user_id;
        $key = (string) $key;
        // проверяем старое значение
        $old_value = self::getKey($user_id, $key, null);
        // если новые данные такие же как и старые то ничего не делаем
        if ($old_value == $value)
            return false;
        $is_update = false;

        // сохраняем данные 
        if (false == self::hasCache($user_id, $key)) {
            // определяем новые данные для сохранения переменной
            $new_meta = new self();
            $new_meta->user_id = $user_id;
            $new_meta->u_meta_key = $key;
            $new_meta->u_meta_value = $new_meta->_encode($value);
            // добавляем если в базе еще нет
            $is_update = $new_meta->save();
        }
        else {
            // обновляем если уже есть в базе
            if ($new_meta = self::find()->where("user_id=:user_id AND u_meta_key=:u_meta_key", array(':user_id' => $user_id, ':u_meta_key' => $key))->one()) {
                $new_meta->u_meta_value = $new_meta->_encode($value);
                $is_update = $new_meta->save();
            }
        }
        if ($is_update) {
            self::setCache($user_id, $key, $value);
        }
        return $is_update;
    }

    /**
     * 
     * @param type $value
     * @return string
     */
    private function _encode($value)
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            //   $value=array('meta_type'=>'array','meta_array'=>$value);
            return json_encode($value);
        }
        if (is_object($value)) {
            $value = array('meta_type' => 'object', 'meta_object' => $value);
            if (is_object($value))
                $value = clone $value;
            return json_encode($value);
        }
        return $value;
    }

    /**
     * 
     * @param type $value
     * @return type
     */
    private function _decode($value)
    {
        if (\app\helpers\MyJson::isJSON($value)) {
            $value = (object) json_decode($value);
            if (@$value->meta_type == 'object') {
                $value = (object) $value->meta_object;
                return $value;
            }
            $value = \yii\helpers\ArrayHelper::toArray($value);
        }
        return $value;
    }

    /**
     * 
     * @param type $user_id
     * @param type $key
     * @param type $defaultValue
     * @return null
     */
    static function getKey($user_id, $key, $defaultValue = null)
    {
        $user_id = (int) $user_id;
        $key = (string) $key;
        if (false !== self::hasCache($user_id, $key)) {
            return self::getCache($user_id, $key);
        }
        if ($row = self::find()->where("user_id=:user_id AND u_meta_key=:u_meta_key", array(':user_id' => $user_id, ':u_meta_key' => $key))->one()) {
            $value = $row->_decode($row->u_meta_value);
            self::setCache($user_id, $key, $value);
            return $value;
        }
        if (null !== $defaultValue) {
            return $defaultValue;
        }
        return null;
    }

    /**
     * удаление метаданных
     * @param type $user_id
     * @param type $key
     * @return boolean
     */
    static function deleteKey($user_id, $key)
    {
        $user_id = (int) $user_id;
        $key = (string) $key;
        $value = self::getKey($user_id, $key, null);
        if (self::hasCache($user_id, $key)) {
            $where = [];
            $where['user_id'] = $user_id;
            $where['u_meta_key'] = $key;
            if (self::deleteAll($where)) {
                self::setCache($user_id, $key, null);
                return true;
            }
        }
        return false;
    }

    /**
     * проверка метаданных пользователя в временный кэш на период работы скрипта
     * @param type $user_id
     * @param type $key
     * @return boolean
     */
    static function hasCache($user_id, $key)
    {
        if (array_key_exists($user_id, (array) self::$_cache)) {
            if (array_key_exists($key, (array) self::$_cache[$user_id])) {
                return true;
            }
        }
        return false;
    }

    /**
     * вывод метаданных из временный кэш на период работы скрипта
     * @param type $user_id
     * @param type $key
     * @return boolean
     */
    static function getCache($user_id, $key)
    {
        if (false !== self::hasCache($user_id, $key)) {
            return self::$_cache[$user_id][$key];
        }
        return null;
    }

    /**
     * сохранить метаданные во временный кэш на период работы скрипта
     * @param type $user_id
     * @param type $key
     */
    static function setCache($user_id, $key, $value = null)
    {
        //$md_object = md5(json_encode($object));
        if (!array_key_exists($user_id, self::$_cache)) {
            self::$_cache[$user_id] = array();
        }
        if (!isset($value) AND array_key_exists($key, self::$_cache[$user_id])) {
            // удаляем из кэша если $value=null
            unset(self::$_cache[$user_id][$key]);
        }
        else {
            // добавляем или обновляем кэш
            self::$_cache[$user_id][$key] = $value;
        }
    }

    /**
     *  загружаем в кэш все данные что у нас есть по конкретному пользователю
     * @param type $object
     * @param type $key
     */
    static function getKeys($user_id)
    {
        $user_id = (int) $user_id;
        //очищаем весь кэш из памяти
        if (array_key_exists($user_id, self::$_cache)) {
            unset(self::$_cache[$user_id]);
        }
        // загружаем новый кэш
        if ($rows = self::findAll(['user_id' => $user_id])) {
            foreach ($rows AS $row) {
                $value = $row->_decode($row->u_meta_value);
                self::setCache($user_id, $row->u_meta_key, $value);
            }
            return $rows;
        }
        return array();
    }

}
