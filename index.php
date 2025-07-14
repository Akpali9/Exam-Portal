<?php
session_start();

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "exam_portal";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Create tables if not exists
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','student') DEFAULT 'student',
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        duration INT NOT NULL COMMENT 'Duration in minutes',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT NOT NULL,
        question_text TEXT NOT NULL,
        option1 VARCHAR(255) NOT NULL,
        option2 VARCHAR(255) NOT NULL,
        option3 VARCHAR(255) NOT NULL,
        option4 VARCHAR(255) NOT NULL,
        correct_option INT NOT NULL,
        FOREIGN KEY (exam_id) REFERENCES exams(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS exam_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT NOT NULL,
        student_id INT NOT NULL,
        score INT NOT NULL,
        total_questions INT NOT NULL,
        percentage FLOAT NOT NULL,
        taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (exam_id) REFERENCES exams(id),
        FOREIGN KEY (student_id) REFERENCES users(id),
        UNIQUE KEY unique_exam_student (exam_id, student_id)
    )",
    
    "CREATE TABLE IF NOT EXISTS student_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        result_id INT NOT NULL,
        question_id INT NOT NULL,
        selected_option INT NOT NULL,
        FOREIGN KEY (result_id) REFERENCES exam_results(id),
        FOREIGN KEY (question_id) REFERENCES questions(id)
    )"
];

foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // User Registration
    if (isset($_POST['register'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        
        $sql = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', '$role')";
        if ($conn->query($sql)) {
            $_SESSION['message'] = "Registration successful! Please login.";
            $_SESSION['message_type'] = "success";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $_SESSION['message'] = "Error: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }
    }
    
    // Admin creating user
    if (isset($_POST['create_user']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $created_by = $_SESSION['user_id'];
        
        $sql = "INSERT INTO users (name, email, password, role, created_by) 
                VALUES ('$name', '$email', '$password', '$role', $created_by)";
        if ($conn->query($sql)) {
            $_SESSION['message'] = ucfirst($role) . " created successfully!";
            $_SESSION['message_type'] = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "?action=manage_users");
            exit();
        } else {
            $_SESSION['message'] = "Error: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }
            $avatar = handleAvatarUpload();
    
    $sql = "INSERT INTO users (name, email, password, role, avatar) 
            VALUES ('$name', '$email', '$password', '$role', " . 
            ($avatar ? "'$avatar'" : "NULL") . ")";
    }
    
    // User Login
    if (isset($_POST['login'])) {
        $email = $conn->real_escape_string($_POST['email']);
        $password = $_POST['password'];
        
        $sql = "SELECT * FROM users WHERE email='$email'";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // In login handler
if (password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['avatar'] = $user['avatar']; 

                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $_SESSION['message'] = "Invalid password!";
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "No user found with that email!";
            $_SESSION['message_type'] = "error";
        }
    }
    
    // Create Exam (Admin)
    if (isset($_POST['create_exam']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $title = $conn->real_escape_string($_POST['title']);
        $description = $conn->real_escape_string($_POST['description']);
        $duration = intval($_POST['duration']);
        $admin_id = $_SESSION['user_id'];
        
        // Get start and end times
        $start_time = $conn->real_escape_string($_POST['start_time']);
        $end_time = $conn->real_escape_string($_POST['end_time']);
        
        $sql = "INSERT INTO exams (title, description, duration, created_by, start_time, end_time) 
                VALUES ('$title', '$description', $duration, $admin_id, '$start_time', '$end_time')";
        
        if ($conn->query($sql)) {
            $_SESSION['message'] = "Exam created successfully!";
            $_SESSION['message_type'] = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "?action=manage_exams");
            exit();
        } else {
            $_SESSION['message'] = "Error creating exam: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }
    }
    
    // Add Question (Admin)
    if (isset($_POST['add_question']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $exam_id = intval($_POST['exam_id']);
        $question_text = $conn->real_escape_string($_POST['question_text']);
        $option1 = $conn->real_escape_string($_POST['option1']);
        $option2 = $conn->real_escape_string($_POST['option2']);
        $option3 = $conn->real_escape_string($_POST['option3']);
        $option4 = $conn->real_escape_string($_POST['option4']);
        $correct_option = intval($_POST['correct_option']);
        
        $sql = "INSERT INTO questions (exam_id, question_text, option1, option2, option3, option4, correct_option) 
                VALUES ($exam_id, '$question_text', '$option1', '$option2', '$option3', '$option4', $correct_option)";
        
        if ($conn->query($sql)) {
            $_SESSION['message'] = "Question added successfully!";
            $_SESSION['message_type'] = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "?action=manage_exam&exam_id=$exam_id");
            exit();
        } else {
            $_SESSION['message'] = "Error adding question: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }
    }
    
    // Submit Exam (Student)
    if (isset($_POST['submit_exam']) && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
        $exam_id = intval($_POST['exam_id']);
        $student_id = $_SESSION['user_id'];
        
        // Check if student has already taken this exam
        $check_sql = "SELECT * FROM exam_results WHERE exam_id = $exam_id AND student_id = $student_id";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            $_SESSION['message'] = "You have already taken this exam!";
            $_SESSION['message_type'] = "error";
            header("Location: " . $_SERVER['PHP_SELF'] . "?action=home");
            exit();
        }
        
        // Calculate score
        $total_questions = 0;
        $correct_answers = 0;
        
        $sql = "SELECT * FROM questions WHERE exam_id = $exam_id";
        $result = $conn->query($sql);
        
        while ($question = $result->fetch_assoc()) {
            $total_questions++;
            $qid = $question['id'];
            if (isset($_POST['answer_'.$qid]) && intval($_POST['answer_'.$qid]) === $question['correct_option']) {
                $correct_answers++;
            }
        }
        
        $percentage = ($correct_answers / $total_questions) * 100;
        
        // Save exam result
        $sql = "INSERT INTO exam_results (exam_id, student_id, score, total_questions, percentage) 
                VALUES ($exam_id, $student_id, $correct_answers, $total_questions, $percentage)";
        
        if ($conn->query($sql)) {
            $result_id = $conn->insert_id;
            
            // Save student answers
            $result = $conn->query("SELECT * FROM questions WHERE exam_id = $exam_id");
            while ($question = $result->fetch_assoc()) {
                $qid = $question['id'];
                $selected_option = isset($_POST['answer_'.$qid]) ? intval($_POST['answer_'.$qid]) : 0;
                
                $sql = "INSERT INTO student_answers (result_id, question_id, selected_option) 
                        VALUES ($result_id, $qid, $selected_option)";
                $conn->query($sql);
            }
            
            $_SESSION['exam_result'] = [
                'score' => $correct_answers,
                'total' => $total_questions,
                'percentage' => $percentage,
                'exam_id' => $exam_id
            ];
            
            header("Location: " . $_SERVER['PHP_SELF'] . "?action=exam_result");
            exit();
        } else {
            $_SESSION['message'] = "Error saving exam result: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Helper functions
function getExams($conn, $admin_id = null) {
    $sql = "SELECT * FROM exams";
    if ($admin_id) {
        $sql .= " WHERE created_by = $admin_id";
    }
    $result = $conn->query($sql);
    $exams = [];
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    return $exams;
}

function getAvailableExams($conn, $student_id) {
    $current_time = date('Y-m-d H:i:s');
    $sql = "SELECT e.* 
            FROM exams e
            WHERE NOT EXISTS (
                SELECT 1 
                FROM exam_results er 
                WHERE er.exam_id = e.id AND er.student_id = $student_id
            )
            AND e.start_time <= '$current_time'
            AND e.end_time >= '$current_time'";
    $result = $conn->query($sql);
    $exams = [];
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    return $exams;
}

function getUpcomingExams($conn, $student_id) {
    $current_time = date('Y-m-d H:i:s');
    $sql = "SELECT e.* 
            FROM exams e
            WHERE NOT EXISTS (
                SELECT 1 
                FROM exam_results er 
                WHERE er.exam_id = e.id AND er.student_id = $student_id
            )
            AND e.start_time > '$current_time'";
    $result = $conn->query($sql);
    $exams = [];
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    return $exams;
}

function getExam($conn, $exam_id) {
    $sql = "SELECT * FROM exams WHERE id = $exam_id";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

function getQuestions($conn, $exam_id) {
    $sql = "SELECT * FROM questions WHERE exam_id = $exam_id";
    $result = $conn->query($sql);
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    return $questions;
}
// Handle avatar upload function
function handleAvatarUpload() {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "avatars/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $filename = uniqid() . '_' . basename($_FILES['avatar']['name']);
        $targetFile = $targetDir . $filename;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        
        // Check if image file
        $check = getimagesize($_FILES['avatar']['tmp_name']);
        if ($check === false) {
            return null;
        }
        
        // Allow certain file formats
        if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
            return null;
        }
        
        // Try to upload file
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
            return $targetFile;
        }
    }
    return null;
}

function getExamResults($conn, $exam_id) {
    $sql = "SELECT er.*, u.name AS student_name 
            FROM exam_results er
            JOIN users u ON er.student_id = u.id
            WHERE er.exam_id = $exam_id
            ORDER BY er.taken_at DESC";
    $result = $conn->query($sql);
    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    return $results;
}

function getUsers($conn, $role = null) {
    $sql = "SELECT * FROM users";
    if ($role) {
        $sql .= " WHERE role='$role'";
    }
    $result = $conn->query($sql);
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    return $users;
}

// Determine current action
$action = isset($_GET['action']) ? $_GET['action'] : 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Exam Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #5e7cff;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --success-light: #7ddbf4;
            --danger: #f72585;
            --warning: #fca311;
            --light: #f8f9fa;
            --light-2: #eef2ff;
            --dark: #212529;
            --dark-2: #343a40;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border: #dee2e6;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
            background-size: cover;
            background-attachment: fixed;
        }
        
        body.admin-login {
            background: linear-gradient(135deg, #2c3e50 0%, #1a2530 100%);
        }
        
        body.student-login {
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo h1 {
            font-weight: 700;
            font-size: 28px;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .logo-icon {
            font-size: 32px;
            background: white;
            color: var(--primary);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 15px;
        }
        
        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 8px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
        }
        
        nav a:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
        }
        
        .btn:hover {
            background: var(--secondary);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(67, 97, 238, 0.4);
        }
        
        .btn-secondary {
            background: var(--gray);
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.3);
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-success {
            background: var(--success);
            box-shadow: 0 4px 10px rgba(76, 201, 240, 0.3);
        }
        
        .btn-success:hover {
            background: #0dcaf0;
        }
        
        .btn-danger {
            background: var(--danger);
            box-shadow: 0 4px 10px rgba(247, 37, 133, 0.3);
        }
        
        .btn-warning {
            background: var(--warning);
            color: var(--dark);
            box-shadow: 0 4px 10px rgba(252, 163, 17, 0.3);
        }
        
        .btn-light {
            background: white;
            color: var(--primary);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .btn-light:hover {
            background: #f0f0f0;
            color: var(--secondary);
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
            transition: var(--transition);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            border-bottom: 1px solid var(--border);
            padding-bottom: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .exam-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .exam-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .exam-header {
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 20px;
        }
        
        .exam-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .exam-body {
            padding: 25px;
        }
        
        .exam-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            font-size: 14px;
            color: var(--gray);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark-2);
        }
        
        input, select, textarea {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 16px;
            transition: var(--transition);
            background: white;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .options-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--warning);
            color: var(--dark);
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 700;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .question {
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border);
        }
        
        .question-text {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark-2);
        }
        
        .options label {
            display: block;
            padding: 15px 20px;
            margin-bottom: 12px;
            background: var(--light-2);
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid transparent;
        }
        
        .options label:hover {
            background: #e0e7ff;
            border-color: var(--primary-light);
        }
        
        .options input[type="radio"] {
            width: auto;
            margin-right: 12px;
            accent-color: var(--primary);
        }
        
        .results-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 25px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .results-table th, .results-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .results-table th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }
        
        .results-table tr:nth-child(even) {
            background: var(--light-2);
        }
        
        .results-table tr:hover {
            background: #e0e7ff;
        }
        
        .message {
            padding: 18px;
            margin-bottom: 25px;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
        }
        
        .success {
            background: rgba(76, 201, 240, 0.15);
            color: #0a9396;
            border: 1px solid var(--success-light);
        }
        
        .error {
            background: rgba(247, 37, 133, 0.15);
            color: #9d0208;
            border: 1px solid rgba(247, 37, 133, 0.25);
        }
        
        .result-card {
            text-align: center;
            max-width: 500px;
            margin: 50px auto;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .score-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin: 0 auto 30px;
            font-size: 28px;
            font-weight: 700;
            box-shadow: 0 10px 25px rgba(67, 97, 238, 0.3);
        }
        
        .score-value {
            font-size: 42px;
            line-height: 1;
            margin-bottom: 5px;
        }
        
        footer {
            text-align: center;
            padding: 30px 0;
            color: var(--gray);
            font-size: 14px;
            margin-top: 50px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 50px;
        }
        
        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--warning) 0%, #ff6b00 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }
        
        .login-container {
            max-width: 450px;
            margin: 80px auto;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .login-header {
            margin-bottom: 30px;
        }
        
        .login-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--dark-2);
        }
        
        .login-subtitle {
            color: var(--gray);
            font-size: 16px;
        }
        
        .login-icon {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 30px;
            background: var(--light-2);
            border-radius: 12px;
            padding: 5px;
        }
        
        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .tab.active {
            background: var(--primary);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .student-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 25px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .student-table th, .student-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .student-table th {
            background: var(--success);
            color: white;
            font-weight: 600;
        }
        
        .student-table tr:nth-child(even) {
            background: var(--light-2);
        }
        
        .student-table tr:hover {
            background: #e0f7ff;
        }
        
        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 6px 15px rgba(67, 97, 238, 0.4);
            z-index: 1000;
            transition: var(--transition);
        }
        
        .floating-btn:hover {
            transform: translateY(-5px) scale(1.05);
            background: var(--secondary);
        }
        
        .user-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: var(--transition);
            border: 1px solid var(--light-gray);
        }
        
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .user-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
.avatar, .user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
}

.avatar img, .user-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 18px;
            color: var(--dark-2);
        }
        
        .user-email {
            color: var(--gray);
            font-size: 14px;
        }
        
        .user-role {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .role-admin {
            background: rgba(67, 97, 238, 0.15);
            color: var(--primary);
        }
        
        .role-student {
            background: rgba(76, 201, 240, 0.15);
            color: var(--success);
        }
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .options-grid {
                grid-template-columns: 1fr;
            }
            
            header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
              
            }
            
            .login-container {
                margin: 40px auto;
                padding: 0 15px;
            }
        }
        .datetime-input {
            display: flex;
            gap: 15px;
        }
        
        .datetime-input input {
            flex: 1;
        }
        
        .exam-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }
        
        .status-upcoming {
            background: rgba(252, 163, 17, 0.15);
            color: #ca6702;
        }
        
        .status-active {
            background: rgba(76, 201, 240, 0.15);
            color: #0a9396;
        }
        
        .status-ended {
            background: rgba(247, 37, 133, 0.15);
            color: #9d0208;
        }
        
        .exam-schedule {
            margin-top: 10px;
            font-size: 14px;
            color: var(--gray);
        }
    </style>
</head>
<body class="<?php 
    if (!isset($_SESSION['user_id'])) {
        if (isset($_POST['login']) && $_POST['role'] === 'admin') echo 'admin-login';
        if (isset($_POST['login']) && $_POST['role'] === 'student') echo 'student-login';
    } 
?>">
    <div class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?php echo $_SESSION['message_type']; ?>">
                <?php 
                    echo $_SESSION['message']; 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- Login/Register Page -->
            <div class="login-container">
                <div class="login-card">
                    <div class="login-header">
                        <div class="login-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h2 class="login-title">University Exam Portal</h2>
                        <p class="login-subtitle">Where learning meets assessment</p>
                    </div>
                    
                    <div class="tabs">
                        <div class="tab active" data-tab="login">Login</div>
                        <div class="tab" data-tab="register">Register</div>
                    </div>
                    
                    <div class="tab-content active" id="login-tab">
                        <form method="POST">
                            <div class="form-group">
                                <label for="role">Login As</label>
                                <select id="role" name="role" required>
                                    <option value="student">Student</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" required placeholder="Enter your email">
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" required placeholder="Enter your password">
                            </div>
                            <button type="submit" name="login" class="btn" style="width: 100%;">Login</button>
                        </form>
                    </div>
                    
                    <div class="tab-content" id="register-tab">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" required placeholder="Enter your full name">
                            </div>
                            <div class="form-group">
                                <label for="reg_email">Email Address</label>
                                <input type="email" id="reg_email" name="email" required placeholder="Enter your email">
                            </div>
                            <div class="form-group">
        <label for="avatar">Profile Picture (optional)</label>
        <input type="file" id="avatar" name="avatar" accept="image/*">
    </div>
                            <div class="form-group">
                                <label for="reg_password">Password</label>
                                <input type="password" id="reg_password" name="password" required placeholder="Create a password">
                            </div>
                            <input type="hidden" name="role" value="student">
                            <button type="submit" name="register" class="btn btn-success" style="width: 100%;">Register as Student</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <script>
                // Tab switching
                document.querySelectorAll('.tab').forEach(tab => {
                    tab.addEventListener('click', () => {
                        // Remove active class from all tabs
                        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                        
                        // Add active class to clicked tab
                        tab.classList.add('active');
                        
                        // Show corresponding content
                        const tabId = tab.getAttribute('data-tab');
                        document.getElementById(`${tabId}-tab`).classList.add('active');
                    });
                });
            </script>
            
        <?php else: ?>
            <!-- User is logged in -->
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <!-- Admin Dashboard -->
                <header>

                    <nav>
                        <ul>
                            <li><a href="?action=home"><i class="fas fa-home"></i> Dashboard</a></li>
                            <li><a href="?action=create_exam"><i class="fas fa-plus-circle"></i> Create Exam</a></li>
                            <li><a href="?action=manage_exams"><i class="fas fa-tasks"></i> Manage Exams</a></li>
                            <li><a href="?action=view_results"><i class="fas fa-chart-bar"></i> Results</a></li>
                            <li><a href="?action=manage_users"><i class="fas fa-users"></i> Users</a></li>
                             <div class="user-info">
                       <?php if (!empty($_SESSION['avatar'])): ?>
        <img src="<?= $_SESSION['avatar'] ?>" class="avatar" alt="Profile Picture">
    <?php else: ?>
        <div class="avatar"><?= substr($_SESSION['name'], 0, 1) ?></div>
    <?php endif; ?>
    <span><?= htmlspecialchars($_SESSION['name']) ?></span>
    <a href="?logout=1" class="btn btn-light" style="padding: 8px 16px;"><i class="fas fa-sign-out-alt"></i></a>
</div>
                        </ul>
                        
                    </nav>
                   
                </header>
                
                <?php 
                switch ($action) {
                    case 'create_exam':
                        // Create Exam Form
                        ?>
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-plus-circle"></i> Create New Exam</h2>
                                <a href="?action=manage_exams" class="btn btn-secondary">Back to Exams</a>
                            </div>
                            
                            <form method="POST">
                                <div class="form-group">
                                    <label for="title">Exam Title</label>
                                    <input type="text" id="title" name="title" required placeholder="Enter exam title">
                                </div>
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" rows="3" required placeholder="Enter exam description"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="duration">Duration (minutes)</label>
                                    <input type="number" id="duration" name="duration" min="1" required placeholder="Enter exam duration">
                                </div>
                                
                                <div class="form-group">
                                    <label>Exam Schedule</label>
                                    <div class="datetime-input">
                                        <div style="flex:1;">
                                            <label for="start_time">Start Time</label>
                                            <input type="datetime-local" id="start_time" name="start_time" required>
                                        </div>
                                        <div style="flex:1;">
                                            <label for="end_time">End Time</label>
                                            <input type="datetime-local" id="end_time" name="end_time" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="create_exam" class="btn">Create Exam</button>
                            </form>
                        </div>
                        <?php
                        break;
                        
                    case 'manage_exam':
                        // Manage Exam (Add Questions)
                        if (isset($_GET['exam_id'])) {
                            $exam_id = intval($_GET['exam_id']);
                            $exam = getExam($conn, $exam_id);
                            $questions = getQuestions($conn, $exam_id);
                            ?>
                            <div class="card">
                                <div class="card-header">
                                    <h2 class="card-title"><i class="fas fa-edit"></i> Manage Exam: <?php echo htmlspecialchars($exam['title']); ?></h2>
                                    <a href="?action=manage_exams" class="btn btn-secondary">Back to Exams</a>
                                </div>
                                
                                <div class="exam-schedule">
                                    <strong>Schedule:</strong> 
                                    <?php 
                                        echo date('M d, Y h:i A', strtotime($exam['start_time'])); 
                                        echo ' to ';
                                        echo date('M d, Y h:i A', strtotime($exam['end_time'])); 
                                    ?>
                                </div>
                                
                                <form method="POST">
                                    <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                                    <div class="form-group">
                                        <label for="question_text">Question</label>
                                        <textarea id="question_text" name="question_text" rows="3" required placeholder="Enter the question"></textarea>
                                    </div>
                                    
                                    <div class="options-grid">
                                        <div class="form-group">
                                            <label for="option1">Option 1</label>
                                            <input type="text" id="option1" name="option1" required placeholder="Option 1">
                                        </div>
                                        <div class="form-group">
                                            <label for="option2">Option 2</label>
                                            <input type="text" id="option2" name="option2" required placeholder="Option 2">
                                        </div>
                                        <div class="form-group">
                                            <label for="option3">Option 3</label>
                                            <input type="text" id="option3" name="option3" required placeholder="Option 3">
                                        </div>
                                        <div class="form-group">
                                            <label for="option4">Option 4</label>
                                            <input type="text" id="option4" name="option4" required placeholder="Option 4">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="correct_option">Correct Option</label>
                                        <select id="correct_option" name="correct_option" required>
                                            <option value="1">Option 1</option>
                                            <option value="2">Option 2</option>
                                            <option value="3">Option 3</option>
                                            <option value="4">Option 4</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" name="add_question" class="btn">Add Question</button>
                                </form>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h2 class="card-title"><i class="fas fa-list"></i> Existing Questions (<?php echo count($questions); ?>)</h2>
                                </div>
                                
                                <?php if (count($questions) > 0): ?>
                                    <?php foreach ($questions as $index => $question): ?>
                                        <div class="question">
                                            <div class="question-text"><?php echo ($index+1).'. '.htmlspecialchars($question['question_text']); ?></div>
                                            <div class="options">
                                                <label>
                                                    <input type="radio" name="q<?php echo $question['id']; ?>" disabled> 
                                                    <?php echo htmlspecialchars($question['option1']); ?>
                                                    <?php if ($question['correct_option'] == 1) echo '<span style="color:var(--success); font-weight:bold;"> ✓ Correct</span>'; ?>
                                                </label>
                                                <label>
                                                    <input type="radio" name="q<?php echo $question['id']; ?>" disabled> 
                                                    <?php echo htmlspecialchars($question['option2']); ?>
                                                    <?php if ($question['correct_option'] == 2) echo '<span style="color:var(--success); font-weight:bold;"> ✓ Correct</span>'; ?>
                                                </label>
                                                <label>
                                                    <input type="radio" name="q<?php echo $question['id']; ?>" disabled> 
                                                    <?php echo htmlspecialchars($question['option3']); ?>
                                                    <?php if ($question['correct_option'] == 3) echo '<span style="color:var(--success); font-weight:bold;"> ✓ Correct</span>'; ?>
                                                </label>
                                                <label>
                                                    <input type="radio" name="q<?php echo $question['id']; ?>" disabled> 
                                                    <?php echo htmlspecialchars($question['option4']); ?>
                                                    <?php if ($question['correct_option'] == 4) echo '<span style="color:var(--success); font-weight:bold;"> ✓ Correct</span>'; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>No questions added yet. Add your first question!</p>
                                <?php endif; ?>
                            </div>
                            <?php
                        }
                        break;
                        
                    case 'view_results':
                        // View Results
                        $exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
                        $exams = getExams($conn, $_SESSION['user_id']);
                        
                        // If specific exam is selected
                        if ($exam_id > 0) {
                            $exam = getExam($conn, $exam_id);
                            $results = getExamResults($conn, $exam_id);
                        }
                        ?>
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-chart-bar"></i> Exam Results</h2>
                            </div>
                            
                            <div class="grid">
                                <?php foreach ($exams as $exam): ?>
                                    <div class="exam-card">
                                        <div class="exam-header">
                                            <div class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></div>
                                            <div class="exam-schedule">
                                                <?php 
                                                    echo date('M d, h:i A', strtotime($exam['start_time'])); 
                                                    echo ' - ';
                                                    echo date('h:i A', strtotime($exam['end_time'])); 
                                                ?>
                                            </div>
                                        </div>
                                        <div class="exam-body">
                                            <div class="exam-meta">
                                                <a href="?action=view_results&exam_id=<?php echo $exam['id']; ?>" class="btn">View Results</a>
                                                <a href="#" class="btn btn-success">Download CSV</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <?php if ($exam_id > 0): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h2 class="card-title"><i class="fas fa-poll"></i> Results for: <?php echo htmlspecialchars($exam['title']); ?></h2>
                                    <a href="#" class="btn btn-success">Download CSV</a>
                                </div>
                                
                                <?php if (count($results) > 0): ?>
                                    <table class="results-table">
                                        <thead>
                                            <tr>
                                                <th>Student Name</th>
                                                <th>Score</th>
                                                <th>Percentage</th>
                                                <th>Date Taken</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($results as $result): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($result['student_name']); ?></td>
                                                    <td><?php echo $result['score']; ?>/<?php echo $result['total_questions']; ?></td>
                                                    <td><?php echo number_format($result['percentage'], 2); ?>%</td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($result['taken_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p>No results available for this exam yet.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php
                        break;
                    
                    case 'manage_users':
                        // Manage Users (Admins and Students)
                        $admins = getUsers($conn, 'admin');
                        $students = getUsers($conn, 'student');
                        ?>
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-users"></i> Manage Users</h2>
                                <a href="#" class="btn" id="addUserBtn">Add User</a>
                            </div>
                            
                            <h3 style="margin: 20px 0 15px; color: var(--primary);"><i class="fas fa-user-tie"></i> Administrators</h3>
                            <div class="grid">
                                <?php foreach ($admins as $admin): ?>
                                    <div class="user-card">
                                        <div class="user-header">
                                             <?php if (!empty($admin['avatar'])): ?>
        <img src="<?= $admin['avatar'] ?>" class="user-avatar" alt="User Avatar">
    <?php else: ?>
                                            <div class="user-avatar"><?php echo substr($admin['name'], 0, 1); ?></div>
                                                <?php endif; ?>
                                            <div class="user-details">
                                                <div class="user-name"><?php echo htmlspecialchars($admin['name']); ?></div>
                                                <div class="user-email"><?php echo htmlspecialchars($admin['email']); ?></div>
                                            </div>
                                            <span class="user-role role-admin">Admin</span>
                                        </div>
                                        <div class="user-meta">
                                            <small>Created: <?php echo date('M d, Y', strtotime($admin['created_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <h3 style="margin: 30px 0 15px; color: var(--success);"><i class="fas fa-user-graduate"></i> Students</h3>
                            <div class="grid">
                                <?php foreach ($students as $student): ?>
                                    <div class="user-card">
                                        <div class="user-header">
                                            <div class="user-avatar"><?php echo substr($student['name'], 0, 1); ?></div>
                                            <div class="user-details">
                                                <div class="user-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                                <div class="user-email"><?php echo htmlspecialchars($student['email']); ?></div>
                                            </div>
                                            <span class="user-role role-student">Student</span>
                                        </div>
                                        <div class="user-meta">
                                            <small>Created: <?php echo date('M d, Y', strtotime($student['created_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Add User Modal -->
                        <div class="card" id="addUserForm" style="display: none;">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-user-plus"></i> Add New User</h2>
                                <a href="#" class="btn btn-secondary" id="closeUserForm">Cancel</a>
                            </div>
                            <form method="POST">
                                <div class="form-group">
                                    <label for="user_name">Full Name</label>
                                    <input type="text" id="user_name" name="name" required placeholder="Enter user's full name">
                                </div>
                                <div class="form-group">
                                    <label for="user_email">Email Address</label>
                                    <input type="email" id="user_email" name="email" required placeholder="Enter user's email">
                                </div>
                                <div class="form-group">
                                    <label for="user_password">Password</label>
                                    <input type="password" id="user_password" name="password" required placeholder="Set a password">
                                </div>
                                <div class="form-group">
                                    <label for="user_role">Role</label>
                                    <select id="user_role" name="role" required>
                                        <option value="admin">Administrator</option>
                                        <option value="student">Student</option>
                                    </select>
                                </div>
                                <button type="submit" name="create_user" class="btn btn-success">Create User Account</button>
                            </form>
                        </div>
                        
                        <script>
                            // Show/hide add user form
                            document.getElementById('addUserBtn').addEventListener('click', function(e) {
                                e.preventDefault();
                                document.getElementById('addUserForm').style.display = 'block';
                                this.style.display = 'none';
                            });
                            
                            document.getElementById('closeUserForm').addEventListener('click', function(e) {
                                e.preventDefault();
                                document.getElementById('addUserForm').style.display = 'none';
                                document.getElementById('addUserBtn').style.display = 'inline-block';
                            });
                        </script>
                        <?php
                        break;
                        
                    case 'manage_exams':
                    default:
                        // Admin Dashboard Content
                        $admin_id = $_SESSION['user_id'];
                        $exams = getExams($conn, $admin_id);
                        ?>
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-book"></i> My Created Exams</h2>
                                <a href="?action=create_exam" class="btn">Create New Exam</a>
                            </div>
                            
                            <?php if (count($exams) > 0): ?>
                                <div class="grid">
                                    <?php foreach ($exams as $exam): 
                                        $current_time = time();
                                        $start_time = strtotime($exam['start_time']);
                                        $end_time = strtotime($exam['end_time']);
                                        
                                        $status = '';
                                        if ($current_time < $start_time) {
                                            $status = '<span class="exam-status status-upcoming">Upcoming</span>';
                                        } elseif ($current_time > $end_time) {
                                            $status = '<span class="exam-status status-ended">Ended</span>';
                                        } else {
                                            $status = '<span class="exam-status status-active">Active</span>';
                                        }
                                    ?>
                                        <div class="exam-card">
                                            <div class="exam-header">
                                                <div class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></div>
                                                <div><?php echo $status; ?></div>
                                            </div>
                                            <div class="exam-body">
                                                <p><?php echo htmlspecialchars($exam['description']); ?></p>
                                                <div class="exam-schedule">
                                                    <strong>Schedule:</strong> 
                                                    <?php 
                                                        echo date('M d, Y h:i A', strtotime($exam['start_time'])); 
                                                        echo ' to ';
                                                        echo date('M d, Y h:i A', strtotime($exam['end_time'])); 
                                                    ?>
                                                </div>
                                                <div class="exam-meta">
                                                    <a href="?action=manage_exam&exam_id=<?php echo $exam['id']; ?>" class="btn">Manage Exam</a>
                                                    <a href="?action=view_results&exam_id=<?php echo $exam['id']; ?>" class="btn btn-secondary">View Results</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p>No exams created yet. Create your first exam!</p>
                            <?php endif; ?>
                        </div>
                        <?php
                        break;
                }
                ?>
                
            <?php else: ?>
                <!-- Student Dashboard -->
                <header>
                    <div class="logo">
                        <div class="logo-icon"><i class="fas fa-user-graduate"></i></div>
                        <h1>Student Dashboard</h1>
                    </div>
                    <nav>
                        <ul>
                            <li><a href="?action=home"><i class="fas fa-home"></i> Dashboard</a></li>
                            <li><a href="?action=exam_results"><i class="fas fa-medal"></i> Results</a></li>
                        </ul>
                    </nav>
                    <div class="user-info">
                        <div class="avatar"><?php echo substr($_SESSION['name'], 0, 1); ?></div>
                        <span><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                        <a href="?logout=1" class="btn btn-light" style="padding: 8px 16px;"><i class="fas fa-sign-out-alt"></i></a>
                    </div>
                </header>
                
                <?php 
                switch ($action) {
                    case 'take_exam':
                        // Take Exam
                        if (isset($_GET['exam_id'])) {
                            $exam_id = intval($_GET['exam_id']);
                            $exam = getExam($conn, $exam_id);
                            $questions = getQuestions($conn, $exam_id);
                            
                            // Check if exam is available
                            $current_time = time();
                            $start_time = strtotime($exam['start_time']);
                            $end_time = strtotime($exam['end_time']);
                            
                            if ($current_time < $start_time) {
                                $_SESSION['message'] = "This exam is not available yet. It starts at " . date('M d, Y h:i A', $start_time);
                                $_SESSION['message_type'] = "error";
                                header("Location: " . $_SERVER['PHP_SELF'] . "?action=home");
                                exit();
                            }
                            
                            if ($current_time > $end_time) {
                                $_SESSION['message'] = "This exam has ended. It was available until " . date('M d, Y h:i A', $end_time);
                                $_SESSION['message_type'] = "error";
                                header("Location: " . $_SERVER['PHP_SELF'] . "?action=home");
                                exit();
                            }
                            ?>
                            <script>
                                // Timer functionality
                                function startTimer(duration) {
                                    let timer = duration * 60;
                                    const display = document.getElementById('timer');
                                    
                                    const timerInterval = setInterval(function () {
                                        const minutes = Math.floor(timer / 60);
                                        const seconds = timer % 60;
                                        
                                        display.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                                        
                                        if (--timer < 0) {
                                            clearInterval(timerInterval);
                                            document.getElementById('examForm').submit();
                                        }
                                    }, 1000);
                                }
                                
                                window.onload = function () {
                                    const duration = <?php echo $exam['duration']; ?>;
                                    startTimer(duration);
                                };
                            </script>
                            
                            <div class="timer" id="timer">
                                <i class="fas fa-clock"></i>
                                <?php echo str_pad($exam['duration'], 2, '0', STR_PAD_LEFT); ?>:00
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h2 class="card-title"><i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($exam['title']); ?></h2>
                                </div>
                                <p><?php echo htmlspecialchars($exam['description']); ?></p>
                                <p class="exam-meta">Time Remaining: <span id="time-display"><?php echo $exam['duration']; ?>:00</span></p>
                                <p class="exam-schedule">
                                    <strong>Exam available until:</strong> 
                                    <?php echo date('M d, Y h:i A', strtotime($exam['end_time'])); ?>
                                </p>
                            </div>
                            
                            <form method="POST" id="examForm">
                                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                                
                                <?php foreach ($questions as $index => $question): ?>
                                    <div class="card question">
                                        <div class="question-text"><?php echo ($index+1).'. '.htmlspecialchars($question['question_text']); ?></div>
                                        <div class="options">
                                            <label>
                                                <input type="radio" name="answer_<?php echo $question['id']; ?>" value="1" required>
                                                <?php echo htmlspecialchars($question['option1']); ?>
                                            </label>
                                            <label>
                                                <input type="radio" name="answer_<?php echo $question['id']; ?>" value="2">
                                                <?php echo htmlspecialchars($question['option2']); ?>
                                            </label>
                                            <label>
                                                <input type="radio" name="answer_<?php echo $question['id']; ?>" value="3">
                                                <?php echo htmlspecialchars($question['option3']); ?>
                                            </label>
                                            <label>
                                                <input type="radio" name="answer_<?php echo $question['id']; ?>" value="4">
                                                <?php echo htmlspecialchars($question['option4']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="card">
                                    <button type="submit" name="submit_exam" class="btn"><i class="fas fa-paper-plane"></i> Submit Exam</button>
                                </div>
                            </form>
                            <?php
                        }
                        break;
                        
                    case 'exam_result':
                        // Exam Result
                        if (isset($_SESSION['exam_result'])) {
                            $result = $_SESSION['exam_result'];
                            unset($_SESSION['exam_result']);
                            $exam = getExam($conn, $result['exam_id']);
                            ?>
                            <div class="result-card">
                                <div class="score-circle">
                                    <div class="score-value"><?php echo $result['score']; ?>/<?php echo $result['total']; ?></div>
                                    <div><?php echo number_format($result['percentage'], 2); ?>%</div>
                                </div>
                                
                                <h2 style="font-size: 28px; margin-bottom: 15px;"><?php 
                                    if ($result['percentage'] >= 80) {
                                        echo "Excellent Performance!";
                                    } elseif ($result['percentage'] >= 60) {
                                        echo "Good Job!";
                                    } elseif ($result['percentage'] >= 40) {
                                        echo "Passed!";
                                    } else {
                                        echo "Needs Improvement";
                                    }
                                ?></h2>
                                <p style="font-size: 18px; margin-bottom: 30px;">You scored <?php echo $result['score']; ?> out of <?php echo $result['total']; ?> questions in the <strong><?php echo htmlspecialchars($exam['title']); ?></strong> exam.</p>
                                
                                <a href="?action=home" class="btn"><i class="fas fa-home"></i> Back to Dashboard</a>
                            </div>
                            <?php
                        } else {
                            header("Location: " . $_SERVER['PHP_SELF'] . "?action=home");
                            exit();
                        }
                        break;
                        
                    default:
                        // Student Dashboard Content
                        $exams = getAvailableExams($conn, $_SESSION['user_id']);
                        $upcoming_exams = getUpcomingExams($conn, $_SESSION['user_id']);
                        ?>
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-book-open"></i> Available Exams</h2>
                                <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! You can take each exam only once during its scheduled time.</p>
                            </div>
                            
                            <?php if (count($exams) > 0): ?>
                                <div class="grid">
                                    <?php foreach ($exams as $exam): ?>
                                        <div class="exam-card">
                                            <div class="exam-header">
                                                <div class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></div>
                                                <span class="exam-status status-active">Active</span>
                                            </div>
                                            <div class="exam-body">
                                                <p><?php echo htmlspecialchars($exam['description']); ?></p>
                                                <div class="exam-schedule">
                                                    <strong>Available until:</strong> 
                                                    <?php echo date('M d, Y h:i A', strtotime($exam['end_time'])); ?>
                                                </div>
                                                <div class="exam-meta">
                                                    <a href="?action=take_exam&exam_id=<?php echo $exam['id']; ?>" class="btn">Take Exam</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="message success">
                                    <i class="fas fa-check-circle"></i> No exams currently available. Check back later!
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (count($upcoming_exams) > 0): ?>
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-calendar"></i> Upcoming Exams</h2>
                                <p>These exams will become available at the scheduled start time.</p>
                            </div>
                            <div class="grid">
                                <?php foreach ($upcoming_exams as $exam): ?>
                                    <div class="exam-card">
                                        <div class="exam-header">
                                            <div class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></div>
                                            <span class="exam-status status-upcoming">Upcoming</span>
                                        </div>
                                        <div class="exam-body">
                                            <p><?php echo htmlspecialchars($exam['description']); ?></p>
                                            <div class="exam-schedule">
                                                <strong>Starts at:</strong> 
                                                <?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?>
                                            </div>
                                            <div class="exam-meta">
                                                <button class="btn" disabled>Not available yet</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php
                        break;
                }
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; <?php echo date('Y'); ?> University Exam Portal. All rights reserved.</p>
    </footer>
    
    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin' && $action === 'manage_users'): ?>
        <a href="#" class="floating-btn" id="floatingAddBtn">
            <i class="fas fa-plus"></i>
        </a>
        <script>
            // Floating button to add user
            document.getElementById('floatingAddBtn').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('addUserBtn').click();
            });
        </script>
    <?php endif; ?>
</body>
</html>
