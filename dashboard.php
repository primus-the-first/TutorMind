<?php
require_once 'includes/check_auth.php';
require_once 'includes/db_mysql.php';

$displayName = isset($_SESSION['first_name']) && !empty($_SESSION['first_name'])
    ? $_SESSION['first_name']
    : (isset($_SESSION['username']) ? $_SESSION['username'] : 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - TutorMind</title>
    <link rel="stylesheet" href="assets/css/ui-overhaul.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Funnel+Display:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --sidebar-width: 240px;
            --dashboard-bg: #f5f0e8;
            --card-bg: #fffdf7;
            --card-shadow: 3px 3px 0 #1a1a2e;
            --card-border: 2px solid #1a1a2e;
            --section-gap: 2rem;
            --heat-0: rgba(123,63,242,0.08);
            --heat-1: rgba(123,63,242,0.3);
            --heat-2: rgba(123,63,242,0.58);
            --heat-3: rgba(123,63,242,0.92);
            --font-display: 'Funnel Display', Georgia, serif;
            --font-body: 'Outfit', system-ui, sans-serif;
            --ink: #1a1a2e;
            --accent-violet: #7C3AED;
            --accent-emerald: #059669;
            --accent-amber: #d97706;
            --accent-rose: #e11d48;
            --accent-sky: #0284c7;
            --accent-indigo: #4f46e5;
        }
        .dark-mode {
            --dashboard-bg: #0f0f1a;
            --card-bg: #1a1a2e;
            --card-shadow: 3px 3px 0 rgba(124,58,237,0.25);
            --card-border: 2px solid rgba(255,255,255,0.12);
            --heat-0: rgba(123,63,242,0.1);
            --heat-1: rgba(123,63,242,0.32);
            --heat-2: rgba(123,63,242,0.6);
            --heat-3: rgba(123,63,242,0.95);
            --ink: rgba(255,255,255,0.15);
        }

        /* ── Hand-drawn SVG icon base ─────────────────── */
        .icon-svg {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1em; height: 1em;
            vertical-align: -0.125em;
            flex-shrink: 0;
        }
        .icon-svg svg {
            width: 100%; height: 100%;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: square;
            stroke-linejoin: miter;
        }

        /* ── Layout ─────────────────────────────────────── */
        body { margin: 0; background: var(--dashboard-bg); font-family: var(--font-body); }

        .sidebar-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 200;
            opacity: 0;
            transition: opacity 0.3s;
        }

        /* ── Sidebar ─────────────────────────────────────── */
        .dashboard-sidebar {
            position: fixed;
            left: 0; top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--card-bg);
            border-right: 1px solid var(--border, rgba(0,0,0,0.08));
            display: flex;
            flex-direction: column;
            z-index: 300;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1.4rem 1.25rem 1.25rem;
            font-family: var(--font-display);
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border, rgba(0,0,0,0.07));
            letter-spacing: -0.3px;
        }
        .sidebar-brand .icon-svg { color: var(--accent-violet); font-size: 1.5rem; }

        .sidebar-section-label {
            font-family: var(--font-display);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-secondary);
            padding: 1.25rem 1.25rem 0.4rem;
            opacity: 0.8;
        }

        .sidebar-nav { flex: 1; padding: 0.25rem 0.75rem; }

        .sidebar-nav-item {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.65rem 0.75rem;
            border-radius: 0;
            color: var(--text-secondary);
            font-family: var(--font-display);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            margin-bottom: 2px;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        .sidebar-nav-item .icon-svg { width: 18px; font-size: 1.1rem; flex-shrink: 0; }
        .sidebar-nav-item:hover { background: rgba(123,63,242,0.07); color: var(--primary, #7C3AED); }
        .sidebar-nav-item.active {
            background: rgba(123,63,242,0.12);
            color: var(--primary, #7C3AED);
            font-weight: 600;
            border-left: 3px solid var(--primary, #7C3AED);
            padding-left: calc(0.75rem - 3px);
        }

        .sidebar-footer {
            padding: 1rem 0.75rem 1.25rem;
            border-top: 1px solid var(--border, rgba(0,0,0,0.07));
        }
        .sidebar-back-btn {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.65rem 0.75rem;
            border-radius: 6px;
            color: var(--text-secondary);
            font-family: var(--font-display);
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s;
        }
        .sidebar-back-btn:hover { background: rgba(0,0,0,0.05); color: var(--text-primary); }
        .dark-mode .sidebar-back-btn:hover { background: rgba(255,255,255,0.06); }

        /* ── Main area ─────────────────────────────────────── */
        .dashboard-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .dashboard-header {
            position: sticky;
            top: 0;
            z-index: 200;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border, rgba(0,0,0,0.08));
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.9rem 2rem;
            gap: 1rem;
        }

        /* Offset scroll targets by sticky header height */
        .dashboard-section {
            scroll-margin-top: 70px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .hamburger-btn {
            display: none;
            align-items: center;
            justify-content: center;
            width: 36px; height: 36px;
            border: none;
            background: none;
            color: var(--text-primary);
            font-size: 1.1rem;
            cursor: pointer;
            border-radius: 8px;
            transition: background 0.15s;
        }
        .hamburger-btn:hover { background: rgba(0,0,0,0.06); }

        .header-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .header-title span { color: var(--text-secondary); font-weight: 400; font-size: 0.875rem; margin-left: 0.4rem; }

        .custom-dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            min-width: 140px;
            padding: 0.5rem 1rem;
            border: 2px solid var(--ink);
            border-radius: 4px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-family: var(--font-display);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 2px 2px 0 var(--ink);
            transition: transform 0.1s, box-shadow 0.1s;
            outline: none;
        }
        .dropdown-trigger:hover {
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0 var(--ink);
        }
        .dropdown-trigger:active {
            transform: translate(1px, 1px);
            box-shadow: 1px 1px 0 var(--ink);
        }
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 160px;
            background: var(--card-bg);
            border: 2px solid var(--ink);
            border-radius: 4px;
            box-shadow: 4px 4px 0 var(--ink);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: opacity 0.15s, transform 0.15s, visibility 0.15s;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            padding: 0.3rem;
        }
        .dropdown-menu.open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .dropdown-option {
            padding: 0.6rem 1rem;
            font-family: var(--font-display);
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
            border-radius: 2px;
            transition: background 0.1s, transform 0.1s;
        }
        .dropdown-option:hover {
            background: var(--ink);
            color: var(--card-bg);
            transform: translateX(2px);
        }
        .dropdown-option.selected {
            background: var(--accent-violet);
            color: #fff;
        }
        .dropdown-option.selected:hover {
            background: var(--accent-violet);
            color: #fff;
            transform: translateX(0);
        }

        .dashboard-content {
            padding: var(--section-gap) 2rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: var(--section-gap);
            isolation: isolate;
            overflow-x: hidden;
        }

        /* ── Section headings ─────────────────────────────── */
        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }
        .section-heading h2 {
            font-family: var(--font-display);
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0;
        }
        .section-heading h2 .icon-svg { color: var(--accent-violet); font-size: 1.1rem; }
        .section-badge {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            background: var(--dashboard-bg);
            padding: 0.2rem 0.65rem;
            border-radius: 0;
            border: 1px solid var(--ink);
        }

        /* ── KPI Cards ─────────────────────────────────────── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .kpi-card {
            background: var(--card-bg);
            border-radius: 0;
            padding: 1.25rem;
            padding-left: 1.4rem;
            box-shadow: var(--card-shadow);
            border: var(--card-border);
            transition: transform 0.15s, box-shadow 0.15s;
            position: relative;
            overflow: hidden;
            opacity: 0;
            animation: kpiSlideUp 0.4s ease forwards;
        }
        .kpi-card:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 var(--ink);
        }

        .kpi-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .kpi-icon {
            width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
        }

        .trend-badge {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 0;
            display: inline-flex;
            align-items: center;
            gap: 2px;
            border: 1px solid currentColor;
        }
        .trend-badge.up   { background: rgba(16,185,129,0.05); color: #10b981; }
        .trend-badge.down { background: rgba(239,68,68,0.05);  color: #ef4444; }
        .trend-badge.neutral { background: rgba(0,0,0,0.03); color: var(--text-secondary); border-color: var(--ink); }

        .kpi-value {
            font-family: var(--font-display);
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 0.3rem;
            letter-spacing: -0.5px;
        }
        .kpi-value small {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-secondary);
            letter-spacing: 0;
        }
        .kpi-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* KPI accent stripe — left side, bold */
        .kpi-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 4px;
            background: var(--kpi-accent, var(--accent-violet));
            border-radius: 0;
        }

        @keyframes kpiSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Heatmap ─────────────────────────────────────── */
        .heatmap-card {
            background: var(--card-bg);
            border-radius: 0;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--card-shadow);
            border: var(--card-border);
        }
        .heatmap-card h3 {
            font-family: var(--font-display);
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 1rem;
        }
        .heatmap-card h3 .icon-svg { color: var(--accent-violet); font-size: 1.1rem; }

        .heatmap-wrapper { overflow-x: auto; padding-bottom: 4px; }

        .heatmap-months-row {
            display: flex;
            margin-left: 30px;
            margin-bottom: 4px;
            font-size: 0.68rem;
            color: var(--text-secondary);
            gap: 0;
        }
        .heatmap-month-label { min-width: 0; }

        .heatmap-body { display: flex; gap: 6px; }

        .heatmap-day-labels {
            display: flex;
            flex-direction: column;
            gap: 2px;
            font-size: 0.62rem;
            color: var(--text-secondary);
            margin-right: 4px;
            margin-top: 0;
        }
        .heatmap-day-label { height: 12px; line-height: 12px; }

        .heatmap-grid { display: flex; gap: 2px; }
        .heatmap-week { display: flex; flex-direction: column; gap: 2px; }

        .heat-cell {
            width: 14px; height: 14px;
            border-radius: 1px;
            transition: opacity 0.15s;
        }
        .heat-cell:hover { opacity: 0.75; }
        .heat-0 { background: var(--heat-0); }
        .heat-1 { background: var(--heat-1); }
        .heat-2 { background: var(--heat-2); }
        .heat-3 { background: var(--heat-3); }
        .heat-future { background: transparent; border: 1px dashed var(--border, rgba(0,0,0,0.1)); }

        .heatmap-legend {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 0.75rem;
            justify-content: flex-end;
        }
        .heatmap-legend .heat-cell { cursor: default; }

        /* Tooltip */
        .hm-tooltip {
            position: fixed;
            background: rgba(15,15,30,0.88);
            color: #fff;
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 6px;
            pointer-events: none;
            z-index: 9999;
            display: none;
            white-space: nowrap;
        }

        /* ── Chart cards ─────────────────────────────────── */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .charts-row-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .chart-card {
            background: var(--card-bg);
            border-radius: 6px;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
            border: var(--card-border);
        }
        .chart-card h3 {
            font-family: var(--font-display);
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 1rem;
        }
        .chart-card h3 .icon-svg { color: var(--accent-violet); font-size: 1rem; }

        .chart-container { position: relative; height: 220px; }
        .chart-container.tall { height: 280px; }

        /* ── Subject progress ─────────────────────────────── */
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .subject-card {
            background: var(--card-bg);
            border-radius: 6px;
            padding: 1.1rem 1.25rem;
            box-shadow: var(--card-shadow);
            border: var(--card-border);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .subject-card:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 var(--ink);
        }

        .subject-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.6rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .subject-pct {
            font-size: 0.875rem;
            font-weight: 700;
        }

        .subject-bar-track {
            height: 8px;
            background: var(--dashboard-bg);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        .subject-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.6s ease;
        }

        .subject-meta {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* ── Mini stat rows ───────────────────────────────── */
        .mini-stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .mini-stat-card {
            background: var(--card-bg);
            border-radius: 6px;
            padding: 1rem 1.1rem;
            box-shadow: var(--card-shadow);
            border: var(--card-border);
            text-align: center;
        }
        .mini-stat-value {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.5px;
        }
        .mini-stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }

        /* ── Recent sessions ──────────────────────────────── */
        .sessions-card {
            background: var(--card-bg);
            border-radius: 6px;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
            border: var(--card-border);
        }
        .sessions-card h3 {
            font-family: var(--font-display);
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 1rem;
        }
        .sessions-card h3 .icon-svg { color: var(--accent-violet); font-size: 1.1rem; }

        .session-list { display: flex; flex-direction: column; gap: 0.6rem; }

        .session-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.875rem 1rem;
            background: var(--dashboard-bg);
            border-radius: 10px;
            transition: background 0.15s;
        }
        .session-item:hover { background: rgba(123,63,242,0.06); }

        .session-info { flex: 1; min-width: 0; }
        .session-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.2rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .session-meta { font-size: 0.75rem; color: var(--text-secondary); }

        .session-goal-badge {
            font-size: 0.68rem;
            font-weight: 600;
            padding: 0.2rem 0.55rem;
            border-radius: 20px;
            background: rgba(123,63,242,0.1);
            color: var(--primary, #7C3AED);
            white-space: nowrap;
            flex-shrink: 0;
        }

        .session-progress-wrap { width: 80px; flex-shrink: 0; }
        .progress-bar { height: 6px; background: var(--border, rgba(0,0,0,0.1)); border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary, #7C3AED), #a855f7); border-radius: 3px; }
        .progress-pct { font-size: 0.7rem; color: var(--text-secondary); text-align: right; margin-top: 3px; }

        /* ── Quiz recent list ─────────────────────────────── */
        .quiz-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .quiz-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: var(--dashboard-bg);
            border-radius: 10px;
        }
        .quiz-score-badge {
            min-width: 44px;
            text-align: center;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
        }
        .quiz-score-badge.high   { background: rgba(16,185,129,0.12); color: #10b981; }
        .quiz-score-badge.mid    { background: rgba(245,158,11,0.12);  color: #f59e0b; }
        .quiz-score-badge.low    { background: rgba(239,68,68,0.12);   color: #ef4444; }
        .quiz-question {
            flex: 1;
            font-size: 0.8rem;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .quiz-type-tag {
            font-size: 0.68rem;
            padding: 0.15rem 0.5rem;
            border-radius: 20px;
            background: rgba(99,102,241,0.1);
            color: #6366f1;
            flex-shrink: 0;
            white-space: nowrap;
        }

        /* ── Loading / Empty states ───────────────────────── */
        .loading-state {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5rem 2rem;
            color: var(--text-secondary);
            flex-direction: column;
            gap: 1rem;
        }
        .brutalist-spinner {
            width: 28px; height: 28px;
            border: 2.5px solid var(--accent-violet);
            border-radius: 2px;
            animation: spinSquare 0.9s cubic-bezier(0.68, -0.55, 0.27, 1.55) infinite;
        }
        @keyframes spinSquare {
            0%   { transform: rotate(0deg) scale(1); }
            50%  { transform: rotate(180deg) scale(0.85); border-radius: 50%; }
            100% { transform: rotate(360deg) scale(1); }
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }
        .empty-state .icon-svg { font-size: 2.5rem; opacity: 0.35; margin-bottom: 0.75rem; display: block; margin-inline: auto; }
        .empty-state p { margin: 0 0 0.5rem; font-size: 0.9rem; font-family: var(--font-display); }
        .empty-state small { font-size: 0.8rem; }
        .empty-cta {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.25rem;
            padding: 0.6rem 1.25rem;
            background: var(--accent-violet);
            color: #fff;
            border-radius: 4px;
            border: 2px solid var(--ink);
            box-shadow: 3px 3px 0 var(--ink);
            font-family: var(--font-display);
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            text-decoration: none;
            transition: transform 0.12s, box-shadow 0.12s;
        }
        .empty-cta:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 var(--ink);
        }
        .empty-cta:active {
            transform: translate(1px, 1px);
            box-shadow: 1px 1px 0 var(--ink);
        }
        

        /* ── Responsive ───────────────────────────────────── */
        @media (max-width: 900px) {
            .dashboard-sidebar {
                transform: translateX(-100%);
                z-index: 400;
            }
            body.sidebar-open .dashboard-sidebar { transform: translateX(0); }
            body.sidebar-open .sidebar-overlay { display: block; opacity: 1; }
            .dashboard-main { margin-left: 0; }
            .hamburger-btn { display: flex; }
            .dashboard-content { padding: 1.25rem 1rem; }
            .dashboard-header { padding: 0.75rem 1rem; }
            .charts-row { grid-template-columns: 1fr; }
            .charts-row-3 { grid-template-columns: 1fr; }
        }

        @media (max-width: 600px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .subjects-grid { grid-template-columns: 1fr; }
            .mini-stats-row { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<nav class="dashboard-sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="assets/logo-bridge.svg" alt="TutorMind" style="width:28px;height:28px;flex-shrink:0;">
        TutorMind
    </div>

    <p class="sidebar-section-label">Analytics</p>

    <div class="sidebar-nav">
        <button class="sidebar-nav-item active" data-section="overview">
            <span class="icon-svg"><svg viewBox="0 0 24 24"><polyline points="3 17 8 11 13 15 21 5"/><circle cx="21" cy="5" r="1.5" fill="currentColor" stroke="none"/></svg></span><span>Overview</span>
        </button>
        <button class="sidebar-nav-item" data-section="learning">
            <span class="icon-svg"><svg viewBox="0 0 24 24"><path d="M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/><line x1="22" y1="10" x2="22" y2="16"/></svg></span><span>Learning</span>
        </button>
        <button class="sidebar-nav-item" data-section="subjects">
            <span class="icon-svg"><svg viewBox="0 0 24 24"><path d="M4 4h5v5H4z"/><path d="M4 15h5v5H4z"/><path d="M15 4h5v5h-5z"/><path d="M15 15h5v5h-5z"/></svg></span><span>Subjects</span>
        </button>
        <button class="sidebar-nav-item" data-section="quizzes">
            <span class="icon-svg"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9a3 3 0 0 1 5.8 1c0 2-3 2.5-3 4.5"/><circle cx="12" cy="17.5" r="0.8" fill="currentColor" stroke="none"/></svg></span><span>Quizzes</span>
        </button>
        <button class="sidebar-nav-item" data-section="focus">
            <span class="icon-svg"><svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><polyline points="12 9 12 13 15 15"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="9" y1="2" x2="15" y2="2"/></svg></span><span>Focus Time</span>
        </button>
    </div>

    <div class="sidebar-footer">
        <a href="tutor_mysql.php" class="sidebar-back-btn">
            <span class="icon-svg"><svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="11 18 5 12 11 6"/></svg></span><span>Back to Chat</span>
        </a>
    </div>
</nav>

<!-- Main -->
<div class="dashboard-main">
    <header class="dashboard-header">
        <div class="header-left">
            <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle sidebar">
                <span class="icon-svg" style="font-size:1.2rem"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></span>
            </button>
            <div class="header-title">
                Dashboard <span>Hi, <?= htmlspecialchars($displayName) ?></span>
            </div>
        </div>
        <div class="custom-dropdown" id="customPeriodDropdown">
            <button class="dropdown-trigger" id="dropdownTrigger" aria-haspopup="listbox" aria-expanded="false">
                <span id="dropdownTriggerText">Last 30 Days</span>
                <span class="icon-svg" style="font-size:1.1rem;margin-left:8px;"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            </button>
            <div class="dropdown-menu" role="listbox">
                <div class="dropdown-option" role="option" data-value="7days">Last 7 Days</div>
                <div class="dropdown-option selected" role="option" data-value="30days">Last 30 Days</div>
                <div class="dropdown-option" role="option" data-value="90days">Last 90 Days</div>
                <div class="dropdown-option" role="option" data-value="all">All Time</div>
            </div>
            <select class="period-select" id="periodSelect" style="display:none">
                <option value="7days">Last 7 Days</option>
                <option value="30days" selected>Last 30 Days</option>
                <option value="90days">Last 90 Days</option>
                <option value="all">All Time</option>
            </select>
        </div>
    </header>

    <div class="dashboard-content" id="dashboardContent">
        <div class="loading-state">
            <div class="brutalist-spinner"></div>
            <span>Loading your analytics…</span>
        </div>
    </div>
</div>

<!-- Heatmap tooltip -->
<div class="hm-tooltip" id="hmTooltip"></div>

<script>
(async () => {
    // ── Utilities ──────────────────────────────────────────
    const COLORS = ['#7C3AED','#059669','#d97706','#e11d48','#0284c7','#4f46e5','#ec4899','#06b6d4'];

    // Redesigned icon library — geometric, bold, neobrutalist
    // Rules: stroke only, no opacity fills, square linecaps, miter joins, primitive shapes
    const ICO = {
        // Activity / pulse — sharp zigzag, like an EKG readout
        pulse:      '<span class="icon-svg"><svg viewBox="0 0 24 24"><polyline points="2 12 6 12 8 4 10 20 12 12 14 8 16 14 18 12 22 12"/></svg></span>',

        // Grad cap — flat square mortarboard, geometric
        gradCap:    '<span class="icon-svg"><svg viewBox="0 0 24 24"><polygon points="12 3 22 8 12 13 2 8"/><polyline points="6 10 6 18"/><path d="M6 18c2 2 10 2 12 0v-8"/></svg></span>',

        // Dashboard grid — 4 equal squares, dead flat
        grid4:      '<span class="icon-svg"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></span>',

        // Question mark — inside a square, not circle
        question:   '<span class="icon-svg"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18"/><path d="M9 9h1.5a2.5 2.5 0 0 1 0 5H12v2"/><line x1="12" y1="18" x2="12" y2="18.5"/></svg></span>',

        // Clock — square body with clock hands
        clock:      '<span class="icon-svg"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18"/><polyline points="12 7 12 13 16 13"/></svg></span>',

        // Chat bubble — hard rectangular bubble, no curves
        chatBubble: '<span class="icon-svg"><svg viewBox="0 0 24 24"><polygon points="3 3 21 3 21 15 9 15 3 21"/><line x1="7" y1="8" x2="17" y2="8"/><line x1="7" y1="12" x2="13" y2="12"/></svg></span>',

        // Book open — two flat halves with spine line
        bookOpen:   '<span class="icon-svg"><svg viewBox="0 0 24 24"><path d="M2 4h9v16H2z"/><path d="M13 4h9v16h-9z"/><line x1="11" y1="4" x2="13" y2="4"/><line x1="11" y1="20" x2="13" y2="20"/></svg></span>',

        // Trophy — tall rectangular cup
        trophy:     '<span class="icon-svg"><svg viewBox="0 0 24 24"><rect x="7" y="3" width="10" height="9"/><polyline points="7 7 3 7 3 9 7 12"/><polyline points="17 7 21 7 21 9 17 12"/><line x1="12" y1="12" x2="12" y2="18"/><line x1="8" y1="21" x2="16" y2="21"/></svg></span>',

        // Flame — angular/polygonal flame shape
        flame:      '<span class="icon-svg"><svg viewBox="0 0 24 24"><polygon points="12 2 16 8 20 13 17 14 20 20 7 20 9 14 4 13 8 8"/></svg></span>',

        // Calendar — open top
        calendar:   '<span class="icon-svg"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg></span>',

        // Flag — rectangular flag on pole
        flag:       '<span class="icon-svg"><svg viewBox="0 0 24 24"><line x1="5" y1="2" x2="5" y2="22"/><polygon points="5 3 19 3 19 13 5 13"/></svg></span>',

        // Lightning bolt — keep sharp polygon
        bolt:       '<span class="icon-svg"><svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10"/></svg></span>',

        // Area chart — stairstep bars, not curves
        areaChart:  '<span class="icon-svg"><svg viewBox="0 0 24 24"><polyline points="2 20 2 14 7 14 7 8 12 8 12 11 17 11 17 6 22 6"/><line x1="2" y1="20" x2="22" y2="20"/></svg></span>',

        // Network nodes — square nodes, hard lines
        nodes:      '<span class="icon-svg"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="4" height="4"/><rect x="16" y="6" width="4" height="4"/><rect x="10" y="16" width="4" height="4"/><line x1="8" y1="6" x2="16" y2="8"/><line x1="6" y1="8" x2="11" y2="16"/><line x1="18" y1="10" x2="13" y2="16"/></svg></span>',

        // Target / crosshair — open cross
        target:     '<span class="icon-svg"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/><line x1="12" y1="22" x2="12" y2="18"/></svg></span>',

        // History / undo clock — arrow + clock face
        history:    '<span class="icon-svg"><svg viewBox="0 0 24 24"><polyline points="3 3 3 8 8 8"/><path d="M3.5 8a9 9 0 1 1 .5 7"/><polyline points="12 7 12 12 15 15"/></svg></span>',

        // Layers — three flat stacked rects
        layers:     '<span class="icon-svg"><svg viewBox="0 0 24 24"><polygon points="12 2 22 7 12 12 2 7"/><polyline points="2 12 12 17 22 12"/><polyline points="2 17 12 22 22 17"/></svg></span>',

        // List with checkboxes
        listCheck:  '<span class="icon-svg"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="4" height="4"/><rect x="3" y="11" width="4" height="4"/><rect x="3" y="17" width="4" height="4"/><line x1="10" y1="7" x2="21" y2="7"/><line x1="10" y1="13" x2="21" y2="13"/><line x1="10" y1="19" x2="21" y2="19"/></svg></span>',

        // Pie chart — strict wedge + circle
        pieChart:   '<span class="icon-svg"><svg viewBox="0 0 24 24"><path d="M12 2v10h10a10 10 0 1 1-10-10z"/><path d="M12 2a10 10 0 0 1 10 10"/></svg></span>',

        // Calendar with grid dots
        calendarAlt:'<span class="icon-svg"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>',

        // Star / sparkle — 4-point sharp star
        sparkle:    '<span class="icon-svg"><svg viewBox="0 0 24 24"><polygon points="12 2 14 10 22 12 14 14 12 22 10 14 2 12 10 10"/></svg></span>',

        // Inbox — floating tray
        inbox:      '<span class="icon-svg"><svg viewBox="0 0 24 24"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg></span>',

        // No-chat / error chat — speech bubble with X
        noChat:     '<span class="icon-svg"><svg viewBox="0 0 24 24"><polygon points="3 3 21 3 21 15 9 15 3 21"/><line x1="9" y1="7" x2="15" y2="11"/><line x1="15" y1="7" x2="9" y2="11"/></svg></span>',

        // Robot / AI shield (TutorMind brand icon)
        robot:      '<span class="icon-svg"><svg viewBox="0 0 24 24"><polygon points="12 2 20 6 20 14 12 22 4 14 4 6"/><rect x="9" y="9" width="2" height="2"/><rect x="13" y="9" width="2" height="2"/><line x1="10" y1="14" x2="14" y2="14"/></svg></span>',

        // Exclamation — naked
        exclaim:    '<span class="icon-svg"><svg viewBox="0 0 24 24"><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>',
    };

    const isDark       = () => document.body.classList.contains('dark-mode');
    const gridColor    = () => isDark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const tickColor    = () => isDark() ? '#666' : '#888';
    const legendColor  = () => isDark() ? '#aaa' : '#555';

    const charts = {};
    function destroyChart(key) {
        if (charts[key]) { charts[key].destroy(); delete charts[key]; }
    }

    function formatMinutes(mins) {
        mins = Math.round(mins);
        if (mins < 60) return `${mins}m`;
        const h = Math.floor(mins / 60), m = mins % 60;
        return m > 0 ? `${h}h ${m}m` : `${h}h`;
    }

    function getTimeAgo(dateStr) {
        const date = new Date(dateStr.replace(' ', 'T'));
        const sec  = Math.floor((Date.now() - date) / 1000);
        for (const [unit, s] of [['year',31536000],['month',2592000],['week',604800],['day',86400],['hour',3600],['minute',60]]) {
            const n = Math.floor(sec / s);
            if (n >= 1) return `${n} ${unit}${n > 1 ? 's' : ''} ago`;
        }
        return 'Just now';
    }

    function trendBadge(current, prev) {
        if (prev === null || prev === undefined) return '';
        const delta = current - prev;
        if (delta === 0) return `<span class="trend-badge neutral">→ 0</span>`;
        const sign = delta > 0 ? '↑' : '↓';
        const cls  = delta > 0 ? 'up' : 'down';
        return `<span class="trend-badge ${cls}">${sign} ${Math.abs(delta)}</span>`;
    }

    function subjectColor(pct) {
        if (pct >= 70) return '#10b981';
        if (pct >= 40) return '#f59e0b';
        return '#ef4444';
    }

    function quizScoreClass(score) {
        if (score >= 70) return 'high';
        if (score >= 40) return 'mid';
        return 'low';
    }

    const GOAL_LABELS = {
        homework_help: 'Homework', test_prep: 'Test Prep',
        explore: 'Explore', practice: 'Practice', general: 'General'
    };
    const QUIZ_TYPE_LABELS = {
        recognition: 'Recognition', cued: 'Cued Recall',
        free_recall: 'Free Recall', application: 'Application'
    };

    // ── Dark mode ──────────────────────────────────────────
    async function applyDarkMode() {
        try {
            const r = await fetch('api/user_settings.php?action=get');
            const d = await r.json();
            if (d.success && d.settings) {
                const dark = d.settings.dark_mode === true || d.settings.dark_mode === 1;
                document.body.classList.toggle('dark-mode', dark);
                localStorage.setItem('darkMode', dark ? 'enabled' : 'disabled');
                return;
            }
        } catch (_) {}
        if (localStorage.getItem('darkMode') === 'enabled') document.body.classList.add('dark-mode');
    }
    await applyDarkMode();

    // ── Sidebar toggle ─────────────────────────────────────
    const sidebar        = document.getElementById('sidebar');
    const overlay        = document.getElementById('sidebarOverlay');
    const hamburgerBtn   = document.getElementById('hamburgerBtn');

    function closeSidebar() { document.body.classList.remove('sidebar-open'); }

    hamburgerBtn.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
    overlay.addEventListener('click', closeSidebar);

    // Sidebar nav — smooth scroll with header offset
    document.querySelectorAll('.sidebar-nav-item[data-section]').forEach(btn => {
        btn.addEventListener('click', () => {
            const el = document.getElementById('section-' + btn.dataset.section);
            if (el) {
                const header = document.querySelector('.dashboard-header');
                const headerH = header ? header.getBoundingClientRect().height : 65;
                const top = el.getBoundingClientRect().top + window.scrollY - headerH - 12;
                window.scrollTo({ top, behavior: 'smooth' });
            }
            if (window.innerWidth <= 900) closeSidebar();
        });
    });

    // Custom Dropdown Logic
    const dropdownTrigger = document.getElementById('dropdownTrigger');
    const dropdownMenu = document.querySelector('.dropdown-menu');
    const dropdownOptions = document.querySelectorAll('.dropdown-option');
    const triggerText = document.getElementById('dropdownTriggerText');
    const hiddenSelect = document.getElementById('periodSelect');

    dropdownTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu.classList.toggle('open');
    });

    document.addEventListener('click', () => {
        dropdownMenu.classList.remove('open');
    });

    dropdownOptions.forEach(option => {
        option.addEventListener('click', () => {
            dropdownOptions.forEach(opt => opt.classList.remove('selected'));
            option.classList.add('selected');
            triggerText.textContent = option.textContent;
            hiddenSelect.value = option.dataset.value;
            hiddenSelect.dispatchEvent(new Event('change'));
        });
    });

    // IntersectionObserver for active nav highlight
    function setupSectionObserver() {
        const sectionEls = document.querySelectorAll('.dashboard-section');
        if (!sectionEls.length) return;
        const obs = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id.replace('section-', '');
                    document.querySelectorAll('.sidebar-nav-item').forEach(b => {
                        b.classList.toggle('active', b.dataset.section === id);
                    });
                }
            });
        }, { rootMargin: '-80px 0px -55% 0px', threshold: 0 });
        sectionEls.forEach(s => obs.observe(s));
    }

    // ── Data & rendering ───────────────────────────────────
    const periodSelect = document.getElementById('periodSelect');
    let lastData = null;

    async function loadDashboard() {
        const period  = periodSelect.value;
        const content = document.getElementById('dashboardContent');
        content.innerHTML = `<div class="loading-state"><div class="brutalist-spinner"></div><span>Loading…</span></div>`;
        Object.keys(charts).forEach(destroyChart);

        try {
            const [res] = await Promise.all([
                fetch(`api/analytics.php?period=${period}`),
                new Promise(r => setTimeout(r, 250))
            ]);
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Unknown error');
            lastData = data;
            renderAll(data);
            setupSectionObserver();
        } catch (err) {
            content.innerHTML = `
                <div class="empty-state">
                    ${ICO.exclaim}
                    <p>Could not load dashboard data</p>
                    <small>${err.message}</small>
                </div>`;
        }
    }

    function renderAll(data) {
        const content = document.getElementById('dashboardContent');
        content.innerHTML = `
            <section class="dashboard-section" id="section-overview"></section>
            <section class="dashboard-section" id="section-learning"></section>
            <section class="dashboard-section" id="section-subjects"></section>
            <section class="dashboard-section" id="section-quizzes"></section>
            <section class="dashboard-section" id="section-focus"></section>
        `;
        renderOverview(data);
        renderLearning(data);
        renderSubjects(data.subjectProgress || []);
        renderQuizzes(data.quizStats || {});
        renderFocus(data.pomodoroStats || {});
    }

    // ── Overview ───────────────────────────────────────────
    function renderOverview(data) {
        const el = document.getElementById('section-overview');
        const { stats, trends, heatmap } = data;
        const hasTrends = trends !== null && periodSelect.value !== 'all';

        const focusHrs  = data.pomodoroStats?.totalMinutes
            ? formatMinutes(data.pomodoroStats.totalMinutes) : '—';
        const quizAvg   = (data.quizStats?.totalAnswered > 0)
            ? data.quizStats.overallAvg + '%' : '—';

        const kpis = [
            { label: 'Study Sessions',  value: stats.totalSessions,
              svg: ICO.chatBubble,  color: '#4f46e5',
              trend: hasTrends ? trendBadge(stats.totalSessions, trends.prevTotalSessions) : '' },
            { label: 'Topics Explored', value: stats.topicsStudied,
              svg: ICO.bookOpen,    color: '#059669',
              trend: hasTrends ? trendBadge(stats.topicsStudied, trends.prevTopicsStudied) : '' },
            { label: 'Avg Progress',    value: stats.avgProgress + '%',
              svg: ICO.trophy,      color: '#d97706',
              trend: hasTrends ? trendBadge(stats.avgProgress, trends.prevAvgProgress) : '' },
            { label: 'Day Streak',
              value: stats.currentStreak + ' 🔥',
              svg: ICO.flame,       color: '#e11d48',
              trend: '' },
            { label: 'Active Days',     value: stats.activeDays,
              svg: ICO.calendar,    color: '#0284c7',
              trend: hasTrends ? trendBadge(stats.activeDays, trends.prevActiveDays) : '' },
            { label: 'Milestones',
              value: `${stats.milestonesCompleted}<small>/${stats.milestonesTotal}</small>`,
              svg: ICO.flag,        color: '#7C3AED',
              trend: '' },
            { label: 'Focus Time',      value: focusHrs,
              svg: ICO.clock,       color: '#ec4899',
              trend: '' },
            { label: 'Quiz Avg Score',  value: quizAvg,
              svg: ICO.bolt,        color: '#7C3AED',
              trend: '' },
        ];

        el.innerHTML = `
            <div class="section-heading">
                <h2>${ICO.pulse} Overview</h2>
            </div>
            <div class="kpi-grid">
                ${kpis.map((k, i) => `
                <div class="kpi-card" style="--kpi-accent:${k.color};animation-delay:${i * 60}ms">
                    <div class="kpi-card-top">
                        <div class="kpi-icon" style="color:${k.color}">
                            ${k.svg}
                        </div>
                        ${k.trend}
                    </div>
                    <div class="kpi-value">${k.value}</div>
                    <div class="kpi-label">${k.label}</div>
                </div>`).join('')}
            </div>
            <div class="heatmap-card">
                <h3>${ICO.calendarAlt} Study Activity — Past Year</h3>
                <div class="heatmap-wrapper" id="heatmapWrapper"></div>
                <div class="heatmap-legend">
                    Less <div class="heat-cell heat-0"></div>
                    <div class="heat-cell heat-1"></div>
                    <div class="heat-cell heat-2"></div>
                    <div class="heat-cell heat-3"></div> More
                </div>
            </div>
        `;

        buildHeatmap(heatmap || {});
    }

    // ── Heatmap builder ────────────────────────────────────
    function buildHeatmap(data) {
        const wrapper = document.getElementById('heatmapWrapper');
        if (!wrapper) return;

        const today     = new Date();
        today.setHours(0,0,0,0);
        const startDate = new Date(today);
        startDate.setDate(startDate.getDate() - 364);
        startDate.setDate(startDate.getDate() - startDate.getDay()); // align to Sunday

        const weeks = [];
        const monthLabels = []; // { weekIndex, label }
        let cur = new Date(startDate);
        let lastMonth = -1;

        while (cur <= today || weeks.length < 53) {
            const week = [];
            const weekStart = new Date(cur);
            if (weekStart.getMonth() !== lastMonth) {
                monthLabels.push({ weekIndex: weeks.length, label: weekStart.toLocaleDateString('en-US',{month:'short'}) });
                lastMonth = weekStart.getMonth();
            }
            for (let d = 0; d < 7; d++) {
                const dateStr = cur.toISOString().split('T')[0];
                week.push({ date: dateStr, count: data[dateStr] || 0, future: cur > today });
                cur.setDate(cur.getDate() + 1);
            }
            weeks.push(week);
            if (cur > today && weeks.length >= 53) break;
        }

        const CELL = 14; // cell + gap
        const monthsRow = document.createElement('div');
        monthsRow.className = 'heatmap-months-row';
        monthsRow.style.marginLeft = '30px';

        let prevIdx = 0;
        monthLabels.forEach((m, i) => {
            const gap  = (m.weekIndex - prevIdx) * CELL;
            const span = document.createElement('span');
            span.className = 'heatmap-month-label';
            span.style.paddingLeft = (i === 0 ? 0 : gap) + 'px';
            span.textContent = m.label;
            monthsRow.appendChild(span);
            prevIdx = m.weekIndex;
        });

        const body = document.createElement('div');
        body.className = 'heatmap-body';

        const dayLabels = document.createElement('div');
        dayLabels.className = 'heatmap-day-labels';
        ['S','M','T','W','T','F','S'].forEach((d, i) => {
            const lbl = document.createElement('div');
            lbl.className = 'heatmap-day-label';
            lbl.textContent = [1,3,5].includes(i) ? d : '';
            dayLabels.appendChild(lbl);
        });

        const grid = document.createElement('div');
        grid.className = 'heatmap-grid';

        weeks.forEach(week => {
            const col = document.createElement('div');
            col.className = 'heatmap-week';
            week.forEach(cell => {
                const div = document.createElement('div');
                if (cell.future) {
                    div.className = 'heat-cell heat-future';
                } else {
                    const lvl = cell.count === 0 ? 0 : cell.count === 1 ? 1 : cell.count <= 3 ? 2 : 3;
                    div.className = `heat-cell heat-${lvl}`;
                    div.dataset.date  = cell.date;
                    div.dataset.count = cell.count;
                }
                col.appendChild(div);
            });
            grid.appendChild(col);
        });

        body.appendChild(dayLabels);
        body.appendChild(grid);
        wrapper.innerHTML = '';
        wrapper.appendChild(monthsRow);
        wrapper.appendChild(body);
    }

    // Heatmap tooltip
    const hmTooltip = document.getElementById('hmTooltip');
    document.addEventListener('mouseover', e => {
        const cell = e.target.closest('[data-date]');
        if (!cell) { hmTooltip.style.display = 'none'; return; }
        const d     = new Date(cell.dataset.date + 'T00:00:00');
        const label = parseInt(cell.dataset.count) === 1 ? '1 session' : `${cell.dataset.count} sessions`;
        const dlbl  = d.toLocaleDateString('en-US', {weekday:'short', month:'short', day:'numeric'});
        hmTooltip.textContent = `${dlbl} · ${label}`;
        hmTooltip.style.display = 'block';
    });
    document.addEventListener('mousemove', e => {
        hmTooltip.style.left = (e.clientX + 14) + 'px';
        hmTooltip.style.top  = (e.clientY - 32) + 'px';
    });
    document.addEventListener('mouseout', e => {
        if (!e.target.closest('[data-date]')) hmTooltip.style.display = 'none';
    });

    // ── Learning section ───────────────────────────────────
    function renderLearning(data) {
        const el = document.getElementById('section-learning');
        const { progressOverTime, topTopics, goalDistribution, recentSessions } = data;

        el.innerHTML = `
            <div class="section-heading">
                <h2>${ICO.gradCap} Learning Activity</h2>
            </div>
            <div class="charts-row">
                <div class="chart-card">
                    <h3>${ICO.areaChart} Progress Over Time</h3>
                    <div class="chart-container tall"><canvas id="progressChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <h3>${ICO.nodes} Topics Breakdown</h3>
                    <div class="chart-container tall"><canvas id="topicsChart"></canvas></div>
                </div>
            </div>
            <div class="charts-row">
                <div class="chart-card">
                    <h3>${ICO.target} Study Goals</h3>
                    <div class="chart-container"><canvas id="goalChart"></canvas></div>
                </div>
                <div class="sessions-card">
                    <h3>${ICO.history} Recent Sessions</h3>
                    <div class="session-list" id="sessionList"></div>
                </div>
            </div>
        `;

        renderProgressChart(progressOverTime);
        renderTopicsChart(topTopics);
        renderGoalChart(goalDistribution);
        renderRecentSessions(recentSessions);
    }

    // Canvas gradient helper — makes fills feel rich instead of flat
    function makeGradient(ctx, colorTop, colorBot, height = 260) {
        const g = ctx.getContext('2d').createLinearGradient(0, 0, 0, height);
        g.addColorStop(0, colorTop);
        g.addColorStop(1, colorBot);
        return g;
    }

    function renderProgressChart(data) {
        destroyChart('progress');
        const ctx = document.getElementById('progressChart');
        if (!ctx) return;
        const grad = makeGradient(ctx, 'rgba(124,58,237,0.35)', 'rgba(124,58,237,0)', 280);
        charts.progress = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => new Date(d.date).toLocaleDateString('en-US',{month:'short',day:'numeric'})),
                datasets: [{
                    label: 'Avg Progress',
                    data: data.map(d => Math.round(d.avg_progress)),
                    borderColor: '#7C3AED',
                    backgroundColor: grad,
                    fill: true, tension: 0.45,
                    pointRadius: 5, pointHoverRadius: 8,
                    pointBackgroundColor: '#7C3AED',
                    pointBorderColor: '#fff', pointBorderWidth: 2,
                    pointHoverBackgroundColor: '#fff', pointHoverBorderColor: '#7C3AED', pointHoverBorderWidth: 2.5,
                    borderWidth: 2.5,
                }]
            },
            options: chartOptions({ yMax: 100, suffix: '%', animation: { duration: 900, easing: 'easeOutQuart' } })
        });
    }

    function renderTopicsChart(topics) {
        destroyChart('topics');
        const ctx = document.getElementById('topicsChart');
        if (!ctx) return;
        const labels = Object.keys(topics), values = Object.values(topics);
        if (!labels.length) { ctx.parentElement.innerHTML = emptyState('No topic data yet'); return; }
        charts.topics = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: COLORS.slice(0, labels.length),
                    borderWidth: 3,
                    borderColor: isDark() ? '#1a1a2e' : '#fffdf7',
                    hoverOffset: 10,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '58%',
                animation: { animateRotate: true, animateScale: true, duration: 900, easing: 'easeOutBack' },
                plugins: {
                    legend: { position: 'right', labels: { color: legendColor(), font: { size: 11, family: "'Outfit', system-ui, sans-serif" }, padding: 14, usePointStyle: true, pointStyleWidth: 8 } },
                    tooltip: {
                        backgroundColor: isDark() ? '#1a1a2e' : '#fffdf7',
                        titleColor: isDark() ? '#fff' : '#1a1a2e',
                        bodyColor: isDark() ? '#ccc' : '#444',
                        borderColor: isDark() ? 'rgba(255,255,255,0.15)' : '#1a1a2e',
                        borderWidth: 2, cornerRadius: 4, padding: 10,
                        titleFont: { family: "'Funnel Display', Georgia, serif", weight: '700' },
                        bodyFont: { family: "'Outfit', system-ui, sans-serif" },
                    }
                }
            }
        });
    }

    function renderGoalChart(goalDistribution) {
        destroyChart('goal');
        const ctx = document.getElementById('goalChart');
        if (!ctx) return;
        const labels = goalDistribution.map(g => GOAL_LABELS[g.goal] || g.goal);
        const values = goalDistribution.map(g => g.count);
        charts.goal = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: COLORS.slice(0, labels.length),
                    borderRadius: 4, borderWidth: 2, borderColor: isDark() ? 'rgba(255,255,255,0.15)' : '#0f172a', borderSkipped: false,
                }]
            },
            options: chartOptions({
                yStepSize: 1,
                animation: {
                    delay: (ctx) => ctx.dataIndex * 80,
                    duration: 700, easing: 'easeOutBounce'
                }
            })
        });
    }

    function renderRecentSessions(sessions) {
        const list = document.getElementById('sessionList');
        if (!list) return;
        if (!sessions || !sessions.length) {
            list.innerHTML = `<div class="empty-state">${ICO.noChat}<p>No sessions yet</p></div>`;
            return;
        }
        list.innerHTML = sessions.map(s => `
            <div class="session-item">
                <div class="session-info">
                    <div class="session-title">${s.title || 'Untitled Session'}</div>
                    <div class="session-meta">${s.date ? getTimeAgo(s.date) : ''}</div>
                </div>
                <span class="session-goal-badge">${GOAL_LABELS[s.goal] || s.goal || 'General'}</span>
                <div class="session-progress-wrap">
                    <div class="progress-bar"><div class="progress-fill" style="width:${s.progress}%"></div></div>
                    <div class="progress-pct">${s.progress}%</div>
                </div>
            </div>`).join('');
    }

    // ── Subjects section ───────────────────────────────────
    function renderSubjects(subjects) {
        const el = document.getElementById('section-subjects');
        el.innerHTML = `
            <div class="section-heading">
                <h2>${ICO.grid4} Subject Progress</h2>
                ${subjects.length ? `<span class="section-badge">${subjects.length} topic${subjects.length !== 1 ? 's' : ''}</span>` : ''}
            </div>
            ${subjects.length === 0
                ? `<div class="chart-card"><div class="empty-state">
                    ${ICO.bookOpen}
                    <p>No subject data yet</p>
                    <small>Progress tracking starts after your first tutoring session</small>
                    <a href="tutor_mysql.php" class="empty-cta">Start a Session</a>
                   </div></div>`
                : `<div class="subjects-grid">${subjects.map(s => {
                    const color = subjectColor(s.completionPct);
                    const msLabel = s.milestonesTotal > 0
                        ? `${s.milestonesCompleted}/${s.milestonesTotal} milestones`
                        : `${s.avgProgress}% avg progress`;
                    return `
                    <div class="subject-card">
                        <div class="subject-name">
                            <span>${s.topic}</span>
                            <span class="subject-pct" style="color:${color}">${s.completionPct}%</span>
                        </div>
                        <div class="subject-bar-track">
                            <div class="subject-bar-fill" style="width:${s.completionPct}%;background:${color}"></div>
                        </div>
                        <div class="subject-meta">${s.sessions} session${s.sessions !== 1 ? 's' : ''} · ${msLabel}</div>
                    </div>`;
                }).join('')}</div>`
            }
        `;
    }

    // ── Quizzes section ────────────────────────────────────
    function renderQuizzes(q) {
        const el = document.getElementById('section-quizzes');
        const empty = !q.totalAnswered;

        el.innerHTML = `
            <div class="section-heading">
                <h2>${ICO.question} Quiz Performance</h2>
            </div>
            ${empty
                ? `<div class="chart-card"><div class="empty-state">
                    ${ICO.question}
                    <p>No quiz data yet</p>
                    <small>Recall quizzes appear after tutoring sessions</small>
                    <a href="tutor_mysql.php" class="empty-cta">Start a Session</a>
                   </div></div>`
                : `
                <div class="mini-stats-row">
                    <div class="mini-stat-card">
                        <div class="mini-stat-value" style="color:#7b3ff2">${q.overallAvg}%</div>
                        <div class="mini-stat-label">Avg Score</div>
                    </div>
                    <div class="mini-stat-card">
                        <div class="mini-stat-value">${q.totalAnswered}</div>
                        <div class="mini-stat-label">Quizzes Answered</div>
                    </div>
                    <div class="mini-stat-card">
                        <div class="mini-stat-value" style="font-size:1rem;padding-top:0.3rem">
                            ${q.byType?.length ? (QUIZ_TYPE_LABELS[q.byType[0].type] || q.byType[0].type) : '—'}
                        </div>
                        <div class="mini-stat-label">Best Question Type</div>
                    </div>
                </div>
                <div class="charts-row">
                    <div class="chart-card">
                        <h3>${ICO.pulse} Score Over Time</h3>
                        <div class="chart-container"><canvas id="quizScoreChart"></canvas></div>
                    </div>
                    <div class="chart-card">
                        <h3>${ICO.layers} Score by Type</h3>
                        <div class="chart-container"><canvas id="quizTypeChart"></canvas></div>
                    </div>
                </div>
                ${q.recentQuizzes?.length ? `
                <div class="sessions-card">
                    <h3>${ICO.listCheck} Recent Quizzes</h3>
                    <div class="quiz-list">
                        ${q.recentQuizzes.map(r => `
                        <div class="quiz-item">
                            <span class="quiz-score-badge ${quizScoreClass(r.score)}">${r.score}%</span>
                            <span class="quiz-question">${r.question}</span>
                            <span class="quiz-type-tag">${QUIZ_TYPE_LABELS[r.question_type] || r.question_type}</span>
                        </div>`).join('')}
                    </div>
                </div>` : ''}
            `}
        `;

        if (!empty) {
            renderQuizScoreChart(q.scoreOverTime || []);
            renderQuizTypeChart(q.byType || []);
        }
    }

    function renderQuizScoreChart(data) {
        destroyChart('quizScore');
        const ctx = document.getElementById('quizScoreChart');
        if (!ctx || !data.length) return;
        const grad = makeGradient(ctx, 'rgba(5,150,105,0.35)', 'rgba(5,150,105,0)', 220);
        charts.quizScore = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => new Date(d.date).toLocaleDateString('en-US',{month:'short',day:'numeric'})),
                datasets: [{
                    label: 'Avg Score',
                    data: data.map(d => d.avg_score),
                    borderColor: '#059669',
                    backgroundColor: grad,
                    fill: true, tension: 0.45,
                    pointRadius: 5, pointHoverRadius: 8,
                    pointBackgroundColor: '#059669',
                    pointBorderColor: '#fff', pointBorderWidth: 2,
                    pointHoverBackgroundColor: '#fff', pointHoverBorderColor: '#059669', pointHoverBorderWidth: 2.5,
                    borderWidth: 2.5,
                }]
            },
            options: chartOptions({ yMax: 100, suffix: '%', animation: { duration: 900, easing: 'easeOutQuart' } })
        });
    }

    function renderQuizTypeChart(byType) {
        destroyChart('quizType');
        const ctx = document.getElementById('quizTypeChart');
        if (!ctx || !byType.length) return;
        charts.quizType = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: byType.map(t => QUIZ_TYPE_LABELS[t.type] || t.type),
                datasets: [{
                    data: byType.map(t => t.avg_score),
                    backgroundColor: COLORS.slice(0, byType.length),
                    borderRadius: 4, borderWidth: 2, borderColor: isDark() ? 'rgba(255,255,255,0.15)' : '#0f172a', borderSkipped: false,
                }]
            },
            options: chartOptions({
                yMax: 100, suffix: '%',
                animation: { delay: (ctx) => ctx.dataIndex * 80, duration: 700, easing: 'easeOutBounce' }
            })
        });
    }

    // ── Focus / Pomodoro section ───────────────────────────
    function renderFocus(p) {
        const el = document.getElementById('section-focus');
        const empty = !p.totalSessions;

        el.innerHTML = `
            <div class="section-heading">
                <h2>${ICO.clock} Focus Time</h2>
            </div>
            ${empty
                ? `<div class="chart-card"><div class="empty-state">
                    ${ICO.clock}
                    <p>No focus sessions yet</p>
                    <small>Use Pomodoro mode during a session to track focus time</small>
                    <a href="tutor_mysql.php" class="empty-cta">Start a Session</a>
                   </div></div>`
                : `
                <div class="mini-stats-row">
                    <div class="mini-stat-card">
                        <div class="mini-stat-value" style="color:#ec4899">${formatMinutes(p.totalMinutes)}</div>
                        <div class="mini-stat-label">Total Focus Time</div>
                    </div>
                    <div class="mini-stat-card">
                        <div class="mini-stat-value">${p.completedSessions}</div>
                        <div class="mini-stat-label">Sessions Completed</div>
                    </div>
                    <div class="mini-stat-card">
                        <div class="mini-stat-value" style="color:#10b981">${p.completionRate}%</div>
                        <div class="mini-stat-label">Completion Rate</div>
                    </div>
                </div>
                <div class="charts-row">
                    <div class="chart-card">
                        <h3>${ICO.areaChart} Daily Focus Minutes</h3>
                        <div class="chart-container"><canvas id="focusTimeChart"></canvas></div>
                    </div>
                    <div class="chart-card">
                        <h3>${ICO.pieChart} Focus Mode</h3>
                        <div class="chart-container"><canvas id="focusModeChart"></canvas></div>
                    </div>
                </div>
            `}
        `;

        if (!empty) {
            renderFocusTimeChart(p.focusOverTime || []);
            renderFocusModeChart(p.modeDistribution || []);
        }
    }

    function renderFocusTimeChart(data) {
        destroyChart('focusTime');
        const ctx = document.getElementById('focusTimeChart');
        if (!ctx || !data.length) return;
        const grad = makeGradient(ctx, 'rgba(236,72,153,0.85)', 'rgba(236,72,153,0.25)', 220);
        charts.focusTime = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => new Date(d.date).toLocaleDateString('en-US',{month:'short',day:'numeric'})),
                datasets: [{
                    label: 'Minutes',
                    data: data.map(d => Math.round(d.total_minutes)),
                    backgroundColor: grad,
                    borderRadius: 4, borderWidth: 2, borderColor: isDark() ? 'rgba(255,255,255,0.15)' : '#0f172a', borderSkipped: false,
                }]
            },
            options: chartOptions({
                yLabel: 'min',
                animation: { delay: (ctx) => ctx.dataIndex * 40, duration: 600, easing: 'easeOutCubic' }
            })
        });
    }

    function renderFocusModeChart(modes) {
        destroyChart('focusMode');
        const ctx = document.getElementById('focusModeChart');
        if (!ctx || !modes.length) return;
        const modeColors = { gentle: '#059669', standard: '#4f46e5', challenge: '#e11d48' };
        charts.focusMode = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: modes.map(m => m.mode.charAt(0).toUpperCase() + m.mode.slice(1)),
                datasets: [{
                    data: modes.map(m => m.count),
                    backgroundColor: modes.map(m => modeColors[m.mode] || '#7C3AED'),
                    borderWidth: 3,
                    borderColor: isDark() ? '#1a1a2e' : '#fffdf7',
                    hoverOffset: 10,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '58%',
                animation: { animateRotate: true, animateScale: true, duration: 900, easing: 'easeOutBack' },
                plugins: {
                    legend: { position: 'bottom', labels: { color: legendColor(), font: { size: 11, family: "'Outfit', system-ui, sans-serif" }, padding: 12, usePointStyle: true, pointStyleWidth: 8 } },
                    tooltip: {
                        backgroundColor: isDark() ? '#1a1a2e' : '#fffdf7',
                        titleColor: isDark() ? '#fff' : '#1a1a2e',
                        bodyColor: isDark() ? '#ccc' : '#444',
                        borderColor: isDark() ? 'rgba(255,255,255,0.15)' : '#1a1a2e',
                        borderWidth: 2, cornerRadius: 4, padding: 10,
                        titleFont: { family: "'Funnel Display', Georgia, serif", weight: '700' },
                        bodyFont: { family: "'Outfit', system-ui, sans-serif" },
                    }
                }
            }
        });
    }

    // ── Shared chart options factory ───────────────────────
    function chartOptions({ yMax, suffix, yStepSize, yLabel, animation } = {}) {
        return {
            responsive: true, maintainAspectRatio: false,
            animation: animation ?? { duration: 700, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark() ? '#1a1a2e' : '#fffdf7',
                    titleColor: isDark() ? '#fff' : '#1a1a2e',
                    bodyColor: isDark() ? '#ccc' : '#444',
                    borderColor: isDark() ? 'rgba(255,255,255,0.15)' : '#1a1a2e',
                    borderWidth: 2,
                    cornerRadius: 4,
                    padding: 10,
                    titleFont: { family: "'Funnel Display', Georgia, serif", weight: '700' },
                    bodyFont: { family: "'Outfit', system-ui, sans-serif" },
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ...(yMax ? { max: yMax } : {}),
                    grid: { color: gridColor(), drawBorder: false, borderDash: [3, 3] },
                    ticks: {
                        color: tickColor(),
                        font: { family: "'Outfit', system-ui, sans-serif", size: 11 },
                        ...(yStepSize ? { stepSize: yStepSize } : {}),
                        ...(suffix ? { callback: v => v + suffix } : {}),
                        ...(yLabel ? { callback: v => v + yLabel } : {}),
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: tickColor(),
                        font: { family: "'Outfit', system-ui, sans-serif", size: 11 }
                    }
                }
            }
        };
    }

    function emptyState(msg) {
        return `<div class="empty-state">${ICO.inbox}<p>${msg}</p></div>`;
    }

    // ── Dark mode reactivity: rebuild charts ───────────────
    const darkObserver = new MutationObserver(() => {
        if (lastData) {
            renderProgressChart(lastData.progressOverTime);
            renderTopicsChart(lastData.topTopics);
            renderGoalChart(lastData.goalDistribution);
            if (lastData.quizStats?.totalAnswered) {
                renderQuizScoreChart(lastData.quizStats.scoreOverTime);
                renderQuizTypeChart(lastData.quizStats.byType);
            }
            if (lastData.pomodoroStats?.totalSessions) {
                renderFocusTimeChart(lastData.pomodoroStats.focusOverTime);
                renderFocusModeChart(lastData.pomodoroStats.modeDistribution);
            }
        }
    });
    darkObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });

    // ── Init ───────────────────────────────────────────────
    periodSelect.addEventListener('change', loadDashboard);
    loadDashboard();
})();
</script>
</body>
</html>
