<?php

include 'creds.php';
include 'vendor/autoload.php';

/**
 * This is how we determine who is allowed to call through and when.
 *
 * @param type $caller
 * @param type $whitelist
 * @param type $starttime
 * @param type $endtime
 * @return boolean
 */
function is_allowed($number, $whitelist = array(), $starttime = 9, $endtime = 17)
{
    if (in_array($number, $whitelist)) {
        return true;
    }

    $currentHour = date('G');
    if (($starttime <= $currentHour) && ($currentHour < $endtime)) {
        return true;
    }

    return false;
}

function is_allowed_adv($number, $starttime = 9, $endtime = 17)
{
    if (onWhitelist($number)) {
        return true;
    }
    if (onBlacklist($number)) {
        return false;
    }

    $currentHour = date('G');
    if (($starttime <= $currentHour) && ($currentHour < $endtime)) {
        return true;
    }

    return false;
}

class Permissions
{
    protected $_file_db;

    public function __construct() {
        $this->_file_db = new PDO('sqlite:messaging.sqlite3');
        $this->_file_db->exec("CREATE TABLE IF NOT EXISTS lists (
                            id INTEGER PRIMARY KEY,
                            number TEXT,
                            type TEXT,
                            name TEXT,
                            time INTEGER)");
    }

    public function initDB()
    {
        return $this->_file_db;
    }

    public function onWhitelist($number)
    {
        $file_db = $this->_file_db;
        $select = "SELECT * FROM lists WHERE number = :number AND type LIKE '%:list%'";
        $stmt = $file_db->prepare($select);
        $stmt->bindValue(':number', $number, SQLITE3_TEXT);
        $stmt->bindValue(':list', 'white', SQLITE3_TEXT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        return (count($result)) ? true : false;
    }

    public function onBlacklist($number)
    {
        $file_db = $this->_file_db;
        $select = "SELECT * FROM lists WHERE number = :number AND type LIKE '%:list%'";
        $stmt = $file_db->prepare($select);
        $stmt->bindValue(':number', $number, SQLITE3_TEXT);
        $stmt->bindValue(':list', 'black', SQLITE3_TEXT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        return (count($result)) ? true : false;
    }

    public function removeNumber($number)
    {
        $file_db = $this->_file_db;
        $delete = "DELETE FROM lists WHERE number = :number";
        $stmt = $file_db->prepare($delete);
        $stmt->bindValue(':number', $number, SQLITE3_TEXT);

        return $stmt->execute();
    }

    public function updateList($number, $list_type)
    {
        $file_db = $this->_file_db;
        $this->removeNumber($number);

        $insert = "INSERT INTO lists (number, type, time) VALUES (:number, :type, :time)";
        $stmt = $file_db->prepare($insert);
        $stmt->bindValue(':number', $number, SQLITE3_TEXT);
        $stmt->bindValue(':type',   $list_type, SQLITE3_TEXT);
        $stmt->bindValue(':time',   time(), SQLITE3_TEXT);

        return $stmt->execute();
    }
}

function initDB()
{
    $perms = new Permissions();
    return $perms->initDB();
}

function onWhitelist($number)
{
    $perms = new Permissions();
    return $perms->onWhitelist($number);
}

function onBlacklist($number)
{
    $perms = new Permissions();
    return $perms->onBlacklist($number);
}

function find_in_list($number)
{
    $type = '';

    $file_db = initDB();
    $select = "SELECT * FROM lists WHERE number = :number";
    $stmt = $file_db->prepare($select);
    $stmt->bindValue(':number', $number, SQLITE3_TEXT);
    $stmt->execute();

    $result = $stmt->fetchAll();

    foreach ($result as $row) {
        $type = $row['type'];
    }

    return $type;
}

function remove_number($number)
{
    $perms = new Permissions();
    return $perms->removeNumber($number);
}

function update_list($number, $list_type)
{
    $perms = new Permissions();
    return $perms->updateList($number, $list_type);
}

function process_command($message)
{
    $words = explode(' ', $message);
    $response = "Unknown command. Use: allow, block, remove, status, or name with a phone number to manage your lists";

    if (2 > count($words)) {
        return $response;
    }

    $command = $words[0];
    $number  = preg_replace("/[^0-9]/", "", $words[1]);
    
    switch($command) {
        case 'allow':       // add to whitelist
            $result = update_list($number, 'white');
            $response = ($result) ? "$number has been added to your whitelist" :
                "An error has occured adding $number to your whitelist";
            break;
        case 'block':       // add to blacklist
            $result = update_list($number, 'black');
            $response = ($result) ? "$number has been added to your blacklist" :
                "An error has occured adding $number to your blacklist";
            break;
        case 'remove':      // remove from either list
            $result = remove_number($number);
            $response = ($result) ? "$number has been removed from your lists" :
                "An error has occured removing $number from your lists";
            break;
        case 'status':      // check where the number is
            $result = find_in_list($number);
            $response = ('' == $result) ? "$number was not in your lists" :
                "$number is in your {$result}list";
            break;
        case 'name':        // add a name to the number
            $response = "This will eventually assign a name to this number: $number";
            break;
        default:
            // do nothing, let the "unknown" response from above fall through
    }

    return $response;
}