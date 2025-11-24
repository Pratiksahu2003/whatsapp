import './bootstrap';

// Dashboard functionality
document.addEventListener('DOMContentLoaded', function() {
    // Update current time
    function updateTime() {
        const now = new Date();
        const timeElement = document.getElementById('current-time');
        if (timeElement) {
            timeElement.textContent = now.toLocaleString();
        }
    }
    
    updateTime();
    setInterval(updateTime, 1000);
});
