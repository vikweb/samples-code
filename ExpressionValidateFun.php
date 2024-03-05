<?php 
/**
 * Проверка выражения на наличие открывающихся и закрывающихся скобок с разными элементами
 * $_rules = массив открывающих и закрывающих пар жлементов
 * @param string $expression
 * @return boolean
 */
     function validatExpression($expression){
        if(!empty($expression)){
            $_rules=['start'=>['{','(','['],'end'=>['}',')',']']];
            $flag_start=[];
            for($i=0;$i<strlen($expression);$i++){
                $sim=$expression[$i];//можно substr($expression,$i,1); 
                // проверка  вхождения любого из стартовых символов
                if(in_array($sim,$_rules['start'])){
                    // в последней ячейке храним статус открытой скобки  
                    $flag_start[]= array_search($sim,$_rules['start']);
                }
                // проверка вхождения любого из закрывающих символов
                if(in_array($sim,$_rules['end'])){
                    // если в массиве открытых блоков нет данных то false
                    if(empty($flag_start)){
                        return false;
                    }
                    $lastRule=array_pop($flag_start);
                    // статус закрываемого элемента
                    $rule=array_search($sim,$_rules['end']);
                    // если статус последнего открытого элемента не совпадает со статусом последнего элемента то false
                    if($lastRule!==$rule){
                        return false;
                    }
                }
            }
            if(empty($flag_start)){
                return true;
            }
        }
        return false;
    }



echo 'Test:<br>';
$strTest=['...{...{...[...]...}...}...(...)...','...{...{...[.(..]...}...}..).(...)...','[({})]',
    '({[]})()','(((','[([)','[[(({}))]] [({})]',"['1':fun1({'param1':12,'param2':10})]"];
foreach($strTest AS $str){
    echo "'{$str}'=>".((validatExpression($str))?'true':'false').'<br>';
}
