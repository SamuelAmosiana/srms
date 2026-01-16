<!DOCTYPE html>
<html>
<head>
    <title>Test Phone Display</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .modal {
            display: block !important;
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
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 5px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-body p {
            margin: 10px 0;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        #documents_list {
            list-style-type: none;
            padding: 0;
        }
        
        #documents_list li {
            margin: 5px 0;
        }
        
        .test-button {
            margin: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <button class="test-button" onclick="testWithPhone()">Test With Phone Number</button>
    <button class="test-button" onclick="testWithoutPhone()">Test Without Phone Number</button>
    
    <!-- View Application Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> Application Details</h3>
                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>Name:</strong> <span id="view_name"></span></p>
                <p><strong>Email:</strong> <span id="view_email"></span></p>
                <p><strong>Programme:</strong> <span id="view_programme"></span></p>
                <p><strong>Intake:</strong> <span id="view_intake"></span></p>
                <p><strong>Submitted:</strong> <span id="view_submitted"></span></p>
                <div id="documents_section">
                    <strong>Documents:</strong>
                    <ul id="documents_list"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
            </div>
        </div>
    </div>

    <script>
        function viewApplication(app) {
            // Set application details in the modal
            document.getElementById('view_name').textContent = app.full_name || 'N/A';
            document.getElementById('view_email').textContent = app.email || 'N/A';
            document.getElementById('view_programme').textContent = app.programme_name || 'N/A';
            document.getElementById('view_intake').textContent = app.intake_name || 'N/A';
            document.getElementById('view_submitted').textContent = app.created_at ? new Date(app.created_at).toLocaleDateString() : 'N/A';
            
            // Handle documents
            const docsList = document.getElementById('documents_list');
            docsList.innerHTML = '';
            
            try {
                // Parse documents JSON
                const documents = app.documents ? JSON.parse(app.documents) : {};
                
                if (Array.isArray(documents)) {
                    if (documents.length > 0) {
                        documents.forEach(doc => {
                            const li = document.createElement('li');
                            if (typeof doc === 'string') {
                                li.textContent = doc;
                            } else if (doc.path && doc.name) {
                                const downloadUrl = `/srms/enrollment/download_document.php?file=${encodeURIComponent(doc.path.split('/').pop())}&original_name=${encodeURIComponent(doc.name)}`;
                                console.log("FINAL DOWNLOAD URL:", downloadUrl);
                                li.innerHTML = `<a onclick="window.location.href='${downloadUrl}'; return false;" style="cursor:pointer; color:blue; text-decoration:underline;">${doc.name}</a>`;
                            } else {
                                li.textContent = JSON.stringify(doc);
                            }
                            docsList.appendChild(li);
                        });
                    } else {
                        docsList.innerHTML = '<li>No documents attached</li>';
                    }
                } else if (typeof documents === 'object' && documents !== null) {
                    // Handle object format
                    let hasDocuments = false;
                    for (const key in documents) {
                        if (typeof documents[key] === 'object' && documents[key].path) {
                            const li = document.createElement('li');
                            const downloadUrl = `/srms/enrollment/download_document.php?file=${encodeURIComponent(documents[key].path.split('/').pop())}&original_name=${encodeURIComponent(documents[key].name)}`;
                            console.log("FINAL DOWNLOAD URL:", downloadUrl);
                            li.innerHTML = `<a onclick="window.location.href='${downloadUrl}'; return false;" style="cursor:pointer; color:blue; text-decoration:underline;">${documents[key].name}</a>`;
                            docsList.appendChild(li);
                            hasDocuments = true;
                        } else if (key !== 'path' && key !== 'name' && documents[key]) {
                            const li = document.createElement('li');
                            // Format the label nicely
                            let label = key.replace('_', ' ');
                            // Capitalize first letter
                            label = label.charAt(0).toUpperCase() + label.slice(1);
                            li.innerHTML = `<strong>${label}:</strong> ${documents[key]}`;
                            docsList.appendChild(li);
                            hasDocuments = true;
                        }
                    }
                    if (!hasDocuments) {
                        docsList.innerHTML = '<li>No documents attached</li>';
                    }
                } else {
                    docsList.innerHTML = '<li>No documents attached</li>';
                }
            } catch (e) {
                // Handle case where documents is not valid JSON
                if (app.documents) {
                    const li = document.createElement('li');
                    li.textContent = app.documents;
                    docsList.appendChild(li);
                } else {
                    docsList.innerHTML = '<li>No documents attached</li>';
                }
            }
            
            document.getElementById('viewModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function testWithPhone() {
            const testApp = {
                full_name: 'Test User',
                email: 'test@example.com',
                programme_name: 'Computer Science',
                intake_name: 'January 2024',
                created_at: '2023-01-01 10:00:00',
                documents: JSON.stringify({
                    'phone': '123-456-7890',
                    'occupation': 'Developer',
                    'schedule': 'weekdays',
                    'experience': '5 years',
                    'goals': 'Career advancement'
                })
            };
            
            viewApplication(testApp);
        }
        
        function testWithoutPhone() {
            const testApp = {
                full_name: 'Test User 2',
                email: 'test2@example.com',
                programme_name: 'Business Administration',
                intake_name: 'April 2024',
                created_at: '2023-02-01 10:00:00',
                documents: JSON.stringify({
                    'occupation': 'Manager',
                    'schedule': 'weekends',
                    'experience': '10 years',
                    'goals': 'Leadership skills'
                })
            };
            
            viewApplication(testApp);
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>