<?php
session_start();

// Optional: If the user is not logged in, redirect back to login page
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FinSight – Dashboard</title>
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Font Awesome for Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <!-- Include D3.js for advanced visualizations -->
  <script src="https://d3js.org/d3.v7.min.js"></script>
  <!-- Include Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
  <!-- Include the Date Adapter for time scales -->
  <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
  <!-- Include additional Chart.js plugins -->
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
  <script src="https://cdn.jsdelivr.net/npm/hammerjs@2.0.8"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@1.2.1/dist/chartjs-plugin-zoom.min.js"></script>
  <style>
    /* Basic Reset */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      color: #333;
      background-color: #f8f9fa;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      line-height: 1.7;
      background-image: 
        radial-gradient(circle at 15% 15%, rgba(10, 37, 64, 0.02) 0%, transparent 60%),
        radial-gradient(circle at 85% 85%, rgba(0, 123, 255, 0.03) 0%, transparent 60%);
    }
    /* Header */
    header {
      display: flex;
      flex-direction: column;
      align-items: center;
      background: linear-gradient(120deg, #0A2540 0%, #103155 100%);
      color: #fff;
      padding: 1.5rem 2rem;
      position: relative;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      z-index: 100;
    }
    
    header::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, #007bff, #00c6ff);
    }
    
    header h1 {
      margin: 0;
      font-weight: 600;
      letter-spacing: 1px;
      margin-bottom: 0.75rem;
      text-align: center;
      position: relative;
      display: inline-block;
    }
    
    header h1::after {
      content: '';
      position: absolute;
      bottom: -5px;
      left: 50%;
      transform: translateX(-50%);
      width: 40px;
      height: 2px;
      background-color: rgba(255, 255, 255, 0.5);
    }
    
    .header-nav {
      display: flex;
      gap: 15px;
      justify-content: flex-end;
      align-items: center;
      width: 100%;
      margin-top: 0.5rem;
    }
    
    .header-nav a {
      text-decoration: none;
      background-color: rgba(255, 255, 255, 0.1);
      color: rgba(255, 255, 255, 0.85);
      padding: 0.5rem 1.25rem;
      border-radius: 6px;
      font-weight: 500;
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      display: flex;
      align-items: center;
    }
    
    .header-nav a i {
      margin-right: 0.5rem;
      font-size: 0.9rem;
      transition: transform 0.3s ease;
    }
    
    .header-nav a:hover {
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .header-nav a:hover i {
      transform: translateX(-2px);
    }
    
    .header-nav a.help-button {
      background: linear-gradient(90deg, #007bff, #0069d9);
      box-shadow: 0 4px 10px rgba(0, 123, 255, 0.2);
    }
    
    .header-nav a.help-button:hover {
      background: linear-gradient(90deg, #0062cc, #004494);
      box-shadow: 0 6px 15px rgba(0, 123, 255, 0.3);
    }
    
    /* Layout: Sidebar + Main Content */
    .layout { 
      display: flex;
      position: relative;
    }
    
    /* Sidebar */
    aside.sidebar {
      position: fixed;
      margin-top: 150px
      left: 0;
      width: 320px;
      height: calc(100vh - 150px);
      background-color: transparent;
      padding: 1.5rem;
      overflow-y: auto;
      scrollbar-width: thin;
      scrollbar-color: #ccc transparent;
    }
    
    aside.sidebar::-webkit-scrollbar {
      width: 6px;
    }
    
    aside.sidebar::-webkit-scrollbar-thumb {
      background-color: #ccc;
      border-radius: 3px;
    }
    
    aside.sidebar::-webkit-scrollbar-track {
      background-color: transparent;
    }
    
    .sidebar h2 {
      margin-bottom: 1rem;
      color: #0A2540;
      font-size: 1.25rem;
      font-weight: 600;
      position: relative;
      padding-bottom: 0.75rem;
      display: flex;
      align-items: center;
    }
    
    .sidebar h2::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 40px;
      height: 3px;
      background: linear-gradient(90deg, #007bff, #00c6ff);
    }
    
    .sidebar h2 i {
      margin-right: 0.5rem;
      color: #007bff;
    }
    
    .sidebar p {
      font-size: 0.9rem;
      color: #555;
      margin-bottom: 1.25rem;
    }
    
    /* Table in sidebar */
    .sidebar-table-container {
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
      overflow: hidden;
      border: 1px solid #E9ECEF;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .sidebar-table-container:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }
    
    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      background-color: #fff;
      border-radius: 10px;
      overflow: hidden;
    }
    
    table thead {
      background: linear-gradient(90deg, #F8F9FA 0%, #f2f5fa 100%);
    }
    
    table th, table td {
      text-align: left;
      padding: 1rem;
      border-bottom: 1px solid #E9ECEF;
      font-size: 0.9rem;
    }
    
    table thead th {
      font-weight: 600;
      text-transform: uppercase;
      color: #0A2540;
      letter-spacing: 0.5px;
      font-size: 0.75rem;
    }
    
    table tbody tr:last-child td {
      border-bottom: none;
    }
    
    table tbody tr {
      transition: all 0.2s ease;
      cursor: pointer;
    }
    
    table tbody tr:hover {
      background-color: #F8F9FA;
      transform: scale(1.01);
    }
    
    .topic-link {
      color: #007bff;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
    }
    
    .topic-link i {
      margin-right: 0.5rem;
      font-size: 0.8rem;
      opacity: 0;
      transform: translateX(-5px);
      transition: all 0.3s ease;
    }
    
    .topic-link:hover {
      color: #0056b3;
    }
    
    .topic-link:hover i {
      opacity: 1;
      transform: translateX(0);
    }
    
    /* Main Content */
    main.content {
      margin-left: 320px;
      padding: 2rem 2.5rem;
      flex: 1;
      min-height: calc(100vh - 150px);
    }
    
    /* Dashboard Controls */
    .dashboard-controls {
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background-color: #fff;
      padding: 1rem 1.5rem;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
      border: 1px solid #E9ECEF;
    }
    
    .dashboard-controls button {
      padding: 0.75rem 1.5rem;
      border: none;
      background: linear-gradient(90deg, #007bff 0%, #0069d9 100%);
      color: #fff;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px rgba(0, 123, 255, 0.2);
      display: flex;
      align-items: center;
    }
    
    .dashboard-controls button i {
      margin-right: 0.5rem;
    }
    
    .dashboard-controls button:hover {
      background: linear-gradient(90deg, #0062cc 0%, #004494 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(0, 123, 255, 0.3);
    }
    
    .dashboard-controls .last-update {
      color: #6c757d;
      font-size: 0.85rem;
      display: flex;
      align-items: center;
    }
    
    .dashboard-controls .last-update i {
      margin-right: 0.5rem;
      color: #007bff;
    }
    
    /* Stats Row */
    .stats-row {
      display: flex;
      gap: 1.5rem;
      margin-bottom: 2.5rem;
      flex-wrap: wrap;
    }
    
    .stat-card {
      flex: 1 1 200px;
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
      text-align: center;
      padding: 1.75rem;
      min-width: 200px;
      border: 1px solid #E9ECEF;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 5px;
      height: 100%;
      background: linear-gradient(180deg, #007bff 0%, #0056b3 100%);
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }
    
    .stat-card .stat-icon {
      position: absolute;
      top: 1rem;
      right: 1rem;
      font-size: 2.5rem;
      color: rgba(0, 123, 255, 0.1);
    }
    
    .stat-card h2 {
      font-size: 2.5rem;
      margin-bottom: 0.75rem;
      color: #007bff;
      font-weight: 700;
      position: relative;
      z-index: 1;
    }
    
    .stat-card p {
      color: #555;
      font-weight: 500;
      position: relative;
      z-index: 1;
    }
    
    /* Charts Section */
    .charts-section {
      display: flex;
      flex-direction: column;
      gap: 2.5rem;
      justify-content: center;
      align-items: center;
    }
    
    .chart-container {
      width: 100%;
      max-width: 1000px;
      height: 600px;
      background-color: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
      padding: 2rem;
      text-align: center;
      position: relative;
      margin-bottom: 1.5rem;
      border: 1px solid #E9ECEF;
      transition: all 0.3s ease;
    }
    
    .chart-container:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    }
    
    .chart-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 5px;
      background: linear-gradient(90deg, #007bff, #00c6ff);
      border-radius: 12px 12px 0 0;
    }
    
    .chart-container h3 {
      margin-bottom: 1rem;
      color: #0A2540;
      font-weight: 600;
      font-size: 1.25rem;
      position: relative;
      display: inline-block;
      padding-bottom: 0.75rem;
    }
    
    .chart-container h3::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 40px;
      height: 2px;
      background-color: rgba(0, 123, 255, 0.5);
    }
    
    .chart-canvas {
      width: 100% !important;
      height: 90% !important;
      margin-top: 1rem;
    }
    
    /* Footer */
    footer {
      background: linear-gradient(120deg, #0A2540 0%, #103155 100%);
      color: #ADB5BD;
      text-align: center;
      padding: 1.5rem;
      font-size: 0.9rem;
      position: relative;
    }
    
    footer::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    }
    
    /* Responsive Adjustments */
    @media (max-width: 992px) {
      aside.sidebar {
        width: 280px;
      }
      
      main.content {
        margin-left: 280px;
      }
    }
    
    @media (max-width: 768px) {
      .stats-row {
        flex-direction: column;
      }
      
      main.content {
        margin-left: 0;
        padding: 1.5rem;
      }
      
      aside.sidebar {
        position: static;
        width: 100%;
        height: auto;
        margin-bottom: 1.5rem;
        padding: 1rem;
      }
      
      .layout {
        flex-direction: column;
      }
      
      .chart-container {
        padding: 1.5rem;
        height: 500px;
      }
      
      .dashboard-controls {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
      }
      
      .dashboard-controls button {
        width: 100%;
      }
    }
    
    /* Word Cloud Styling */
    .word-cloud-container {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      align-items: center;
      text-align: center;
      padding: 20px;
      gap: 10px;
      position: relative;
      min-height: 400px;
      overflow: hidden;
      border-radius: 8px;
      background-color: rgba(242, 239, 231, 0.1);
    }
    
    .word-cloud-word {
      display: inline-block;
      transition: transform 0.2s ease;
      padding: 5px;
      position: absolute;
      white-space: nowrap;
      transform-origin: center center;
      z-index: 1;
    }
    
    .word-cloud-word:hover {
      transform: scale(1.1) !important;
      z-index: 10;
    }

    /* Positioning classes for word cloud */
    .word-center {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) !important;
      z-index: 5;
      font-weight: bold;
    }
    
    .word-inner {
      position: absolute;
      z-index: 4;
    }
    
    .word-middle {
      position: absolute;
      z-index: 3;
    }
    
    .word-outer {
      position: absolute;
      z-index: 2;
    }

    /* Heatmap Styling */
    .heatmap-container {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 5px;
      margin: 20px;
    }
    
    .heatmap-cell {
      padding: 15px;
      text-align: center;
      border-radius: 4px;
      position: relative;
      min-height: 100px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      transition: all 0.3s ease;
    }
    
    .heatmap-cell:hover {
      transform: scale(1.05);
    }
    
    .heatmap-date {
      font-size: 0.8rem;
      font-weight: bold;
      margin-bottom: 5px;
    }
    
    .heatmap-value {
      font-size: 1rem;
    }

/* ─── Chat Toggle Button ─── */
#chatbot-toggle {
  position: fixed;
  bottom: 24px;
  right: 24px;
  width: 56px;
  height: 56px;
  background: #007bff;
  color: #fff;
  border-radius: 50%;
  box-shadow: 0 4px 16px rgba(0,0,0,0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: transform 0.2s ease, background 0.2s ease;
  z-index: 1000;
}
#chatbot-toggle:hover {
  background: #0056b3;
  transform: scale(1.1);
}

/* ─── Widget Container ─── */
#chatbot-widget {
  position: fixed;
  bottom: 90px;    /* sits above the toggle */
  right: 24px;
  width: 500px;
  height: 680px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.25);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  opacity: 0;
  transform: translateY(20px);
  transition: opacity 0.3s ease, transform 0.3s ease;
  z-index: 999;
}
#chatbot-widget.expanded {
  opacity: 1;
  transform: translateY(0);
}

/* ─── Header ─── */
#chatbot-widget header {
  background: linear-gradient(120deg, #0A2540 0%, #103155 100%);
  color: #fff;
  padding: 0.5rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: relative
}
#chatbot-widget header h1 {
  font-size: 1rem;
  font-weight: 600;
  margin: 0;
}
#chatbot-widget header button {
  background: transparent;
  border: none;
  color: #fff;
  font-size: 1.1rem;
  cursor: pointer;
  transition: color 0.2s ease;
}
#chatbot-widget header button:hover {
  color: #ffdddd;
}

/* ─── Messages & Input ─── */
#chatbot-widget #chat-container {
  display: flex;
  flex-direction: column;
  flex: 1;
}
#chatbot-widget #messages {
  flex: 1;
  padding: 0.75rem;
  overflow-y: auto;
}
#chatbot-widget #chat-form {
  display: flex;
  border-top: 1px solid #e1e4e8;
}
#chatbot-widget #msg-input {
  flex: 1;
  border: none;
  padding: 0.6rem 0.8rem;
  font-size: 0.9rem;
}
#chatbot-widget #msg-input:focus {
  outline: none;
}
#chatbot-widget #send-btn {
  background: #0A2540;
  color: #fff;
  border: none;
  padding: 0 1.2rem;
  font-size: 0.9rem;
  cursor: pointer;
  transition: background 0.2s ease;
}
#chatbot-widget #send-btn:hover {
  background: #082038;
}

  </style>
</head>
<body>
  <!-- Header -->
  <header>
    <h1>FinSight Dashboard</h1>
    <div class="header-nav">
      <a href="customization.php"><i class="fas fa-sliders-h"></i> Customization</a>
      <a href="help.html" class="help-button"><i class="fas fa-question-circle"></i> Help</a>
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>
  <!-- Main Layout: Sidebar + Content -->
  <div class="layout">
    <!-- Sidebar (Fixed) -->
    <aside class="sidebar">
      <h2><i class="fas fa-fire"></i> Trending Topics</h2>
      <p>Explore the articles and a summary by clicking the topics below.</p>
      <div class="sidebar-table-container">
        <table>
          <thead>
            <tr>
              <th>Topics</th>
              <th># Articles </th>
            </tr>
          </thead>
          <tbody id="trending-topics-body">
            <!-- Dynamically populated -->
          </tbody>
        </table>
      </div>
    </aside>
    <!-- Main Content Area -->
    <main class="content">
      <!-- Dashboard Controls -->
      <div class="dashboard-controls">
        <button onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh Data</button>
        <span id="lastUpdate" class="last-update"><i class="far fa-clock"></i> Last updated: Loading...</span>
      </div>
      <!-- Stats Row -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-newspaper"></i></div>
          <h2 id="articles-tracked">0</h2>
          <p>Articles Tracked</p>
        </div>
      </div>
      <!-- Charts Section -->
      <div class="charts-section">
        <!-- Line Chart: Weekly Trend (only topics with ≥100 articles) -->
        <div class="chart-container" id="lineChartContainer">
          <h3>Weekly Topic Trends <span class="badge">≥ 10 Articles</span></h3>
          <canvas id="lineChart" class="chart-canvas"></canvas>
        </div>
        
        <!-- Pie Chart: Top 10 Topics Distribution -->
        <div class="chart-container" id="pieChartContainer">
          <h3>Top 10 Topics Distribution</h3>
          <canvas id="pieChart" class="chart-canvas"></canvas>
        </div>
        
        <!-- Word Cloud: Top 30 Trending Words -->
        <div class="chart-container" id="wordChartContainer">
          <h3>Top 30 Trending Words Cloud</h3>
          <canvas id="wordChart" class="chart-canvas"></canvas>
        </div>
        
        <!-- Topic Distribution Over Time (Replaced Violin plot) -->
        <div class="chart-container" id="topicDistributionContainer">
          <h3>Topic Distribution Over Time</h3>
          <canvas id="topicDistributionChart" class="chart-canvas"></canvas>
        </div>
        
        <!-- Clustered Bar Chart: Top 3 Topics Per Day -->
        <div class="chart-container" id="clusteredChartContainer">
          <h3>Top 3 Topics Per Day</h3>
          <canvas id="clusteredChart" class="chart-canvas"></canvas>
        </div>
        
        <!-- Calendar Heat Map: Daily Activity -->
        <div class="chart-container" id="heatmapChartContainer">
          <h3>Calendar Heat Map (Topic Activity)</h3>
          <canvas id="heatmapChart" class="chart-canvas"></canvas>
        </div>
        
        <!-- Scatter Plot: Topic Hotness -->
        <div class="chart-container" id="scatterChartContainer">
          <h3>Topic Hotness Distribution</h3>
          <canvas id="scatterChart" class="chart-canvas"></canvas>
        </div>
        
        <!-- Bar Chart: Daily Top Topic -->
        <div class="chart-container" id="barChartContainer">
          <h3>Daily Top Topic</h3>
          <canvas id="barChart" class="chart-canvas"></canvas>
        </div>
      </div>
    </main>
  </div>
  <!-- Footer -->
  <footer>
    <div class="footer-copyright">
      © 2025 FinSight Technologies. All rights reserved.
    </div>
  </footer>
  
  <script>
    // Register the datalabels plugin
    const ChartDataLabels = window.ChartDataLabels;
    Chart.register(ChartDataLabels);
    
    // Custom color palette based on client requirements
    const colorPalette = ['#006A71', '#48A6A7', '#9ACBD0', '#F2EFE7'];
    
    // Helper: generate a color from our palette with variation
    function getCustomColor(index) {
      const baseColor = colorPalette[index % colorPalette.length];
      return baseColor;
    }

    function getColorFromWhiteToBlack(intensity) {
      // intensity should be between 0 (white) and 1 (black)
      const r = Math.floor(255 * (1 - intensity));
      const g = Math.floor(255 * (1 - intensity));
      const b = Math.floor(255 * (1 - intensity));
      return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
    }
    
    // Helper: generate a variation of a color
    function getColorVariation(baseColor, variation) {
      // Parse the hex color to RGB
      const r = parseInt(baseColor.slice(1, 3), 16);
      const g = parseInt(baseColor.slice(3, 5), 16);
      const b = parseInt(baseColor.slice(5, 7), 16);
      
      // Adjust brightness based on variation (positive = lighter, negative = darker)
      const adjust = (color, amount) => {
        return Math.max(0, Math.min(255, Math.round(color + amount * 255)));
      };
      
      // Create new RGB values
      const newR = adjust(r, variation);
      const newG = adjust(g, variation);
      const newB = adjust(b, variation);
      
      // Convert back to hex
      return `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`;
    }
    
    // Generate an array of colors from our palette with variations
    function generatePaletteColors(count) {
      const colors = [];
      const baseColors = colorPalette;
      
      if (count <= baseColors.length) {
        return baseColors.slice(0, count);
      }
      
      // Add base colors
      colors.push(...baseColors);
      
      // Generate variations if we need more colors
      let variation = -0.2; // Start with slightly darker
      let baseIndex = 0;
      
      while (colors.length < count) {
        const baseColor = baseColors[baseIndex % baseColors.length];
        colors.push(getColorVariation(baseColor, variation));
        
        baseIndex++;
        if (baseIndex % baseColors.length === 0) {
          variation += 0.1; // Adjust variation for next round
        }
      }
      
      return colors;
    }
    
    // Helper: generate a random color (for line chart series)
    function getRandomColor() {
      return colorPalette[Math.floor(Math.random() * colorPalette.length)];
    }

    // Helper: Generate a color palette with N colors
    function generateColorPalette(n) {
      return generatePaletteColors(n);
    }

    // Update Line Chart:
    // topicTrends is an object { topic: [count_day1, ..., count_day7], ... }
    // daysLabels is an array of 7 day strings.
    function updateLineChart(daysLabels, topicTrends) {
      const datasets = [];
      const benchmark = 10;
      let colorIndex = 0;
      
      Object.keys(topicTrends).forEach(topic => {
        const counts = topicTrends[topic];
        const total = counts.reduce((sum, count) => sum + count, 0);
        if (total >= benchmark) {
          datasets.push({
            label: topic,
            data: counts,
            borderColor: getCustomColor(colorIndex),
            backgroundColor: 'rgba(0,0,0,0)',
            tension: 0.3,
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 6
          });
          colorIndex++;
        }
      });
      if (datasets.length === 0) {
        document.getElementById('lineChartContainer').innerHTML = "<p class='no-data'>No topics meet the benchmark of " + benchmark + " articles.</p>";
        return;
      }
      const ctxLine = document.getElementById('lineChart').getContext('2d');
      new Chart(ctxLine, {
        type: 'line',
        data: {
          labels: daysLabels,
          datasets: datasets
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { 
              beginAtZero: true, 
              grid: {
                color: 'rgba(0, 0, 0, 0.05)'
              },
              title: { 
                display: true, 
                text: '# Articles',
                font: {
                  weight: '600'
                }
              } 
            },
            x: { 
              grid: {
                color: 'rgba(0, 0, 0, 0.05)'
              },
              title: { 
                display: true, 
                text: 'Day',
                font: {
                  weight: '600'
                }
              } 
            }
          },
          plugins: {
            legend: {
              position: 'top',
              labels: {
                boxWidth: 15,
                usePointStyle: true,
                pointStyle: 'circle'
              }
            }
          }
        }
      });
    }

    // Update Pie Chart:
    // Uses pie_chart data (object with topic:count pairs)
    function updatePieChart(pieData) {
      const topics = [];
      const counts = [];
      const topN = 10; // Show top 10 topics
      
      let counter = 0;
      for (const [topic, count] of Object.entries(pieData)) {
        if (counter < topN) {
          topics.push(topic);
          counts.push(count);
          counter++;
        } else {
          break;
        }
      }
      
      const colors = generatePaletteColors(topics.length);
      
      const ctxPie = document.getElementById('pieChart').getContext('2d');
      new Chart(ctxPie, {
        type: 'pie',
        data: {
          labels: topics,
          datasets: [{
            data: counts,
            backgroundColor: colors,
            borderColor: '#fff',
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right',
              labels: {
                boxWidth: 15,
                padding: 15
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.parsed || 0;
                  const total = context.dataset.data.reduce((sum, val) => sum + val, 0);
                  const percentage = Math.round((value / total) * 100);
                  return `${label}: ${value} articles (${percentage}%)`;
                }
              }
            }
          }
        }
      });
    }

    function updateWordCloudChart(wordData) {
      const words = [];
      const maxFontSize = 26; // Reduced max font size
      const minFontSize = 10; // Reduced min font size
      
      // Convert word data to array for sorting
      const wordArray = Object.entries(wordData).map(([text, value]) => ({ text, value }));
      
      // Sort by frequency (highest first)
      wordArray.sort((a, b) => b.value - a.value);
      
      // Limit to the top 25 words to prevent overcrowding
      const limitedWords = wordArray.slice(0, 25);
      
      // Get the maximum count to scale font sizes
      const maxCount = limitedWords.length > 0 ? limitedWords[0].value : 0;
      
      // Convert to format needed for the word cloud
      limitedWords.forEach(({ text, value }) => {
        // Scale font size based on count relative to max count
        const fontSize = Math.max(
          minFontSize, 
          Math.floor((value / maxCount) * maxFontSize)
        );
        
        // Determine color based on frequency (darker for higher frequency)
        const colorIndex = Math.floor((value / maxCount) * (colorPalette.length - 1)); // Corrected color index
        const baseColor = colorPalette[colorIndex]; // Use darker colors for higher frequency
        
        words.push({
          text,
          value,
          fontSize,
          color: baseColor,
          rotation: Math.random() > 0.8 ? 90 : 0, // Make most words horizontal (80%) for better readability
          width: 0,  // Will be calculated after element creation
          height: 0  // Will be calculated after element creation
        });
      });
      
      // Render using HTML div elements
      const container = document.getElementById('wordChartContainer');
      container.innerHTML = '<h3>Top 30 Trending Words Cloud</h3><div class="word-cloud-container"></div>';
      const cloudContainer = container.querySelector('.word-cloud-container');
      
      // Calculate container dimensions
      const containerWidth = cloudContainer.offsetWidth || 600;
      const containerHeight = 350; // Fixed height
      cloudContainer.style.height = `${containerHeight}px`;
      
      // Center point of the container
      const centerX = containerWidth / 2;
      const centerY = containerHeight / 2;
      
      // Create invisible elements to calculate dimensions
      const invisibleContainer = document.createElement('div');
      invisibleContainer.style.position = 'absolute';
      invisibleContainer.style.visibility = 'hidden';
      invisibleContainer.style.pointerEvents = 'none';
      document.body.appendChild(invisibleContainer);
      
      // Create elements to measure dimensions
      words.forEach(word => {
        const el = document.createElement('div');
        el.textContent = word.text;
        el.style.fontSize = `${word.fontSize}px`;
        el.style.position = 'absolute';
        el.style.whiteSpace = 'nowrap';
        el.style.transform = `rotate(${word.rotation}deg)`;
        invisibleContainer.appendChild(el);
        
        // Get element dimensions
        const rect = el.getBoundingClientRect();
        word.width = rect.width;
        word.height = rect.height;
      });
      
      // Remove the measuring container
      document.body.removeChild(invisibleContainer);
      
      // Calculate bounds constraints (leave margin)
      const margin = 10;
      const minX = margin;
      const maxX = containerWidth - margin;
      const minY = margin;
      const maxY = containerHeight - margin;
      
      // Function to check if a word position would overlap with existing words
      function checkOverlap(word, x, y, placedWords) {
        // Calculate bounding box considering rotation
        const padding = 4; // Reduced padding between words
        let width = word.width + padding * 2;
        let height = word.height + padding * 2;
        
        // If rotated, swap dimensions
        if (word.rotation === 90) {
          [width, height] = [height, width];
        }
        
        // Half dimensions for calculation
        const halfWidth = width / 2;
        const halfHeight = height / 2;
        
        // Box bounds
        const left = x - halfWidth;
        const right = x + halfWidth;
        const top = y - halfHeight;
        const bottom = y + halfHeight;
        
        // Check if outside container bounds (with margin)
        if (left < minX || right > maxX || top < minY || bottom > maxY) {
          return true; // Outside bounds
        }
        
        // Check against all placed words
        for (const placed of placedWords) {
          const pWidth = placed.width + padding * 2;
          const pHeight = placed.height + padding * 2;
          
          // Half dimensions for calculation
          const pHalfWidth = pWidth / 2;
          const pHalfHeight = pHeight / 2;
          
          // Box bounds for placed word
          const pLeft = placed.x - pHalfWidth;
          const pRight = placed.x + pHalfWidth;
          const pTop = placed.y - pHalfHeight;
          const pBottom = placed.y + pHalfHeight;
          
          // Check for intersection
          if (right > pLeft && left < pRight && bottom > pTop && top < pBottom) {
            return true; // Overlap detected
          }
        }
        
        return false; // No overlap
      }
      
      // Place words without overlap
      const placedWords = [];
      
      // Place the most important word in the center
      if (words.length > 0) {
        const centerWord = words[0];
        centerWord.x = centerX;
        centerWord.y = centerY;
        placedWords.push(centerWord);
      }
      
      // Place remaining words using a more compact spiral pattern
      const spiral = function(t) {
        // Archimedean spiral: r = a + b*theta
        // This creates a tighter spiral for better word packing
        const a = 0;
        const b = 5;
        const theta = t * 0.8; // Slower spiral progression
        const r = a + b * theta;
        return [
          r * Math.cos(theta),
          r * Math.sin(theta)
        ];
      };
      
      // Place remaining words
      for (let i = 1; i < words.length; i++) {
        const word = words[i];
        const frequencyRatio = word.value / maxCount;
        // Determine initial distance based on frequency
        // Higher frequency words should be closer to center
        let initialTValue = 1 + (1 - frequencyRatio) * 5;
        let tIncrement = 0.1; // Smaller increment for smoother spiral
        let maxAttempts = 300; // More attempts for better placement
        let attempts = 0;
        let placed = false;
        // Try to place the word along the spiral
        while (!placed && attempts < maxAttempts) {
          const t = initialTValue + attempts * tIncrement;
          const [offsetX, offsetY] = spiral(t);
          // Calculate position
          const x = centerX + offsetX;
          const y = centerY + offsetY;
          // Check for overlaps
          if (!checkOverlap(word, x, y, placedWords)) {
            word.x = x;
            word.y = y;
            placedWords.push(word);
            placed = true;
          }
          attempts++;
        }
        // If we can't place the word after all attempts, skip it
        if (!placed) {
          console.log(`Could not place word: ${word.text}`);
          continue;
        }
      }
      
      // Create the final word cloud with placed words
      placedWords.forEach((word, index) => {
        const wordEl = document.createElement('div');
        wordEl.className = 'word-cloud-word';
        wordEl.textContent = word.text;
        wordEl.style.fontSize = `${word.fontSize}px`;
        wordEl.style.color = word.color;
        // Apply calculated position
        wordEl.style.left = `${word.x}px`;
        wordEl.style.top = `${word.y}px`;
        wordEl.style.transform = `translate(-50%, -50%) rotate(${word.rotation}deg)`;
        // Add position class for z-index control
        if (index === 0) {
          wordEl.classList.add('word-center');
        } else {
          const frequencyRatio = word.value / maxCount;
          if (frequencyRatio > 0.7) {
            wordEl.classList.add('word-inner');
          } else if (frequencyRatio > 0.4) {
            wordEl.classList.add('word-middle');
          } else {
            wordEl.classList.add('word-outer');
          }
        }
        cloudContainer.appendChild(wordEl);
      });
    }

    // Update Topic Distribution Over Time
    function updateTopicDistributionChart(daysLabels, topicTrends) {
      // Take top 5 topics based on total count
      const topicTotals = {};
      Object.keys(topicTrends).forEach(topic => {
        topicTotals[topic] = topicTrends[topic].reduce((sum, count) => sum + count, 0);
      });
      
      // Sort topics by total count
      const sortedTopics = Object.keys(topicTotals).sort((a, b) => topicTotals[b] - topicTotals[a]).slice(0, 5);
      
      // Prepare datasets for each top topic
      const datasets = sortedTopics.map((topic, index) => {
        const color = getCustomColor(index);
        return {
          label: topic,
          data: topicTrends[topic],
          backgroundColor: color + '40', // Add transparency
          borderColor: color,
          fill: true,
          tension: 0.4
        };
      });
      
      const ctxDistribution = document.getElementById('topicDistributionChart').getContext('2d');
      new Chart(ctxDistribution, {
        type: 'line',
        data: {
          labels: daysLabels,
          datasets: datasets
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              stacked: false,
              title: {
                display: true,
                text: 'Article Count'
              }
            },
            x: {
              title: {
                display: true,
                text: 'Day'
              }
            }
          },
          elements: {
            line: {
              fill: true
            }
          },
          plugins: {
            tooltip: {
              mode: 'index',
              intersect: false
            },
            legend: {
              position: 'top'
            },
            zoom: {
              zoom: {
                wheel: {
                  enabled: true
                },
                pinch: {
                  enabled: true
                },
                mode: 'xy'
              },
              pan: {
                enabled: true,
                mode: 'xy'
              }
            }
          }
        }
      });
    }

    // Update Clustered Bar Chart:
    // Uses clustered_bar data with top 3 topics per day
    function updateClusteredBarChart(clusteredData) {
      const days = clusteredData.map(item => item.day);
      
      // Prepare datasets - one for each position (1st, 2nd, 3rd)
      const datasets = [
        { label: 'Top Topic', backgroundColor: colorPalette[0], data: [] },
        { label: '2nd Topic', backgroundColor: colorPalette[1], data: [] },
        { label: '3rd Topic', backgroundColor: colorPalette[2], data: [] }
      ];
      
      // Prepare tooltip labels for each day/position
      const topicLabels = days.map(() => ['', '', '']);
      
      // Fill the datasets with count values
      clusteredData.forEach((dayData, dayIndex) => {
        dayData.topics.forEach((topic, position) => {
          datasets[position].data.push(topic.count);
          topicLabels[dayIndex][position] = topic.topic;
        });
      });
      
      const ctxClustered = document.getElementById('clusteredChart').getContext('2d');
      new Chart(ctxClustered, {
        type: 'bar',
        data: {
          labels: days,
          datasets: datasets
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            x: {
              title: {
                display: true,
                text: 'Day of Week'
              }
            },
            y: {
              beginAtZero: true,
              title: {
                display: true,
                text: 'Article Count'
              }
            }
          },
          plugins: {
            tooltip: {
              callbacks: {
                title: function(context) {
                  return context[0].label;
                },
                label: function(context) {
                  const dayIndex = context.dataIndex;
                  const position = context.datasetIndex;
                  const topic = topicLabels[dayIndex][position];
                  const count = context.parsed.y;
                  
                  return `${datasets[position].label} (${topic}): ${count} articles`;
                }
              }
            },
            datalabels: {
              color: '#fff',
              font: {
                weight: 'bold'
              },
              formatter: function(value) {
                return value > 0 ? value : '';
              }
            }
          }
        },
        plugins: [ChartDataLabels]
      });
    }

    // Update Heatmap Chart
    function updateHeatmapChart(heatmapData, daysLabels) {
      const container = document.getElementById('heatmapChartContainer');
      container.innerHTML = '<h3>Calendar Heat Map (Topic Activity)</h3><div class="heatmap-container"></div>';
      const heatmapContainer = container.querySelector('.heatmap-container');
      // Style the container
      heatmapContainer.style.display = 'grid';
      heatmapContainer.style.gridTemplateColumns = 'repeat(7, 1fr)';
      heatmapContainer.style.gap = '5px';
      heatmapContainer.style.padding = '20px';
      // Get max count for color scaling
      const maxCount = Math.max(...Object.values(heatmapData));
      // Create cells for each day
      daysLabels.forEach(dateStr => {
        const count = heatmapData[dateStr] || 0;
        const cell = document.createElement('div');
        cell.className = 'heatmap-cell';
        // Calculate color based on intensity using the same color palette as other charts
        const intensity = count / maxCount;
        // Reverse intensity for darker colors for higher values
        const colorIndex = Math.min(colorPalette.length - 1, 
                                  Math.floor((1 - intensity) * colorPalette.length));
        const baseColor = colorPalette[colorIndex];
        cell.style.backgroundColor = baseColor;
        cell.style.color = '#fff'; // Always white text for better contrast
        cell.style.padding = '10px';
        cell.style.borderRadius = '4px';
        cell.style.textAlign = 'center';
        cell.style.transition = 'transform 0.2s ease';
        // Format date
        const date = new Date(dateStr);
        const dayName = date.toLocaleDateString('en-US', { weekday: 'short' });
        const dayNum = date.getDate();
        cell.innerHTML = `
          <div style="font-size: 0.8rem; margin-bottom: 5px;">${dayName}</div>
          <div style="font-weight: bold;">${dayNum}</div>
          <div style="font-size: 0.8rem;">${count} articles</div>
        `;
        cell.addEventListener('mouseover', () => {
          cell.style.transform = 'scale(1.05)';
        });
        cell.addEventListener('mouseout', () => {
          cell.style.transform = 'scale(1)';
        });
        heatmapContainer.appendChild(cell);
      });
    }

    // Update Scatter Chart (Topic Hotness)
    function updateScatterChart(scatterData) {
      const container = document.getElementById('scatterChartContainer');
      container.innerHTML = '<h3>Topic Hotness Distribution</h3><div class="scatter-grid"></div>';
      const grid = container.querySelector('.scatter-grid');
      // Style the grid
      grid.style.display = 'grid';
      grid.style.gridTemplateColumns = 'repeat(5, 1fr)';
      grid.style.gridTemplateRows = 'repeat(5, 1fr)'; // Changed to 5x5 for 25 topics
      grid.style.gap = '10px';
      grid.style.padding = '20px';
      grid.style.height = '500px';
      // Get max count for color scaling
      const maxCount = Math.max(...scatterData.map(item => item.count));
      // Create grid items
      scatterData.forEach((item, index) => {
        const cell = document.createElement('div');
        cell.className = 'scatter-cell';
        // Calculate color based on intensity using the same color palette as other charts
        const intensity = item.count / maxCount;
        // Reverse intensity for darker colors for higher values
        const colorIndex = Math.min(colorPalette.length - 1, 
                                  Math.floor((1 - intensity) * colorPalette.length));
        const baseColor = colorPalette[colorIndex];
        cell.style.backgroundColor = baseColor;
        cell.style.color = '#fff'; // Always white text for better contrast
        cell.style.padding = '10px';
        cell.style.borderRadius = '4px';
        cell.style.display = 'flex';
        cell.style.flexDirection = 'column';
        cell.style.justifyContent = 'center';
        cell.style.alignItems = 'center';
        cell.style.textAlign = 'center';
        cell.style.fontSize = '0.9rem';
        cell.style.transition = 'transform 0.2s ease';
        cell.innerHTML = `
          <div style="font-weight: bold; margin-bottom: 5px;">${item.topic}</div>
          <div>${item.count} articles</div>
        `;
        cell.addEventListener('mouseover', () => {
          cell.style.transform = 'scale(1.05)';
        });
        cell.addEventListener('mouseout', () => {
          cell.style.transform = 'scale(1)';
        });
        grid.appendChild(cell);
      });
    }

    // Update Bar Chart:
    // Uses daily_top_topic data (an array of objects: {day, topic, count})
    function updateBarChart(labels, data, topTopics) {
      // Find the max count to determine color intensity
      const maxCount = Math.max(...data);
      
      // Create color array based on intensity (higher value = darker color)
      const barColors = data.map(count => {
        const intensity = count / maxCount;
        // Use reverse index to get darker colors for higher values
        const colorIndex = Math.min(colorPalette.length - 1, 
                                  Math.floor((1 - intensity) * colorPalette.length));
        return colorPalette[colorIndex];
      });
      
      const ctxBar = document.getElementById('barChart').getContext('2d');
      new Chart(ctxBar, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Daily Top Topic',
            data: data,
            backgroundColor: barColors
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true, title: { display: true, text: '# Articles' } },
            x: { title: { display: true, text: 'Day' } }
          },
          plugins: {
            tooltip: {
              callbacks: {
                label: function(context) {
                  let index = context.dataIndex;
                  let topic = topTopics[index] || '';
                  return topic + ': ' + context.parsed.y + ' articles';
                }
              }
            },
            legend: { display: false }
          }
        }
      });
    }

    document.addEventListener("DOMContentLoaded", function () {
      fetch('get_dashboard_data.php')
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(data => {
          console.log('Fetched Data:', data);
          // Validate data structure
          if (!data || !Array.isArray(data.trending_topics) || typeof data.total_articles !== 'number') {
            throw new Error('Invalid data structure');
          }
          // Update Sidebar Trending Topics
          const tbody = document.querySelector('#trending-topics-body');
          tbody.innerHTML = data.trending_topics.map(topic => `
            <tr>
              <td><a href="trending_topics.html?topic=${encodeURIComponent(topic.Topic)}" class="topic-link">${topic.Topic}</a></td>
              <td>${topic.article_count}</td>
            </tr>
          `).join('');
          // Update Articles Count and Last Update Time
          document.getElementById('articles-tracked').textContent = data.total_articles;
          document.getElementById('lastUpdate').textContent = 'Last updated: ' + data.last_update_time;
          
          // Retrieve chart data from backend
          const daysLabels = data.chart_data.days_labels;           // Array of 7 day strings
          const topicTrends = data.chart_data.topic_trends;         // Object: { topic: [count_day1,...,count_day7], ... }
          const dailyTop = data.chart_data.daily_top_topic;         // Array of objects: { day, topic, count }
          const pieData = data.chart_data.pie_chart;                // Object: { topic: count, ... }
          const wordData = data.chart_data.word_cloud;              // Object: { word: count, ... }
          const clusteredData = data.chart_data.clustered_bar;      // Array: [{ day, topics: [{topic, count}, ...] }, ...]
          const heatmapData = data.chart_data.heatmap;              // Object: { date: count, ... }
          
          // Prepare bar chart arrays
          const barLabels = dailyTop.map(item => item.day);
          const barData = dailyTop.map(item => item.count);
          const topTopics = dailyTop.map(item => item.topic);
          
          try {
            // Render charts with error handling
            updateLineChart(daysLabels, topicTrends);
            updateScatterChart(data.chart_data.scatter_data);
            updateBarChart(barLabels, barData, topTopics);
            updatePieChart(pieData);
            updateWordCloudChart(wordData);
            updateTopicDistributionChart(data.chart_data.monthly_dates, data.chart_data.monthly_trends);
            updateClusteredBarChart(clusteredData);
            updateHeatmapChart(heatmapData, data.chart_data.monthly_dates);
          } catch(e) {
            console.error("Error rendering charts:", e);
          }
          
          // Check user preferences for visible chart types, if provided
          if (data.user_preferences && data.user_preferences.visible_chart_types) {
            const visibleCharts = data.user_preferences.visible_chart_types.split(',').map(item => item.trim().toLowerCase());
            
            // Set display property for each chart container based on user preferences
            const chartTypes = [
              { type: 'line', id: 'lineChartContainer' },
              { type: 'scatter', id: 'scatterChartContainer' },
              { type: 'bar', id: 'barChartContainer' },
              { type: 'pie', id: 'pieChartContainer' },
              { type: 'word', id: 'wordChartContainer' },
              { type: 'violin', id: 'topicDistributionContainer' },
              { type: 'clustered', id: 'clusteredChartContainer' },
              { type: 'heatmap', id: 'heatmapChartContainer' }
            ];
            
            chartTypes.forEach(chart => {
              if (!visibleCharts.includes(chart.type)) {
                document.getElementById(chart.id).style.display = 'none';
              }
            });
          }
        })
        .catch(error => {
          console.error('Error fetching dashboard data:', error);
          alert('Failed to load dashboard data. Please try again.');
        });
    });
  </script>
<!-- Chat Toggle Button -->
<div id="chatbot-toggle" title="Chat with FinSight">
  <i class="fas fa-comments fa-lg"></i>
</div>

<!-- Chatbot Widget -->
<div id="chatbot-widget">
  <header>
    <h1>FinSight Chatbot</h1>
    <button id="chatbot-close" aria-label="Close chat"><i class="fas fa-times"></i></button>
  </header>
  <?php include __DIR__ . '/chatbot_ui.html'; ?>
</div>

<script>
  const toggleBtn = document.getElementById('chatbot-toggle');
  const widget   = document.getElementById('chatbot-widget');
  const closeBtn = document.getElementById('chatbot-close');

  toggleBtn.addEventListener('click', () => {
    widget.classList.toggle('expanded');
  });
  closeBtn.addEventListener('click', () => {
    widget.classList.remove('expanded');
  });
</script>


</body>
</html>
