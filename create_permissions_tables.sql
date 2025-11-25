-- Create user_permissions table
CREATE TABLE IF NOT EXISTS user_permissions (
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Insert default permissions if they don't exist
INSERT IGNORE INTO permissions (id, name, description, created_at) VALUES
(1, 'manage_users', 'Manage users', NOW()),
(2, 'manage_roles', 'Manage roles', NOW()),
(3, 'manage_courses', 'Manage courses', NOW()),
(4, 'manage_programmes', 'Manage programmes', NOW()),
(5, 'manage_intakes', 'Manage intakes', NOW()),
(6, 'course_registrations', 'Manage course registrations', NOW()),
(7, 'enrollment_approvals', 'Approve enrollments', NOW()),
(8, 'view_reports', 'View reports', NOW()),
(9, 'manage_finance', 'Manage finance', NOW()),
(10, 'manage_settings', 'Manage system settings', NOW());

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_user_permissions_user_id ON user_permissions(user_id);
CREATE INDEX IF NOT EXISTS idx_user_permissions_permission_id ON user_permissions(permission_id);