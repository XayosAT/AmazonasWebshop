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

    function __destruct()
    {
        // closes connection to server
        $this->conn->close();
    }

    public function Get_UserEmail($email){
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

    public function Get_Basket_Items($email){
        $query = "SELECT fk_prod_ID, amount, product_name, price, picture, short_description FROM amazonas_webshop.basket
                  JOIN amazonas_webshop.product ON amazonas_webshop.basket.fk_prod_ID = amazonas_webshop.product.prod_ID
                  JOIN amazonas_webshop.user ON amazonas_webshop.basket.fk_user_ID = amazonas_webshop.user.user_ID
                  JOIN amazonas_webshop.person ON amazonas_webshop.user.fk_pers_ID = amazonas_webshop.person.pers_ID
                  WHERE email = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $tmp = $stmt->get_result()->fetch_all();
        if ($tmp == null) {
            return "No items";
        }
        return $tmp;
    }

    public function Remove_Item_From_Basket($param){
        $email = $param["email"];
        $productID = $param["productID"];
        $amount = $param["amount"];

        $this->Update_Stock_Of_Product($productID, $amount);

        $userID = $this->Get_User_ID_From_Email($email);
        $query = "DELETE FROM basket WHERE fk_prod_ID = ? AND fk_user_ID = ".$userID[0];
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $productID);

        if ($stmt->execute()) {
            // Deletion successful
            return "Item deleted from the basket.";
        } else {
            // Error occurred
            return "Error deleting item from the basket.";
        }
    }

    public function Increase_amount_of_Product($productID, $email, $amount){

        $sql = "SELECT stock FROM amazonas_webshop.product WHERE prod_ID = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $productID);
        $stmt->execute();
        $tmp = $stmt->get_result()->fetch_assoc();
        $stock = $tmp["stock"];
        if ($stock < $amount) {
            return "Not enough items in stock";
        }

        $userID = $this->Get_User_ID_From_Email($email);
        $update = "UPDATE basket SET amount = amount + ? WHERE fk_prod_ID = ? AND fk_user_ID = $userID[0]";
        $stmt = $this->conn->prepare($update);
        $stmt->bind_param("ii", $amount, $productID);
        $result = $stmt->execute();

        $result = $result && $this->Update_Stock_Of_Product($productID, (0-$amount));

        if ($result) {
            // Update successful
            return "Item updated in the basket +";
        } else {
            // Error occurred
            return "Error updating item amount.";
        }
    }

    public function Add_Item_To_Basket($param){
        $productID = $param["prodId"];
        $email = $param["email"];
        $amount = $param["amount"];
        $type = $param["type"];

        $userID = $this->Get_User_ID_From_Email($email);

        $prodArr = $this->Get_Prod_ID_From_User_ID($userID[0]);


        foreach ($prodArr as $prod){
            if($prod[0] === $productID) {
                if($type === "-")
                    return $this->Decrease_Amount_In_Basket($productID, $email);
                else
                    return $this->Increase_amount_of_Product($productID, $email, $amount);
            }
        }

        $result = $this->Update_Stock_Of_Product($productID, (0-$amount));
        if (!$result) {
            return "Error adding item to the basket";
        }
        $insert = "INSERT INTO basket (fk_prod_ID, fk_user_ID, amount) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($insert);
        $stmt->bind_param("iii", $productID, $userID[0], $amount);
        $result = $result && $stmt->execute();


        if ($result) {
            // Insertion successful
            return "Item added to the basket";
        } else {
            // Error occurred
            return "Error adding item to the basket";
        }
    }

    public function Get_Prod_ID_From_User_ID($userID){
        $query = "SELECT fk_prod_ID FROM amazonas_webshop.basket WHERE fk_user_ID = $userID";
        return $this->conn->query($query)->fetch_all();
    }

    public function Decrease_Amount_In_Basket($productID, $email){
        $userID = $this->Get_User_ID_From_Email($email);
        $update = "UPDATE basket SET amount = amount - 1 WHERE fk_prod_ID = ? AND fk_user_ID = $userID[0]";
        $stmt = $this->conn->prepare($update);
        $stmt->bind_param("i", $productID);
        $result = $stmt->execute();

        $result = $result && $this->Update_Stock_Of_Product($productID, 1);

        if($result){
            return "Item updated in the basket -";
        } else {
            return "Error updating item in the basket";
        }
    }

    public function Update_Stock_Of_Product($productID, $amount){

        $stmt = $this->conn->prepare("SELECT stock FROM amazonas_webshop.product WHERE prod_ID = ?");
        $stmt->bind_param("i", $productID);
        $stmt->execute();
        $tmp = $stmt->get_result()->fetch_assoc();
        $stock = $tmp["stock"];
        if($amount < 0 && $stock < abs($amount)){
            return false;
        }

        $update = "UPDATE product SET stock = stock + ? WHERE prod_ID = ?";
        $stmt = $this->conn->prepare($update);
        $stmt->bind_param("ii", $amount, $productID);
        if ($stmt->execute()) {
            // Update successful
            return true;
        } else {
            // Error occurred
            return false;
        }
    }

    public function querySearchDetails($searchTerm) {
        // searches keywords in details
        $term = '%' . $searchTerm . '%';
        $query = "SELECT prod_ID, product_name FROM amazonas_webshop.product WHERE product.product_name LIKE ? LIMIT 10";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $term);
        $stmt->execute();
        $tmp = $stmt->get_result()->fetch_all();
        if ($tmp == null) {
            error_log("NULL1");
            return "NULL";
        }
        return $tmp;
    }

    public function Get_User_ID_From_Email($email){
        $query = "SELECT pers_ID FROM amazonas_webshop.person WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_row();
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
        $sql = "SELECT date, amount, price, picture, product_name, short_description
                    FROM `order`
                    INNER JOIN ordered_products ON `order`.r_ID = ordered_products.fk_r_ID
                    INNER JOIN product ON ordered_products.fk_prod_ID = product.prod_ID
                    INNER JOIN user ON `order`.fk_user_ID = user.user_ID
                    INNER JOIN person ON user.fk_pers_ID = person.pers_ID
                    WHERE email = ?
                    ORDER BY date DESC
                    LIMIT 10";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $param);
        $stmt->execute();
        $tmp = $stmt->get_result()->fetch_all();
        if ($tmp == null) {
            return "No orders";
        }
        return $tmp;
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

        return "Success";
    }

    public function validate_email_and_name($email, $firstname, $lastname) {
        $query = "SELECT email, firstname, lastname FROM amazonas_webshop.person WHERE email = ? AND firstname = ? AND lastname = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $email, $firstname, $lastname);
        $stmt->execute();
        $tmp = $stmt->get_result()->fetch_row();
        if ($tmp == null) {
            return false;
        } else {
            return true;
        }
    }

    public function paymentIntoDatabase($param) {
        $email = $param["email"];
        $firstname = $param["firstname"];
        $lastname = $param["lastname"];
        $expmonth = $param["expmonth"];
        $expyear = $param["expyear"];
        $date = date("d.m.y");
        error_log($date);


        if(date_format(date_create($date), "m") > $expmonth && date_format(date_create($date), "y") >= $expyear) {
            return "Card is expired";
        }

        if(!$this->validate_email_and_name($email, $firstname, $lastname)) {
            return "Email or name is not valid";
        }

        $userID = $this->Get_User_ID_From_Email($email);
        $tmp = $this->Get_Basket_Items($email);
        if($tmp === "No items") {
            return "Basket is empty";
        }

        $status = 1;

        foreach ($tmp as $value) {

            $query = "INSERT INTO amazonas_webshop.order (fk_user_ID, status, date) VALUES (?, ?, NOW() )";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $userID,$status);
            $stmt->execute();

            $id = $this->conn->query("SELECT MAX(r_ID) as r_ID FROM amazonas_webshop.order")->fetch_assoc()['r_ID'];

            $query = "INSERT INTO amazonas_webshop.ordered_products (fk_r_ID, fk_prod_ID, amount) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iii", $id, $value[0], $value[1]);


            if($stmt->execute()) {
                $this->Remove_All_From_Basket($email);
                return "Success";
            } else {
                return "Error";
            }
        }
        return "Error";
    }

    public function Remove_All_From_Basket($email){

        $userID = $this->Get_User_ID_From_Email($email);
        $query = "SELECT fk_prod_ID, amount FROM amazonas_webshop.basket WHERE fk_user_ID = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $userID[0]);
        $stmt->execute();

        $tmp = $stmt->get_result()->fetch_all();

        foreach ($tmp as $value) {
            $this->Update_Stock_Of_Product($value[0], $value[1]);
            $query = "DELETE FROM amazonas_webshop.basket WHERE fk_user_ID = ? AND fk_prod_ID = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $userID[0], $value[0]);
            $stmt->execute();
        }


        if ($tmp == null) {
            return "Empty";
        }
        return "Success";
    }
}