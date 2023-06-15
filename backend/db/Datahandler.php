<?php

namespace db;
use mysqli;

include ("dbaccess.php");
class Datahandler
{
    private $servername;
    private $username;
    private $password;
    private $db;
    private $conn;
    private $credentials;

    function __construct()
    {
        // establishes connection to server
        $this->credentials = connect();
        $this->servername = $this->credentials["servername"];
        $this->username = $this->credentials["username"];
        $this->password = $this->credentials["password"];
        $this->db = $this->credentials["db"];
        $this->conn = new MySQLi($this->servername, $this->username, $this->password, $this->db);
    }

    //brauchen wir das?
    function __destruct()
    {
        // closes connection to server
        $this->conn->close();
    }

    public function Get_UserEmail($email){
        //$query = "SELECT email, pers_ID FROM amazonas_webshop.person WHERE email = ?";
        $query = "SELECT email, pers_ID, is_active FROM amazonas_webshop.person
                  JOIN amazonas_webshop.user ON amazonas_webshop.person.pers_ID = amazonas_webshop.user.fk_pers_ID
                  WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_row();
    }

    public function Get_Userpassword($id){
        $query = "SELECT password FROM amazonas_webshop.user WHERE fk_pers_ID = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $tmp = $stmt->get_result()->fetch_assoc();
        return $tmp["password"];
    }



    public function Get_Userdata($param){
        $EmailTemp = $param["emailLogin"];
        $emailarr = $this->Get_UserEmail($EmailTemp);
        if($emailarr == null){
            return "Wrong email";
        }
        $email = $emailarr[0];
        $id = $emailarr[1];
        $active = $emailarr[2];
        if($active == 0){
            return "Account not activated";
        }
        $password = $this->Get_Userpassword($id);

        $tmp = "Wrong password";
        if(password_verify($param["passwordLogin"], $password) && $email === $param["emailLogin"]) {
            $query="SELECT email, firstname, lastname, city, postal_code, street, housenumber, doornumber
                    FROM address a
                    INNER JOIN person p ON a.addr_ID = p.fk_addr_ID
                    WHERE email = ? AND (SELECT password FROM user WHERE fk_pers_ID = ".$id.") = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ss", $email, $password);
            $stmt->execute();
            $tmp = $stmt->get_result()->fetch_row();
            if ($tmp == null) {
                return "Not found";
            }
        }
        return $tmp;
    }

    public function getAccountDetails($param){

        $sql = "SELECT email, firstname, lastname, city, postal_code, street, housenumber, doornumber
                FROM address a
                INNER JOIN person p ON a.addr_ID = p.fk_addr_ID
                WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $param);
        $stmt->execute();
        $tmp = $stmt->get_result()->fetch_row();
        if ($tmp == null) {
            return "Not found";
        }
        return $tmp;
    }

    public function getOrders($param) {
        //TODO: SQL Query for getting orders
        return "No orders";
    }

    public function getProducts(){
        $sql = "SELECT * FROM amazonas_webshop.product";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $tmp = $stmt->get_result()->fetch_all();
        if ($tmp == null) {
            return "No Products";
        }
        return $tmp;
    }
    public function getSpecificProduct($id){
        $sql = "SELECT * FROM amazonas_webshop.product WHERE prod_ID = ".$id;
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $tmp = $stmt->get_result()->fetch_row();
        if ($tmp == null) {
            return "No Product";
        }
        // foreach($tmp as $key => $value){
        //     error_log($key." ".$value);
        // }
        return $tmp;
    }

    public function registerUser($param)
    {
        if($this->Get_UserEmail($param["email"]) != null){
            return "Email already exists";
        }

        $param["password"] = password_hash($param['password'], PASSWORD_DEFAULT);

        $query = "INSERT INTO amazonas_webshop.address (street, housenumber, doornumber, postal_code, city) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("siiis", $param["street"], $param["housenumber"], $param["doornumber"], $param["postalcode"], $param["city"]);
        $stmt->execute();

        // get id of last entry
        $id = $this->conn->query("SELECT MAX(addr_ID) as addr_ID FROM amazonas_webshop.address")->fetch_assoc()['addr_ID'];

        $query = "INSERT INTO amazonas_webshop.person (email, fk_addr_ID, firstname, lastname) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("siss", $param["email"], $id, $param["firstname"], $param["lastname"]);
        $stmt->execute();

        // get id of last entry
        $id = $this->conn->query("SELECT MAX(pers_ID) as pers_ID FROM amazonas_webshop.person")->fetch_assoc()['pers_ID'];

        $query = "INSERT INTO amazonas_webshop.user (fk_pers_ID, password, is_active) VALUES (?, ?, TRUE)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("is", $id, $param["password"]);
        $stmt->execute();

        $query = "SELECT * FROM amazonas_webshop.person
                      JOIN amazonas_webshop.user ON amazonas_webshop.person.pers_ID = amazonas_webshop.user.fk_pers_ID
                      JOIN amazonas_webshop.address ON amazonas_webshop.person.fk_addr_ID = amazonas_webshop.address.addr_ID
                      WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $param["email"]);
        $stmt->execute();
        $tmp = $stmt->get_result()->fetch_row();
        if ($tmp == null) {
            return "NULL";
        }
        return $tmp;
    }
}