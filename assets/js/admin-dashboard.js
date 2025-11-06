// Admin Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeTheme();
    initializeDropdowns();
    initializeSidebar();
    initializeAnimations();
});

// Theme Management
function initializeTheme() {
    const savedTheme = localStorage.getItem('adminTheme') || 'light';
    document.body.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);
}

function toggleTheme() {
    const currentTheme = document.body.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    document.body.setAttribute('data-theme', newTheme);
    localStorage.setItem('adminTheme', newTheme);
    updateThemeIcon(newTheme);
    
    // Add transition effect
    document.body.style.transition = 'all 0.3s ease';
    setTimeout(() => {
        document.body.style.transition = '';
    }, 300);
}

function updateThemeIcon(theme) {
    const themeIcon = document.getElementById('theme-icon');
    if (themeIcon) {
        themeIcon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
    }
}

// Dropdown Management
function initializeDropdowns() {
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(dropdown => {
            // Check if the click is on the profile button or within the dropdown
            const dropdownContainer = dropdown.parentElement; // This is the .dropdown div
            const profileBtn = dropdownContainer.querySelector('.profile-btn');
            
            // If click is on the profile button, let the button's onclick handler handle it
            if (profileBtn && profileBtn.contains(event.target)) {
                return;
            }
            
            // If click is on the dropdown itself or its children, don't close it
            if (dropdown.contains(event.target)) {
                return;
            }
            
            // If click is outside both the button and dropdown, close the dropdown
            dropdown.classList.remove('show');
        });
    });
}

function toggleDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    dropdown.classList.toggle('show');
}

// Sidebar Management
function initializeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    // Check if mobile view
    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        } else {
            sidebar.classList.remove('collapsed', 'show');
            mainContent.classList.remove('expanded');
        }
    });
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (window.innerWidth <= 768) {
        // Mobile behavior
        sidebar.classList.toggle('show');
    } else {
        // Desktop behavior
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
    }
}

// Animation Management
function initializeAnimations() {
    // Add fade-in animation to main content
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.classList.add('fade-in');
    }
    
    // Add staggered animation to cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('slide-in-left');
    });
    
    const actionCards = document.querySelectorAll('.action-card');
    actionCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('slide-in-right');
    });
}

// Utility Functions
function showNotification(message, type = 'success') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Search functionality
function initializeSearch() {
    const searchInput = document.getElementById('globalSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            // Implement search logic here
            console.log('Searching for:', query);
        });
    }
}

// Real-time updates
function startRealTimeUpdates() {
    // Check for updates every 30 seconds
    setInterval(updateDashboardStats, 30000);
}

function updateDashboardStats() {
    // Fetch updated statistics
    fetch('api/dashboard-stats.php')
        .then(response => response.json())
        .then(data => {
            updateStatCards(data);
        })
        .catch(error => {
            console.error('Error updating stats:', error);
        });
}

function updateStatCards(stats) {
    // Update stat cards with new data
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        const statType = card.dataset.stat;
        if (stats[statType]) {
            const statValue = card.querySelector('.stat-info h3');
            if (statValue) {
                // Animate number change
                animateNumber(statValue, stats[statType]);
            }
        }
    });
}

function animateNumber(element, newValue) {
    const currentValue = parseInt(element.textContent.replace(/,/g, ''));
    const increment = (newValue - currentValue) / 20;
    let current = currentValue;
    
    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= newValue) || (increment < 0 && current <= newValue)) {
            current = newValue;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current).toLocaleString();
    }, 50);
}

// Export functions for global access
window.toggleTheme = toggleTheme;
window.toggleDropdown = toggleDropdown;
window.toggleSidebar = toggleSidebar;
window.showNotification = showNotification;
window.confirmAction = confirmAction;