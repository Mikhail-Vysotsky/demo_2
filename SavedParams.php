<?php
/**
 * Created by PhpStorm.
 * User: misha
 * Date: 14.06.18
 * Time: 12:45
 */
require_once '../headers/db.inc';

class SavedParams
{
    var $db;
    var $id;
    var $target;
    var $id_user;
    var $id_dep;
    var $saved_params;

    /**
     * SavedParams constructor.
     * @param db $db
     * @param string $script_name
     */
    public function __construct($db, $script_name)
    {
        $this->db = $db;
        $this->target = $script_name;
        $this->setFromSession();
        $this->getSavedParams_byUser();
    }

    public function setFromSession()
    {
        if (!session_id()) @ session_start();
        if (!empty($_SESSION['cur_user'])) $this->id_user = $_SESSION['cur_user'];
        if (!empty($_SESSION['cur_dep'])) $this->id_dep = $_SESSION['cur_dep'];
    }

    /**
     * @param integer $id_user
     */
    public function setIdUser($id_user)
    {
        $this->id_user = $id_user;
    }

    /**
     * @param integer $id_dep
     */
    public function setIdDep($id_dep)
    {
        $this->id_dep = $id_dep;
    }

    /**
     * @return mixed
     */
    public function getSavedParams_byUser()
    {
        $sql = "SELECT ID, SAVED_CONFIG FROM SAVED_PARAMS WHERE ID_USER=? AND SCRIPT_NAME=?";
        $row = $this->db->row($sql, array($this->id_user, $this->target));
        if (!empty($row))
            $this->id = $row['ID'];
        return $this->decodeParams($row['SAVED_CONFIG']);
    }

    /**
     * @return mixed
     */
    public function getSavedParams_byDep()
    {
        $sql = "SELECT ID, SAVED_CONFIG FROM SAVED_PARAMS WHERE ID_DEP=? AND SCRIPT_NAME=?";
        $row = $this->db->row($sql, array($this->id_dep, $this->target));
        if (!empty($row))
            $this->id = $row['ID'];
        return $this->decodeParams($row['SAVED_CONFIG']);
    }

    public function save()
    {
        if (empty($this->id)) {
            // insert new row
            $sql = "INSERT INTO SAVED_PARAMS (ID_USER, ID_DEP, SCRIPT_NAME, SAVED_CONFIG) VALUES (?, ?, ?, ?)";
            $arData = array($this->id_user, $this->id_dep, $this->target, $this->encodeParams($this->saved_params));
        } else {
            // update by id
            $sql = "UPDATE SAVED_PARAMS SET SAVED_CONFIG=? WHERE ID=?";
            $arData = array($this->encodeParams($this->saved_params), $this->id);
        }

        $this->db->exec($sql, $arData);
    }

    /**
     * @param mixed $saved_params
     */
    public function setSavedParams($saved_params)
    {
        $this->saved_params = $saved_params;
    }

    private function encodeParams($arParams)
    {
        return serialize($arParams);
    }

    private function decodeParams($cell)
    {
        if (empty($cell)) {
            $this->saved_params = false;
            $this->id = null;
            return false;
        }
        $this->saved_params = unserialize($cell);
        return $this->saved_params;
    }
}