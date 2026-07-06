<?php
class Result {
    private $conn;
    private $table_name = "results";

    public $id;
    public $student_id;
    public $subject_id;
    public $semester;
    public $ca_mark = null;

    public $endterm_mark = null;
    public $marks;
    public $gpa = null;
    public $grade = null;
    public $exam_date;
    public $published;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                 (student_id, subject_id, semester, ca_mark, endterm_mark, marks, gpa, grade, exam_date, published) 
                 VALUES (:student_id, :subject_id, :semester, :ca_mark, :endterm_mark, :marks, :gpa, :grade, :exam_date, 0)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":student_id", $this->student_id);
        $stmt->bindParam(":subject_id", $this->subject_id);
        $stmt->bindParam(":semester", $this->semester, PDO::PARAM_INT);
        $stmt->bindParam(":ca_mark", $this->ca_mark);

        $stmt->bindParam(":endterm_mark", $this->endterm_mark);
        $stmt->bindParam(":marks", $this->marks);
        $stmt->bindParam(":gpa", $this->gpa);
        $stmt->bindParam(":grade", $this->grade);
        $stmt->bindParam(":exam_date", $this->exam_date);

        return $stmt->execute();
    }

    public function publish($id) {
        $query = "UPDATE " . $this->table_name . "
                 SET published = 1 
                 WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    public function getStudentResults($student_id, $semester = null) {
        $query = "SELECT r.*, s.name as subject_name, s.code as subject_code 
                 FROM " . $this->table_name . " r
                 JOIN subjects s ON r.subject_id = s.id
                 WHERE r.student_id = :student_id 
                   AND r.published = 1";

        if($semester !== null) {
            $query .= " AND r.semester = :semester";
        }

        $query .= " ORDER BY r.exam_date DESC, s.name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":student_id", $student_id);

        if($semester !== null) {
            $stmt->bindParam(":semester", $semester, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt;
    }
}
?>