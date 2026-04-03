// assets/js/invoice_packing_module.js

$(document).ready(function() {
    const API_URL = 'process/invoice_packing_handler.php';
    const PARTNER_API = 'process/partners_handler.php';

    // Initialize Flatpickr
    flatpickr(".datepicker", {
        dateFormat: "d/m/Y",
        allowInput: true
    });

    // --- UTILITIES ---
    function formatNumber(num) {
        return new Intl.NumberFormat('en-US').format(num);
    }

    function parseNumber(str) {
        return parseFloat(str.replace(/,/g, '')) || 0;
    }

    // Small JS version of read_number_vn for instant UI feedback
    // Accuracy-improved Vietnamese number reading
    function docSo(so) {
        if (so == 0) return 'Không đồng';
        if (so < 0) return 'Âm ' + docSo(Math.abs(so));

        const units = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];
        const digits = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

        let s = Math.floor(so).toString();
        let len = s.length;
        let groups = [];
        for (let i = len; i > 0; i -= 3) {
            groups.push(s.substring(Math.max(0, i - 3), i));
        }

        let rs = "";
        for (let i = 0; i < groups.length; i++) {
            let val = parseInt(groups[i]);
            if (val > 0 || (i === 0 && groups.length === 1)) {
                let text = readGroup3(groups[i], i < groups.length - 1);
                rs = text + (units[i] ? ' ' + units[i] : '') + ' ' + rs;
            }
        }

        rs = rs.trim();
        if (!rs) return "Không đồng";
        return rs.charAt(0).toUpperCase() + rs.slice(1) + " đồng";
    }

    function readGroup3(group, showZeroHundred) {
        const digits = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
        group = group.padStart(3, '0');
        let h = parseInt(group[0]);
        let t = parseInt(group[1]);
        let u = parseInt(group[2]);

        let res = "";
        if (h > 0 || showZeroHundred) {
            res += digits[h] + " trăm ";
        }

        if (t === 0) {
            if (u > 0 && (h > 0 || showZeroHundred)) res += "lẻ ";
        } else if (t === 1) {
            res += "mười ";
        } else {
            res += digits[t] + " mươi ";
        }

        if (u > 0) {
            if (u === 1 && t > 1) res += "mốt";
            else if (u === 5 && t > 0) res += "lăm";
            else res += digits[u];
        }

        return res.trim();
    }

    // --- PARTNER AUTOCOMPLETE ---
    $(".partner-autocomplete").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: PARTNER_API,
                dataType: "json",
                data: {
                    action: 'search',
                    term: request.term
                },
                success: function(res) {
                    if (res.success && res.data) {
                        response($.map(res.data, function(item) {
                            return {
                                label: item.name + (item.tax_id ? ' (' + item.tax_id + ')' : ''),
                                value: item.name,
                                id: item.id,
                                address: item.address,
                                phone: item.phone,
                                tax: item.tax_id
                            };
                        }));
                    } else {
                        response([]);
                    }
                }
            });
        },
        minLength: 1,
        select: function(event, ui) {
            const isBillTo = $(this).attr('id') === 'bill_to_name';
            const suffix = isBillTo ? 'bill' : 'ship';
            const prefix = isBillTo ? 'bill_to' : 'ship_to';

            $(`#partner_${suffix}_id`).val(ui.item.id);
            $(`#${prefix}_address_display`).text(ui.item.address || '-');
            $(`#${prefix}_phone_display`).text(ui.item.phone || '-');
            $(`#${prefix}_tax_display`).text(ui.item.tax || '-');

            // Auto-fetch prefix for BILL TO
            if (isBillTo) {
                $.getJSON(API_URL, { action: 'get_partner_last_prefix', partner_id: ui.item.id }, function(res) {
                    if (res.success && res.prefix) {
                        $("#invoice_prefix").val(res.prefix).trigger('change');
                    }
                });
            }
        }
    });

    // --- INVOICE NO GENERATION ---
    $("#invoice_prefix").on('change', function() {
        const prefix = $(this).val();
        if (!prefix) return;

        $.getJSON(API_URL, { action: 'generate_number', prefix: prefix }, function(res) {
            if (res.success) {
                $("#invoice_seq_display").val(res.next_seq.toString().padStart(2, '0'));
            }
        });
    });

    // --- ITEM ROW MANAGEMENT ---
    function addItemRow(data = {}) {
        const rowCount = $("#items-body tr").length + 1;
        const priceVal = data.price ? parseNumber(data.price.toString()) : 0;
        const qtyVal = data.quantity ? parseNumber(data.quantity.toString()) : 0;
        const totalVal = priceVal * qtyVal;

        const html = `
            <tr>
                <td class="text-center">${rowCount}</td>
                <td><textarea class="form-control form-control-sm item-desc product-autocomplete" rows="1" name="items[${rowCount}][description]" required>${data.description || ''}</textarea></td>
                <td><input type="text" class="form-control form-control-sm text-end item-qty" name="items[${rowCount}][quantity]" value="${qtyVal}" required></td>
                <td><input type="text" class="form-control form-control-sm text-end item-price" name="items[${rowCount}][price]" value="${formatNumber(priceVal)}" required></td>
                <td><input type="text" class="form-control form-control-sm text-end item-total bg-light" value="${formatNumber(totalVal)}" readonly></td>
                <td><input type="text" class="form-control form-control-sm" name="items[${rowCount}][remark]" value="${data.remark || ''}"></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-link text-danger btn-remove-row"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `;
        $("#items-body").append(html);

        // Add Product Autocomplete to the new row
        $(".product-autocomplete").last().autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: 'process/product_handler.php',
                    dataType: "json",
                    data: {
                        action: 'search',
                        term: request.term
                    },
                    success: function(res) {
                        if (res.success && res.data) {
                            response($.map(res.data, function(item) {
                                return {
                                    label: item.name + (item.unit_name ? ' (' + item.unit_name + ')' : ''),
                                    value: item.name,
                                    id: item.id,
                                    price: 0 // Could fetch price if available in DB
                                };
                            }));
                        } else {
                            response([]);
                        }
                    }
                });
            },
            minLength: 1,
            select: function(event, ui) {
                const row = $(this).closest('tr');
                // Could auto-populate price if available
            }
        });

        updateTotals();
    }

    $("#btn-add-item").click(function() {
        addItemRow();
    });

    $(document).on('click', '.btn-remove-row', function() {
        $(this).closest('tr').remove();
        // Re-index row numbers
        $("#items-body tr").each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
        updateTotals();
    });

    $(document).on('input', '.item-qty, .item-price', function() {
        const row = $(this).closest('tr');
        const qty = parseNumber(row.find('.item-qty').val());
        const price = parseNumber(row.find('.item-price').val());
        const total = qty * price;

        row.find('.item-total').val(formatNumber(total));
        updateTotals();
    });

    // Format price input on blur
    $(document).on('blur', '.item-price', function() {
        const val = parseNumber($(this).val());
        $(this).val(formatNumber(val));
    });

    function updateTotals() {
        let grandTotal = 0;
        $("#items-body tr").each(function() {
            grandTotal += parseNumber($(this).find('.item-total').val());
        });
        $("#total_amount_display").text(formatNumber(grandTotal));
        $("#total_amount").val(grandTotal);
        $("#total_text_display").text(docSo(grandTotal));
    }

    // --- FORM ACTIONS ---
    $("#btn-create-new").click(function() {
        resetForm();
        $("#invoice-form-card").slideDown();
        $("#form-title").text("Tạo mới Invoice");
        
        // Auto-fetch last prefix
        $.getJSON(API_URL, { action: 'get_last_prefix' }, function(res) {
            if (res.success && res.prefix) {
                $("#invoice_prefix").val(res.prefix).trigger('change');
            }
        });

        if ($("#items-body tr").length === 0) addItemRow();
    });

    $("#btn-close-form, #btn-cancel").click(function() {
        $("#invoice-form-card").slideUp();
    });

    function resetForm() {
        $("#invoice-form")[0].reset();
        $("#invoice_id").val('');
        $("#invoice_seq_display").val('XX');
        $("#total_remark").val('');
        $("#items-body").empty();
        $("#bill_to_address_display, #bill_to_phone_display, #bill_to_tax_display").text('-');
        $("#ship_to_address_display, #ship_to_phone_display, #ship_to_tax_display").text('-');
        updateTotals();
    }

    $("#invoice-form").submit(function(e) {
        e.preventDefault();
        const formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'save' });

        $.ajax({
            url: API_URL,
            type: 'POST',
            data: $.param(formData),
            success: function(res) {
                if (res.success) {
                    alert(res.message);
                    $("#invoice-form-card").slideUp();
                    loadInvoiceList();
                } else {
                    alert('Lỗi: ' + res.message);
                }
            }
        });
    });

    // --- LIST AND CRUD ---
    function loadInvoiceList() {
        $.getJSON(API_URL, { action: 'list' }, function(res) {
            if (res.success) {
                let html = '';
                res.data.forEach(function(item) {
                    html += `
                        <tr>
                            <td><strong>${item.invoice_no}</strong></td>
                            <td>${item.invoice_date}</td>
                            <td>${item.bill_to_name}</td>
                            <td>${item.ship_to_name || '-'}</td>
                            <td class="text-end fw-bold text-primary">${formatNumber(item.total_amount)}</td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary btn-edit" data-id="${item.id}" title="Sửa"><i class="bi bi-pencil"></i></button>
                                    <a href="process/export_invoice_pdf.php?id=${item.id}" target="_blank" class="btn btn-outline-secondary" title="Xuất PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                                    <button class="btn btn-outline-danger btn-delete" data-id="${item.id}" title="Xóa"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                if (res.data.length === 0) {
                    html = '<tr><td colspan="6" class="text-center py-4 text-muted">Chưa có dữ liệu</td></tr>';
                }
                $("#invoice-list-body").html(html);
            }
        });
    }

    $(document).on('click', '.btn-edit', function() {
        const id = $(this).data('id');
        $.getJSON(API_URL, { action: 'get', id: id }, function(res) {
            if (res.success) {
                resetForm();
                const d = res.data;
                $("#invoice_id").val(d.id);
                $("#invoice_prefix").val(d.invoice_prefix);
                $("#invoice_year").val(d.invoice_year);
                $("#invoice_seq_display").val(d.invoice_seq.toString().padStart(2, '0'));
                $("#invoice_date").val(d.invoice_date);
                
                $("#partner_bill_id").val(d.partner_bill_id);
                $("#bill_to_name").val(d.bill_to_name);
                $("#bill_to_address_display").text(d.bill_to_address || '-');
                $("#bill_to_phone_display").text(d.bill_to_phone || '-');
                $("#bill_to_tax_display").text(d.bill_to_tax || '-');

                $("#partner_ship_id").val(d.partner_ship_id);
                $("#ship_to_name").val(d.ship_to_name);
                $("#ship_to_address_display").text(d.ship_to_address || '-');
                $("#ship_to_phone_display").text(d.ship_to_phone || '-');
                $("#ship_to_tax_display").text(d.ship_to_tax || '-');

                $("#total_remark").val(d.total_remark || '');
                $("#invoice_packing").val(d.packing);
                $("#invoice_net_weight").val(d.net_weight);
                
                if (d.items) {
                    const itemsToLoad = Array.isArray(d.items) ? d.items : Object.values(d.items);
                    itemsToLoad.forEach(function(item) {
                        addItemRow(item);
                    });
                }

                $("#invoice-form-card").slideDown();
                $("#form-title").text("Chỉnh sửa Invoice: " + d.invoice_no);
                window.scrollTo(0, 0);
            }
        });
    });

    $(document).on('click', '.btn-delete', function() {
        if (!confirm('Bạn có chắc chắn muốn xóa Invoice này?')) return;
        const id = $(this).data('id');
        $.post(API_URL, { action: 'delete', id: id }, function(res) {
            if (res.success) {
                loadInvoiceList();
            } else {
                alert(res.message);
            }
        }, 'json');
    });

    // Initial load
    loadInvoiceList();
});
