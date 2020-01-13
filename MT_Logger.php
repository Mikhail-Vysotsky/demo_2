<?php

/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 03.02.17
 * Time: 13:24
 */

if (file_exists('../headers/docalog.inc'))  //todo c������ ����������
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
     * @param $_uadesc_id - ������� ����������. ������ � ���� UADESC � � ������� docalog.inc � �������� define ����������
     * @param string $log_to - ����. �� ��������� docalog.inc ���������� GLOBALS['db']. ���� ��� ���, �� ��������������
     * �� ������������ � ������������ ������� DB.
     * @param null $instance - ���������� ������� ����.
     * @return MT_Logger
     */
    function MT_Logger($_uadesc_id, $log_to = 'db', $instance = null) {
        if ($log_to === 'db_instance') {    //todo ������� DocaSaveLog ��� ��� ������������ GLOBALS['db']
            $GLOBALS['db'] = $instance;
        }
        $this->uadesc = $_uadesc_id;
    }

//    function log_data($data) {
//        DocaSaveLog($this->uadesc, 0, 0, $data);
//    }

    /**
     * �����-��������� ��� ����������� � ����������� dopcode. �� ������ ������ ����� �� ������������
     * @param $_ua_desc
     * @param int $dopcode1
     * @param int $dopcode2
     */
    function log_desc($_ua_desc, $dopcode1=0, $dopcode2=0) {
        DocaSaveLog($_ua_desc, 0, 0);
    }

    /**
     * @param string|json $custom_json_data - ������, ������� ������� � UALOG.CUSTOM_DATA. ������������ ������ ���
     * ����������� ����������.
     */
    function log($custom_json_data = null) {
        DocaSaveLog($this->uadesc, 0, 0, $custom_json_data);
    }
}