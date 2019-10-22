<?php

namespace app\components\validators;

use Yii;
use yii\validators\Validator;
/*
* проверка выражений на наличие правильно открытых и закрытых скобок [{()}]
* @author Viktor Serobaba <vikwebas@gmail.com>
* @since 1.0
*/
class ExpressionValidator extends Validator
{

    /**
     * @var array массив открывающих и закрывающих пар жлементов
     */
    private $_rules = ['start' => ['{', '(', '['], 'end' => ['}', ')', ']']];

    /**
     * Задает валидатор для модели
     * @param object $model
     * @param string $attribute
     */
    public function validateAttribute($model, $attribute)
    {
        if (!empty($model->$attribute)) {
            if ($this->validatExpression($model->$attribute)) {
                return true;
            }
            $this->addError($model, $attribute, Yii::t('app/error', 'Неверное выражение. Не соблюдены правила открытых и закрытых скобок.'));
        }
        $this->addError($model, $attribute, Yii::t('app/error', 'Нет данных в выражении.'));
        return false;
    }

    /**
     * Проверка выражения на наличие открывающихся и закрывающихся скобок с разными элементами из $_rules
     * @param string $expression
     * @return boolean
     */
    function validatExpression($expression)
    {
        if (!empty($expression)) {
            $flag_start = [];
            for ($i = 0; $i < strlen($expression); $i++) {
                $sim = $expression[$i];
                // проверка  вхождения любого из стартовых символов
                if (in_array($sim, $this->_rules['start'])) {
                    $flag_start[] = array_search($sim, $this->_rules['start']);
                }
                // проверка вхождения любого из закрывающих символов
                if (in_array($sim, $this->_rules['end'])) {
                    if (empty($flag_start)) {
                        return false;
                    }
                    $lastRule = array_pop($flag_start);
                    $rule = array_search($sim, $this->_rules['end']);
                    // если статус последнего открытого элемента $lastRule не совпадает со статусом последнего элемента $rule то false
                    if ($lastRule !== $rule) {
                        return false;
                    }
                }
            }
            if (empty($flag_start)) {
                return true;
            }
        }
        return false;
    }

}
