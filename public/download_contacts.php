<?php
// ---------- DATABASE CONNECTION ----------
// $host = "127.0.0.1";
// $port = 3306;
// $dbname = "dbipfkhop9oqna";
// $username = "uwee0g3bir9pr";
// $password = "4&e21%61sh11";

$host = "127.0.0.1";
$port = 3306;
$dbname = "exeat";
$username = "root";
$password = "";

$mysqli = new mysqli($host, $username, $password, $dbname, $port);
if ($mysqli->connect_errno) {
    die("DB Connection Failed: " . $mysqli->connect_error);
}

// ---------- FUNCTION TO CLEAN/VALIDATE NUMBERS ----------
function extract_phones($string)
{
    if (!$string || trim($string) == "")
        return [];
    $clean = str_replace([";", ",", " ", "|", "/", "\\"], ",", $string);
    $parts = explode(",", $clean);
    $valid = [];
    foreach ($parts as $p) {
        $p = trim($p);
        $p = preg_replace('/\D/', '', $p);
        if (preg_match('/^0[7-9][0-1]\d{8}$/', $p))
            $valid[] = $p;
    }
    return $valid;
}

// ---------- HANDLE AUTOMATIC UPDATE OF DUPLICATES ----------
if (isset($_POST['action']) && $_POST['action'] == 'auto_update_duplicates') {
    $phone_map_json = $_POST['phone_map'];
    $phone_map = json_decode($phone_map_json, true);
    if (!$phone_map || !is_array($phone_map)) {
        echo json_encode(['status' => 0, 'msg' => 'Invalid phone map data!']);
        exit;
    }
    foreach ($phone_map as $phone => $sids) {
        $first_phone = $phone;
        foreach ($sids as $sid) {
            $stmt = $mysqli->prepare("UPDATE student_contacts SET phone_no=? WHERE student_id=?");
            $stmt->bind_param("si", $first_phone, $sid);
            $stmt->execute();
        }
    }
    echo json_encode(['status' => 1, 'msg' => 'All duplicates updated automatically!']);
    exit;
}

// ---------- BUILD QUERY & FILTERS ----------
$level_filter = $_GET['level'] ?? [];
if (!is_array($level_filter)) {
    // Handle single string case (legacy link support)
    $level_filter = ($level_filter == 'all' || $level_filter == '') ? [] : [$level_filter];
}

$filter = $_GET['filter'] ?? 'all';

// Base Query
$query = "
    SELECT 
        sc.student_id, 
        sc.phone_no, 
        sc.phone_no_two, 
        sa.level
    FROM student_contacts sc
    JOIN students s ON sc.student_id = s.id
    LEFT JOIN student_academics sa ON sc.student_id = sa.student_id
    WHERE s.status = 1
";

// Add Level Filter to SQL if selected
if (!empty($level_filter) && !in_array('all', $level_filter)) {
    // Sanitize each level
    $safe_levels = array_map(function ($l) use ($mysqli) {
        return "'" . $mysqli->real_escape_string($l) . "'";
    }, $level_filter);

    $levels_str = implode(',', $safe_levels);
    $query .= " AND sa.level IN ($levels_str)";
}

// Function to build query string for links
function build_url($params)
{
    return '?' . http_build_query(array_merge($_GET, $params));
}

// ---------- HANDLE PHONES ONLY DOWNLOAD ----------
if (isset($_GET['download']) && $_GET['download'] == 'phones_only') {
    header('Content-Type: text/csv; charset=utf-8');
    $filename = "phone_numbers_" . (empty($level_filter) ? 'All_Levels' : implode('_', $level_filter)) . ".csv";
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Phone Number']);

    $result = $mysqli->query($query);
    $seen = [];

    while ($row = $result->fetch_assoc()) {
        $phones1 = extract_phones($row['phone_no']);
        $phones2 = extract_phones($row['phone_no_two']);
        $final = !empty($phones1) ? $phones1[0] : (!empty($phones2) ? $phones2[0] : '');

        if ($final == '')
            continue;
        if (in_array($final, $seen))
            continue;

        $seen[] = $final;
        $formatted = '="' . $final . '"';
        fputcsv($output, [$formatted]);
    }
    fclose($output);
    exit;
}

// ---------- HANDLE CSV DOWNLOAD ----------
if (isset($_GET['download']) && $_GET['download'] == 1) {
    header('Content-Type: text/csv; charset=utf-8');
    $filename = "student_contacts_" . (empty($level_filter) ? 'All_Levels' : implode('_', $level_filter)) . ".csv";
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student ID', 'Level', 'Cleaned Phone']);

    $result = $mysqli->query($query);

    while ($row = $result->fetch_assoc()) {
        $phones1 = extract_phones($row['phone_no']);
        $phones2 = extract_phones($row['phone_no_two']);
        $final = !empty($phones1) ? $phones1[0] : (!empty($phones2) ? $phones2[0] : '');

        if ($filter == 'duplicates' && $final == '')
            continue;
        if ($filter == 'null' && $final != '')
            continue;

        $formatted = '="' . $final . '"';
        fputcsv($output, [$row['student_id'], $row['level'], $formatted]);
    }
    fclose($output);
    exit;
}

// ---------- FETCH DATA FOR VIEW ----------
$result = $mysqli->query($query);
$students = [];
$phone_map = [];
$nullNumbers = [];

while ($row = $result->fetch_assoc()) {
    $sid = $row['student_id'];
    $phones1 = extract_phones($row['phone_no']);
    $phones2 = extract_phones($row['phone_no_two']);
    $final = !empty($phones1) ? $phones1[0] : (!empty($phones2) ? $phones2[0] : '');

    if ($final == '')
        $nullNumbers[] = $sid;

    $students[$sid] = [
        'phone_no' => $row['phone_no'],
        'phone_no_two' => $row['phone_no_two'],
        'clean' => $final,
        'level' => $row['level']
    ];

    if ($final != '') {
        if (!isset($phone_map[$final]))
            $phone_map[$final] = [];
        $phone_map[$final][] = $sid;
    }
}

// ---------- IDENTIFY DUPLICATES ----------
$duplicates = array_filter($phone_map, function ($v) {
    return count($v) > 1; });
$shared_numbers = $duplicates;
$total_shared = count($shared_numbers);

// ---------- STATISTICS ----------
$total_students = count($students);
$total_duplicates = count($duplicates);
$total_null = count($nullNumbers);

// ---------- PAGINATION ----------
$filtered_students = [];
foreach ($students as $sid => $data) {
    // Filter by 'duplicates' or 'null' view if set
    $isDuplicate = isset($duplicates[$data['clean']]);
    if ($filter == 'duplicates' && !$isDuplicate)
        continue;
    if ($filter == 'null' && $data['clean'] != '')
        continue;
    $filtered_students[$sid] = $data;
}

$perPage = 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$total_filtered = count($filtered_students);
$totalPages = ceil($total_filtered / $perPage);
$students_page = array_slice($filtered_students, ($page - 1) * $perPage, $perPage, true);

// Get Unique Levels for Filter
$levels = ['100', '200', '300', '400', '500', '600', 'PG'];
?>

<!DOCTYPE html>
<html>

<head>
    <title>Veritas Student Contacts (Active Only)</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f4f4f4;
        }

        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #2c3e50;
            color: #fff;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .dup {
            background: #ffebee !important;
        }

        .null {
            background: #fff3e0 !important;
        }

        .btn {
            padding: 8px 15px;
            background: #27ae60;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            font-size: 14px;
        }

        .btn:hover {
            background: #219150;
        }

        .filter {
            margin-bottom: 20px;
            background: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
        }

        .filter-levels {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .filter-views a {
            text-decoration: none;
            color: #2980b9;
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid transparent;
        }

        .filter-views a.active {
            background: #2980b9;
            color: white;
        }

        .filter-views a:hover:not(.active) {
            background: #e0e0e0;
        }

        .checkbox-label {
            background: white;
            padding: 5px 10px;
            border: 1px solid #BDC3C7;
            border-radius: 4px;
            cursor: pointer;
            user-select: none;
        }

        .checkbox-label:hover {
            background: #f9f9f9;
        }

        .checkbox-label input {
            margin-right: 5px;
        }

        .stats {
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
        }

        .stat-box {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #3498db;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .pagination {
            margin-top: 20px;
        }

        .pagination a {
            margin-right: 5px;
            padding: 5px 10px;
            border: 1px solid #ddd;
            text-decoration: none;
            background: white;
        }

        .pagination a.current {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .quick-dl {
            background: #e8f6f3;
            padding: 15px;
            border: 1px solid #d1f2eb;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
    </style>
</head>

<body>

    <div class="container">
        <h1 style="margin-top:0;">Student Contacts Manager</h1>
        <p style="color:#7f8c8d;">Showing ONLY Active Students (Status = 1)</p>

        <div class="stats">
            <div class="stat-box"><strong>Total Found:</strong> <?= $total_students ?></div>
            <div class="stat-box" style="border-left-color:#e74c3c;"><strong>Duplicates:</strong>
                <?= $total_duplicates ?></div>
            <div class="stat-box" style="border-left-color:#f39c12;"><strong>Missing Numbers:</strong>
                <?= $total_null ?></div>
        </div>

        <!-- FILTER BAR -->
        <div class="filter">
            <form method="GET" id="filterForm">
                <input type="hidden" name="filter" value="<?= $filter ?>">

                <div class="filter-levels">
                    <strong style="margin-right:10px; align-self:center;">Levels:</strong>
                    <?php foreach ($levels as $lvl): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="level[]" value="<?= $lvl ?>" <?= in_array($lvl, $level_filter) ? 'checked' : '' ?> onchange="document.getElementById('filterForm').submit()">
                            <?= $lvl ?> Lvl
                        </label>
                    <?php endforeach; ?>
                </div>
            </form>

            <div class="filter-views" style="display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <strong style="margin-right:10px;">View:</strong>
                    <a href="<?= build_url(['filter' => 'all', 'page' => 1]) ?>"
                        class="<?= $filter == 'all' ? 'active' : '' ?>">All</a>
                    <a href="<?= build_url(['filter' => 'duplicates', 'page' => 1]) ?>"
                        class="<?= $filter == 'duplicates' ? 'active' : '' ?>">Duplicates</a>
                    <a href="<?= build_url(['filter' => 'null', 'page' => 1]) ?>"
                        class="<?= $filter == 'null' ? 'active' : '' ?>">Missing</a>
                </div>
                <div>
                    <a href="<?= build_url(['download' => 1]) ?>" class="btn">Download Full CSV</a>
                </div>
            </div>
        </div>

        <!-- NEW SECTION: QUICK DOWNLOAD PHONE NUMBERS ONLY -->
        <div class="quick-dl">
            <div>
                <h3 style="margin:0 0 5px 0; color:#16a085;">📱 Quick Export: Phone Numbers Only</h3>
                <p style="margin:0; font-size:13px; color:#555;">
                    Download a clean list of <b>just phone numbers</b> for the currently selected levels.
                    Ideal for Bulk SMS. Excludes duplicates automatically.
                </p>
            </div>
            <div>
                <a href="<?= build_url(['download' => 'phones_only']) ?>" class="btn"
                    style="background:#16a085; border:1px solid #148f77;">Download Phones List</a>
            </div>
        </div>
    </div>

    <?php if ($filter === 'all' && $total_duplicates > 0): ?>
        <div
            style="margin-bottom:15px; background:#fff3cd; padding:10px; border-radius:4px; border:1px solid #ffeeba; color:#856404;">
            ⚠️ Found <?= $total_duplicates ?> duplicate numbers.
            <button onclick="autoUpdate()" style="margin-left:10px; padding:5px 10px; cursor:pointer;">Fix
                Auto-Duplicates</button>
            <button onclick="showShared()" style="margin-left:5px; padding:5px 10px; cursor:pointer;">View
                Details</button>
        </div>
    <?php endif; ?>

    <!-- DATA TABLE -->
    <table>
        <tr>
            <th>Student ID</th>
            <th>Level</th>
            <th>Original Phone</th>
            <th>Cleaned Phone</th>
            <th>Duplicate?</th>
        </tr>
        <?php if (empty($students_page)): ?>
            <tr>
                <td colspan="5" style="text-align:center; padding:30px;">No students found matching filters.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($students_page as $sid => $data):
                $isDuplicate = isset($duplicates[$data['clean']]);
                $rowClass = $isDuplicate ? 'dup' : ($data['clean'] == '' ? 'null' : '');
                ?>
                <tr class="<?= $rowClass ?>">
                    <td><?= $sid ?></td>
                    <td><?= $data['level'] ?? 'N/A' ?></td>
                    <td><?= $data['phone_no'] ?></td>
                    <td><b><?= $data['clean'] ?></b></td>
                    <td><?= $isDuplicate ? '<span style="color:red;font-weight:bold;">Yes</span>' : 'No' ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <!-- PAGINATION -->
    <div class="pagination">
        <?php if ($totalPages > 1): ?>
            <?php
            // Simple pagination logic (prev 2, current, next 2)
            $range = 2;
            $start = max(1, $page - $range);
            $end = min($totalPages, $page + $range);

            if ($page > 1)
                echo '<a href="?page=' . ($page - 1) . '&filter=' . $filter . '&level=' . $level_filter . '">&laquo; Prev</a>';

            if ($start > 1)
                echo '<a href="?page=1&filter=' . $filter . '&level=' . $level_filter . '">1</a> ... ';

            for ($i = $start; $i <= $end; $i++): ?>
                <a href="?page=<?= $i ?>&filter=<?= $filter ?>&level=<?= $level_filter ?>"
                    class="<?= $i == $page ? 'current' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            if($end < $totalPages) echo ' ... <a href="?page=' .$totalPages.'&filter='.$filter.' &level='.$level_filter.'">'.$totalPages.'</a>';

                    if($page < $totalPages) echo '<a href=" ?page='.($page+1).' &filter='.$filter.'
            &level='.$level_filter.'">Next &raquo;</a>';
                    ?>
    <?php endif; ?>
    </div>
</div>

<script>
    // Auto-update duplicates globally
    function autoUpdate() {
        if (!confirm(" This will automatically replace all duplicate phone numbers with the first valid one. Proceed ? ")) return; let phone_map = <?= json_encode($duplicates) ?>; let formData = new FormData();
        formData.append('action', 'auto_update_duplicates'); formData.append('phone_map',
            JSON.stringify(phone_map)); fetch('<?= $_SERVER['PHP_SELF'] ?>', {
                method: 'POST', body: formData
            }).then(res => res.json())
                .then(data => {
                    alert(data.msg);
                    if (data.status == 1) location.reload();
                });
    }

    // Show shared numbers
    function showShared() {
        let shared = <?= json_encode($shared_numbers) ?>;
        if (Object.keys(shared).length === 0) {
            alert(" No shared phone numbers found!"); return;
        } let html = "<div
        style = 'position:fixed;top:10%;left:50%;transform:translateX(-50%);background:white;padding:20px;border:2px solid #333;box-shadow:0 0 100px rgba(0,0,0,0.5);max-height:80vh;overflow-y:auto;z-index:999;' >
            " ; html +=" < h2 style = 'margin-top:0;' > Shared Phone Numbers < button
        onclick = 'this.parentElement.parentElement.remove()' style = 'float:right;' > X</button ></h2 > " ; html
            +="<table border='1' style='border-collapse:collapse;width:100%;margin-top:10px;'>"; html += "<tr>
            < th > Phone Number</th >
                <th>Student IDs Connected</th>
                    </tr > " ; // Convert object to array for sorting let sorted=[]; for (let num in shared)
        sorted.push([num, shared[num]]); // Sort by number of duplicates (descending) sorted.sort((a, b)=>
        b[1].length - a[1].length);

        for (let item of sorted) {
            html += "<tr>
                < td > "+item[0]+"</td >
                    <td>"+item[1].join(", ")+" ("+item[1].length+" students)</td>
            </tr > ";
        }
        html += "</table>
    </div > ";
        let div = document.createElement('div');
        div.innerHTML = html;
        document.body.appendChild(div);
    }
</script>
</body>

</html>