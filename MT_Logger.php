<?php

/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 03.02.17
 * Time: 13:24
 */

if (file_exists('../headers/docalog.inc'))  //todo cделать автолоадер
    $inc = '../headers/docalog.inc';
elseif(file_exists('../../headers/docalog.inc'))
    $inc = '../../headers/docalog.inc';

if (file_exists('../../headers/db.inc')) {
    require_once '../../headers/db.inc';
} elseif (file_exists('../headers/db.inc')) {
    require_once '../headers/db.inc';
}


require_once $inc;

class MT_Logger
{
    var $uadesc;

    /**
     * MT_Logger constructor.
     * @param $_uadesc_id - хардкод дескрипшна. описан в базе UADESC и в скрипте docalog.inc в качестве define переменных
     * @param string $log_to - флаг. по умолчанию docalog.inc использует GLOBALS['db']. Если его нет, то переопределяем
     * на передаваемый в конструкторе инстанс DB.
     * @param null $instance - собственно инстанс базы.
     * @return MT_Logger
     */
    function MT_Logger($_uadesc_id, $log_to = 'db', $instance = null) {
        if ($log_to === 'db_instance') {    //todo костыль DocaSaveLog ибо там используется GLOBALS['db']
            $GLOBALS['db'] = $instance;
        }
        $this->uadesc = $_uadesc_id;
    }

//    function log_data($data) {
//        DocaSaveLog($this->uadesc, 0, 0, $data);
//    }

    /**
     * метод-заготовка для логирования с параметрами dopcode. на данный момент нигде не используется
     * @param $_ua_desc
     * @param int $dopcode1
     * @param int $dopcode2
     */
    function log_desc($_ua_desc, $dopcode1=0, $dopcode2=0) {
        DocaSaveLog($_ua_desc, 0, 0);
    }

    /**
     * @param string|json $custom_json_data - строка, которая пишется в UALOG.CUSTOM_DATA. Используется только для
     * логирования расписания.
     */
    function log($custom_json_data = null) {
        DocaSaveLog($this->uadesc, 0, 0, $custom_json_data);
    }
}