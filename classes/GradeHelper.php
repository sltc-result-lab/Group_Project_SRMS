<?php
class GradeHelper {
    public static function getGradeData($marks) {
        $marks = (float)$marks;
        if ($marks >= 85) return ['grade' => 'A+', 'point' => 4.0, 'class' => 'grade-ap'];
        if ($marks >= 80) return ['grade' => 'A',  'point' => 4.0, 'class' => 'grade-a'];
        if ($marks >= 75) return ['grade' => 'A-', 'point' => 3.7, 'class' => 'grade-a'];
        if ($marks >= 70) return ['grade' => 'B+', 'point' => 3.3, 'class' => 'grade-b'];
        if ($marks >= 65) return ['grade' => 'B',  'point' => 3.0, 'class' => 'grade-b'];
        if ($marks >= 60) return ['grade' => 'B-', 'point' => 2.7, 'class' => 'grade-b'];
        if ($marks >= 55) return ['grade' => 'C+', 'point' => 2.3, 'class' => 'grade-c'];
        if ($marks >= 50) return ['grade' => 'C',  'point' => 2.0, 'class' => 'grade-c'];
        if ($marks >= 45) return ['grade' => 'C-', 'point' => 1.7, 'class' => 'grade-c'];
        if ($marks >= 40) return ['grade' => 'D+', 'point' => 1.3, 'class' => 'grade-d'];
        if ($marks >= 35) return ['grade' => 'D',  'point' => 1.0, 'class' => 'grade-d'];
        return ['grade' => 'E', 'point' => 0.0, 'class' => 'grade-f'];
    }
}
?>
