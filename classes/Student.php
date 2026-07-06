<?php
class Student {
    private $conn;
    private $table_name = "students";

    public $id;
    public $register_number;
    public $name;
    public $email;
    public $degree_programme;
    public $date_of_birth;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                 (register_number, name, email, degree_programme, date_of_birth) 
                 VALUES (:register_number, :name, :email, :degree_programme, :date_of_birth)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":register_number", $this->register_number);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":degree_programme", $this->degree_programme);
        $stmt->bindParam(":date_of_birth", $this->date_of_birth);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function read() {
        $query = "SELECT * FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " 
                 WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();
        return $stmt;
    }

    public function delete($id) {
        // First check if there are any results for this student
        $check_query = "SELECT id FROM results WHERE student_id = :id LIMIT 1";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(":id", $id);
        $check_stmt->execute();
        
        if($check_stmt->rowCount() > 0) {
            // Student has results, cannot delete
            return false;
        }
        
        // If no results exist, proceed with deletion
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                 SET register_number = :register_number,
                     name = :name,
                     email = :email,
                     degree_programme = :degree_programme
                 WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":register_number", $this->register_number);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":degree_programme", $this->degree_programme);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?> 