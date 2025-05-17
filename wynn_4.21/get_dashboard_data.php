<?php
session_start();
header('Content-Type: application/json');

// --- Database credentials ---
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "wynn_fyp";

// --- Read optional days parameter, clamp 1–30 ---
$days_param = isset($_GET['days']) 
    ? max(1, min(30, (int)$_GET['days'])) 
    : 7;

$days_to_track       = $days_param;
$article_window_days = $days_param;
$top_n_topics        = isset($_GET['top_n']) 
    ? (int)$_GET['top_n'] 
    : 10;
$top_word_cloud      = 30;

// --- Connect ---
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error'=>'DB connect failed: '.$conn->connect_error]));
}
$conn->set_charset("utf8mb4");

// --- 1) Count total articles ---
$total_cutoff = date('Y-m-d H:i:s', strtotime("-{$article_window_days} days"));
$stmt = $conn->prepare("
  SELECT COUNT(*) AS cnt
    FROM topics_file 
   WHERE Created_Time >= ?
");
$stmt->bind_param("s", $total_cutoff);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$totalArticles = (int)($row['cnt'] ?? 0);
$stmt->close();

// --- 2) Prepare date ranges for different charts ---
$weekly_dates = [];
$monthly_dates = [];
for ($i = 0; $i < 7; $i++) {
    $weekly_dates[] = date('Y-m-d', strtotime("-{$i} days"));
}
for ($i = 0; $i < 28; $i++) {
    $monthly_dates[] = date('Y-m-d', strtotime("-{$i} days"));
}
$weekly_dates = array_reverse($weekly_dates);
$monthly_dates = array_reverse($monthly_dates);

// --- 3) Build topic counts (unique per article) ---
$date_cutoff = date('Y-m-d H:i:s', strtotime("-30 days")); // Get 30 days of data
$sql = "
  SELECT Topic_ID, Content, DATE(Created_Time) AS article_date
    FROM topics_file
   WHERE Created_Time >= ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date_cutoff);
$stmt->execute();
$result = $stmt->get_result();

$topic_article_ids       = [];
$daily_topic_article_ids = [];
while ($r = $result->fetch_assoc()) {
    $id   = $r['Topic_ID'];
    $d    = $r['article_date'];
    $keys = explode(',', $r['Content'] ?? '');
    foreach ($keys as $kw) {
        $t = trim($kw);
        if ($t === '') continue;
        $topic_article_ids[$t][$id] = true;
        $daily_topic_article_ids[$d][$t][$id] = true;
    }
}
$stmt->close();

// --- 4) Count uniques ---
$all_topic_counts   = [];
$daily_topic_counts = [];
foreach ($topic_article_ids as $t => $ids) {
    $all_topic_counts[$t] = count($ids);
}
foreach ($daily_topic_article_ids as $d => $topics) {
    foreach ($topics as $t => $ids) {
        $daily_topic_counts[$d][$t] = count($ids);
    }
}

// --- 5) Top N topics ---
arsort($all_topic_counts);
$top_topics = array_slice(array_keys($all_topic_counts), 0, $top_n_topics);
$top_25_topics = array_slice(array_keys($all_topic_counts), 0, 25);

// --- 6) Sidebar counts (SQL method) ---
$sidebar_topics_output = [];
foreach ($top_topics as $t) {
    // count all rows matching this topic in the exact days window
    $stmt2 = $conn->prepare("
      SELECT COUNT(*) AS cnt
        FROM topics_file
       WHERE Content LIKE ?
         AND Created_Time >= ?
    ");
    $like = "%{$t}%";
    $stmt2->bind_param("ss", $like, $total_cutoff);
    $stmt2->execute();
    $cnt = (int) $stmt2->get_result()->fetch_assoc()['cnt'];
    $stmt2->close();

    $sidebar_topics_output[] = [
        'Topic'         => $t,
        'article_count' => $cnt
    ];
}

// sort descending by count
usort($sidebar_topics_output, function($a, $b) {
    return $b['article_count'] <=> $a['article_count'];
});


// --- 7) Trend data ---
$topic_trends_output = [];
foreach ($top_topics as $t) {
    $topic_trends_output[$t] = array_fill(0, 7, 0);
}
$days_labels = [];
foreach ($weekly_dates as $i => $d) {
    $days_labels[] = $d;
    if (isset($daily_topic_counts[$d])) {
        foreach ($top_topics as $t) {
            $topic_trends_output[$t][$i] = $daily_topic_counts[$d][$t] ?? 0;
        }
    }
}

// --- 8. Prepare daily‐top and clustered‐bar data ---
$daily_top_topic_output = [];
$daily_top_three_topics = [];
foreach ($weekly_dates as $d) {
    $counts = $daily_topic_counts[$d] ?? [];
    arsort($counts);
    $top3 = array_slice($counts, 0, 3, true);

    // pad to 3 entries if needed
    while (count($top3) < 3) {
        $top3[] = 0;
    }
    $keys = array_keys($top3);

    // single top for bar chart
    $daily_top_topic_output[] = [
        'day'   => date('D', strtotime($d)),
        'topic' => $keys[0],
        'count' => $top3[$keys[0]]
    ];

    // clustered bar data
    $cluster = [];
    foreach ($keys as $k) {
        $cluster[] = ['topic' => $k, 'count' => $top3[$k]];
    }
    $daily_top_three_topics[] = [
        'day'    => date('D', strtotime($d)),
        'date'   => $d,
        'topics' => $cluster
    ];
}

// --- 9) Prepare pie‐chart & word‐cloud data ---
$pie_chart_data  = array_slice($all_topic_counts, 0, $top_n_topics, true);
$word_cloud_data = array_slice($all_topic_counts, 0, $top_word_cloud, true);

// --- 10) Prepare heatmap data ---
$heatmap_data = [];
foreach ($monthly_dates as $d) {
    $heatmap_data[$d] = array_sum($daily_topic_counts[$d] ?? []);
}

// --- 11) Prepare monthly topic distribution data ---
$monthly_topic_trends = [];
foreach ($top_topics as $t) {
    $monthly_topic_trends[$t] = array_fill(0, 30, 0);
}
foreach ($monthly_dates as $i => $d) {
    if (isset($daily_topic_counts[$d])) {
        foreach ($top_topics as $t) {
            $monthly_topic_trends[$t][$i] = $daily_topic_counts[$d][$t] ?? 0;
        }
    }
}

// --- 12) Prepare scatter plot data ---
$scatter_data = [];
foreach ($top_25_topics as $t) {
    $total_count = $all_topic_counts[$t];
    $scatter_data[] = [
        'topic' => $t,
        'count' => $total_count
    ];
}

// --- 13) Fetch user preferences (optional) ---
$user_preferences = null;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $pstmt = $conn->prepare("
      SELECT visible_chart_types
        FROM user_dashboard_preferences
       WHERE User_ID = ?
    ");
    $pstmt->bind_param("i", $uid);
    $pstmt->execute();
    $pref = $pstmt->get_result()->fetch_assoc();
    $pstmt->close();
    if ($pref) {
        $user_preferences = ['visible_chart_types' => $pref['visible_chart_types']];
    }
}

// --- 14) Output JSON ---
$output = [
    'trending_topics'  => $sidebar_topics_output,
    'total_articles'   => $totalArticles,
    'last_update_time' => date('Y-m-d H:i:s'),
    'chart_data'       => [
        'days_labels'     => $days_labels,
        'topic_trends'    => $topic_trends_output,
        'daily_top_topic' => $daily_top_topic_output,
        'pie_chart'       => $pie_chart_data,
        'word_cloud'      => $word_cloud_data,
        'clustered_bar'   => $daily_top_three_topics,
        'heatmap'         => $heatmap_data,
        'monthly_trends'  => $monthly_topic_trends,
        'monthly_dates'   => $monthly_dates,
        'scatter_data'    => $scatter_data
    ],
    'user_preferences' => $user_preferences
];

echo json_encode($output, JSON_PRETTY_PRINT);
$conn->close();
?>
