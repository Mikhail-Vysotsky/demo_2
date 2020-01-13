<?php

/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 27.04.17
 * Time: 15:31
 *
 * класс распределения пациентов по участкам
 */
include_once 'headers/Helper.php';
include_once '../headers/Helper.php';
set_time_limit(900);

class PatAllocation
{
    /**
     * @var db
     */
    var $db;
    var $age_max;
    var $cur_date_timestamp;
    /**
     * костыльный костыль
     * @var bool
     */
    var $show_percent_load = false;
    /**
     * @var array(int)
     */
    var $arPediatorsID;
    /**
     * @var int список пациентов, данные по которым изменены
     */
    var $changed_pat_count = 0;

    /**
     * PatAllocation constructor.
     * @param db $db_inc
     * @return PatAllocation
     * @internal param bool|db $db db
     */
    function PatAllocation($db_inc)
    {
        $this->db = $db_inc;

        $this->age_max = $this->db->cell("SELECT VAL_PAR FROM PARSYS WHERE NAME='Верхняя граница возраста детей для формы №7'");
        if (empty($this->age_max)) $this->age_max = 18;
        $this->cur_date_timestamp = mktime(0, 0, 0);
    }

    function show_percent_load()
    {
        $this->show_percent_load = true;
    }

    function hide_percent_load()
    {
        $this->show_percent_load = false;
    }

    function getShowPercentLoad()
    {
        return $this->show_percent_load;
    }

    function getAllocationRules($id_uch_board = null)
    {
        // список педиатров
        $ped_sql = "SELECT ID, NAME FROM PROFILS WHERE UPPER(NAME) LIKE '%ПЕДИАТР%'";
        $ped_rows = $this->db->rows($ped_sql);

        if ($ped_rows) {
            foreach ($ped_rows as $ped_id) {
                $this->arPediatorsID[] = $ped_id['ID'];
            }
        } else {
            $this->arPediatorsID = null;
            //return false;
        }

        if (!is_null($id_uch_board)) $uch_board_where = " AND ub.ID_UCH_BOARD = $id_uch_board";
        $sql = "SELECT
                  u.ID_UCH,
                  ub.ID_UCH_BOARD,
                  ub.HOME_START,
                  ub.HOME_END,
                  ub.STREET,
                  ub.ID_LIST,
                  ub.ID_WORK,
                  u.ID_PROFIL as PROF
                FROM UCHASTOK_BOARD ub
                  LEFT JOIN UCHASTOK u ON u.ID_UCH=ub.ID_UCH
                WHERE (ub.OLD_MARK IS NULL OR ub.OLD_MARK = '')
                $uch_board_where";
        $rows = $this->db->rows($sql);
        if (!$rows) return $rows;

        foreach ($rows as $k => $row) {
            if (in_array($row['PROF'], $this->arPediatorsID)) {
                $rows[$k]['IS_PED'] = 1;
            } else {
                $rows[$k]['IS_PED'] = 0;
            }

            $home_start = trim($row['HOME_START']);
            $home_end = trim($row['HOME_END']);

            $rows[$k]['HOME_START'] = PatAllocation::get_home_num($home_start);
            $rows[$k]['HOME_START_2'] = ord(PatAllocation::get_home_str($home_start));
            $rows[$k]['HOME_END'] = PatAllocation::get_home_num($home_end);
            $rows[$k]['HOME_END_2'] = ord(PatAllocation::get_home_str($home_end));

            //todo пока не понял смысл приседаний с home_start/home_start_2 и home_end/home_end_2, тупо переписал

            if (empty($rows[$k]['HOME_START'])) $rows[$k]['HOME_START'] = 0;

            if (empty($rows[$k]['HOME_END'])) $rows[$k]['HOME_END'] = 9999;

            if (empty($rows[$k]['HOME_END'])) {
                $rows[$k]['HOME_END'] = 9999;
                if (empty($rows[$k]['HOME_END_2'])) $rows[$k]['HOME_END_2'] = 9999;
            } else {
                if (empty($rows[$k]['HOME_END_2'])) $rows[$k]['HOME_END_2'] = 0;
            }
        }

        return $rows;
    }

    function distributePatients($child_ter = 0, $all_pats = 0, $id_uch_board = 0, $id_pat = 0)
    {
        // условия выбора пациентов для распределения
        if ($all_pats) {
            $uch_where = "";
        } else {
            $uch_where = " AND pl.ID_UCHASTOK IS NULL";
        }

        if ($id_pat) {
            $pat_where = " AND pl.ID_PAT = $id_pat";
        } else {
            $pat_where = "";
        }

        $pat_group = 500; // колличество пациентов в одной иттерации распределения
//        $code_empty = ord(""); //todo не знаю зачем это было сделано

        // получаем правила для распределения по участкам
        $arRules = $this->getAllocationRules();

        // берем всех пациентов без участков
        $sql_pat_total = "SELECT COUNT(*) FROM PAT_LIST WHERE (OLD_MARK IS NULL OR OLD_MARK='') AND ID_UCHASTOK IS NULL";
        $pat_total = $this->db->cell($sql_pat_total);

        // определяем данные для постраничного выбора пациентов
        $for_because = round($pat_total / $pat_group) * $pat_group;

        // обрабатываем пациентов пачками по $pat_group
        for ($skip = 0; $skip <= $for_because; $skip += $pat_group) {
            $sql_pats = "SELECT FIRST $pat_group SKIP $skip
                        pl.CITY_R, pl.CITY, pl.STREET, pl.STREET_R,
                        pl.HOME, pl.HOME_R, pl.POINT,
                        pl.D_BIR, pl.ID_PAT,
                        
                        p.WORKING
                      FROM PAT_LIST pl
                        LEFT JOIN PASSPORT p ON p.ID_PAT = pl.ID_PAT
                      WHERE (pl.OLD_MARK IS NULL OR pl.OLD_MARK='') 
                        $uch_where
                        $pat_where
                        ";


            // получаем список пациентов
            $arPats = $this->db->rows($sql_pats);
            // обработка массива пациентов arPats
            foreach ($arPats as $_pk => $pat) {
                // определение улицы и дома пациента. Если есть адрес регистрации - используем его.
                if ($pat['CITY_R'] > 0 || $pat['STREET_R'] > 0) {
                    $id_street = $pat['STREET_R'];
                    $home = trim($pat['HOME_R']);
                } else {
                    $id_street = $pat['STREET'];
                    $home = trim($pat['HOME']);
                }
                $id_uch = null;
                $home_street = $arPats[$_pk]['HOME_STREET'] = ord(PatAllocation::get_home_str($home));
                $home = $arPats[$_pk]['HOME_NUM'] = PatAllocation::get_home_num($home);

                // определение населенного пункта? непонял...
                $id_punkt = $pat['POINT'];

                // вычисляем возраст, если D_BIR указан
                $age = null;
                if (!empty($pat['D_BIR'])) {
                    $age = round(($this->cur_date_timestamp - $pat['D_BIR']) / (86400 * 365.25));
                }

                $arPats[$_pk]['AGE'] = $age;
                if (isset($arPats[$_pk]['AGE']) && $arPats[$_pk]['AGE'] < $this->age_max) $arPats[$_pk]['CHILD'] = 1;

                // распределение пациентов по месту работы
                if ($pat['WORKING'] > 0) {
                    $f_work = false;
                    foreach ($arRules as $rule) {
                        if ($rule['ID_WORK'] == $pat['WORKING']) {
                            $arPats[$_pk]['SET_ID_UCH'] = $rule['ID_UCH'];
                            $arPats[$_pk]['SET_ID_UCH_BOARD'] = $rule['ID_UCH_BOARD'];
                            $arPats[$_pk]['SET_BY'] = 'PAT.WORKING > 0';
                            $f_work = true;
                            break;
                        }
                    }
                    // если удалось распределить по месту работы, то переходим к следующиму пациенту
                    if ($f_work) continue;
                }

                // если участок не установлен раннее смотрим по адресу
                if (is_null($id_uch)) {
                    //обходим правила на предмет вхождения по улице и номеру дома
                    foreach ($arRules as $key_r => $rule) {
                        $stored_r_key = false;
                        // пропускаем, если не надо распределять детей на терапевтов
                        if (isset($arPats[$_pk]['AGE']) && $arPats[$_pk]['AGE'] <= $this->age_max && $rule['IS_PED'] == 0 && $child_ter == 1) continue;
                        if (!isset($arPats[$_pk]['CHILD']) && $arPats[$_pk]['CHILD'] !== 1 && $rule['IS_PED'] == 1) continue;

                        // распределение по дому и улице
                        if ($home > 0 && $id_street > 0) {
                            if ($rule['STREET'] == $id_street) {
                                if (!empty($rule['HOME_START_2'])
                                    && $rule['HOME_START_2'] == $home_street    // совпадает дробь дома полностью
                                    && ($rule['HOME_START'] == $home)
                                ) {
                                    $arPats[$_pk]['SET_ID_UCH'] = $rule['ID_UCH'];
                                    $arPats[$_pk]['SET_ID_UCH_BOARD'] = $rule['ID_UCH_BOARD'];
                                    $arPats[$_pk]['SET_BY'] = "HOME NUMBER, SUB_NUMBER AND STREET IN RULE";
                                    $stored_r_key = $key_r;
//                                    echo "KEY STORED!!!!<br>";
                                    break;

                                } else if (($rule['HOME_START'] == 0 && $rule['HOME_END'] == 0)    // значит распределяем всю улицу сюда?
                                    || (
                                        (($rule['HOME_START'] <= $home) && ($home <= $rule['HOME_END']))
                                        && ((!empty($home_street) && $rule['HOME_START_2'] <= $home_street) || empty($home_street))
                                        && ((!empty($home_street) && $home_street <= $rule['HOME_END_2']) || empty($home_street))
                                    )
                                ) {
                                    $arPats[$_pk]['SET_ID_UCH'] = $rule['ID_UCH'];
                                    $arPats[$_pk]['SET_ID_UCH_BOARD'] = $rule['ID_UCH_BOARD'];
                                    $arPats[$_pk]['SET_BY'] = "HOME NUMBER AND STREET IN RULE";
//                                    echo "SET BY HOME AND STREET NUM<br>";
                                }
                            }
                            // распределение только по наименованию улицы
                        } elseif ($id_street > 0) {
                            if ($rule['STREET'] == $id_street) {
                                $arPats[$_pk]['SET_ID_UCH'] = $rule['ID_UCH'];
                                $arPats[$_pk]['SET_ID_UCH_BOARD'] = $rule['ID_UCH_BOARD'];
                                $arPats[$_pk]['SET_BY'] = "JUST STREET";
                            }
                            // тут я не понял что за фича, в id_punkt пишется значение из поля POINT
                        } elseif ($id_punkt > 0) {
                            if ($rule['STREET'] == $id_punkt) {
                                $arPats[$_pk]['SET_ID_UCH'] = $rule['ID_UCH'];
                                $arPats[$_pk]['SET_ID_UCH_BOARD'] = $rule['ID_UCH_BOARD'];
                                $arPats[$_pk]['SET_BY'] = "BY POINT FIELD";
                            }
                        }
                    }
                    // если где-то в цикле запомнили номер правила, то распределяем соответственно этому номеру
                    if (!empty($stored_r_key)) {
                        $arPats[$_pk]['SET_ID_UCH'] = $arRules[$stored_r_key]['ID_UCH'];
                        $arPats[$_pk]['SET_ID_UCH_BOARD'] = $arRules[$stored_r_key]['ID_UCH_BOARD'];
                        $arPats[$_pk]['SET_BY'] = "FROM STORED RULE KEY: " . $arPats[$_pk]['SET_BY'];
//                        echo "SET BY STORED KEY!!!<br>";
                    }

                }
            }
            //todo c FIREBIRD 2.1 доступно UPDATE OR INSERT и ON DUBLICATE KEY UPDATE. По этому пока костыль.
            // пишем изменения в базу
            foreach ($arPats as $pat) {
                if (empty($pat['SET_ID_UCH'])) continue;

                $update_query = "UPDATE PAT_LIST
                SET
                DATE_ATTACH=$this->cur_date_timestamp,
                DATE_UNATTACH=NULL,
                ID_UCHASTOK=" . $pat['SET_ID_UCH'] . ",
                ID_UCH_BOARD=" . $pat['SET_ID_UCH_BOARD'] . "
                WHERE ID_PAT=" . $pat['ID_PAT'];
                $res = $this->db->exec($update_query);

                if ($res) $this->changed_pat_count++;
            }

            // показываем проценты если надо
            if ($this->show_percent_load) {
                if ($skip == 0) $percent = 0;
                else $percent = round(($skip / $for_because) * 100);
                PatAllocation::echo_percentload($percent);
            }
        }
    }


    /**
     * При определении возраста детей используется параметр системы "Верхняя граница возраста детей для формы №7"
     * возвращает числовую составляющую номера дома
     * @param $str_in
     * @return int
     */
    function get_home_num($str_in)
    {
        $s1 = strpos($str_in, "/");
        $s2 = strpos($str_in, '\\');
        $s3 = strpos($str_in, '-');
        if ($s1 !== false) {    // то первый тип
            $dom_beg_1 = preg_replace('/\D/', '', substr($str_in, 0, $s1));
        } elseif ($s2 !== false) {
            $dom_beg_1 = preg_replace('/\D/', '', substr($str_in, 0, $s2));
        } elseif ($s3 !== false) {
            $dom_beg_1 = preg_replace('/\D/', '', substr($str_in, 0, $s3));
        } elseif (is_numeric($str_in)) {    // просто число
            $dom_beg_1 = $str_in;
        } else {    // с буквой
            $dom_beg_1 = preg_replace('/\D/', '', $str_in);
        }
        return (int)$dom_beg_1;
    }

    /**
     * возвращает дробную составляющую номера дома
     * @param $str_in
     * @return int|mixed|string
     */
    function get_home_str($str_in)
    {
        $str_in = str_replace(' ', '', $str_in);
        $s1 = strpos($str_in, "/");
        $s2 = strpos($str_in, '\\');
        $s3 = strpos($str_in, '-');
        if ($s1 !== false) {
            $dom_beg_2 = preg_replace('/\D/', '', substr($str_in, $s1 + 1));
        } elseif ($s2 !== false) {
            $dom_beg_2 = preg_replace('/\D/', '', substr($str_in, $s2 + 1));
        } elseif ($s3 !== false) {
            $dom_beg_2 = preg_replace('/\D/', '', substr($str_in, $s3 + 1));
        } elseif (is_numeric($str_in)) {
            $dom_beg_2 = 0;
        } else {
            $dom_beg_2 = preg_replace('/\d/', '', $str_in);
            $dom_beg_2 = substr($dom_beg_2, 0, 1);
        }

        return mb_strtolower($dom_beg_2);
    }

    function echo_percentload($tek_percent)
    {
        $script_string = '
            <script language="JavaScript1.2">
            document.all[\'percentload\'].innerHTML=\'<table border=0 cellspacing=0 cellpadding=0 width=100%>\\n<tr>\\n<td width=' . $tek_percent . '% style="background-color: blue; color: white;" align=center>' . $tek_percent . '%</td><td>&nbsp;</td>\\n</tr>\\n</table>\';
            </script>
        ';
        echo $script_string;
        flush();
    }
}