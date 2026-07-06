<?php
class Admin
{
    private $conn;
    private $table_name = "admins";

    public $id;
    public $username;
    public $password;
    public $email;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function login($username, $password)
    {
        $query = "SELECT id, username, password FROM " . $this->table_name . " 
                 WHERE username = :username AND password = :password LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":password", $password);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->username = $row['username'];
            return true;
        }
        return false;
    }

    public function changePassword($new_password)
    {
        $query = "UPDATE " . $this->table_name . "
                 SET password = :password 
                 WHERE id = :id";

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }
}
?>