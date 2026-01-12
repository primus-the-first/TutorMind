<?php
require_once 'check_auth.php';
require_once 'db_mysql.php';

$displayName = isset($_SESSION['first_name']) && !empty($_SESSION['first_name']) ? $_SESSION['first_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Dashboard - TutorMind</title>
    <link rel="stylesheet" href="ui-overhaul.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --dashboard-bg: #f8f9fc;
            --card-bg: #ffffff;
            --card-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .dark-mode {
            --dashboard-bg: #0f0f1a;
            --card-bg: #1a1a2e;
            --card-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        
        .dashboard-container {
            min-height: 100vh;
            background: var(--dashboard-bg);
            padding: 2rem;
            transition: background 0.3s ease;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .dashboard-header h1 {
            font-size: 1.75rem;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .dashboard-header h1 i {
            color: var(--primary);
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .period-select {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--text);
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .back-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(123, 63, 242, 0.3);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }
        
        .stat-card.sessions .stat-icon { background: rgba(99, 102, 241, 0.15); color: #6366f1; }
        .stat-card.topics .stat-icon { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .stat-card.progress .stat-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .stat-card.streak .stat-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .chart-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
        }
        
        .chart-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .chart-container {
            position: relative;
            height: 250px;
        }
        
        /* Recent Sessions */
        .recent-sessions {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
        }
        
        .recent-sessions h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .session-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .session-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--dashboard-bg);
            border-radius: 12px;
            transition: background 0.2s ease;
        }
        
        .session-item:hover {
            background: rgba(123, 63, 242, 0.05);
        }
        
        .session-info {
            flex: 1;
        }
        
        .session-title {
            font-weight: 500;
            color: var(--text);
            margin-bottom: 0.25rem;
        }
        
        .session-meta {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .session-progress {
            width: 100px;
        }
        
        .progress-bar {
            height: 8px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #a855f7);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .progress-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-align: right;
            margin-top: 0.25rem;
        }
        
        /* Loading State */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4rem;
            color: var(--text-secondary);
        }
        
        .loading i {
            font-size: 2rem;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1><i class="fas fa-chart-line"></i> Learning Dashboard</h1>
            <div class="header-actions">
                <select class="period-select" id="periodSelect">
                    <option value="7days">Last 7 Days</option>
                    <option value="30days" selected>Last 30 Days</option>
                    <option value="90days">Last 90 Days</option>
                    <option value="all">All Time</option>
                </select>
                <a href="tutor_mysql.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Chat
                </a>
            </div>
        </div>
        
        <div id="dashboard-content">
            <div class="loading">
                <i class="fas fa-spinner"></i>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Apply dark mode from localStorage
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
        }
        
        const periodSelect = document.getElementById('periodSelect');
        let progressChart = null;
        let topicsChart = null;
        
        async function loadDashboard() {
            const period = periodSelect.value;
            const container = document.getElementById('dashboard-content');
            
            container.innerHTML = '<div class="loading"><i class="fas fa-spinner"></i></div>';
            
            try {
                const response = await fetch(`api/analytics.php?period=${period}`);
                const data = await response.json();
                
                if (!data.success) throw new Error(data.error);
                
                renderDashboard(data);
            } catch (error) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Failed to load dashboard data</p>
                        <p style="font-size: 0.8rem">${error.message}</p>
                    </div>
                `;
            }
        }
        
        function renderDashboard(data) {
            const container = document.getElementById('dashboard-content');
            const { stats, topTopics, progressOverTime, goalDistribution, recentSessions } = data;
            
            container.innerHTML = `
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card sessions">
                        <div class="stat-icon"><i class="fas fa-comments"></i></div>
                        <div class="stat-value">${stats.totalSessions}</div>
                        <div class="stat-label">Study Sessions</div>
                    </div>
                    <div class="stat-card topics">
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                        <div class="stat-value">${stats.topicsStudied}</div>
                        <div class="stat-label">Topics Explored</div>
                    </div>
                    <div class="stat-card progress">
                        <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                        <div class="stat-value">${stats.avgProgress}%</div>
                        <div class="stat-label">Avg Progress</div>
                    </div>
                    <div class="stat-card streak">
                        <div class="stat-icon"><i class="fas fa-fire"></i></div>
                        <div class="stat-value">${stats.currentStreak} 🔥</div>
                        <div class="stat-label">Day Streak</div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <h3><i class="fas fa-chart-area"></i> Progress Over Time</h3>
                        <div class="chart-container">
                            <canvas id="progressChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <h3><i class="fas fa-pie-chart"></i> Topics Breakdown</h3>
                        <div class="chart-container">
                            <canvas id="topicsChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Sessions -->
                <div class="recent-sessions">
                    <h3><i class="fas fa-history"></i> Recent Sessions</h3>
                    <div class="session-list" id="sessionList"></div>
                </div>
            `;
            
            // Render charts
            renderProgressChart(progressOverTime);
            renderTopicsChart(topTopics);
            renderRecentSessions(recentSessions);
        }
        
        function renderProgressChart(data) {
            const ctx = document.getElementById('progressChart').getContext('2d');
            const isDark = document.body.classList.contains('dark-mode');
            
            if (progressChart) progressChart.destroy();
            
            progressChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                    datasets: [{
                        label: 'Avg Progress',
                        data: data.map(d => Math.round(d.avg_progress)),
                        borderColor: '#7b3ff2',
                        backgroundColor: 'rgba(123, 63, 242, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)' },
                            ticks: { color: isDark ? '#888' : '#666' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: isDark ? '#888' : '#666' }
                        }
                    }
                }
            });
        }
        
        function renderTopicsChart(topics) {
            const ctx = document.getElementById('topicsChart').getContext('2d');
            const isDark = document.body.classList.contains('dark-mode');
            
            if (topicsChart) topicsChart.destroy();
            
            const labels = Object.keys(topics);
            const values = Object.values(topics);
            const colors = ['#7b3ff2', '#a855f7', '#10b981', '#6366f1', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899'];
            
            topicsChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors.slice(0, labels.length),
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { color: isDark ? '#ccc' : '#333', font: { size: 11 } }
                        }
                    }
                }
            });
        }
        
        function renderRecentSessions(sessions) {
            const container = document.getElementById('sessionList');
            
            if (!sessions || sessions.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No recent sessions</p></div>';
                return;
            }
            
            container.innerHTML = sessions.map(session => {
                const timeAgo = getTimeAgo(new Date(session.date));
                const goalLabel = session.goal ? session.goal.replace('_', ' ') : 'General';
                
                return `
                    <div class="session-item">
                        <div class="session-info">
                            <div class="session-title">${session.title || 'Untitled Session'}</div>
                            <div class="session-meta">${goalLabel} • ${timeAgo}</div>
                        </div>
                        <div class="session-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${session.progress}%"></div>
                            </div>
                            <div class="progress-label">${session.progress}%</div>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function getTimeAgo(date) {
            const seconds = Math.floor((new Date() - date) / 1000);
            const intervals = {
                year: 31536000,
                month: 2592000,
                week: 604800,
                day: 86400,
                hour: 3600,
                minute: 60
            };
            
            for (const [unit, secondsInUnit] of Object.entries(intervals)) {
                const interval = Math.floor(seconds / secondsInUnit);
                if (interval >= 1) {
                    return `${interval} ${unit}${interval > 1 ? 's' : ''} ago`;
                }
            }
            return 'Just now';
        }
        
        // Event listeners
        periodSelect.addEventListener('change', loadDashboard);
        
        // Initial load
        loadDashboard();
    });
    </script>
</body>
</html>
