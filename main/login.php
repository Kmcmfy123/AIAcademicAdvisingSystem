<?php
require_once __DIR__ . '/../includes/init.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            // Redirect based on role
            switch ($result['role']) {
                case 'student':
                    redirect(APP_URL . '/main/student/dashboard.php');
                    break;
                case 'professor':
                    redirect(APP_URL . '/main/professor/dashboard_prof.php');
                    break;
                case 'admin':
                    redirect(APP_URL . '/main/admin/dashboard.php');
                    break;
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login in <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
    <script>
    document.addEventListener("click", function (e) {

    // EDIT
    if (e.target.classList.contains("edit-btn")) {
        const id = e.target.dataset.id;
        console.log("Editing:", id);
        window.location.href = "editGradeComponent.php?id=" + id;
    }

    // DELETE
    if (e.target.classList.contains("delete-btn")) {
        const id = e.target.dataset.id;

        if (!confirm("Delete this record?")) return;

        fetch("deleteGradeComponent.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) location.reload();
            else alert(res.message || "Delete failed");
        })
        .catch(err => {
            console.error(err);
            alert("Delete error");
        });
    }

});

    function addComponent(period, courseId) {
        console.log("Redirecting with:", period, courseId); // Debugging line
        window.location.href = `addGradeComponent.php?course_id=${courseId}&period=${period}`;
    }

    function editComponent(componentId) {
        if (!componentId) return;
        window.location.href = `${BASE_URL}/editGradeComponent.php?id=${componentId}`;
    }

    function deleteComponent(componentId, courseId) {
        if (!componentId) return;
        if (!confirm('Are you sure you want to delete this record?')) return;

        fetch(`${BASE_URL}/deleteGradeComponent.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: componentId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) window.location.reload();
            else alert(data.message || 'Failed to delete');
        })
        .catch(console.error);
    }

    function uploadSyllabus(courseId) {
        window.location.href = `uploadSyllabus.php?course_id=${courseId}`;
    }

    function replaceSyllabus(courseId) {
        if (confirm('Replace the existing syllabus? The old syllabus will be archived.')) {
            window.location.href = `uploadSyllabus.php?course_id=${courseId}&replace=1`;
        }
    }
</script>
</head>
<body>
    <div class="container" style="max-width: 450px; margin-top: 100px;">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Login</h1>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?= CSRF::tokenField() ?>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </form>
            
            <p style="text-align: center; margin-top: 1rem;">
                <a href="register.php">Register here</a>
            </p>
        </div>
    </div>
</body>
</html>