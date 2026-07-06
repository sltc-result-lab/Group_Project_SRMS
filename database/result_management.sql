CREATE DATABASE IF NOT EXISTS result_management;
USE result_management;

CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    register_number VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    degree_programme VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    degree_programme VARCHAR(20) NOT NULL,
    semester TINYINT DEFAULT 1
);

CREATE TABLE results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    subject_id INT,
    ca_mark DECIMAL(5,2) DEFAULT NULL,
    endterm_mark DECIMAL(5,2) DEFAULT NULL,
    marks FLOAT NOT NULL,
    gpa DECIMAL(3,2) DEFAULT NULL,
    grade VARCHAR(5) DEFAULT NULL,
    exam_date DATE NOT NULL,
    semester TINYINT DEFAULT 1,
    published BOOLEAN DEFAULT FALSE,
    publish_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
);

CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert a default admin user (password: admin123)
INSERT INTO admins (username, password, email) 
VALUES ('admin', 'admin123', 'admin@example.com');

ALTER TABLE students ADD COLUMN date_of_birth DATE AFTER degree_programme;
ALTER TABLE results ADD COLUMN publish_at DATETIME DEFAULT NULL AFTER published;
CREATE INDEX idx_publish_at ON results(publish_at); 