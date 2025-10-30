<?php
// process/product_history_api.php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth_check.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'auth_required']); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action !== 'latest_price') { echo json_encode(['success'=>false,'message'=>'Invalid action']); exit; }

$product_id = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$partner_id = trim((string)($_GET['partner_id'] ?? $_POST['partner_id'] ?? ''));
$source     = strtolower(trim((string)($_GET['source'] ?? $_POST['source'] ?? 'order')));
if ($product_id <= 0) { echo json_encode(['success'=>false,'message'=>'Thiếu product_id']); exit; }

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo) { $dbPath = __DIR__.'/../includes/db.php'; if (file_exists($dbPath)) { require_once $dbPath; $pdo = $db ?? null; } }
if (!$pdo) { echo json_encode(['success'=>false,'message'=>'DB not connected']); exit; }

/* Map bảng & cột theo source */
$cfgMap = [
  'order' => [
    'header'=>'sales_orders','detail'=>'sales_order_details',
    'header_id'=>'id','detail_fk'=>'order_id',
    'date_expr'=>'COALESCE(h.expected_delivery_date, h.order_date)','base_date'=>'h.order_date',
    'currency'=>'h.currency','partner_col'=>'h.supplier_id','exclude_status'=>['cancelled'],
  ],
  'quote' => [
    'header'=>'sales_quotes','detail'=>'sales_quote_details',
    'header_id'=>'id','detail_fk'=>'quote_id',
    'date_expr'=>'h.quote_date','base_date'=>'h.quote_date',
    'currency'=>'h.currency','partner_col'=>'h.customer_id','exclude_status'=>[], // thêm ['rejected','expired'] nếu muốn loại
  ],
];
if (!isset($cfgMap[$source])) $source='order';
$cfg=$cfgMap[$source];

$H=$cfg['header']; $D=$cfg['detail']; $headerId=$cfg['header_id']; $detailFk=$cfg['detail_fk'];
$dateExpr=$cfg['date_expr']; $baseDate=$cfg['base_date']; $currency=$cfg['currency'];
$partnerCol=$cfg['partner_col']; $excl=$cfg['exclude_status'];

$sql = "SELECT d.unit_price, {$baseDate} AS base_date, {$dateExpr} AS ref_date, {$currency} AS currency
        FROM {$D} d JOIN {$H} h ON h.{$headerId} = d.{$detailFk} WHERE d.product_id = :pid";
$params = [':pid'=>$product_id];

if (!empty($excl)) {
  $in=[]; foreach ($excl as $i=>$val){ $ph=":c{$i}"; $in[]=$ph; $params[$ph]=$val; }
  $sql .= " AND (h.status IS NULL OR h.status NOT IN (".implode(',', $in)."))";
}
if ($partner_id !== '') {
  $sql .= " AND {$partnerCol} = :partner_id";
  $params[':partner_id'] = ctype_digit($partner_id) ? (int)$partner_id : $partner_id;
}
$sql .= " ORDER BY {$dateExpr} DESC, h.{$headerId} DESC LIMIT 1";

try {
  $stmt=$pdo->prepare($sql);
  foreach($params as $k=>$v){ $stmt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
  $stmt->execute();
  $row=$stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) { echo json_encode(['success'=>true,'data'=>null]); exit; }

  echo json_encode(['success'=>true,'data'=>[
    'last_date'=>$row['ref_date'] ?: $row['base_date'],
    'unit_price'=> isset($row['unit_price']) ? (float)$row['unit_price'] : null,
    'currency'=>$row['currency'] ?: 'VND',
    'source'=>$source
  ]]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
}
