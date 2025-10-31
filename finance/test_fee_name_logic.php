<!DOCTYPE html>
<html>
<head>
    <title>Test Fee Name Logic</title>
</head>
<body>
    <h1>Test Fee Name Logic</h1>
    
    <form method="POST" action="">
        <label for="fee_name">Fee Name:</label>
        <select name="fee_name" id="fee_name" required>
            <option value="">Select or Type Fee Name</option>
            <option value="Application Fee">Application Fee</option>
            <option value="Tuition Fee">Tuition Fee</option>
            <option value="other">-- Other --</option>
        </select>
        <br><br>
        
        <input type="text" name="fee_name_custom" id="fee_name_custom" placeholder="Enter custom fee name" style="display: none;">
        <br><br>
        
        <input type="submit" value="Submit">
    </form>
    
    <script>
        document.getElementById('fee_name').addEventListener('change', function() {
            const customInput = document.getElementById('fee_name_custom');
            if (this.value === 'other') {
                customInput.style.display = 'block';
                customInput.required = true;
                customInput.value = '';
            } else {
                customInput.style.display = 'none';
                customInput.required = false;
            }
        });
    </script>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<h2>Submission Results:</h2>";
        echo "<p>Selected fee name: " . htmlspecialchars($_POST['fee_name']) . "</p>";
        echo "<p>Custom fee name: " . htmlspecialchars($_POST['fee_name_custom'] ?? 'N/A') . "</p>";
        
        $fee_name = !empty($_POST['fee_name_custom']) ? $_POST['fee_name_custom'] : $_POST['fee_name'];
        echo "<p>Final fee name to use: " . htmlspecialchars($fee_name) . "</p>";
    }
    ?>
</body>
</html>