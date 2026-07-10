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

    public function login($identifier, $password)
    {
        /*
            If your admins table has email column, keep this query.
            If your admins table does NOT have email column,
            remove: OR email = :identifier
        */

        $query = "SELECT id, username, email, password 
                  FROM " . $this->table_name . " 
                  WHERE username = :identifier OR email = :identifier 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":identifier", $identifier);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            /*
                New hashed password check
            */
            if (password_verify($password, $row['password'])) {
                $this->id = $row['id'];
                $this->username = $row['username'];
                $this->email = $row['email'] ?? null;
                return true;
            }

            /*
                Optional: old plain text password support.
                This helps if your old admin password is still plain text in DB.
                After successful login, it automatically converts it to hashed password.
            */
            if (hash_equals($row['password'], $password)) {
                $this->id = $row['id'];
                $this->username = $row['username'];
                $this->email = $row['email'] ?? null;

                $this->changePassword($password);

                return true;
            }
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