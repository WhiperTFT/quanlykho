<?php
// File: process/sales_quote_serverside.php (Đã sửa lỗi HY093 và loại bỏ ký tự lạ)
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Bật hiển thị lỗi PHP (chỉ trên môi trường phát triển)
// Hãy COMMENT hoặc XÓA các dòng này trên môi trường Production!


// Thiết lập header Content-Type sớm để đảm bảo phản hồi là JSON ngay cả khi có lỗi PHP
header('Content-Type: application/json');

// Khởi tạo mảng response mặc định để dễ dàng trả về lỗi
$response = [
    'draw' => 0,
    'recordsTotal' => 0,
    'recordsFiltered' => 0,
    'data' => [],
    'error' => null, // Thêm trường error
];

// Sử dụng khối try-catch chính để bắt mọi lỗi xảy ra trong quá trình xử lý
try {
    // --- Cấu hình cột ---
    // Ánh xạ index cột DataTables sang tên cột trong DB hoặc alias SQL
    $column_map = [
        1 => 'so.quote_number',
        2 => 'so.quote_date', // Sử dụng tên cột DB cho sắp xếp và lọc server-side
        3 => 'p.name',
        4 => 'so.grand_total', // Sử dụng tên cột DB cho sắp xếp (nếu cần sort theo số)
        5 => 'so.status',
    ];

    // --- Lấy tham số từ DataTables (POST) ---
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $searchValue = $_POST['search']['value'] ?? ''; // Global search (đang tắt trong JS)

    // Sắp xếp
    $quote_column_index = $_POST['quote'][0]['column'] ?? 1; // Mặc định cột số đơn hàng (index 1)
    $quote_dir = $_POST['quote'][0]['dir'] ?? 'desc';
    // Ánh xạ index cột sang tên cột DB, mặc định là 'so.quote_number' nếu index không hợp lệ
    $quote_column_name = $column_map[$quote_column_index] ?? 'so.quote_number';

    // Lấy giá trị lọc từ các bộ lọc cột (đã gửi từ JS trong columns[x][search][value] hoặc custom param)
    $filter_quote_number = $_POST['columns'][1]['search']['value'] ?? '';
    $filter_quote_date = $_POST['columns'][2]['search']['value'] ?? '';
    $filter_customer_name = $_POST['columns'][3]['search']['value'] ?? '';
    // $filter_grand_total = $_POST['columns'][4]['search']['value'] ?? ''; // Nếu có bộ lọc cho cột tổng tiền
    $filter_status = $_POST['columns'][5]['search']['value'] ?? '';

    // Lấy bộ lọc chi tiết sản phẩm (nếu có, gửi kèm như tham số POST tùy chỉnh)
    $itemDetailsFilter = $_POST['item_details_filter'] ?? '';

    // Lấy bộ lọc Năm và Tháng từ dropdowns mới (Gửi kèm như tham số POST tùy chỉnh)
    // Đã sửa giá trị mặc định thành chuỗi rỗng ''
    $filterYear = $_POST['filter_year'] ?? '';
    $filterMonth = $_POST['filter_month'] ?? '';


    // --- Xây dựng câu lệnh SQL (Phần SELECT và FROM/JOIN cơ bản) ---
    // Xác định có cần JOIN bảng chi tiết hay không dựa trên bộ lọc itemDetailsFilter
    $use_details_join = !empty($itemDetailsFilter);

    // Phần SELECT các cột cần thiết
    $sql_select_base = "
        SELECT
            so.id,
            so.quote_number,
            so.quote_date,
            so.currency,
            so.grand_total,
            so.status,
            p.name as customer_name
            ";

$sql_from_join = " FROM 
                    sales_quotes so 
                 LEFT JOIN 
                    partners p ON so.customer_id = p.id AND p.type = 'customer'
                 -- LEFT JOIN attachments att ON so.id = att.document_id AND att.document_type = 'sales_quote' 
                 ";

    // Nếu cần JOIN bảng chi tiết cho bộ lọc item details, thêm vào đây (LEFT JOIN để không bỏ sót đơn hàng không có item)
    if ($use_details_join) {
        $sql_from_join .= " LEFT JOIN sales_quote_details sod ON so.id = sod.quote_id";
    }


    // --- Xây dựng mệnh đề WHERE và tham số ---
    // === BƯỚC 2: Khởi tạo mảng điều kiện và tham số ===
    $where = ""; // Khởi tạo điều kiện WHERE chính là chuỗi rỗng
    $params = []; // Khởi tạo mảng tham số cho prepared statement là mảng rỗng

    $columnFilters = []; // Mảng tạm chứa các điều kiện dạng chuỗi SQL (ví dụ: "so.quote_number LIKE :filter_number")
    $columnParams = []; // Mảng tạm chứa các tham số cho các điều kiện trong $columnFilters (ví dụ: [':filter_number' => '%value%'])

    // Xử lý Global search (nếu được bật trong JS)
    if (!empty($searchValue)) {
        // Cần đảm bảo các cột trong global search có index đúng trong $column_map nếu dùng tên cột DB
        // $where .= " WHERE (so.quote_number LIKE :global_search OR p.name LIKE :global_search)"; // Ví dụ
        // $params[':global_search'] = "%" . $searchValue . "%";
        // Lưu ý: Nếu dùng global search, cần điều chỉnh truy vấn đếm tổng và đếm sau lọc
    }

    // Xử lý Column filtering (đã gửi từ JS)
    // === BƯỚC 3A: Thêm các điều kiện lọc cột hiện có vào $columnFilters và $columnParams ===
    if (!empty($filter_quote_number)) {
        $columnFilters[] = "so.quote_number LIKE :filter_quote_number";
        $columnParams[':filter_quote_number'] = "%" . $filter_quote_number . "%";
    }
    // Xử lý định dạng ngày "dd/mm/yyyy" từ bộ lọc ngày cụ thể
    if (!empty($filter_quote_date)) {
        $dateObj = DateTime::createFromFormat('d/m/Y', $filter_quote_date);
        if ($dateObj) { // Kiểm tra xem định dạng ngày có đúng không
            $columnFilters[] = "DATE(so.quote_date) = :filter_exact_date";
            $columnParams[':filter_exact_date'] = $dateObj->format('Y-m-d'); // Chuyển vềYYYY-MM-DD
        }
    }

    // Thêm các bộ lọc cột khác ở đây (customer_name, status, etc.)
    if (!empty($filter_customer_name)) {
        $columnFilters[] = "p.name LIKE :filter_customer";
        $columnParams[':filter_customer'] = "%" . $filter_customer_name . "%";
    }
    if (!empty($filter_status)) {
        $columnFilters[] = "so.status = :filter_status";
        $columnParams[':filter_status'] = $filter_status;
    }


    // === BƯỚC 3B: Thêm bộ lọc chi tiết sản phẩm (itemDetailsFilter) vào $columnFilters và $columnParams ===
    if (!empty($itemDetailsFilter)) {
        // Thêm điều kiện lọc trên bảng chi tiết (sod)
        // Cần đảm bảo tên cột product_name_snapshot là đúng trong bảng sod
        $columnFilters[] = "sod.product_name_snapshot LIKE :filter_item";
        $columnParams[':filter_item'] = "%" . $itemDetailsFilter . "%";
        // $use_details_join đã được xử lý ở phần $sql_from_join
        // SELECT DISTINCT so.id hoặc GROUP BY so.id cần được xử lý ở truy vấn chính và truy vấn đếm sau lọc
    }

    // === BƯỚC 3C: Thêm bộ lọc NĂM và THÁNG vào $columnFilters và $columnParams ===
    // Lọc theo NĂM (nếu có giá trị) - Giá trị rỗng '' khi chọn "Tất cả năm"
    if (!empty($filterYear)) {
        $columnFilters[] = "YEAR(so.quote_date) = :filter_year";
        $columnParams[':filter_year'] = (int) $filterYear; // Chuyển sang số nguyên cho an toàn
    }

    // Lọc theo THÁNG (nếu có giá trị) - Giá trị rỗng '' khi chọn "Tất cả tháng"
    if (!empty($filterMonth)) {
        $filterMonthInt = (int) $filterMonth;
        if ($filterMonthInt >= 1 && $filterMonthInt <= 12) { // Kiểm tra tháng hợp lệ
            $columnFilters[] = "MONTH(so.quote_date) = :filter_month";
            $columnParams[':filter_month'] = $filterMonthInt;
        }
    }
    // === KẾT THÚC BƯỚC 3 ===


    // === BƯỚC 4: Gom các điều kiện trong $columnFilters lại và nối vào $where ===
    if (!empty($columnFilters)) {
        // Nối các điều kiện trong $columnFilters bằng " AND "
        // Nối vào $where chính. Nếu $where ban đầu rỗng (không có global search), bắt đầu bằng " WHERE ". Ngược lại, nối bằng " AND ".
        $where .= (empty($where) ? " WHERE " : " AND ") . implode(" AND ", $columnFilters);
        // Gộp các tham số của bộ lọc cột vào mảng params chính
        // array_merge sẽ ghi đè nếu có key trùng, nhưng tên placeholder của chúng ta là duy nhất nên không sao
        $params = array_merge($params, $columnParams);
    }
    // === KẾT THÚC BƯỚC 4 ===


    // --- Kết thúc xử lý lọc ---


    // --- Xây dựng câu truy vấn chính (SELECT DATA) ---
    // === BƯỚC 5: Xây dựng câu truy vấn SELECT chính ===
    // Bắt đầu với SELECT và FROM/JOIN, sau đó nối WHERE, ORDER BY, LIMIT
    // Sử dụng $sql_select_base và $sql_from_join đã định nghĩa ở trên

    // Xử lý DISTINCT hoặc GROUP BY nếu lọc theo item details để tránh trùng lặp đơn hàng
    // DISTINCT so.id thường là cách đơn giản hơn nếu bạn chỉ cần thông tin đơn hàng chính
    // Nếu dùng DISTINCT, cần đảm bảo tất cả các cột trong SELECT đều nằm trong mệnh đề GROUP BY hoặc là hàm tổng hợp
    // Cách đơn giản nhất để tránh lỗi SQL khi dùng DISTINCT với so.* là liệt kê tường minh các cột từ so và p
    if ($use_details_join) {
         $sql = "
            SELECT DISTINCT
                so.id,
                so.quote_number,
                so.quote_date,
                so.currency,
                p.name as customer_name,
                so.grand_total,
                so.status
            " . $sql_from_join . $where; // Sử dụng $sql_from_join đã có JOIN sod nếu cần
    } else {
         // Nếu không lọc item details, truy vấn chính sử dụng SELECT so.*
         $sql = $sql_select_base . $sql_from_join . $where;
    }


    // --- Xử lý ORDER BY ---
    $sql .= " ORDER BY " . $quote_column_name . " " . $quote_dir;


    // --- Xử lý LIMIT và OFFSET cho phân trang ---
    $sql .= " LIMIT :limit OFFSET :offset";
    $params[':limit'] = $length; // Thêm tham số limit (INTEGER)
    $params[':offset'] = $start; // Thêm tham số offset (INTEGER)

    // --- Lấy tổng số bản ghi (không lọc) ---
    // === BƯỚC 6A: Xây dựng và thực thi truy vấn đếm tổng ===
    // Query đếm tổng (không áp dụng các bộ lọc tùy chỉnh, chỉ áp dụng global search nếu có)
    // Sử dụng phần FROM/JOIN cơ bản (chỉ JOIN partners)
    $sql_total = "SELECT COUNT(so.id) FROM sales_quotes so JOIN partners p ON so.customer_id = p.id AND p.type = 'customer'";

    // Thực thi truy vấn đếm tổng
    // Không cần tham số nào nếu global search tắt và không có bộ lọc nào khác áp dụng ở đây
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute([]); // Thực thi với mảng tham số rỗng
    $totalRecords = $stmt_total->fetchColumn();


    // --- Lấy số bản ghi sau khi lọc ---
    // === BƯỚC 6B: Xây dựng và thực thi truy vấn đếm sau lọc ===
    // Query đếm sau khi áp dụng TẤT CẢ các bộ lọc
    // Bắt đầu với SELECT COUNT và phần FROM/JOIN cần thiết cho các bộ lọc được áp dụng
    $sql_filtered = "SELECT COUNT(" . ($use_details_join ? "DISTINCT so.id" : "so.id") . ")"; // COUNT DISTINCT nếu JOIN sod

    // Phần FROM/JOIN cho truy vấn đếm sau lọc
    // Đảm bảo luôn JOIN partners và chỉ JOIN sod khi cần
    $sql_filtered_from_join = " FROM sales_quotes so JOIN partners p ON so.customer_id = p.id AND p.type = 'customer'"; // Luôn JOIN partners

    if ($use_details_join) {
        $sql_filtered_from_join .= " LEFT JOIN sales_quote_details sod ON so.id = sod.quote_id"; // JOIN sod nếu lọc item
    }

    $sql_filtered .= $sql_filtered_from_join . $where; // Nối FROM/JOIN và WHERE


    // Thực thi truy vấn đếm sau lọc
    // Sử dụng MẢNG $params đã chứa TẤT CẢ tham số cho TẤT CẢ các bộ lọc (bao gồm limit/offset)
    // LƯU Ý: Truy vấn COUNT không cần LIMIT/OFFSET. Cần tạo một mảng params riêng cho truy vấn đếm sau lọc.
    $params_filtered_count = $params; // Bắt đầu với tất cả params
    // Xóa tham số LIMIT và OFFSET vì chúng không được dùng trong truy vấn COUNT
    unset($params_filtered_count[':limit']);
    unset($params_filtered_count[':offset']);


    $stmt_filtered = $pdo->prepare($sql_filtered);
    $stmt_filtered->execute($params_filtered_count); // <<< Sử dụng mảng params ĐÃ LOẠI BỎ LIMIT/OFFSET
    $filteredRecords = $stmt_filtered->fetchColumn();


    // --- Lấy dữ liệu chính ---
    // === BƯỚC 6C: Thực thi truy vấn chính ===
    // Sử dụng $sql đã xây dựng hoàn chỉnh (có LIMIT/OFFSET)
    // Sử dụng MẢNG $params đã chứa TẤT CẢ tham số (bao gồm limit/offset)
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);


    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // --- Xử lý dữ liệu trước khi gửi về client ---
    // === BƯỚC 7: Format dữ liệu và trả về JSON ===
    $formatted_data = [];
    // Đảm bảo vòng lặp format dữ liệu ở đây
    foreach ($data as $row) {
        // Format ngày tháng
        try {
            $row['quote_date_formatted'] = !empty($row['quote_date']) ? (new DateTime($row['quote_date']))->format('d/m/Y') : '-';
        } catch (Exception $e) { $row['quote_date_formatted'] = $row['quote_date']; } // Giữ nguyên giá trị gốc nếu format lỗi
        // Format tiền tệ
        $currency = $row['currency'] ?? 'VND';
        // Sử dụng ',' cho phần nghìn và '.' cho phần thập phân nếu không phải VND
        // Sửa logic format số thập phân và nghìn
        if (isset($row['grand_total'])) {
             $total = (float) $row['grand_total'];
             if ($currency === 'VND') {
                 $row['grand_total_formatted'] = number_format($total, 0, ',', '.'); // VND: 0 thập phân, dấu phân cách nghìn là '.'
             } else {
                  $row['grand_total_formatted'] = number_format($total, 2, '.', ','); // Khác VND: 2 thập phân, dấu thập phân là '.', dấu phân cách nghìn là ','
             }
        } else {
             $row['grand_total_formatted'] = '0';
        }


        // Format trạng thái (Đã sử dụng match, cần PHP 8+ hoặc dùng if/else if)
        // Nếu bạn dùng PHP phiên bản cũ hơn PHP 8, hãy thay thế match bằng if/else if
        // if (strtolower($row['status'] ?? '') === 'draft') { $row['status_badge'] = 'bg-secondary'; $row['status_text'] = $lang['status_draft'] ?? 'Draft'; } else if (...) ...
        $status_key = 'status_' . strtolower($row['status'] ?? '');
        $row['status_text'] = $lang[$status_key] ?? ucfirst($row['status'] ?? '');
        $row['status_badge'] = match (strtolower($row['status'] ?? '')) {
            'draft' => 'bg-secondary',
            'quoteed', 'confirmed' => 'bg-info text-dark',
            'partially_received' => 'bg-warning text-dark',
            'fully_received', 'completed', 'shipped' => 'bg-success',
            'cancelled', 'rejected' => 'bg-danger',
            default => 'bg-light text-dark',
        };

        // Thêm các cột khác cần thiết cho DataTables render function (ví dụ: ID đơn hàng, currency)
        // Đảm bảo ID đơn hàng (so.id) và currency được SELECT ở truy vấn chính
        // Các cột này đã được SELECT nếu bạn sử dụng 'SELECT so.*' hoặc SELECT tường minh ở $sql_select_base
        // $row['id'] = $row['id']; // Chỉ cần dòng này nếu bạn không dùng so.* và không SELECT id tường minh
        // $row['currency'] = $row['currency']; // Chỉ cần dòng này nếu bạn không dùng so.* và không SELECT currency tường minh


        $formatted_data[] = $row; // Thêm hàng đã format vào mảng
    }

    // === Trả về dữ liệu dưới dạng JSON cho DataTables ===
    // Bao gồm thông báo lỗi DB trong response chỉ trên môi trường phát triển để dễ debug
    // Trên production, chỉ trả về error chung hoặc thông báo lỗi thân thiện hơn
    $response['draw'] = $draw; // Request draw ID
    $response['recordsTotal'] = $totalRecords; // Tổng số bản ghi (không lọc)
    $response['recordsFiltered'] = $filteredRecords; // Tổng số bản ghi sau khi lọc
    $response['data'] = $formatted_data; // Dữ liệu cho trang hiện tại

    // Không cần set header Content-Type ở đây nếu đã set ở đầu

    // Kết thúc script trong khối try
    // echo json_encode($response); // Không cần echo ở đây nếu sẽ echo ở cuối file
    // exit; // Không exit ở đây để khối catch có thể bắt lỗi


} catch (PDOException $e) {
    // Bắt lỗi PDO (liên quan đến DB: SQL Syntax, Connection, Prepared Statement, Binding)
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine()); // Log lỗi chi tiết
    http_response_code(500); // Trả về status code 500

    $response['error'] = "Database error: " . $e->getMessage(); // Thêm thông báo lỗi vào response (chỉ trên dev)
    $response['recordsTotal'] = 0; // Đảm bảo tổng số bằng 0 khi có lỗi
    $response['recordsFiltered'] = 0;
    $response['data'] = []; // Đảm bảo data rỗng khi có lỗi

} catch (Exception $e) {
    // Bắt các lỗi PHP hoặc lỗi khác không phải PDO
    error_log("General Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine()); // Log lỗi chi tiết
    http_response_code(500); // Trả về status code 500

    $response['error'] = "General error: " . $e->getMessage(); // Thêm thông báo lỗi vào response (chỉ trên dev)
    $response['recordsTotal'] = 0; // Đảm bảo tổng số bằng 0 khi có lỗi
    $response['recordsFiltered'] = 0;
    $response['data'] = []; // Đảm bảo data rỗng khi có lỗi
}

// Gửi phản hồi JSON cuối cùng
echo json_encode($response);

// Kết thúc script
exit;