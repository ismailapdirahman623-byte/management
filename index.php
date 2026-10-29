<?php
session_start();

$pdo = new PDO(
    "mysql:host=localhost;dbname=student_management;charset=utf8mb4",
    "root",
    "",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]
);

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$error = '';
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(
        $_SESSION['csrf'],
        $_POST['csrf'] ?? ''
    )) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'delete') {
            $stmt = $pdo->prepare(
                "DELETE FROM students WHERE id = ?"
            );
            $stmt->execute([(int)$_POST['id']]);
        } elseif ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $studentId = trim($_POST['student_id'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $course = trim($_POST['course'] ?? '');

            if ($studentId === '' || $name === '') {
                throw new Exception(
                    'Student ID and name are required.'
                );
            }

            if (strlen($studentId) > 30 ||
                strlen($name) > 100 ||
                strlen($email) > 150 ||
                strlen($phone) > 30 ||
                strlen($course) > 100) {
                throw new Exception(
                    'One or more fields are too long.'
                );
            }

            if ($email !== '' &&
                !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception(
                    'Please enter a valid email address.'
                );
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    "UPDATE students
                     SET student_id=?, name=?, email=?,
                         phone=?, course=?
                     WHERE id=?"
                );
                $stmt->execute([
                    $studentId, $name, $email,
                    $phone, $course, $id
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO students
                     (student_id, name, email, phone, course)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $studentId, $name, $email,
                    $phone, $course
                ]);
            }
        }

        header('Location: index.php');
        exit;
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            $error = 'That student ID already exists.';
        } else {
            error_log($ex->getMessage());
            $error = 'A database error occurred.';
        }
    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare(
        "SELECT * FROM students WHERE id = ?"
    );
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();

    if (!$edit) {
        $error = 'Student record not found.';
    }
}

$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    $stmt = $pdo->prepare(
        "SELECT * FROM students
         WHERE student_id LIKE ?
            OR name LIKE ?
            OR course LIKE ?
         ORDER BY id DESC"
    );
    $term = "%$q%";
    $stmt->execute([$term, $term, $term]);
    $students = $stmt->fetchAll();
} else {
    $students = $pdo->query(
        "SELECT * FROM students ORDER BY id DESC"
    )->fetchAll();
}

$form = $edit ?: [
    'id' => '',
    'student_id' => '',
    'name' => '',
    'email' => '',
    'phone' => '',
    'course' => ''
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1">
<title>Student Management System</title>
<style>
* { box-sizing: border-box; }
body {
    font-family: Arial, sans-serif;
    margin: 0;
    background: #f3f6fb;
    color: #243047;
}
header {
    background: #173b72;
    color: white;
    padding: 22px;
    text-align: center;
}
main {
    max-width: 1100px;
    margin: 25px auto;
    padding: 0 16px;
}
.panel {
    background: white;
    padding: 22px;
    margin-bottom: 22px;
    border-radius: 10px;
    box-shadow: 0 2px 9px #0000000d;
}
.fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}
input {
    width: 100%;
    padding: 11px;
    margin-top: 6px;
    border: 1px solid #ccd3df;
    border-radius: 5px;
}
button, .btn {
    border: 0;
    border-radius: 5px;
    padding: 10px 14px;
    background: #245bb5;
    color: white;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.danger { background: #c0392b; }
.cancel { background: #687386; }
.table-wrap { overflow-x: auto; }
table {
    border-collapse: collapse;
    width: 100%;
    min-width: 700px;
}
th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e2e7ef;
}
th { background: #edf2fa; }
.actions {
    display: flex;
    gap: 6px;
    align-items: center;
}
.actions form { margin: 0; }
.message {
    background: #fff0e8;
    padding: 12px;
    border-radius: 5px;
}
@media(max-width: 600px) {
    .fields { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<header>
    <h1>Student Management System</h1>
    <p>Register, search and manage student records</p>
</header>

<main>

<?php if ($error): ?>
    <p class="message"><?= e($error) ?></p>
<?php endif; ?>

<section class="panel">
<h2><?= $form['id'] ? 'Edit Student' : 'Register Student' ?></h2>

<form method="post">
    <input type="hidden" name="csrf"
           value="<?= e($_SESSION['csrf']) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id"
           value="<?= e($form['id']) ?>">

    <div class="fields">
        <label>Student ID *
            <input name="student_id" maxlength="30"
                   required value="<?= e($form['student_id']) ?>">
        </label>

        <label>Full Name *
            <input name="name" maxlength="100"
                   required value="<?= e($form['name']) ?>">
        </label>

        <label>Email
            <input type="email" name="email" maxlength="150"
                   value="<?= e($form['email']) ?>">
        </label>

        <label>Phone
            <input name="phone" maxlength="30"
                   value="<?= e($form['phone']) ?>">
        </label>

        <label>Course
            <input name="course" maxlength="100"
                   value="<?= e($form['course']) ?>">
        </label>
    </div>

    <p>
        <button type="submit">
            <?= $form['id'] ? 'Update Student' : 'Add Student' ?>
        </button>

        <?php if ($form['id']): ?>
            <a class="btn cancel" href="index.php">Cancel</a>
        <?php endif; ?>
    </p>
</form>
</section>

<section class="panel">
<h2>Student Records (<?= count($students) ?>)</h2>

<form method="get">
    <label>Search by student ID, name or course
        <input name="q" value="<?= e($q) ?>"
               placeholder="Enter search term">
    </label>
    <p><button type="submit">Search</button>
       <a class="btn cancel" href="index.php">Show All</a></p>
</form>

<div class="table-wrap">
<table>
<thead>
<tr>
    <th>ID</th>
    <th>Student ID</th>
    <th>Name</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Course</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($students as $s): ?>
<tr>
    <td><?= e($s['id']) ?></td>
    <td><?= e($s['student_id']) ?></td>
    <td><?= e($s['name']) ?></td>
    <td><?= e($s['email']) ?></td>
    <td><?= e($s['phone']) ?></td>
    <td><?= e($s['course']) ?></td>
    <td>
        <div class="actions">
            <a class="btn"
               href="?edit=<?= (int)$s['id'] ?>">Edit</a>

            <form method="post"
                  onsubmit="return confirm('Delete this student?')">
                <input type="hidden" name="csrf"
                       value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="action"
                       value="delete">
                <input type="hidden" name="id"
                       value="<?= (int)$s['id'] ?>">
                <button class="danger" type="submit">
                    Delete
                </button>
            </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>

<?php if (!$students): ?>
<tr><td colspan="7">No students found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>
</main>
</body>
</html>