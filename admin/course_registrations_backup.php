session_start();
require_once '../config.php';
require_once '../auth.php';

// Initialize variables
$pending_registrations = [];
$intakes = [];
$programmes = [];
$courses = [];
$defined_courses = [];
$message = '';
$messageType = '';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin role or course_registrations permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('course_registrations', $pdo)) {
    header('Location: ../login.php');
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT u.*, ap.full_name, ap.staff_id FROM users u LEFT JOIN admin_profile ap ON u.id = ap.user_id WHERE u.id = ?");
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_registration':
                $registration_id = $_POST['registration_id'];
                
                try {
                    $stmt = $pdo->prepare("UPDATE course_registration SET status = 'approved' WHERE id = ?");
                    $stmt->execute([$registration_id]);
                    
                    $message = "Course registration approved successfully!";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'reject_registration':
                $registration_id = $_POST['registration_id'];
                $rejection_reason = trim($_POST['rejection_reason']);
                
                if (empty($rejection_reason)) {
                    $message = "Please provide a rejection reason!";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE course_registration SET status = 'rejected', rejection_reason = ? WHERE id = ?");
                        $stmt->execute([$rejection_reason, $registration_id]);
                        
                        $message = "Course registration rejected!";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'define_intake_courses':
                $intake_id = $_POST['intake_id'];
                $term = trim($_POST['term']);
                $programme_id = $_POST['programme_id'] ?? null; // New field (optional for backward compatibility)
                $course_ids = $_POST['course_ids'] ?? [];
                
                if (empty($intake_id) || empty($term)) {
                    $message = "Please select intake and term!";
                    $messageType = 'error';
                } else {
                    try {
                        // Check if programme_id column exists
                        $column_exists = false;
                        try {
                            $check_column = $pdo->query("SHOW COLUMNS FROM intake_courses LIKE 'programme_id'");
                            $column_exists = $check_column->rowCount() > 0;
                        } catch (Exception $e) {
                            $column_exists = false;
                        }
                        
                        if ($column_exists) {
                            // Delete existing courses for this intake/term (and programme if provided)
                            if (!empty($programme_id)) {
                                $delete_stmt = $pdo->prepare("DELETE FROM intake_courses WHERE intake_id = ? AND term = ? AND programme_id = ?");
                                $delete_stmt->execute([$intake_id, $term, $programme_id]);
                            } else {
                                $delete_stmt = $pdo->prepare("DELETE FROM intake_courses WHERE intake_id = ? AND term = ? AND (programme_id IS NULL OR programme_id = ?)");
                                $delete_stmt->execute([$intake_id, $term, 0]);
                            }
                            
                            // Add new courses
                            foreach ($course_ids as $course_id) {
                                $insert_stmt = $pdo->prepare("INSERT INTO intake_courses (intake_id, term, programme_id, course_id) VALUES (?, ?, ?, ?)");
                                $insert_stmt->execute([$intake_id, $term, $programme_id ?: null, $course_id]);
                            }
                        } else {
                            // Use old method without programme_id
                            $delete_stmt = $pdo->prepare("DELETE FROM intake_courses WHERE intake_id = ? AND term = ?");
                            $delete_stmt->execute([$intake_id, $term]);
                            
                            // Add new courses
                            foreach ($course_ids as $course_id) {
                                $insert_stmt = $pdo->prepare("INSERT INTO intake_courses (intake_id, term, course_id) VALUES (?, ?, ?)");
                                $insert_stmt->execute([$intake_id, $term, $course_id]);
                            }
                        }
                        
                        $message = "Courses defined for intake and term successfully!";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'delete_defined_course':
                $defined_course_id = $_POST['defined_course_id'];
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM intake_courses WHERE id = ?");
                    $stmt->execute([$defined_course_id]);
                    
                    $message = "Defined course deleted successfully!";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'update_defined_course':
                $defined_course_id = $_POST['defined_course_id'];
                $intake_id = $_POST['edit_intake_id'];
                $term = trim($_POST['edit_term']);
                $programme_id = $_POST['edit_programme_id'] ?? null;
                $course_id = $_POST['edit_course_id'];
                
                try {
                    // Check if programme_id column exists
                    $column_exists = false;
                    try {
                        $check_column = $pdo->query("SHOW COLUMNS FROM intake_courses LIKE 'programme_id'");
                        $column_exists = $check_column->rowCount() > 0;
                    } catch (Exception $e) {
                        $column_exists = false;
                    }
                    
                    if ($column_exists) {
                        $stmt = $pdo->prepare("UPDATE intake_courses SET intake_id = ?, term = ?, programme_id = ?, course_id = ? WHERE id = ?");
                        $stmt->execute([$intake_id, $term, $programme_id ?: null, $course_id, $defined_course_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE intake_courses SET intake_id = ?, term = ?, course_id = ? WHERE id = ?");
                        $stmt->execute([$intake_id, $term, $course_id, $defined_course_id]);
                    }
                    
                    $message = "Defined course updated successfully!";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get pending registrations
try {
    $pending_query = "
        SELECT cr.*, sp.full_name, sp.student_number as student_id, c.name as course_name, i.name as intake_name
        FROM course_registration cr
        JOIN student_profile sp ON cr.student_id = sp.user_id
        JOIN course c ON cr.course_id = c.id
        JOIN intake i ON sp.intake_id = i.id
        WHERE cr.status = 'pending'
        ORDER BY cr.submitted_at DESC
    ";
    $pending_registrations = $pdo->query($pending_query)->fetchAll();
} catch (Exception $e) {
    $pending_registrations = [];
}

// Get intakes for defining courses
try {
    $intakes = $pdo->query("SELECT * FROM intake ORDER BY start_date DESC")->fetchAll();
} catch (Exception $e) {
    $intakes = [];
}

// Get all programmes for the dropdown
try {
    $programmes = $pdo->query("SELECT * FROM programme ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $programmes = [];
}

// Get all courses for selection
try {
    $courses = $pdo->query("SELECT * FROM course ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $courses = [];
}

// Get defined intake courses (for display or edit) - handle both old and new schema
try {
    // Try to get data with programme information first
    $defined_courses_query = "
        SELECT ic.*, i.name as intake_name, c.name as course_name, c.code as course_code, p.name as programme_name
        FROM intake_courses ic
        JOIN intake i ON ic.intake_id = i.id
        JOIN course c ON ic.course_id = c.id
        LEFT JOIN programme p ON ic.programme_id = p.id
        ORDER BY i.name, ic.term, p.name, c.name
    ";
    $defined_courses = $pdo->query($defined_courses_query)->fetchAll();
} catch (Exception $e) {
    // Fall back to old query without programme information
    try {
        $defined_courses_query = "
            SELECT ic.*, i.name as intake_name, c.name as course_name, c.code as course_code, NULL as programme_name
            FROM intake_courses ic
            JOIN intake i ON ic.intake_id = i.id
            JOIN course c ON ic.course_id = c.id
            ORDER BY i.name, ic.term, c.name
        ";
        $defined_courses = $pdo->query($defined_courses_query)->fetchAll();
    } catch (Exception $e) {
        $defined_courses = [];
    }
}

// Create necessary tables if not exist and update schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS course_registration (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        term VARCHAR(50) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        rejection_reason TEXT,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (course_id) REFERENCES course(id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS intake_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        intake_id INT NOT NULL,
        term VARCHAR(50) NOT NULL,
        course_id INT NOT NULL,
        FOREIGN KEY (intake_id) REFERENCES intake(id),
        FOREIGN KEY (course_id) REFERENCES course(id)
    )");
    
    // Try to add programme_id column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE intake_courses ADD COLUMN programme_id INT");
        $pdo->exec("ALTER TABLE intake_courses ADD CONSTRAINT fk_intake_courses_programme FOREIGN KEY (programme_id) REFERENCES programme(id) ON DELETE SET NULL");
    } catch (Exception $e) {
        // Column might already exist, which is fine
    }
} catch (Exception $e) {
    // Tables might already exist
}
?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
            flex: 1;
        }
        
        .form-group.full-width {
            flex: 1 100%;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-orange {
            background-color: #fd7e14;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h1>Admin Dashboard</h1>
    <div class="flex-container">
        <div class="flex-item">
            <h2>Defined Courses</h2>
            <div class="card">
                <div class="card-header">
                    <h3>Defined Courses</h3>
                    <button class="btn btn-primary btn-sm" onclick="openModal('addModal')">Add Defined Course</button>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Intake</th>
                                <th>Term</th>
                                <th>Programme</th>
                                <th>Course</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($defined_courses as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['intake_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['term']); ?></td>
                                    <td><?php echo htmlspecialchars($course['programme_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['course_code']); ?>)</td>
                                    <td>
                                        <button class="btn btn-orange btn-sm" onclick="editDefinedCourse('<?php echo $course['id']; ?>', '<?php echo $course['intake_id']; ?>', '<?php echo $course['term']; ?>', '<?php echo $course['programme_id']; ?>', '<?php echo $course['course_id']; ?>')">Edit</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteDefinedCourse('<?php echo $course['id']; ?>')">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="flex-item">
            <h2>Intakes</h2>
            <div class="card">
                <div class="card-header">
                    <h3>Intakes</h3>
                    <button class="btn btn-primary btn-sm" onclick="openModal('addIntakeModal')">Add Intake</button>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($intakes as $intake): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($intake['name']); ?></td>
                                    <td><?php echo htmlspecialchars($intake['start_date']); ?></td>
                                    <td><?php echo htmlspecialchars($intake['end_date']); ?></td>
                                    <td>
                                        <button class="btn btn-orange btn-sm" onclick="editIntake('<?php echo $intake['id']; ?>', '<?php echo $intake['name']; ?>', '<?php echo $intake['start_date']; ?>', '<?php echo $intake['end_date']; ?>')">Edit</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteIntake('<?php echo $intake['id']; ?>')">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="flex-item">
            <h2>Programmes</h2>
            <div class="card">
                <div class="card-header">
                    <h3>Programmes</h3>
                    <button class="btn btn-primary btn-sm" onclick="openModal('addProgrammeModal')">Add Programme</button>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programmes as $programme): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($programme['name']); ?></td>
                                    <td><?php echo htmlspecialchars($programme['description']); ?></td>
                                    <td>
                                        <button class="btn btn-orange btn-sm" onclick="editProgramme('<?php echo $programme['id']; ?>', '<?php echo $programme['name']; ?>', '<?php echo $programme['description']; ?>')">Edit</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteProgramme('<?php echo $programme['id']; ?>')">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="flex-item">
            <h2>Courses</h2>
            <div class="card">
                <div class="card-header">
                    <h3>Courses</h3>
                    <button class="btn btn-primary btn-sm" onclick="openModal('addCourseModal')">Add Course</button>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['code']); ?></td>
                                    <td><?php echo htmlspecialchars($course['description']); ?></td>
                                    <td>
                                        <button class="btn btn-orange btn-sm" onclick="editCourse('<?php echo $course['id']; ?>', '<?php echo $course['name']; ?>', '<?php echo $course['code']; ?>', '<?php echo $course['description']; ?>')">Edit</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteCourse('<?php echo $course['id']; ?>')">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Defined Course Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2>Add Defined Course</h2>
            <form method="POST" id="addForm">
                <input type="hidden" name="action" value="add_defined_course">
                <div class="form-row">
                    <div class="form-group">
                        <label for="intake_id">Intake *</label>
                        <select id="intake_id" name="intake_id" required>
                            <option value="">-- Select Intake --</option>
                            <?php foreach ($intakes as $intake): ?>
                                <option value="<?php echo $intake['id']; ?>"><?php echo htmlspecialchars($intake['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="term">Term *</label>
                        <input type="text" id="term" name="term" required placeholder="e.g., Semester 1">
                    </div>
                </div>
                <div class="form-group">
                    <label for="programme_id">Programme</label>
                    <select id="programme_id" name="programme_id">
                        <option value="">-- Select Programme --</option>
                        <?php foreach ($programmes as $programme): ?>
                            <option value="<?php echo $programme['id']; ?>"><?php echo htmlspecialchars($programme['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="course_id">Course *</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">-- Select Course --</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name'] . ' (' . $course['code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Add Course</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2>Edit Defined Course</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_defined_course">
                <input type="hidden" id="edit_defined_course_id" name="defined_course_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_intake_id">Intake *</label>
                        <select id="edit_intake_id" name="edit_intake_id" required>
                            <option value="">-- Select Intake --</option>
                            <?php if (!empty($intakes)): ?>
                                <?php foreach ($intakes as $intake): ?>
                                    <option value="<?php echo $intake['id']; ?>"><?php echo htmlspecialchars($intake['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_term">Term *</label>
                        <input type="text" id="edit_term" name="edit_term" required placeholder="e.g., Semester 1">
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_programme_id">Programme</label>
                    <select id="edit_programme_id" name="edit_programme_id">
                        <option value="">-- Select Programme --</option>
                        <?php if (!empty($programmes)): ?>
                            <?php foreach ($programmes as $programme): ?>
                                <option value="<?php echo $programme['id']; ?>"><?php echo htmlspecialchars($programme['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_course_id">Course *</label>
                    <select id="edit_course_id" name="edit_course_id" required>
                        <option value="">-- Select Course --</option>
                        <?php if (!empty($courses)): ?>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name'] . ' (' . $course['code'] . ')'); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Update Course</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Add Intake Modal -->
    <div id="addIntakeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addIntakeModal')">&times;</span>
            <h2>Add Intake</h2>
            <form method="POST" id="addIntakeForm">
                <input type="hidden" name="action" value="add_intake">
                <div class="form-row">
                    <div class="form-group">
                        <label for="intake_name">Name *</label>
                        <input type="text" id="intake_name" name="intake_name" required>
                    </div>
                    <div class="form-group">
                        <label for="intake_start_date">Start Date *</label>
                        <input type="date" id="intake_start_date" name="intake_start_date" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="intake_end_date">End Date *</label>
                        <input type="date" id="intake_end_date" name="intake_end_date" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Add Intake</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addIntakeModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Add Programme Modal -->
    <div id="addProgrammeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addProgrammeModal')">&times;</span>
            <h2>Add Programme</h2>
            <form method="POST" id="addProgrammeForm">
                <input type="hidden" name="action" value="add_programme">
                <div class="form-group">
                    <label for="programme_name">Name *</label>
                    <input type="text" id="programme_name" name="programme_name" required>
                </div>
                <div class="form-group">
                    <label for="programme_description">Description</label>
                    <textarea id="programme_description" name="programme_description"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Add Programme</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addProgrammeModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div id="addCourseModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addCourseModal')">&times;</span>
            <h2>Add Course</h2>
            <form method="POST" id="addCourseForm">
                <input type="hidden" name="action" value="add_course">
                <div class="form-group">
                    <label for="course_name">Name *</label>
                    <input type="text" id="course_name" name="course_name" required>
                </div>
                <div class="form-group">
                    <label for="course_code">Code *</label>
                    <input type="text" id="course_code" name="course_code" required>
                </div>
                <div class="form-group">
                    <label for="course_description">Description</label>
                    <textarea id="course_description" name="course_description"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Add Course</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCourseModal')">Cancel</button>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }
        
        function deleteIntake(id) {
            document.getElementById('delete_intake_id').value = id;
            document.getElementById('deleteIntakeModal').style.display = 'block';
        }
        
        function editIntake(id, name, startDate, endDate) {
            document.getElementById('edit_intake_id').value = id;
            document.getElementById('edit_intake_name').value = name;
            document.getElementById('edit_intake_start_date').value = startDate;
            document.getElementById('edit_intake_end_date').value = endDate;
            document.getElementById('editIntakeModal').style.display = 'block';
        }
        
        function deleteProgramme(id) {
            document.getElementById('delete_programme_id').value = id;
            document.getElementById('deleteProgrammeModal').style.display = 'block';
        }
        
        function editProgramme(id, name, description) {
            document.getElementById('edit_programme_id').value = id;
            document.getElementById('edit_programme_name').value = name;
            document.getElementById('edit_programme_description').value = description;
            document.getElementById('editProgrammeModal').style.display = 'block';
        }
        
        function deleteCourse(id) {
            document.getElementById('delete_course_id').value = id;
            document.getElementById('deleteCourseModal').style.display = 'block';
        }
        
        function editCourse(id, name, code, description) {
            document.getElementById('edit_course_id').value = id;
            document.getElementById('edit_course_name').value = name;
            document.getElementById('edit_course_code').value = code;
            document.getElementById('edit_course_description').value = description;
            document.getElementById('editCourseModal').style.display = 'block';
        }
        
    </script>
</body>
</html>