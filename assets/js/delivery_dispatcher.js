// File: assets/js/delivery_dispatcher.js

$(document).ready(function() {
    let assignedOrders = []; // Array to track orders currently assigned in the form
    let currentTripStatus = 'all';

    // Initialize
    loadStats();

    // Check for ID in URL to load specific trip
    const urlParams = new URLSearchParams(window.location.search);
    const tripIdFromUrl = urlParams.get('id');
    if (tripIdFromUrl) {
        loadTripDetails(tripIdFromUrl);
    }

    const selectedOrderIds = urlParams.get('selected_order_ids');
    if (selectedOrderIds) {
        // Slight delay to ensure other initializations are complete
        setTimeout(() => loadSelectedOrdersForNewTrip(selectedOrderIds), 200);
    }

    // Event Handlers
    $('#btn-new-trip').on('click', function() {
        resetTripForm();
    });

    // Search and filter trips
    $('#search-trips-input').on('input', function() {
        clearTimeout(window.searchTripsTimeout);
        window.searchTripsTimeout = setTimeout(searchTrips, 500);
    });

    $('.filter-trip-status').on('click', function(e) {
        e.preventDefault();
        $('.filter-trip-status').removeClass('active');
        $(this).addClass('active');
        currentTripStatus = $(this).data('status');
        searchTrips();
    });

    $(document).on('click', '.trip-item', function(e) {
        e.preventDefault();
        const tripId = $(this).data('id');
        loadTripDetails(tripId);
        
        // Highlight active item
        $('.trip-item').removeClass('active');
        $(this).addClass('active');
    });

    $('#btn-add-orders').on('click', function() {
        openOrderSelector();
    });

    $('#btn-confirm-add-orders').on('click', function() {
        addSelectedOrders();
    });

    $(document).on('click', '.btn-remove-order', function() {
        const orderId = $(this).data('id');
        removeOrderFromTrip(orderId);
    });

    $('#btn-print-trip').on('click', function() {
        const tripId = $('#trip_id').val();
        if (tripId) {
            window.open('process/generate_trip_pdf.php?id=' + tripId, '_blank');
        }
    });

    $('#btn-delete-trip').on('click', function() {
        const tripId = $('#trip_id').val();
        if (!tripId) return;

        Swal.fire({
            title: TRIP_LANG.areYouSure,
            text: TRIP_LANG.wontBeAbleToRevert,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: TRIP_LANG.yesDeleteIt
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('process/delivery_handler.php', { action: 'delete_trip', id: tripId }, function(res) {
                    if (res.success) {
                        Swal.fire(TRIP_LANG.deleted, res.message, 'success').then(() => {
                            resetTripForm();
                            searchTrips();
                            loadStats();
                        });
                    } else {
                        Swal.fire(TRIP_LANG.error, res.message, 'error');
                    }
                });
            }
        });
    });

    $('#trip-form').on('submit', function(e) {
        e.preventDefault();
        saveTrip();
    });

    $('#btn-reset-form').on('click', function() {
        resetTripForm();
    });

    // Cost auto-calculation
    $('#base_freight_cost, #extra_costs').on('input', function() {
        calculateTotalCost();
    });

    // Driver selection autocomplete plate
    $('#driver_id_select').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const plate = selectedOption.data('plate');
        if (plate) {
            $('#vehicle_plate').val(plate);
        } else {
            $('#vehicle_plate').val('');
        }
    });

    // Check All functionality
    $('#check-all-orders').on('change', function() {
        $('.order-checkbox:visible').prop('checked', $(this).prop('checked'));
    });

    // Search available orders
    $('#search-available-orders').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('#available-orders-body tr').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(searchTerm) > -1);
        });
        // Update check-all state if needed
        updateCheckAllState();
    });

    function updateCheckAllState() {
        const visibleCheckboxes = $('.order-checkbox:visible');
        if (visibleCheckboxes.length === 0) {
            $('#check-all-orders').prop('checked', false);
            return;
        }
        const allChecked = visibleCheckboxes.length === visibleCheckboxes.filter(':checked').length;
        $('#check-all-orders').prop('checked', allChecked);
    }

    $(document).on('change', '.order-checkbox', function() {
        updateCheckAllState();
    });

    // Helper functions
    function loadStats() {
        $.get('process/delivery_handler.php', { action: 'get_stats' }, function(res) {
            if (res.success) {
                $('#stat-trips-month').text(res.stats.trips_month);
                $('#stat-total-freight').text(res.stats.total_freight);
                $('#stat-pending-orders').text(res.stats.pending_orders);
            }
        });
    }

    function loadTripDetails(id) {
        $.get('process/delivery_handler.php', { action: 'get_trip_details', id: id }, function(res) {
            if (res.success) {
                const trip = res.trip;
                $('#trip_id').val(trip.id);
                $('#trip_number').val(trip.trip_number);
                $('#trip_date').val(trip.trip_date);
                $('#trip_status').val(trip.status);
                $('#driver_id_select').val(trip.driver_id);
                $('#vehicle_plate').val(trip.vehicle_plate);
                $('#trip_notes').val(trip.notes);
                $('#base_freight_cost').val(formatNumber(trip.base_freight_cost));
                $('#extra_costs').val(formatNumber(trip.extra_costs));
                
                $('#editor-title').text(TRIP_LANG.editTrip + ': ' + trip.trip_number);
                $('#editor-actions').removeClass('d-none');
                
                assignedOrders = res.orders.map(o => ({
                    id: o.order_id,
                    order_number: o.order_number,
                    supplier_name: o.supplier_name,
                    customer_name: o.customer_name,
                    items_summary: o.items_summary,
                    status: o.order_status
                }));
                
                renderAssignedOrders();
                calculateTotalCost();
            }
        });
    }

    function resetTripForm() {
        $('#trip-form')[0].reset();
        $('#trip_id').val('');
        $('#trip_number').val('');
        $('#trip_date').val(new Date().toISOString().split('T')[0]);
        $('#editor-title').text(TRIP_LANG.createNewTrip);
        $('#editor-actions').addClass('d-none');
        assignedOrders = [];
        renderAssignedOrders();
        calculateTotalCost();
        $('.trip-item').removeClass('active');
    }

    function loadSelectedOrdersForNewTrip(ids) {
        resetTripForm();
        $.get('process/delivery_handler.php', { action: 'get_order_details_for_trip', ids: ids }, function(res) {
            if (res.success) {
                assignedOrders = res.orders.map(o => ({
                    id: o.id,
                    order_number: o.order_number,
                    supplier_name: o.supplier_name,
                    customer_name: o.customer_name,
                    items_summary: o.items_summary,
                    status: o.status
                }));
                renderAssignedOrders();
            } else {
                Swal.fire(TRIP_LANG.error, res.message, 'error');
            }
        });
    }

    function openOrderSelector() {
        $('#available-orders-body').html('<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>');
        $('#orderSelectorModal').modal('show');
        
        $.get('process/delivery_handler.php', { action: 'get_available_orders' }, function(res) {
            if (res.success) {
                let html = '';
                // Filter out orders already assigned to this trip in the form OR already assigned to another trip
                const filtered = res.orders.filter(o => {
                    const isAlreadyInForm = assignedOrders.find(ao => ao.id == o.id);
                    const isAlreadyInAnotherTrip = !!o.assigned_trip_number;
                    return !isAlreadyInForm && !isAlreadyInAnotherTrip;
                });
                
                if (filtered.length === 0) {
                    html = `<tr><td colspan="6" class="text-center py-4 text-muted">${TRIP_LANG.noPendingOrders}</td></tr>`;
                } else {
                    filtered.forEach(o => {
                        const tripWarning = o.assigned_trip_number ? `<br><small class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> Đã có: ${o.assigned_trip_number}</small>` : '';
                        html += `
                            <tr>
                                <td><input type="checkbox" class="form-check-input order-checkbox" value="${o.id}" 
                                    data-num="${o.order_number}" data-sup="${o.supplier_name}" data-cus="${o.customer_name || 'N/A'}"
                                    data-status="${o.status}" data-items="${o.items_summary || ''}" data-trip="${o.assigned_trip_number || ''}"></td>
                                <td class="fw-bold text-primary">${o.order_number}${tripWarning}</td>
                                <td>${o.order_date}</td>
                                <td>${o.supplier_name}</td>
                                <td>${o.customer_name || 'N/A'}</td>
                                <td>
                                    <div class="small text-muted" style="max-width: 400px;" title="${o.items_summary || ''}">
                                        ${o.items_summary || ''}
                                    </div>
                                    <div class="mt-1">${getStatusBadge(o.status)}</div>
                                </td>
                            </tr>
                        `;
                    });
                }
                $('#available-orders-body').html(html);
                $('#check-all-orders').prop('checked', false);
                $('#search-available-orders').val('');
            }
        });
    }

    function addSelectedOrders() {
        $('.order-checkbox:checked').each(function() {
            const $cb = $(this);
            assignedOrders.push({
                id: $cb.val(),
                order_number: $cb.data('num'),
                supplier_name: $cb.data('sup'),
                customer_name: $cb.data('cus'),
                items_summary: $cb.data('items'),
                status: $cb.data('status')
            });
        });
        
        $('#orderSelectorModal').modal('hide');
        renderAssignedOrders();
    }

    function removeOrderFromTrip(id) {
        assignedOrders = assignedOrders.filter(o => o.id != id);
        renderAssignedOrders();
    }

    function renderAssignedOrders() {
        const $body = $('#trip-orders-body');
        if (assignedOrders.length === 0) {
            $body.html(`<tr><td colspan="6" class="text-center py-4 text-muted small">${TRIP_LANG.noOrdersAssigned}</td></tr>`);
            return;
        }
        
        let html = '';
        assignedOrders.forEach(o => {
            html += `
                <tr>
                    <td class="fw-bold text-primary">${o.order_number}</td>
                    <td>${o.supplier_name}</td>
                    <td>${o.customer_name || 'N/A'}</td>
                    <td>
                        <div class="small text-muted" style="max-width: 300px;">
                            ${o.items_summary || ''}
                        </div>
                    </td>
                    <td class="text-center">${getStatusBadge(o.status)}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-link text-danger btn-remove-order" data-id="${o.id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        $body.html(html);
    }

    function calculateTotalCost() {
        const base = parseFloat($('#base_freight_cost').val().replace(/\./g, '')) || 0;
        const extra = parseFloat($('#extra_costs').val().replace(/\./g, '')) || 0;
        const total = base + extra;
        $('#total_trip_cost_display').text(formatNumber(total) + ' ₫');
    }

    function saveTrip() {
        const formData = new FormData($('#trip-form')[0]);
        formData.append('action', 'save_trip');
        
        // Add assigned order IDs
        assignedOrders.forEach(o => {
            formData.append('order_ids[]', o.id);
        });

        $.ajax({
            url: 'process/delivery_handler.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    Swal.fire(TRIP_LANG.success, res.message, 'success').then(() => {
                        searchTrips();
                        loadStats();
                        if (!res.is_update) {
                            resetTripForm();
                        } else {
                            loadTripDetails($('#trip_id').val());
                        }
                    });
                } else {
                    Swal.fire(TRIP_LANG.error, res.message, 'error');
                }
            },
            error: function() {
                Swal.fire(TRIP_LANG.error, TRIP_LANG.serverError, 'error');
            }
        });
    }

    // Utility: Format number to VND style (dots as thousand separators)
    function formatNumber(n) {
        if (!n) return "0";
        return Math.round(parseFloat(n)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function getStatusBadge(status) {
        let color = 'secondary';
        let label = status;
        
        switch(status) {
            case 'draft': color = 'secondary'; label = 'Nháp'; break;
            case 'sent': color = 'info'; label = 'Đã gửi'; break;
            case 'ordered': color = 'primary'; label = 'Đã đặt hàng'; break;
            case 'partially_received': color = 'warning'; label = 'Đã nhận một phần'; break;
            case 'completed': color = 'success'; label = 'Hoàn tất'; break;
            case 'cancelled': color = 'danger'; label = 'Hủy'; break;
            case 'pending': color = 'warning'; label = 'Chờ xử lý'; break;
        }
        return `<span class="badge bg-${color}">${label}</span>`;
    }

    // Input masking for currency
    $(document).on('input', '.currency-input', function() {
        let val = $(this).val().replace(/\D/g, "");
        if (val === "") val = "0";
        $(this).val(formatNumber(parseInt(val)));
    });
    function searchTrips() {
        const search = $('#search-trips-input').val();
        $('#trips-list').html('<div class="p-4 text-center"><div class="spinner-border spinner-border-sm text-primary"></div></div>');
        
        $.get('process/delivery_handler.php', { 
            action: 'search_trips', 
            search: search,
            status: currentTripStatus
        }, function(res) {
            if (res.success) {
                renderTripsList(res.trips);
            }
        });
    }

    function renderTripsList(trips) {
        if (trips.length === 0) {
            $('#trips-list').html(`
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-truck fs-1 d-block mb-2"></i>
                    Không tìm thấy chuyến xe nào.
                </div>
            `);
            return;
        }

        let html = '';
        trips.forEach(trip => {
            let statusColor = 'info';
            let statusLabel = trip.status.toUpperCase();
            
            switch(trip.status) {
                case 'draft': statusColor = 'secondary'; statusLabel = 'NHÁP'; break;
                case 'scheduled': statusColor = 'info'; statusLabel = 'LÊN LỊCH'; break;
                case 'in_progress': statusColor = 'warning'; statusLabel = 'ĐANG GIAO'; break;
                case 'completed': statusColor = 'success'; statusLabel = 'HOÀN TẤT'; break;
                case 'cancelled': statusColor = 'danger'; statusLabel = 'ĐÃ HỦY'; break;
            }

            const totalCost = formatNumber(parseFloat(trip.base_freight_cost) + parseFloat(trip.extra_costs));
            
            // Format date manually to dd/mm/yyyy
            const d = new Date(trip.trip_date);
            const dateStr = ("0" + d.getDate()).slice(-2) + "/" + ("0" + (d.getMonth() + 1)).slice(-2) + "/" + d.getFullYear();

            html += `
                <a href="#" class="list-group-item list-group-item-action trip-item p-3 ${$('#trip_id').val() == trip.id ? 'active' : ''}" data-id="${trip.id}">
                    <div class="d-flex w-100 justify-content-between align-items-start">
                        <h6 class="mb-1 fw-bold text-primary">${trip.trip_number}</h6>
                        <small class="badge rounded-pill bg-${statusColor}">
                            ${statusLabel}
                        </small>
                    </div>
                    <div class="small text-muted mb-1">
                        <i class="bi bi-person me-1"></i> ${trip.driver_name}
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small><i class="bi bi-calendar3 me-1"></i> ${dateStr}</small>
                        <span class="fw-bold text-dark">${totalCost} ₫</span>
                    </div>
                </a>
            `;
        });
        $('#trips-list').html(html);
    }
});
