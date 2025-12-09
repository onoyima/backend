<?php
// ---------- DATABASE CONNECTION ----------
$host = "127.0.0.1";
$port = 3306;
$dbname = "dbipfkhop9oqna";
$username = "uwee0g3bir9pr";
$password = "4&e21%61sh11";

$mysqli = new mysqli($host, $username, $password, $dbname, $port);
if ($mysqli->connect_errno) {
    die("DB Connection Failed: " . $mysqli->connect_error);
}
// ---------- FUNCTION TO CLEAN/VALIDATE NUMBERS ----------
function extract_phones($string) {
    if (!$string || trim($string) == "") return [];
    $clean = str_replace([";", ",", " ", "|", "/", "\\"], ",", $string);
    $parts = explode(",", $clean);
    $valid = [];
    foreach ($parts as $p) {
        $p = trim($p);
        $p = preg_replace('/\D/', '', $p);
        if (preg_match('/^0[7-9][0-1]\d{8}$/', $p)) $valid[] = $p;
    }
    return $valid;
}

// ---------- HANDLE AUTOMATIC UPDATE OF DUPLICATES ----------
if(isset($_POST['action']) && $_POST['action']=='auto_update_duplicates'){
    $phone_map_json = $_POST['phone_map'];
    $phone_map = json_decode($phone_map_json, true);
    if(!$phone_map || !is_array($phone_map)){
        echo json_encode(['status'=>0,'msg'=>'Invalid phone map data!']);
        exit;
    }
    foreach($phone_map as $phone=>$sids){
        $first_phone = $phone;
        foreach($sids as $sid){
            $stmt = $mysqli->prepare("UPDATE student_contacts SET phone_no=? WHERE student_id=?");
            $stmt->bind_param("si",$first_phone,$sid);
            $stmt->execute();
        }
    }
    echo json_encode(['status'=>1,'msg'=>'All duplicates updated automatically!']);
    exit;
}

// ---------- HANDLE CSV DOWNLOAD ----------
if(isset($_GET['download']) && $_GET['download']==1){
    $filter = $_GET['filter'] ?? 'all';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=student_contacts_filtered.csv');
    $output = fopen('php://output','w');
    fputcsv($output, ['student_id','cleaned_phone']);

    $sql = "SELECT student_id, phone_no, phone_no_two FROM student_contacts";
    $result = $mysqli->query($sql);

    while($row = $result->fetch_assoc()){
        $phones1 = extract_phones($row['phone_no']);
        $phones2 = extract_phones($row['phone_no_two']);
        $final = !empty($phones1)? $phones1[0] : (!empty($phones2)? $phones2[0] : '');
        
        if($filter=='duplicates' && $final=='') continue;
        if($filter=='null' && $final!='') continue;

        $formatted = '="'.$final.'"';
        fputcsv($output, [$row['student_id'],$formatted]);
    }
    fclose($output);
    exit;
}

// ---------- FETCH STUDENT CONTACTS ----------
$sql = "SELECT student_id, phone_no, phone_no_two FROM student_contacts";
$result = $mysqli->query($sql);

$students = [];
$phone_map = []; // phone => list of student_ids
$nullNumbers = [];

while($row = $result->fetch_assoc()){
    $sid = $row['student_id'];
    $phones1 = extract_phones($row['phone_no']);
    $phones2 = extract_phones($row['phone_no_two']);
    $final = !empty($phones1)? $phones1[0] : (!empty($phones2)? $phones2[0] : '');
    
    if($final=='') $nullNumbers[]=$sid;
    
    $students[$sid] = [
        'phone_no'=>$row['phone_no'],
        'phone_no_two'=>$row['phone_no_two'],
        'clean'=>$final
    ];
    if($final!=''){
        if(!isset($phone_map[$final])) $phone_map[$final]=[];
        $phone_map[$final][] = $sid;
    }
}

// ---------- IDENTIFY DUPLICATES & SHARED NUMBERS ----------
$duplicates = array_filter($phone_map,function($v){return count($v)>1;});
$shared_numbers = $duplicates; // shared numbers are duplicates
$total_shared = count($shared_numbers);

// ---------- FILTERING ----------
$filter = $_GET['filter']??'all';

// ---------- STATISTICS ----------
$total_students = count($students);
$total_duplicates = count($duplicates);
$total_null = count($nullNumbers);

// ---------- PAGINATION ----------
$perPage = 50;
$page = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$totalPages = ceil($total_students / $perPage);
$students_page = array_slice($students, ($page-1)*$perPage, $perPage, true);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Contacts Management</title>
    <style>
        body{font-family:Arial, sans-serif; padding:20px;}
        table{border-collapse: collapse; width: 100%; margin-bottom:20px;}
        th, td{border:1px solid #555; padding:8px; text-align:left;}
        th{background:#333; color:#fff;}
        .dup{background:#ffdddd;}
        .null{background:#fff3cd;}
        .btn{padding:8px 12px; background:green; color:white; text-decoration:none; border-radius:4px; cursor:pointer;}
        .filter a{margin-right:10px; text-decoration:none;}
        .stats{margin-bottom:20px;}
        .pagination a{margin-right:5px;text-decoration:none;}
        .pagination a.current{font-weight:bold;}
    </style>
</head>
<body>
<h1>Student Contacts Management</h1>

<div class="stats">
    <strong>Total Students:</strong> <?= $total_students ?> |
    <strong>Total Duplicates:</strong> <?= $total_duplicates ?> |
    <strong>Total Null/Missing:</strong> <?= $total_null ?> |
    <strong>Total Shared Numbers:</strong> <?= $total_shared ?>
</div>

<div class="filter">
    <strong>Filter:</strong>
    <a href="?filter=all">All</a>
    <a href="?filter=duplicates">Duplicates</a>
    <a href="?filter=null">Null / Missing</a>
    <a href="?download=1&filter=<?= $filter ?>" class="btn">Download CSV</a>
    <?php if($total_shared>0): ?>
        <button class="btn" onclick="showShared()">Check Shared Numbers</button>
    <?php endif; ?>
    <?php if($total_duplicates>0): ?>
        <button class="btn" onclick="autoUpdate()">Update Duplicates Automatically</button>
    <?php endif; ?>
</div>

<table>
<tr>
    <th>Student ID</th>
    <th>Phone No</th>
    <th>Phone No Two</th>
    <th>Cleaned Phone</th>
    <th>Duplicate?</th>
</tr>
<?php
foreach($students_page as $sid=>$data){
    $isDuplicate = isset($duplicates[$data['clean']]);
    if($filter=='duplicates' && !$isDuplicate) continue;
    if($filter=='null' && $data['clean']!='') continue;
    $rowClass = $isDuplicate ? 'dup' : ($data['clean']=='' ? 'null':'');
?>
<tr class="<?= $rowClass ?>">
    <td><?= $sid ?></td>
    <td><?= $data['phone_no'] ?></td>
    <td><?= $data['phone_no_two'] ?></td>
    <td><?= $data['clean'] ?></td>
    <td><?= $isDuplicate ? 'Yes':'No' ?></td>
</tr>
<?php } ?>
</table>

<!-- PAGINATION LINKS -->
<div class="pagination">
<?php if($totalPages>1): ?>
    <?php for($i=1;$i<=$totalPages;$i++): ?>
        <a href="?page=<?= $i ?>&filter=<?= $filter ?>" class="<?= $i==$page?'current':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
<?php endif; ?>
</div>

<script>
// Auto-update duplicates globally
function autoUpdate(){
    if(!confirm("This will automatically replace all duplicate phone numbers with the first valid one. Proceed?")) return;
    let phone_map = <?= json_encode($duplicates) ?>;
    let formData = new FormData();
    formData.append('action','auto_update_duplicates');
    formData.append('phone_map',JSON.stringify(phone_map));
    fetch('<?= $_SERVER['PHP_SELF'] ?>',{
        method:'POST',
        body:formData
    }).then(res=>res.json())
    .then(data=>{
        alert(data.msg);
        if(data.status==1) location.reload();
    });
}

// Show shared numbers in page
function showShared(){
    let shared = <?= json_encode($shared_numbers) ?>;
    if(Object.keys(shared).length===0){
        alert("No shared phone numbers found!");
        return;
    }
    let html = "<h2>Shared Phone Numbers</h2>";
    html += "<a href='?download=1&filter=duplicates' class='btn'>Download CSV</a>";
    html += "<table border='1' style='border-collapse:collapse;width:100%;margin-top:10px;'>";
    html += "<tr><th>Phone Number</th><th>Student IDs</th></tr>";
    for(let num in shared){
        html += "<tr><td>"+num+"</td><td>"+shared[num].join(", ")+"</td></tr>";
    }
    html += "</table>";
    let div = document.createElement('div');
    div.innerHTML = html;
    document.body.appendChild(div);
}
</script>
</body>
</html>
