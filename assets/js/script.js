// assets/js/script.js
console.log("Custom script loaded.");

// Đối tượng toàn cục để lưu trữ các instance của CKEditor 5
window.editors = window.editors || {};

// Function để khởi tạo CKEditor cho một selector cụ thể
// Tách hàm này ra để có thể gọi lại nếu cần
function createCKEditor(elementId, initialData = '') {
    const element = document.querySelector(`#${elementId}`);
    if (!element) {
        // console.warn(`script.js: Element #${elementId} not found. CKEditor will not be initialized for it.`);
        return null; // Không làm gì cả nếu không tìm thấy phần tử
    }

    if (typeof ClassicEditor === "undefined") {
        console.warn(`script.js: CKEditor 5 ClassicEditor library not found. Cannot initialize for #${elementId}.`);
        return null;
    }

    // Nếu đã tồn tại CKEditor instance trên element này rồi thì không tạo nữa (phòng trường hợp gọi nhiều lần)
    if (element.ckeditorInstance) {
        // console.warn(`script.js: CKEditor already initialized for #${elementId}.`);
        return element.ckeditorInstance;
    }

    ClassicEditor
        .create(element, {
            // Cấu hình CKEditor của bạn (nếu có)
            // initialData: initialData, // Nếu bạn muốn truyền dữ liệu ban đầu
        })
        .then(editor => {
            element.ckeditorInstance = editor; // Lưu instance lại để kiểm tra
            // console.log(`CKEditor initialized for #${elementId}`);
        })
        .catch(error => {
            console.error(`Error creating CKEditor for #${elementId}:`, error);
        });
    return null; // Hoặc trả về promise nếu cần xử lý bất đồng bộ
}


// Function để khởi tạo Flatpickr datepickers
function initializeDatepicker() {
    if (typeof flatpickr !== "undefined") {
        console.log("script.js: Flatpickr library detected. Initializing datepickers...");

        // Cấu hình chung cho các input có class 'datepicker'
        if ($(".datepicker").length) {
            $(".datepicker:not(.flatpickr-input)").flatpickr({ // Tránh khởi tạo lại trên các input đã có flatpickr-input
                dateFormat: "d/m/Y",
                allowInput: true,
                locale: "vn"
            });
            console.log("script.js: Flatpickr initialized for elements with class 'datepicker'.");
        }

        // Cấu hình cho các ID cụ thể
        const datePickerSelectors = {
            '#orderDate': "d/m/Y",
            '#delivery_date_expected': "d/m/Y",
            '#quote_date': "d/m/Y",
            '#expiry_date': "d/m/Y"
        };

        for (const selector in datePickerSelectors) {
            if ($(selector).length && !$(selector).hasClass('flatpickr-input')) { // Kiểm tra sự tồn tại và chưa được khởi tạo
                flatpickr(selector, {
                    dateFormat: datePickerSelectors[selector],
                    allowInput: true,
                    locale: "vn"
                });
                console.log(`script.js: Flatpickr initialized for ${selector} with format ${datePickerSelectors[selector]}.`);
            }
        }

    } else {
        console.warn("script.js: Flatpickr library not found. Retrying datepicker initialization in 200ms...");
        setTimeout(initializeDatepicker, 200);
    }
}


// Xác nhận xóa
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.btn-delete');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            const message = this.getAttribute('data-confirm') || 'Bạn có chắc chắn muốn xóa mục này không?'; // Sử dụng tiếng Việt mặc định

            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });
});


// Gọi các hàm khởi tạo khi tài liệu đã sẵn sàng (jQuery's ready)
$(document).ready(function() {
    console.log("script.js: Document ready. Initializing components...");

    // Khởi tạo CKEditor cho các textarea cần thiết
    // Các hàm createCKEditor sẽ tự kiểm tra sự tồn tại của phần tử và thư viện
    if ($('#notes').length) {
    createCKEditor('notes');
}
if ($('#emailBody').length) {
    createCKEditor('emailBody');
}

    // Khởi tạo Flatpickr datepickers
    // Hàm initializeDatepicker sẽ tự kiểm tra sự tồn tại của các input
    initializeDatepicker();

    // Các hàm khởi tạo khác của bạn có thể đặt ở đây
    // Ví dụ: initializeSomethingElse();
});